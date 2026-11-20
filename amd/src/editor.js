// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The editor of the content: adds, moves, shows, hides and deletes elements and chapters.
 *
 * @module mod_contentdesigner/editor
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/modal', 'core/modal_events', 'core/str', 'core/fragment', 'core/templates', 'core/notification',
    'core/loadingicon', 'core/ajax'],
    function(Modal, ModalEvents, Str, Fragment, Templates, Notification, LoadingIcon, Ajax) {

        var contextID;

        var cmID;

        let loaderItem = '.contentdesigner-content';

        const editor = (contextid, cmid) => {
            if (document.body.id !== 'page-mod-contentdesigner-editor') {
                return null;
            }
            contextID = contextid;
            cmID = cmid;
            initEventListeners();
            return null;
        };

        const initEventListeners = () => {
            document.body.addEventListener('click', (e) => {
                var addElement = e.target.closest('.contentdesigner-addelement');
                var elementAction = e.target.closest(".element-item .element-actions .action-item");
                var moduleElement = e.target.closest("div.element-item");

                if (elementAction && elementAction != undefined
                    && moduleElement && moduleElement != undefined) {
                    var action = elementAction.getAttribute('data-action');
                    var element = moduleElement.getAttribute('data-elementshortname');
                    var instanceId = moduleElement.getAttribute('data-instanceid');
                    if (action === 'delete') {
                        // Deleting requires confirmation.
                        confirmDeleteElement(element, function() {
                            deleteElement(moduleElement, element, instanceId);
                        });
                    }

                    if (action == 'moveup' || action == 'movedown') {
                        moveElement(moduleElement, action);
                    }

                    if (action == 'status') {
                        updateStatus(moduleElement);
                    }
                }

                if (addElement && addElement != undefined) {
                    e.preventDefault();
                    var position = addElement.dataset.position;
                    var chapter = addElement.dataset.chapter;
                    buildAddElementModal(position, chapter);
                }
            });
        };


        const moveElement = (moduleElement, action) => {
            var promise;
            if (moduleElement.dataset.elementshortname == 'chapter') {
                var item = moduleElement.closest('.chapters-list');
                if (action == 'moveup') {
                    item.parentNode.insertBefore(item, item.previousElementSibling);
                } else {
                    item.parentNode.insertBefore(item, item.nextElementSibling.nextElementSibling);
                }
                let contents = [];
                var items = document.querySelectorAll('ul.course-content-list .chapters-content');
                items.forEach((item) => {
                    contents.push(item.dataset.id);
                });
                promise = Ajax.call([{
                    methodname: 'mod_contentdesigner_move_chapter',
                    args: {cmid: cmID, chapters: contents.join(',')}
                }])[0].then(renderContent).catch(Notification.exception);
                LoadingIcon.addIconToContainerRemoveOnCompletion(loaderItem, promise);
            } else {
                // The chapters whose elements changed, saved in one request.
                var requests = [];

                let chapter = moduleElement.closest('.chapters-content');
                let item = moduleElement.parentNode;
                if (action == 'moveup') {
                    // Append to the Previous chapter if this item is first in the list.
                    if (item.previousElementSibling === null) {
                        let previousChapter = item.closest('.chapters-list').previousElementSibling;
                        // To fix the moodle CI nested loop count of 5. Tested separetly.
                        var append = false;
                        if (previousChapter !== null) {
                            previousChapter.querySelector('.chapter-elements-list').append(item);
                            append = true;
                        }
                        if (append && previousChapter.childNodes[1] != undefined) {
                            requests.push(chapterElementsRequest(previousChapter.childNodes[1]));
                        }
                    } else {
                        item.parentNode.insertBefore(item, item.previousElementSibling);
                    }
                } else {
                    // Prepend to the next chapter if this item is last in the list.
                    if (item.nextElementSibling === null) {
                        let nextChapter = item.closest('.chapters-list').nextElementSibling;
                        // To fix the moodle CI nested loop count of 5. Tested separetly.
                        var prepend = false;
                        if (nextChapter !== null) {
                            nextChapter.querySelector('.chapter-elements-list').prepend(item);
                            prepend = true;
                        }
                        if (prepend && nextChapter.childNodes[1] != undefined) {
                            requests.push(chapterElementsRequest(nextChapter.childNodes[1]));
                        }

                    } else {
                        item.parentNode.insertBefore(item, item.nextElementSibling.nextElementSibling);
                    }
                }

                requests.push(chapterElementsRequest(chapter));
                promise = Promise.all(Ajax.call(requests)).then(renderContent).catch(Notification.exception);
                LoadingIcon.addIconToContainerRemoveOnCompletion(loaderItem, promise);
            }
        };

        /**
         * Renders the content of the editor again, after a change was saved.
         *
         * @returns {Promise}
         */
        const renderContent = () => {
            return Fragment.loadFragment('mod_contentdesigner', 'editor_content', contextID, {cmid: cmID})
                .then((html, js) => Templates.replaceNode('.contentdesigner-content', html, js));
        };

        /**
         * The request that saves the elements of a chapter in their order.
         *
         * @param {HTMLElement} chapter The chapter in the editor.
         * @returns {Object} The request for core/ajax.
         */
        const chapterElementsRequest = (chapter) => {
            let contents = [];
            var items = chapter.querySelectorAll('li.element-item > div.element-item');
            items.forEach((item) => {
                contents.push(item.dataset.contentid);
            });
            return {
                methodname: 'mod_contentdesigner_move_element',
                args: {cmid: cmID, chapterid: chapter.dataset.id, contents: contents.join(',')}
            };
        };

        var updateStatus = (moduleElement) => {
            let statusElement = moduleElement.querySelector('[data-action="status"] > i');
            var params = {
                cmid: cmID,
                element: moduleElement.dataset.elementshortname,
                instanceid: moduleElement.dataset.instanceid,
                visible: moduleElement.dataset.visibility == true ? false : true,
            };

            if (moduleElement.dataset.visibility == true) {
                statusElement.classList.remove('fa-eye');
                statusElement.classList.add('fa-eye-slash');
                moduleElement.dataset.visibility = false;
            } else {
                statusElement.classList.remove('fa-eye-slash');
                statusElement.classList.add('fa-eye');
                moduleElement.dataset.visibility = true;
            }

            var promise = Ajax.call([{
                methodname: 'mod_contentdesigner_update_visibility',
                args: params
            }])[0].catch(Notification.exception);
            LoadingIcon.addIconToContainerRemoveOnCompletion(loaderItem, promise);
        };


        /**
         * Deletes an element (a chapter with its elements) and removes it from the editor.
         *
         * @param {HTMLElement} moduleElement The element in the editor.
         * @param {String} element Shortname of the element.
         * @param {Number} instanceId
         */
        var deleteElement = function(moduleElement, element, instanceId) {
            Ajax.call([{
                methodname: 'mod_contentdesigner_delete_element',
                args: {cmid: cmID, element: element, instanceid: instanceId}
            }])[0].then(() => {
                moduleElement.parentNode.remove();
                return true;
            }).catch(Notification.exception);
        };

        /**
         * Displays the delete confirmation to delete a module
         *
         * @param {String} element
         * @param {Function} onconfirm function to execute on confirm
         */
        var confirmDeleteElement = function(element, onconfirm) {
            var elementTypename = 'cdelement_' + element;
            Str.get_string('pluginname', elementTypename).done(function() {
                var plugindata = {
                    element: element
                };
                Str.get_strings([
                    {key: 'confirm', component: 'core'},
                    {key: 'deletechecktype', component: 'mod_contentdesigner', param: plugindata},
                    {key: 'yes'},
                    {key: 'no'}
                ]).done(function(s) {
                    Notification.confirm(s[0], s[1], s[2], s[3], onconfirm);
                }
                );
            });
        };


        /**
         * Load the elements list modal to insert new element
         *
         * @param {String} position where the element need to insert.
         * @param {Boolean} chapter chapter id to insert element.
         * @returns {Object}
         */
        const buildAddElementModal = (position = "bottom", chapter = 0) => {
            var params = {cmid: cmID};
            Modal.create({
                title: Str.get_string('addelement', 'contentdesigner'),
                body: Fragment.loadFragment('mod_contentdesigner', 'get_elements_list', contextID, params),
                large: false,
            }).then(modal => {
                modal.getRoot().on(ModalEvents.bodyRendered, function() {
                    // Opens the form to add the chosen element.
                    const addElement = (item) => {
                        const urlParams = new URLSearchParams({
                            cmid: cmID,
                            element: item.dataset.element,
                            chapter: chapter,
                            position: position,
                        });
                        window.location = M.cfg.wwwroot + '/mod/contentdesigner/element.php?' + urlParams.toString();
                    };
                    modal.getRoot().get(0).querySelectorAll('.element-item').forEach((item) => {
                        item.addEventListener('click', (e) => addElement(e.currentTarget));
                        // The items are buttons for the keyboard too.
                        item.addEventListener('keydown', (e) => {
                            if (e.key === 'Enter' || e.key === ' ') {
                                e.preventDefault();
                                addElement(e.currentTarget);
                            }
                        });
                    });
                });
                modal.show();
                return modal;
            }).catch(Notification.exception);
        };

        return {
            init: function(contextid, cmid) {
                return editor(contextid, cmid);
            }
        };
    });

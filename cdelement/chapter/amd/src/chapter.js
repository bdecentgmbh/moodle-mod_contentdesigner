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
 * Chapter completion and progress for the content designer module.
 *
 * Learners complete a chapter with its button; the progress bar is updated and the content refreshed, so that the
 * next chapters appear. Clicking a chapter in the progress bar scrolls to it. Addons may complete chapters
 * automatically (chapters with data-autocompletion="1"). Supports the popup course format.
 *
 * @module cdelement_chapter/chapter
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['mod_contentdesigner/elements', 'core/ajax', 'core/fragment', 'core/templates', 'core/loadingicon',
    'core/notification', 'core/str'],
    function(Elements, AJAX, Fragment, Templates, LoadingIcon, Notification, Str) {

        const chapterCTA = 'button.complete-chapter';
        const progressBar = 'div#contentdesigner-progressbar';
        let completionIcon, completionStr;
        let initialised = false;

        const initEventListeners = () => {
            Templates.renderPix('e/tick', 'core').done(function(img) {
                completionIcon = img;
            });
            Str.get_string('completion_manual:done', 'course').done((str) => {
                completionStr = str;
            });

            // Listen once on the body, also when the content is loaded again in a popup.
            if (initialised) {
                return;
            }
            initialised = true;
            document.body.addEventListener('click', completeChapterListener);
            document.body.addEventListener('click', chapterNavigation);

            document.querySelector('#page')?.addEventListener('scroll', stickyProgress);
            // Popup format support.
            var popup = document.querySelector('body.format-popups .modal-content .modal-body');
            if (popup !== null) {
                popup.addEventListener('scroll', stickyProgress);
            }
            window.addEventListener('scroll', stickyProgress);
        };

        const completeChapterListener = (e) => {
            var completeCTA = e.target.closest(chapterCTA);
            if (completeCTA === null) {
                return;
            }
            e.preventDefault();
            var chapter = completeCTA.dataset.chapterid;
            var promise = completeChapter(chapter, completeCTA);
            promise.then(() => {
                updateProgress();
                completeCTA.classList.remove('btn-outline-secondary');
                completeCTA.classList.add('btn-success');
                completeCTA.innerHTML = completionIcon + ' ' + completionStr;
                Elements.removeWarning();
                Elements.refreshContent();
                let chapterlist = e.target.closest('.chapters-list');
                if (chapterlist && !chapterlist.classList.contains('completed')) {
                    chapterlist.classList.add('completed');
                }
                return true;
            }).catch(Notification.exception);
        };

        const stickyProgress = function() {
            var progressElem = document.querySelector('.contentdesigner-progress');
            var contentWrapper = document.querySelector('.contentdesigner-content');
            if (progressElem && contentWrapper) {
                if (contentWrapper.getBoundingClientRect().top < 50) {
                    contentWrapper.classList.add('sticky-progress');
                    progressElem.classList.add('fixed-top');
                } else {
                    progressElem.classList.remove('fixed-top');
                    contentWrapper.classList.remove('sticky-progress');
                }
            }
        };

        const completeChapter = (chapter, button) => {
            var promises = AJAX.call([{
                methodname: 'cdelement_chapter_update_completion',
                args: {
                    chapter: chapter,
                    cmid: Elements.contentDesignerData().cmid
                }
            }]);
            if (button) {
                LoadingIcon.addIconToContainerRemoveOnCompletion(button, promises[0]);
            }
            return promises[0];
        };

        const updateProgress = () => {
            var params = {cmid: Elements.contentDesignerData().cmid};
            Fragment.loadFragment('cdelement_chapter', 'update_progressbar',
                Elements.contentDesignerData().contextid, params).done((html, js) => {
                    Templates.replaceNode(progressBar, html, js);
                }).catch(Notification.exception);
        };

        // Scroll to a chapter when it is clicked in the progress bar.
        const chapterNavigation = function(e) {
            var chapter = e.target.closest('.contentdesigner-chapter');
            if (chapter === null) {
                return;
            }
            var chapterSelector = document.querySelector('li.chapters-list[data-id="' + chapter.dataset.chapter + '"]');
            if (chapterSelector !== null) {
                chapterSelector.scrollIntoView(true);
            }
        };

        return {
            init: function() {
                initEventListeners();
            },
            completeChapter: completeChapter,
            updateProgress: updateProgress,
        };
    });

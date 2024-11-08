define(['jquery', 'mod_contentdesigner/elements', 'core/ajax', 'core/fragment',
'core/templates', 'core/loadingicon', 'core/notification', 'core/str'],
function($, Elements, AJAX, Fragment, Templates, LoadingIcon, Notification, Str) {

    const chapterCTA = 'button.complete-chapter';

    const progressBar = 'div#contentdesigner-progressbar';

    let completionIcon, completionStr;

    const initEventListeners = () => {
        Templates.renderPix('e/tick', 'core').done(function(img) {
            completionIcon = img;
        });
        Str.get_string('completion_manual:done', 'course').done((str) => {
            completionStr = str;
        });
        // Remove previous eventlisteners on body. to support popup format.
        document.body.removeEventListener('click', completeChapterListener);
        document.body.addEventListener('click', completeChapterListener);

        document.querySelector('#page').addEventListener('scroll', () => {
            stickyProgress();
        });

        window.addEventListener('scroll', () => {
            stickyProgress();
        });
    };

    const completeChapterListener = (e) => {
        var completeCTA = e.target.closest(chapterCTA);
        if (completeCTA != undefined) {
            e.preventDefault();
            var chapter = completeCTA.dataset.chapterid;
            var promise = completeChapter(chapter, completeCTA);
            promise.done(() => {
                updateProgress();
                completeCTA.classList.remove('btn-outline-secondary');
                completeCTA.classList.add('btn-success');
                completeCTA.innerHTML = completionIcon + ' ' + completionStr;
                Elements.removeWarning();
                Elements.refreshContent();
                // TODO: Add a additional function to support loadnext chapter works like replaceonrefresh.
                // Until hide this loadNextchapters().
                // Elements.loadNextChapters(chapter);
            }).catch(Notification.exception);
        }
    };

    const stickyProgress = function() {
        var progressElem = document.querySelector('.contentdesigner-progress');
        var contentWrapper = document.querySelector('.contentdesigner-content');
        if (contentWrapper != undefined && contentWrapper.getBoundingClientRect().top < 50) {
            contentWrapper.classList.add('sticky-progress');
            progressElem.classList.add('fixed-top');
        } else {
            progressElem.classList.remove('fixed-top');
            contentWrapper.classList.remove('sticky-progress');
        }
    };

    const completeChapter = (chapter, button) => {
        var promises = AJAX.call([{
            methodname: 'element_chapter_update_completion',
            args: {
                chapter: chapter,
                cmid: Elements.contentDesignerData().cmid
            }
        }]);
        LoadingIcon.addIconToContainerRemoveOnCompletion(button, promises[0]);

        return promises[0];
    };

    const updateProgress = () => {
        var params = {cmid: Elements.contentDesignerData().cmid};
        Fragment.loadFragment('element_chapter', 'update_progressbar', Elements.contentDesignerData().contextid, params).done((html, js) => {
            Templates.replaceNode(progressBar, html, js);
        }).catch(Notification.exception);
    };

    return {
        init: function() {
            initEventListeners();
        },
    };
});

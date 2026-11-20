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
 * Paged display: chapters that are on other pages open their page when they are clicked in the progress bar or in the
 * table of contents. The Previous and Next buttons are links; the page is kept when the content is refreshed
 * (mod_contentdesigner/elements).
 *
 * @module     mod_contentdesigner/pages
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @var {boolean} initialised Whether the listener is registered. */
let initialised = false;

/**
 * The URLs of the pages that the learner can reach, by chapter id.
 *
 * @returns {object}
 */
const chapterUrls = () => {
    const nav = document.querySelector('.contentdesigner-pagenav');
    try {
        return nav ? JSON.parse(nav.dataset.chapterurls || '{}') : {};
    } catch (e) {
        return {};
    }
};

/**
 * Opens the page of a chapter that is not on the page shown.
 *
 * @param {Event} e The click.
 */
const openChapter = (e) => {
    const target = e.target.closest(
        '.contentdesigner-chapter[data-chapter], .toc-block [data-chapter], .toc-block [data-actionid]'
    );
    if (target === null) {
        return;
    }
    const chapterid = target.dataset.chapter || target.dataset.actionid;
    if (!chapterid || document.querySelector('li.chapters-list[data-id="' + chapterid + '"]') !== null) {
        return;
    }
    const url = chapterUrls()[chapterid];
    if (url) {
        e.preventDefault();
        window.location.href = url;
    }
};

/**
 * Registers the listener.
 */
export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;
    document.body.addEventListener('click', openChapter);
};

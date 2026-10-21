<?php
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

namespace mod_contentdesigner\hook;

/**
 * Lets plugins complete a chapter automatically instead of by the learner's "Mark as done" button.
 *
 * Dispatched for every chapter that is shown to a learner. A chapter completed automatically gets no button and
 * the attribute data-autocompletion="1"; the plugin completes it from its own JavaScript through the web service
 * cdelement_chapter_update_completion.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to complete Content Designer chapters automatically.')]
#[\core\attribute\tags('mod_contentdesigner')]
final class chapter_completion {
    /** @var bool Whether the chapter is completed automatically. */
    private bool $automatic = false;

    /**
     * Creates the hook.
     *
     * @param \cm_info $cm The activity.
     * @param \stdClass $chapter The chapter (record of cdelement_chapter).
     */
    public function __construct(
        /** @var \cm_info The activity */
        public readonly \cm_info $cm,
        /** @var \stdClass The chapter (record of cdelement_chapter) */
        public readonly \stdClass $chapter,
    ) {
    }

    /**
     * Completes the chapter automatically.
     */
    public function set_automatic(): void {
        $this->automatic = true;
    }

    /**
     * Whether the chapter is completed automatically.
     *
     * @return bool
     */
    public function is_automatic(): bool {
        return $this->automatic;
    }
}

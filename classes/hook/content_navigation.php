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
 * Lets plugins allow learners to move freely through the content of a Content Designer activity.
 *
 * By default the navigation is sequential: the elements after a mandatory element that the learner has not
 * completed yet stay hidden. A plugin can make the navigation free, so that mandatory elements no longer hide
 * the elements after them. Chapters that are not completed still hide the chapters after them.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to let learners move freely through a Content Designer activity.')]
#[\core\attribute\tags('mod_contentdesigner')]
final class content_navigation {
    /** @var bool Whether the navigation is sequential. */
    private bool $sequential = true;

    /**
     * Creates the hook.
     *
     * @param \cm_info $cm The activity.
     * @param \stdClass $instance The activity record.
     */
    public function __construct(
        /** @var \cm_info The activity */
        public readonly \cm_info $cm,
        /** @var \stdClass The activity record */
        public readonly \stdClass $instance,
    ) {
    }

    /**
     * Lets learners move freely: mandatory elements no longer hide the elements after them.
     */
    public function set_free(): void {
        $this->sequential = false;
    }

    /**
     * Whether the navigation is sequential.
     *
     * @return bool
     */
    public function is_sequential(): bool {
        return $this->sequential;
    }
}

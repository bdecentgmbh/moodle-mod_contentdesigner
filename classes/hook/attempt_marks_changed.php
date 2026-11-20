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
 * Dispatched after the marks of an attempt changed after the learner finished it.
 *
 * For example after a teacher graded a question manually. The plugin that grades the activity recalculates the grade
 * of the attempt and updates the gradebook.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Dispatched after the marks of an attempt in a Content Designer activity changed.')]
#[\core\attribute\tags('mod_contentdesigner', 'grades')]
final class attempt_marks_changed {
    /**
     * Creates the hook.
     *
     * @param \cm_info $cm The activity.
     * @param \stdClass $attempt The attempt (record of contentdesigner_attempts).
     * @param string $element The shortname of the element whose marks changed.
     */
    public function __construct(
        /** @var \cm_info The activity */
        public readonly \cm_info $cm,
        /** @var \stdClass The attempt (record of contentdesigner_attempts) */
        public readonly \stdClass $attempt,
        /** @var string The shortname of the element whose marks changed */
        public readonly string $element,
    ) {
    }
}

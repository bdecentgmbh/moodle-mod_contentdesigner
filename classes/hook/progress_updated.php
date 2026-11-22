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
 * Dispatched after the progress of a learner in a Content Designer activity changed.
 *
 * For example after a chapter was completed or a learner answered an H5P element, a question, a poll or a
 * rating. Elements report their progress with {@see \mod_contentdesigner\elements::progress_updated()}.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Dispatched after the progress of a learner in a Content Designer activity changed.')]
#[\core\attribute\tags('mod_contentdesigner', 'completion')]
final class progress_updated {
    /**
     * Creates the hook.
     *
     * @param \cm_info $cm The activity.
     * @param int $userid The learner.
     * @param string $element The shortname of the element whose progress changed.
     */
    public function __construct(
        /** @var \cm_info The activity */
        public readonly \cm_info $cm,
        /** @var int The learner */
        public readonly int $userid,
        /** @var string The shortname of the element whose progress changed */
        public readonly string $element,
    ) {
    }
}

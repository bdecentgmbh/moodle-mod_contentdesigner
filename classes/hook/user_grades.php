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
 * Lets the plugin that grades Content Designer activities provide the grades for the gradebook.
 *
 * Dispatched by contentdesigner_update_grades() when the gradebook (re)calculates the grades of an activity.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to provide the gradebook grades of a Content Designer activity.')]
#[\core\attribute\tags('mod_contentdesigner', 'grades')]
final class user_grades {
    /** @var \stdClass[] Grades by user id (objects with userid and rawgrade). */
    private array $grades = [];

    /**
     * Creates the hook.
     *
     * @param \stdClass $instance The activity record.
     * @param int $userid The user, 0 for all users.
     */
    public function __construct(
        /** @var \stdClass The activity record */
        public readonly \stdClass $instance,
        /** @var int The user, 0 for all users */
        public readonly int $userid,
    ) {
    }

    /**
     * Sets the grade of a user.
     *
     * @param int $userid The user.
     * @param float|null $rawgrade The grade, null for none.
     */
    public function set_grade(int $userid, ?float $rawgrade): void {
        $this->grades[$userid] = (object) ['userid' => $userid, 'rawgrade' => $rawgrade];
    }

    /**
     * The grades set by the plugins.
     *
     * @return \stdClass[] Grades by user id.
     */
    public function get_grades(): array {
        return $this->grades;
    }
}

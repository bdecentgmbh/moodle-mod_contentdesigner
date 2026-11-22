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
 * Dispatched after a Content Designer activity was created or updated.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Dispatched after a Content Designer activity was created or updated.')]
#[\core\attribute\tags('mod_contentdesigner')]
final class after_instance_saved {
    /**
     * Creates the hook.
     *
     * @param \stdClass $data The saved data: the activity record with the submitted form values
     *                        (coursemodule, cmidnumber, ...).
     * @param bool $created Whether the activity was created.
     */
    public function __construct(
        /** @var \stdClass The saved data */
        public readonly \stdClass $data,
        /** @var bool Whether the activity was created */
        public readonly bool $created,
    ) {
    }
}

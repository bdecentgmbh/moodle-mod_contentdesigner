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

use mod_contentdesigner\elements;

/**
 * Lets plugins add settings to the form of an element (including chapters).
 *
 * Dispatched after the element's own settings and the general settings. Fields named after columns of the
 * element's table (cdelement_<shortname>) or of contentdesigner_options are saved with the element.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to add settings to the forms of Content Designer elements.')]
#[\core\attribute\tags('mod_contentdesigner', 'form')]
final class extend_element_form {
    /**
     * Creates the hook.
     *
     * @param elements $element The element.
     * @param \MoodleQuickForm $mform The form elements.
     * @param \stdClass $instance The activity record.
     * @param int $instanceid The element instance, 0 for a new one.
     */
    public function __construct(
        /** @var elements The element */
        public readonly elements $element,
        /** @var \MoodleQuickForm The form elements */
        public readonly \MoodleQuickForm $mform,
        /** @var \stdClass The activity record */
        public readonly \stdClass $instance,
        /** @var int The element instance, 0 for a new one */
        public readonly int $instanceid,
    ) {
    }
}

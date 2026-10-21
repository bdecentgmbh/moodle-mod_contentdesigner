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
 * Lets plugins add settings to the Content Designer activity form.
 *
 * Dispatched after the name and description, before the standard course module settings. Fields named
 * after columns of the contentdesigner table are loaded and saved by the activity; other values have to be
 * stored by the plugin in {@see after_instance_saved}.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to add settings to the Content Designer activity form.')]
#[\core\attribute\tags('mod_contentdesigner', 'form')]
final class extend_mod_form {
    /**
     * Creates the hook.
     *
     * @param \moodleform_mod $form The activity form.
     * @param \MoodleQuickForm $mform The form elements.
     * @param \stdClass|null $instance The activity record, null for a new activity.
     */
    public function __construct(
        /** @var \moodleform_mod The activity form */
        public readonly \moodleform_mod $form,
        /** @var \MoodleQuickForm The form elements */
        public readonly \MoodleQuickForm $mform,
        /** @var \stdClass|null The activity record, null for a new activity */
        public readonly ?\stdClass $instance,
    ) {
    }
}

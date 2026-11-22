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

namespace mod_contentdesigner\local;

/**
 * A custom activity completion rule that a plugin adds to Content Designer.
 *
 * Plugins register their rules through the {@see \mod_contentdesigner\hook\completion_rules} hook. The name of
 * the rule is the name of the column of the contentdesigner table that stores whether the rule is enabled.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface completion_rule {
    /**
     * The name of the rule, which is also the column of the contentdesigner table.
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * The description of the rule in the activity completion details, for example "Reach the end".
     *
     * @return string
     */
    public function get_description(): string;

    /**
     * Adds the form elements of the rule to the activity form or the default completion form.
     *
     * @param \MoodleQuickForm $mform The form.
     * @param string $suffix The suffix of the completion element names (Moodle 4.3+), may be empty.
     * @return string[] The names of the added elements.
     */
    public function add_form_elements(\MoodleQuickForm $mform, string $suffix): array;

    /**
     * Whether the submitted form data enables the rule.
     *
     * @param array $data The submitted data.
     * @param string $suffix The suffix of the completion element names, may be empty.
     * @return bool
     */
    public function is_enabled(array $data, string $suffix): bool;

    /**
     * The completion state of the rule for a user.
     *
     * @param \cm_info $cm The activity.
     * @param int $userid The user.
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE.
     */
    public function get_state(\cm_info $cm, int $userid): int;
}

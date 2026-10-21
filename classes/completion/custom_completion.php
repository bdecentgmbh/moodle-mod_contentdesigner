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

/**
 * Custom contentdesigner activity completion.
 *
 * @package   mod_contentdesigner
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_contentdesigner\completion;

use core_completion\activity_custom_completion;
use mod_contentdesigner\local\extensions;

/**
 * Custom activity completion of Content Designer: the rules come from the addons.
 *
 * @package    mod_contentdesigner
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * Fetches the completion state for a given completion rule.
     *
     * @param string $rule The completion rule.
     * @return int The completion state.
     */
    public function get_state(string $rule): int {
        $this->validate_rule($rule);
        $rules = extensions::completion_rules();
        if (!isset($rules[$rule])) {
            return COMPLETION_INCOMPLETE;
        }
        return $rules[$rule]->get_state($this->cm, $this->userid);
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return array_keys(extensions::completion_rules());
    }

    /**
     * Returns an associative array of the descriptions of custom completion rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        $descriptions = [];
        foreach (extensions::completion_rules() as $name => $rule) {
            $descriptions[$name] = $rule->get_description();
        }
        return $descriptions;
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return array_merge(
            ['completionview'],
            array_keys(extensions::completion_rules()),
            ['completionusegrade', 'completionpassgrade']
        );
    }
}

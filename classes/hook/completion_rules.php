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

use mod_contentdesigner\local\completion_rule;

/**
 * Lets plugins add custom activity completion rules to Content Designer.
 *
 * The rules are collected once per request; they must not depend on an activity or a user.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to add custom activity completion rules to Content Designer.')]
#[\core\attribute\tags('mod_contentdesigner', 'completion')]
final class completion_rules {
    /** @var completion_rule[] The rules by name. */
    private array $rules = [];

    /**
     * Adds a completion rule.
     *
     * @param completion_rule $rule The rule.
     */
    public function add_rule(completion_rule $rule): void {
        $this->rules[$rule->get_name()] = $rule;
    }

    /**
     * The rules added by the plugins.
     *
     * @return completion_rule[] The rules by name.
     */
    public function get_rules(): array {
        return $this->rules;
    }
}

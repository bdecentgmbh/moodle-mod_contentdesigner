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
 * Lets plugins offer more types for the primary and secondary button of the outro.
 *
 * The types 0 to 4 belong to the outro element itself; plugins use other numbers and render their buttons in
 * {@see outro_button}.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to offer more button types in the outro of Content Designer.')]
#[\core\attribute\tags('mod_contentdesigner')]
final class outro_button_types {
    /** @var string[] Labels by button type. */
    private array $types = [];

    /**
     * Adds a button type.
     *
     * @param int $type The type, a number above 4 that the plugin keeps stable (it is stored).
     * @param string $label The label in the outro settings.
     */
    public function add_type(int $type, string $label): void {
        $this->types[$type] = $label;
    }

    /**
     * The types added by the plugins.
     *
     * @return string[] Labels by button type.
     */
    public function get_types(): array {
        return $this->types;
    }
}

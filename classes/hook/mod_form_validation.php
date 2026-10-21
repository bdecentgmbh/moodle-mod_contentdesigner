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
 * Lets plugins validate the settings they added to the Content Designer activity form.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to validate their settings in the Content Designer activity form.')]
#[\core\attribute\tags('mod_contentdesigner', 'form')]
final class mod_form_validation {
    /** @var array Errors by form element name. */
    private array $errors = [];

    /**
     * Creates the hook.
     *
     * @param array $data The submitted data.
     * @param array $files The submitted files.
     */
    public function __construct(
        /** @var array The submitted data */
        public readonly array $data,
        /** @var array The submitted files */
        public readonly array $files,
    ) {
    }

    /**
     * Adds an error to a form element.
     *
     * @param string $element The name of the form element.
     * @param string $message The error message.
     */
    public function add_error(string $element, string $message): void {
        $this->errors[$element] = $message;
    }

    /**
     * The errors added by the plugins.
     *
     * @return array Errors by form element name.
     */
    public function get_errors(): array {
        return $this->errors;
    }
}

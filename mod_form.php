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
 * Content designer module form.
 *
 * @package    mod_contentdesigner
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/contentdesigner/lib.php');

use mod_contentdesigner\hook\extend_mod_form;
use mod_contentdesigner\hook\mod_form_validation;
use mod_contentdesigner\local\extensions;

/**
 * Content designer module form.
 */
class mod_contentdesigner_mod_form extends moodleform_mod {
    /**
     * Define the mform elements.
     * @return void
     */
    public function definition() {
        global $CFG, $DB;

        $mform =& $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => '48']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // Addons add their settings, for example the navigation and the grading of attempts.
        $instance = $this->_instance ? $DB->get_record('contentdesigner', ['id' => $this->_instance]) : null;
        extensions::dispatch(new extend_mod_form($this, $mform, $instance ?: null));

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * The custom completion rules of the addons.
     *
     * @return array The names of the form elements.
     */
    public function add_completion_rules() {
        $elements = [];
        foreach (extensions::completion_rules() as $rule) {
            $elements = array_merge($elements, $rule->add_form_elements($this->_form, $this->get_suffix()));
        }
        return $elements;
    }

    /**
     * Whether a custom completion rule of the addons is enabled.
     *
     * @param array $data Input data not yet validated.
     * @return bool True if one or more rules is enabled, false if none are.
     */
    public function completion_rule_enabled($data) {
        foreach (extensions::completion_rules() as $rule) {
            if ($rule->is_enabled($data, $this->get_suffix())) {
                return true;
            }
        }
        return false;
    }

    /**
     * Validate the form data.
     *
     * @param array $data Form data.
     * @param array $files Form files.
     * @return array Array of errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $hook = extensions::dispatch(new mod_form_validation($data, $files));
        return array_merge($errors, $hook->get_errors());
    }
}

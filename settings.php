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
 * Contentdesigner module settings.
 *
 * @package   mod_contentdesigner
 * @copyright 2024, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/mod/contentdesigner/lib.php');

$ADMIN->add('modsettings', new admin_category('modcontentdesigner', new lang_string('pluginname', 'mod_contentdesigner')));
$settings = new admin_settingpage('contentdesignergeneralsettings', get_string('gerneralsettings', 'mod_contentdesigner'),
     'moodle/site:config', false);

if ($ADMIN->fulltree) {

    // Chapter title status.
    $settings->add(new admin_setting_configcheckbox('mod_contentdesigner/chaptertitlestatus',
    get_string('titlestatus', 'mod_contentdesigner'), get_string('titlestatus_help', 'mod_contentdesigner'), 0
    ));

}

$ADMIN->add('modcontentdesigner', $settings);

$settings = null; // Reset the settings.

foreach (\core_plugin_manager::instance()->get_plugins_of_type('element') as $plugin) {
    // Load all the element plugins settings pages
    $plugin->load_settings($ADMIN, 'modcontentdesigner', $hassiteconfig);
}


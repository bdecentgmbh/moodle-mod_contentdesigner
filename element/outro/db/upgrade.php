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
 * Upgrade script for the outro element.
 *
 * @package    element_outro
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrede outro element.
 *
 * @param string $oldversion the version we are upgrading from.
 */
function xmldb_element_outro_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024110800) {

        // Element outro table.
        $table = new xmldb_table('element_outro');

        // Outrocontent.
        $outrocontent = new xmldb_field('outrocontent', XMLDB_TYPE_TEXT, null, null, null, null, null, 'secondaryurl');
        // Outrocontent format.
        $outrocontentformat = new xmldb_field('outrocontentformat', XMLDB_TYPE_INTEGER, '2', null, null, null, null, 'outrocontent');
        // Primary button.
        $primarybutton = new xmldb_field('primarybutton', XMLDB_TYPE_INTEGER, '9', null, null, null, '0', 'outrocontentformat');
        // Secondary button.
        $secondarybutton = new xmldb_field('secondarybutton', XMLDB_TYPE_INTEGER, '9', null, null, null, '0', 'primarybutton');

        if (!$dbman->field_exists($table, $outrocontent)) {
            $dbman->add_field($table, $outrocontent);
        }

        if (!$dbman->field_exists($table, $outrocontentformat)) {
            $dbman->add_field($table, $outrocontentformat);
        }

        if (!$dbman->field_exists($table, $primarybutton)) {
            $dbman->add_field($table, $primarybutton);
        }

        if (!$dbman->field_exists($table, $secondarybutton)) {
            $dbman->add_field($table, $secondarybutton);
        }

        upgrade_plugin_savepoint(true, 2024110800, 'element', 'outro');
    }
    return true;
}

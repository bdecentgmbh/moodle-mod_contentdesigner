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
 * Upgrade script for the chapter element.
 *
 * @package    element_chapter
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrede chapter element.
 *
 * @param string $oldversion the version we are upgrading from.
 */
function xmldb_element_chapter_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024110800) {

        // Element chapter table.
        $table = new xmldb_table('element_chapter');

        // Title status.
        $titlestatus = new xmldb_field('titlestatus', XMLDB_TYPE_INTEGER, '2', null, null, null, '0', 'position');
        if (!$dbman->field_exists($table, $titlestatus)) {
            $dbman->add_field($table, $titlestatus);
        }

        upgrade_plugin_savepoint(true, 2024110800, 'element', 'chapter');
    }

    return true;
}

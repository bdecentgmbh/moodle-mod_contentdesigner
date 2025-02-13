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
 * @package    cdelement_chapter
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrede chapter element.
 *
 * @param string $oldversion the version we are upgrading from.
 * @return bool
 */
function xmldb_cdelement_chapter_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion && $oldversion < 2024110801) {

        // Element chapter table.
        $table = new xmldb_table('cdelement_chapter');

        // Title status.
        $titlestatus = new xmldb_field('titlestatus', XMLDB_TYPE_INTEGER, '2', null, null, null, '0', 'position');
        if (!$dbman->field_exists($table, $titlestatus)) {
            $dbman->add_field($table, $titlestatus);
        }

        upgrade_plugin_savepoint(true, 2024110801, 'cdelement', 'chapter');
    }

    if ($oldversion < 1) {
        // Rename the table element_chapter to cdelement_chapter.
        $table = new xmldb_table('element_chapter');
        $cdchaptertable = new xmldb_table('cdelement_chapter');

        if ($dbman->table_exists($table)) {

            if (!$dbman->table_exists($cdchaptertable)) {
                // Rename the existing table.
                $dbman->rename_table($table, 'cdelement_chapter');
            } else {
                // Drop the existing table.
                $dbman->drop_table($cdchaptertable);
                // Rename the existing table.
                $dbman->rename_table($table, 'cdelement_chapter');
            }
        }

        // Rename the table element_chapter_completion to cdelement_chapter_completion.
        $completiontable = new xmldb_table('element_chapter_completion');
        $cdcompletiontable = new xmldb_table('cdelement_chapter_completion');

        if ($dbman->table_exists($completiontable)) {

            if (!$dbman->table_exists($cdcompletiontable)) {
                // Rename the existing table.
                $dbman->rename_table($completiontable, 'cdelement_chapter_completion');
            } else {
                // Drop the existing table.
                $dbman->drop_table($cdcompletiontable);
                // Rename the existing table.
                $dbman->rename_table($completiontable, 'cdelement_chapter_completion');
            }
        }

        // Rename admin configuration settings.
        mod_contentdesigner\plugininfo\cdelement::update_plugins_config('element_chapter', 'cdelement_chapter');
    }

    return true;
}

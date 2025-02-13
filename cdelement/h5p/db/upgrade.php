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
function xmldb_cdelement_h5p_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 1) {
        // Rename the table element_h5p to cdelement_h5p.
        $h5ptable = new xmldb_table('element_h5p');
        $cdh5ptable = new xmldb_table('cdelement_h5p');

        if ($dbman->table_exists($h5ptable)) {

            if (!$dbman->table_exists($cdh5ptable)) {
                // Rename the existing table.
                $dbman->rename_table($h5ptable, 'cdelement_h5p');
            } else {
                // Drop the existing table.
                $dbman->drop_table($cdh5ptable);
                // Rename the existing table.
                $dbman->rename_table($h5ptable, 'cdelement_h5p');
            }
        }

        // Rename the table element_h5p_completion to cdelement_h5p_completion.
        $h5pcompletiontable = new xmldb_table('element_h5p_completion');
        $cdh5pcompletiontable = new xmldb_table('cdelement_h5p_completion');

        if ($dbman->table_exists($h5pcompletiontable)) {

            if (!$dbman->table_exists($cdh5pcompletiontable)) {
                // Rename the existing table.
                $dbman->rename_table($h5pcompletiontable, 'cdelement_h5p_completion');
            } else {
                // Drop the existing table.
                $dbman->drop_table($cdh5pcompletiontable);
                // Rename the existing table.
                $dbman->rename_table($h5pcompletiontable, 'cdelement_h5p_completion');
            }
        }

        // Rename admin configuration settings.
        mod_contentdesigner\plugininfo\cdelement::update_plugins_config('element_h5p', 'cdelement_h5p', ['package']);
    }

    return true;
}
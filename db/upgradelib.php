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
 * Functions for the install and upgrade scripts of mod_contentdesigner and its elements.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Replaces the table of an element with its table from before the elements were renamed (element_* to cdelement_*,
 * Content Designer 1.4). An existing table is only replaced if it holds no data: the install of the renamed element
 * has just created it. If it holds data, the upgrade stops, so that the administrator decides which data to keep.
 *
 * @param string $legacy The table before the rename, for example element_chapter.
 * @param string $table The table now, for example cdelement_chapter.
 * @return void
 */
function contentdesigner_rename_legacy_table(string $legacy, string $table): void {
    global $DB;
    $dbman = $DB->get_manager();
    $legacytable = new xmldb_table($legacy);
    if (!$dbman->table_exists($legacytable)) {
        return;
    }
    $newtable = new xmldb_table($table);
    if ($dbman->table_exists($newtable)) {
        if ($DB->count_records($table) > 0) {
            throw new moodle_exception('upgradelegacytable', 'mod_contentdesigner', '', ['legacy' => $legacy, 'table' => $table]);
        }
        $dbman->drop_table($newtable);
    }
    $dbman->rename_table($legacytable, $table);
}

/**
 * Adds an index to a table, unless the table has it.
 *
 * @param string $table The table.
 * @param string $name The name of the index.
 * @param string[] $fields The fields of the index.
 * @param bool $unique Whether the index is unique.
 * @return void
 */
function contentdesigner_add_index(string $table, string $name, array $fields, bool $unique = false): void {
    global $DB;
    $dbman = $DB->get_manager();
    $xmldbtable = new xmldb_table($table);
    $index = new xmldb_index($name, $unique ? XMLDB_INDEX_UNIQUE : XMLDB_INDEX_NOTUNIQUE, $fields);
    if ($dbman->table_exists($xmldbtable) && !$dbman->index_exists($xmldbtable, $index)) {
        $dbman->add_index($xmldbtable, $index);
    }
}

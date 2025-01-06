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
 * Upgrade file.
 *
 * @package    mod_contentdesigner
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the Content Designer plugin to a new version.
 *
 * This function is responsible for handling the upgrade process
 * for the Content Designer plugin. It checks the current version
 * and applies necessary changes to upgrade to the latest version.
 *
 * @param string $oldversion The version we are upgrading from.
 * @return bool True on success.
 */
function xmldb_contentdesigner_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024110807) {
        $optionstable = new xmldb_table('contentdesigner_options');
        $delay = new xmldb_field('delay', XMLDB_TYPE_CHAR, '10', null, null, null, '0', 'duration');
        if ($dbman->field_exists($optionstable, $delay)) {
            $dbman->change_field_type($optionstable, $delay);
        }

        upgrade_mod_savepoint(true, 2024110807, 'contentdesigner');
    }

    return true;

}

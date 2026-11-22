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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../db/upgradelib.php');

/**
 * Upgrede chapter element.
 *
 * @param string $oldversion the version we are upgrading from.
 * @param bool $rename Due to changing the plugin name, we need to rename the existing table.
 * @return bool
 */
function xmldb_cdelement_chapter_upgrade($oldversion, $rename = false) {
    global $DB;

    $dbman = $DB->get_manager();

    // Check if the plugin is renamed.
    // If the plugin is renamed, we need to rename the existing table without upgrade the save point.
    $runwithoutupgradpoint = $rename;

    // Upgraded from the free edition 1.x: run the steps of the pro edition as well. The flag is set by the upgrade of
    // mod_contentdesigner (db/upgrade.php) and removed at the end of this upgrade.
    $fromfree = (bool) get_config('mod_contentdesigner', 'upgradefromfree_chapter');
    if ($rename || $fromfree) {
        $runwithoutupgradpoint = true;
    }

    if ($runwithoutupgradpoint) {
        contentdesigner_rename_legacy_table('element_chapter', 'cdelement_chapter');
        contentdesigner_rename_legacy_table('element_chapter_completion', 'cdelement_chapter_completion');

        // Rename admin configuration settings.
        mod_contentdesigner\plugininfo\cdelement::update_plugins_config('element_chapter', 'cdelement_chapter');
    }

    if ($oldversion < 2024110801 || $runwithoutupgradpoint) {
        // Element chapter table.
        $table = new xmldb_table('cdelement_chapter');

        // Title status.
        $titlestatus = new xmldb_field('titlestatus', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'position');
        if (!$dbman->field_exists($table, $titlestatus)) {
            $dbman->add_field($table, $titlestatus);
        }

        // Chapter completion.
        $completionmode = new xmldb_field('completionmode', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'titlestatus');
        if (!$dbman->field_exists($table, $completionmode)) {
            $dbman->add_field($table, $completionmode);
        }

        // Don't run the upgrade savepoint if the plugin is renamed.
        if (!$runwithoutupgradpoint) {
            upgrade_plugin_savepoint(true, 2024110801, 'cdelement', 'chapter');
        }
    }

    if ($oldversion < 2025021303 || $runwithoutupgradpoint) {
        // Element chapter table.
        $table = new xmldb_table('cdelement_chapter');

        // Title status.
        $titlestatus = new xmldb_field('titlestatus', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'position');

        if ($dbman->field_exists($table, $titlestatus)) {
            // Ensure existing NULL values are replaced with the default value.
            $DB->execute("UPDATE {cdelement_chapter} SET titlestatus = '0' WHERE titlestatus IS NULL");
            // Change the field to NOT NULL.
            $dbman->change_field_notnull($table, $titlestatus);
        }

        // Chapter completion mode.
        $completionmode = new xmldb_field('completionmode', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'titlestatus');
        if ($dbman->field_exists($table, $completionmode)) {
            // Ensure existing NULL values are replaced with the default value.
            $DB->execute("UPDATE {cdelement_chapter} SET completionmode = '0' WHERE completionmode IS NULL");
            // Change the field to NOT NULL.
            $dbman->change_field_notnull($table, $completionmode);
        }

        // Don't run the upgrade savepoint if the plugin is renamed.
        if (!$runwithoutupgradpoint) {
            upgrade_plugin_savepoint(true, 2025021303, 'cdelement', 'chapter');
        }
    }

    if ($oldversion < 2025050601 || $runwithoutupgradpoint) {
        // Define field learningtools to be added to cdelement_chapter.
        $table = new xmldb_table('cdelement_chapter');
        $field = new xmldb_field('learningtools', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'visible');
        // Add field if it doesn't already exist; existing chapters get the default 0. Chapters of the free edition
        // already have the field, their values are kept.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Don't run the upgrade savepoint if the plugin is renamed.
        if (!$runwithoutupgradpoint) {
            // Save new version.
            upgrade_plugin_savepoint(true, 2025050601, 'cdelement', 'chapter');
        }
    }

    if ($oldversion < 2025050602 || $runwithoutupgradpoint) {
        // Schema alignment for v1.6 release.
        $table = new xmldb_table('cdelement_chapter');

        // ...titlestatus — enforce NOT NULL.
        $titlestatus = new xmldb_field('titlestatus', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'position');
        if ($dbman->field_exists($table, $titlestatus)) {
            $DB->execute("UPDATE {cdelement_chapter} SET titlestatus = '0' WHERE titlestatus IS NULL");
            $dbman->change_field_notnull($table, $titlestatus);
        }

        // Completionmode — enforce NOT NULL.
        $completionmode = new xmldb_field(
            'completionmode',
            XMLDB_TYPE_INTEGER,
            '2',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'titlestatus'
        );
        if ($dbman->field_exists($table, $completionmode)) {
            $DB->execute("UPDATE {cdelement_chapter} SET completionmode = '0' WHERE completionmode IS NULL");
            $dbman->change_field_notnull($table, $completionmode);
        }

        // Don't run the upgrade savepoint if the plugin is renamed.
        if (!$runwithoutupgradpoint) {
            upgrade_plugin_savepoint(true, 2025050602, 'cdelement', 'chapter');
        }
    }

    if ($oldversion < 2026112100 || $runwithoutupgradpoint) {
        // Indexes of the fields that the element is looked up by.
        contentdesigner_add_index('cdelement_chapter', 'contentdesignerid', ['contentdesignerid']);
        contentdesigner_add_index('cdelement_chapter_completion', 'instance_userid', ['instance', 'userid']);

        if (!$runwithoutupgradpoint) {
            upgrade_plugin_savepoint(true, 2026112100, 'cdelement', 'chapter');
        }
    }

    if ($fromfree) {
        unset_config('upgradefromfree_chapter', 'mod_contentdesigner');
    }

    return true;
}

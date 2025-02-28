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
 * This file contains the backup code for the cdelement_richtext plugin.
 *
 * @package   cdelement_richtext
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Provides the information to backup richtext content and files.
 *
 * This just adds its filearea to the annotations and records the number of files.
 */
class backup_cdelement_richtext_subplugin extends backup_subplugin {

    /**
     * Returns the subplugin information to attach to richtext element.
     * @return backup_subplugin_element
     */
    protected function define_contentdesigner_subplugin_structure() {

        // Create XML elements.
        $subplugin = $this->get_subplugin_element();
        $subpluginwrapper = new backup_nested_element($this->get_recommended_name());
        $subpluginelement = new backup_nested_element('cdelement_richtext', ['id'], [
            'contentdesignerid', 'title', 'visible', 'content', 'contentformat', 'timecreated', 'timemodified',
        ]);

        // Connect XML elements into the tree.
        $subplugin->add_child($subpluginwrapper);
        $subpluginwrapper->add_child($subpluginelement);

        // Set source to populate the data.
        $subpluginelement->set_source_table('cdelement_richtext', ['contentdesignerid' => backup::VAR_PARENTID]);
        $subpluginelement->annotate_ids('richtext_instanceid', 'id');

        $subpluginelement->annotate_files('mod_contentdesigner', 'cdelement_richtext_content', null);

        return $subplugin;
    }

}

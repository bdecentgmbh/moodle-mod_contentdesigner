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
 * This file contains the restore code for the cdelement_callout plugin.
 *
 * @package    cdelement_callout
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use cdelement_callout\element;

/**
 * Restore subplugin class.
 *
 * Provides the necessary information needed to restore cdelement_callout.
 */
class restore_cdelement_callout_subplugin extends restore_subplugin {
    /**
     * Returns the paths to be handled by the subplugin.
     *
     * @return array
     */
    protected function define_contentdesigner_subplugin_structure() {

        $paths = [];

        $elename = $this->get_namefor('instance');
        // We used get_recommended_name() so this works.
        $elepath = $this->get_pathfor('/cdelement_callout');
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths;
    }

    /**
     * Processes one cdelement_callout element.
     *
     * @param array $data
     */
    public function process_cdelement_callout_instance($data) {
        global $DB;

        $data = (object) $data;

        $oldinstance = $data->id;
        $data->contentdesignerid = $this->get_new_parentid('contentdesigner');
        // Backups may contain anything: keep only the known types.
        $data->callouttype = element::clean_type($data->callouttype ?? '');
        $data->visible = empty($data->visible) ? 0 : 1;
        $data->contentformat = element::clean_format($data->contentformat ?? FORMAT_HTML);
        $data->callouttitle = core_text::substr(clean_param((string) ($data->callouttitle ?? ''), PARAM_TEXT), 0, 255);
        $data->title = core_text::substr(clean_param((string) ($data->title ?? ''), PARAM_TEXT), 0, 255);
        // Trust is not taken from the backup: the content is shown without cleaning only if the restoring user may
        // trust content.
        $data->contenttrust = (int) element::can_trust_content(\context::instance_by_id($this->task->get_contextid()));

        $newinstance = $DB->insert_record('cdelement_callout', $data);
        $this->set_mapping('callout_instanceid', $oldinstance, $newinstance, true);
    }

    /**
     * Restores the files of the content and the background images after the instances are restored.
     *
     * @return void
     */
    public function after_execute_contentdesigner() {
        $this->add_related_files('mod_contentdesigner', 'cdelement_callout_content', 'callout_instanceid');
        $this->add_related_files('mod_contentdesigner', 'calloutelementbg', 'callout_instanceid');
    }

    /**
     * The contents of the element with links to decode after a restore.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents() {
        return [new restore_decode_content('cdelement_callout', ['content'], 'callout_instanceid')];
    }
}

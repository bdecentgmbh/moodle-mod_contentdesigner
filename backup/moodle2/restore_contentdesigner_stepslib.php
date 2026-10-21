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
 * Definition restore structure steps.
 *
 * @package   mod_contentdesigner
 * @copyright 2022, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_contentdesigner\editor;

/**
 * Structure step to restore contentdesigner activity.
 */
class restore_contentdesigner_activity_structure_step extends restore_activity_structure_step {
    /**
     * Restore steps structure definition.
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $element = new restore_path_element('contentdesigner', '/activity/contentdesigner');
        $paths[] = $element;

        // The attempts first: the user data of the elements refers to them.
        if ($userinfo) {
            $paths[] = new restore_path_element(
                'contentdesigner_attempts',
                '/activity/contentdesigner/attempts/contentdesigner_attempts'
            );
        }

        $paths[] = new restore_path_element(
            'contentdesigner_elements',
            '/activity/contentdesigner/elements/contentdesigner_elements'
        );

        if ($userinfo) {
            $paths[] = new restore_path_element(
                'contentdesigner_completion',
                '/activity/contentdesigner/completion/contentdesigner_completion'
            );
        }

        $this->add_subplugin_structure('cdelement', $element);

        $paths[] = new restore_path_element(
            'contentdesigner_content',
            '/activity/contentdesigner/content/contentdesigner_content'
        );
        $paths[] = new restore_path_element(
            'contentdesigner_options',
            '/activity/contentdesigner/contentdesigneropitons/contentdesigner_options'
        );

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores the activity record.
     *
     * @param array $data The data of the activity.
     */
    protected function process_contentdesigner($data) {
        global $DB;
        $data = (object) $data;
        $data->course = $this->get_courseid();
        $newitemid = $DB->insert_record('contentdesigner', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Maps the elements of the backup to the elements installed on this site, by shortname. Elements that are not
     * installed here are not restored.
     *
     * @param array $data The element record of the backup.
     */
    protected function process_contentdesigner_elements($data) {
        global $DB;
        $data = (object) $data;
        if (!in_array($data->shortname, editor::get_element_names(), true)) {
            return;
        }
        $elementid = $DB->get_field('contentdesigner_elements', 'id', ['shortname' => $data->shortname]);
        if ($elementid) {
            $this->set_mapping('elements', $data->id, $elementid);
        }
    }

    /**
     * The new instance id of an element instance of the backup, if the element and the instance were restored.
     *
     * @param int $oldelementid The element id in the backup.
     * @param int $oldinstanceid The instance id in the backup.
     * @return array [new element id, new instance id, element shortname], or [0, 0, ''] if not restored.
     */
    protected function map_instance(int $oldelementid, int $oldinstanceid): array {
        global $DB;
        $elementid = (int) $this->get_mappingid('elements', $oldelementid);
        if (!$elementid) {
            return [0, 0, ''];
        }
        $name = $DB->get_field('contentdesigner_elements', 'shortname', ['id' => $elementid]);
        $mapping = ($name == 'chapter') ? 'chapterid' : $name . '_instanceid';
        return [$elementid, (int) $this->get_mappingid($mapping, $oldinstanceid), $name];
    }

    /**
     * Restores an element instance in a chapter.
     *
     * @param array $data The content record.
     */
    protected function process_contentdesigner_content($data) {
        global $DB;
        $data = (object) $data;
        [$data->element, $data->instance] = $this->map_instance((int) $data->element, (int) $data->instance);
        $data->chapter = (int) $this->get_mappingid('chapterid', $data->chapter);
        if (!$data->element || !$data->instance || !$data->chapter) {
            return;
        }
        $data->contentdesignerid = $this->get_new_parentid('contentdesigner');
        $data->timemodified = time();
        $contentid = $DB->insert_record('contentdesigner_content', $data);

        // Add the content to the sequence of its chapter.
        $contents = array_filter(explode(',', (string) $DB->get_field('cdelement_chapter', 'contents', ['id' => $data->chapter])));
        $contents[] = $contentid;
        $DB->set_field('cdelement_chapter', 'contents', implode(',', $contents), ['id' => $data->chapter]);
    }

    /**
     * Restores an attempt of a learner.
     *
     * @param array $data The attempt.
     */
    protected function process_contentdesigner_attempts($data) {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (!$data->userid) {
            return;
        }
        $data->contentdesignerid = $this->get_new_parentid('contentdesigner');
        $newid = $DB->insert_record('contentdesigner_attempts', $data);
        $this->set_mapping('contentdesigner_attempt', $oldid, $newid);
    }

    /**
     * Restores the general options of an element instance.
     *
     * @param array $data The options.
     */
    protected function process_contentdesigner_options($data) {
        global $DB;
        $data = (object) $data;
        [$data->element, $data->instance] = $this->map_instance((int) $data->element, (int) $data->instance);
        if (
            !$data->element || !$data->instance
                || $DB->record_exists('contentdesigner_options', ['element' => $data->element, 'instance' => $data->instance])
        ) {
            return;
        }
        $data->timemodified = time();
        $DB->insert_record('contentdesigner_options', $data);
    }

    /**
     * Restores the completion state of a learner.
     *
     * @param array $data The completion record.
     */
    protected function process_contentdesigner_completion($data) {
        global $DB;
        $data = (object) $data;
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (!$data->userid) {
            return;
        }
        $data->contentdesignerid = $this->get_new_parentid('contentdesigner');
        $DB->insert_record('contentdesigner_completion', $data);
    }

    /**
     * Restores the files of the activity and the descriptions of the elements.
     */
    protected function after_execute() {
        $this->add_related_files('mod_contentdesigner', 'intro', null);
        foreach (editor::get_element_names() as $name) {
            if ($name != 'chapter') {
                $this->add_related_files('mod_contentdesigner', $name . 'description', $name . '_instanceid');
            }
        }
    }
}

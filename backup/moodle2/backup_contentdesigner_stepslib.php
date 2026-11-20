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
 * Definition backup-steps
 *
 * @package   mod_contentdesigner
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_contentdesigner\editor;
/**
 * Define the complete contentdesigner structure for backup, with file and id annotations.
 */
class backup_contentdesigner_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define backup steps structure.
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated - table fields.
        $contentdesigner = new backup_nested_element('contentdesigner', ['id'], [
            'course', 'name', 'intro', 'introformat', 'completionendreach', 'navigation', 'completionmandatory', 'timecreated',
            'timemodified', 'enablegrading', 'autostartattempts', 'grade', 'grademethod', 'attemptsallowed', 'displaymode']);

        // The attempts come first: the user data of the elements refers to them, so they are mapped on restore before.
        $attempts = new backup_nested_element('attempts');
        $contentdesignerattempts = new backup_nested_element('contentdesigner_attempts', ['id'], [
            'userid', 'attempt', 'grade', 'timestart', 'timefinish',
        ]);
        $contentdesigner->add_child($attempts);
        $attempts->add_child($contentdesignerattempts);

        $this->add_subplugin_structure('cdelement', $contentdesigner, true);

        $completion = new backup_nested_element('completion');
        $contentdesignercompletion = new backup_nested_element('contentdesigner_completion', ['id'], [
            'userid', 'completion', 'mandatorycompletion', 'starttime', 'timecreated']);

        $elements = new backup_nested_element('elements');
        $contentdesignerelements = new backup_nested_element('contentdesigner_elements', ['id'], [
            'shortname', 'visible', 'timemodified']);

        $content = new backup_nested_element('content');
        $contentdesignercontent = new backup_nested_element('contentdesigner_content', ['id'], [
            'element', 'instance', 'chapter', 'position', 'timecreated', 'timemodified',
        ]);

        $options = new backup_nested_element('contentdesigneropitons');
        $contentdesigneroptions = new backup_nested_element('contentdesigner_options', ['id'], [
            'element', 'instance', 'description', 'descriptionformat', 'showdescription', 'margin', 'padding', 'abovecolorbg',
            'bgimage', 'belowcolorbg', 'animation', 'duration', 'delay', 'direction', 'speed', 'viewport', 'hidedesktop',
            'hidetablet', 'hidemobile', 'excludeungraded', 'timecreated', 'timemodified',
        ]);

        // Build the tree.
        $contentdesigner->add_child($elements);
        $elements->add_child($contentdesignerelements);
        $contentdesigner->add_child($completion);
        $completion->add_child($contentdesignercompletion);
        $contentdesigner->add_child($content);
        $content->add_child($contentdesignercontent);
        $contentdesigner->add_child($options);
        $options->add_child($contentdesigneroptions);

        // Define sources.
        $contentdesigner->set_source_table('contentdesigner', ['id' => backup::VAR_ACTIVITYID]);
        $contentdesignerelements->set_source_sql('SELECT * FROM {contentdesigner_elements}', []);
        $contentdesignercontent->set_source_table('contentdesigner_content', ['contentdesignerid' => backup::VAR_PARENTID]);
        // General options of all element instances of the activity (also those outside chapters: chapters, outro).
        $wheres = [];
        $params = [];
        foreach (editor::get_element_names() as $i => $name) {
            $wheres[] = "(ce.shortname = :sn$i
                          AND co.instance IN (SELECT id FROM {cdelement_$name} WHERE contentdesignerid = :cd$i))";
            $params["sn$i"] = backup_helper::is_sqlparam($name);
            $params["cd$i"] = backup::VAR_PARENTID;
        }
        if ($wheres) {
            $sql = 'SELECT co.*
                      FROM {contentdesigner_options} co
                      JOIN {contentdesigner_elements} ce ON ce.id = co.element
                     WHERE ' . implode(' OR ', $wheres);
            $contentdesigneroptions->set_source_sql($sql, $params);
        }

        // The progress of the learners only with user data.
        if ($userinfo) {
            $contentdesignerattempts->set_source_table('contentdesigner_attempts', ['contentdesignerid' => backup::VAR_PARENTID]);
            $contentdesignercompletion->set_source_table(
                'contentdesigner_completion',
                ['contentdesignerid' => backup::VAR_PARENTID]
            );
        }
        $contentdesignerattempts->annotate_ids('user', 'userid');
        $contentdesignercompletion->annotate_ids('user', 'userid');

        // Define file annotations.
        $contentdesigner->annotate_files('mod_contentdesigner', 'intro', null);
        foreach (array_keys(editor::get_elements()) as $plugin) {
            $contentdesigneroptions->annotate_files('mod_contentdesigner', $plugin . 'elementbg', 'instance');
            $contentdesigneroptions->annotate_files('mod_contentdesigner', $plugin . 'description', 'instance');
        }

        // Return the root element (data), wrapped into standard activity structure.
        return $this->prepare_activity_structure($contentdesigner);
    }
}

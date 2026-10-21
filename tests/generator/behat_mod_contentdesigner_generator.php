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
 * Behat data generator for the content of Content Designer activities.
 *
 * Usage:
 *   Given the following "mod_contentdesigner > chapters" exist:
 *     | contentdesigner | title        |
 *     | Activity name   | Introduction |
 *   And the following "mod_contentdesigner > elements" exist:
 *     | contentdesigner | chapter      | element   | title   | content |
 *     | Activity name   | Introduction | paragraph | Welcome | Hello   |
 *
 * Elements without chapter (the outro) leave the column empty. The other columns are fields of the element table.
 *
 * @package    mod_contentdesigner
 * @category   test
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_contentdesigner_generator extends behat_generator_base {
    /**
     * The entities that can be created.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'chapters' => [
                'singular' => 'chapter',
                'datagenerator' => 'chapter',
                'required' => ['contentdesigner', 'title'],
            ],
            'elements' => [
                'singular' => 'element',
                'datagenerator' => 'element',
                'required' => ['contentdesigner', 'element'],
            ],
        ];
    }

    /**
     * Creates a chapter at the end of an activity.
     *
     * @param array $data
     */
    protected function process_chapter(array $data): void {
        $instance = $this->get_contentdesigner($data['contentdesigner']);
        unset($data['contentdesigner']);
        $this->componentdatagenerator->create_chapter($instance, $data);
    }

    /**
     * Creates an element at the end of a chapter, or outside the chapters.
     *
     * @param array $data
     */
    protected function process_element(array $data): void {
        global $DB;

        $instance = $this->get_contentdesigner($data['contentdesigner']);
        $chapter = null;
        if (!empty($data['chapter'])) {
            $chapter = $DB->get_record(
                'cdelement_chapter',
                ['contentdesignerid' => $instance->id, 'title' => $data['chapter']],
                '*',
                MUST_EXIST
            );
        }
        $element = $data['element'];
        unset($data['contentdesigner'], $data['chapter'], $data['element']);
        $this->componentdatagenerator->create_element($instance, $element, $data, $chapter);
    }

    /**
     * The activity record of a Content Designer activity, by name or idnumber.
     *
     * @param string $identifier Name or idnumber of the activity.
     * @return stdClass
     */
    protected function get_contentdesigner(string $identifier): stdClass {
        global $DB;
        $cm = $this->get_cm_by_activity_name('contentdesigner', $identifier);
        return $DB->get_record('contentdesigner', ['id' => $cm->instance], '*', MUST_EXIST);
    }
}

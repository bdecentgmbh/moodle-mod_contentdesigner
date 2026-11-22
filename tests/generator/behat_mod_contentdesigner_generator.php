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
 * Progress of learners:
 *   And the following "mod_contentdesigner > attempts" exist:
 *     | contentdesigner | user     | timestart      |
 *     | Activity name   | student1 | ##2 days ago## |
 *   And the following "mod_contentdesigner > chapter completions" exist:
 *     | contentdesigner | chapter      | user     | timemodified  |
 *     | Activity name   | Introduction | student1 | ##1 day ago## |
 *   And the following "mod_contentdesigner > h5p completions" exist:
 *     | contentdesigner | h5p          | user     | attempt |
 *     | Activity name   | Exercise     | student1 | 1       |
 *
 * Attempts are numbered per user; H5P completions belong to an attempt (the latest one without the column attempt).
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
            'attempts' => [
                'singular' => 'attempt',
                'datagenerator' => 'attempt',
                'required' => ['contentdesigner', 'user'],
                'switchids' => ['user' => 'userid'],
            ],
            'chapter completions' => [
                'singular' => 'chapter completion',
                'datagenerator' => 'chapter_completion',
                'required' => ['contentdesigner', 'chapter', 'user'],
                'switchids' => ['user' => 'userid'],
            ],
            'h5p completions' => [
                'singular' => 'h5p completion',
                'datagenerator' => 'h5p_completion',
                'required' => ['contentdesigner', 'h5p', 'user'],
                'switchids' => ['user' => 'userid'],
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
     * Creates an attempt of a learner.
     *
     * @param array $data
     */
    protected function process_attempt(array $data): void {
        $instance = $this->get_contentdesigner($data['contentdesigner']);
        $userid = $data['userid'];
        unset($data['contentdesigner'], $data['userid']);
        $this->componentdatagenerator->create_attempt($instance, $userid, $data);
    }

    /**
     * Completes a chapter for a learner.
     *
     * @param array $data
     */
    protected function process_chapter_completion(array $data): void {
        global $DB;
        $instance = $this->get_contentdesigner($data['contentdesigner']);
        $chapter = $DB->get_record(
            'cdelement_chapter',
            ['contentdesignerid' => $instance->id, 'title' => $data['chapter']],
            '*',
            MUST_EXIST
        );
        $this->componentdatagenerator->create_chapter_completion(
            $chapter,
            $data['userid'],
            isset($data['timemodified']) ? (int) $data['timemodified'] : null
        );
    }

    /**
     * Completes an H5P element for a learner, in an attempt.
     *
     * @param array $data
     */
    protected function process_h5p_completion(array $data): void {
        global $DB;
        $instance = $this->get_contentdesigner($data['contentdesigner']);
        $h5p = $DB->get_record('cdelement_h5p', ['contentdesignerid' => $instance->id, 'title' => $data['h5p']], '*', MUST_EXIST);
        $conditions = ['contentdesignerid' => $instance->id, 'userid' => $data['userid']];
        if (!empty($data['attempt'])) {
            $attempt = $DB->get_record('contentdesigner_attempts', $conditions + ['attempt' => $data['attempt']], '*', MUST_EXIST);
        } else {
            $attempt = \mod_contentdesigner\local\attempts::get_latest($instance->id, $data['userid']);
        }
        $this->componentdatagenerator->create_h5p_completion($h5p, $attempt);
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

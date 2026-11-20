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

namespace mod_contentdesigner;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Tests of the backup and restore of activities with their content and the progress of the learners.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_contentdesigner_activity_structure_step
 * @covers     \restore_contentdesigner_activity_structure_step
 * @covers     \restore_contentdesigner_activity_task
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Duplicates a course by backup and restore, with or without user data.
     *
     * @param \stdClass $course The course.
     * @param bool $userdata Whether to include the user data.
     * @return int The id of the new course.
     */
    private function duplicate_course(\stdClass $course, bool $userdata): int {
        global $CFG, $USER;
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value($userdata);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $bc->destroy();
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $CFG->tempdir . '/backup/' . $backupid);

        $newcourseid = \restore_dbops::create_new_course($course->fullname . ' 2', $course->shortname . '_2', $course->category);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('users')->set_value($userdata);
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();
        return $newcourseid;
    }

    /**
     * An activity with a chapter, elements, the outro and the progress of a learner.
     *
     * @return array [course, activity, learner, chapter, h5p element]
     */
    private function create_activity(): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $cd = $this->getDataGenerator()->create_module(
            'contentdesigner',
            ['course' => $course->id, 'displaymode' => \mod_contentdesigner\local\pages::DISPLAY_PAGES]
        );
        $chapter = $gen->create_chapter($cd, ['title' => 'First']);
        $gen->create_element($cd, 'heading', ['heading' => 'Hello'], $chapter);
        $h5p = $gen->create_element($cd, 'h5p', ['mandatory' => 1, 'maxscore' => '1'], $chapter);
        $outro = $gen->create_element($cd, 'outro', ['outrocontent' => 'Bye', 'primarybutton' => 0, 'secondarybutton' => 0]);
        $DB->set_field('contentdesigner_options', 'margin', '7px', ['instance' => $outro->id,
            'element' => $DB->get_field('contentdesigner_elements', 'id', ['shortname' => 'outro'])]);
        $attempt = $gen->create_attempt($cd, $user->id);
        $DB->insert_record('cdelement_chapter_completion', ['instance' => $chapter->id, 'userid' => $user->id,
            'completion' => 1, 'timecreated' => time(), 'timemodified' => time()]);
        $DB->insert_record('cdelement_h5p_completion', ['instance' => $h5p->id, 'userid' => $user->id,
            'cdattemptid' => $attempt->id, 'completion' => 1, 'success' => 1, 'score' => '1', 'scoredata' => '{"max":1}',
            'timecreated' => time(), 'timemodified' => time()]);
        return [$course, $cd, $user, $chapter, $h5p];
    }

    /**
     * With user data, the attempts, chapter completions and H5P results are restored and linked to the new records.
     */
    public function test_restore_with_userdata(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $cd, $user] = $this->create_activity();

        $newcourseid = $this->duplicate_course($course, true);
        $newcd = $DB->get_record('contentdesigner', ['course' => $newcourseid], '*', MUST_EXIST);

        $chapter = $DB->get_record('cdelement_chapter', ['contentdesignerid' => $newcd->id], '*', MUST_EXIST);
        $this->assertEquals('First', $chapter->title);
        $this->assertCount(2, array_filter(explode(',', $chapter->contents)));
        $this->assertEquals(2, $DB->count_records('contentdesigner_content', ['contentdesignerid' => $newcd->id]));
        $outro = $DB->get_record('cdelement_outro', ['contentdesignerid' => $newcd->id], '*', MUST_EXIST);
        $outroelement = $DB->get_field('contentdesigner_elements', 'id', ['shortname' => 'outro']);
        $this->assertEquals('7px', $DB->get_field(
            'contentdesigner_options',
            'margin',
            ['element' => $outroelement, 'instance' => $outro->id]
        ));

        $attempt = $DB->get_record('contentdesigner_attempts', ['contentdesignerid' => $newcd->id], '*', MUST_EXIST);
        $this->assertEquals($user->id, $attempt->userid);
        $this->assertTrue($DB->record_exists('cdelement_chapter_completion', ['instance' => $chapter->id, 'userid' => $user->id]));
        $h5p = $DB->get_record('cdelement_h5p', ['contentdesignerid' => $newcd->id], '*', MUST_EXIST);
        $this->assertTrue($DB->record_exists(
            'cdelement_h5p_completion',
            ['instance' => $h5p->id, 'cdattemptid' => $attempt->id, 'userid' => $user->id]
        ));
    }

    /**
     * Without user data, no progress of learners is restored.
     */
    public function test_restore_without_userdata(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course] = $this->create_activity();

        $newcourseid = $this->duplicate_course($course, false);
        $newcd = $DB->get_record('contentdesigner', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertEquals(\mod_contentdesigner\local\pages::DISPLAY_PAGES, $newcd->displaymode);
        $this->assertEquals(2, $DB->count_records('contentdesigner_content', ['contentdesignerid' => $newcd->id]));
        $this->assertEquals(0, $DB->count_records('contentdesigner_attempts', ['contentdesignerid' => $newcd->id]));
        $h5p = $DB->get_record('cdelement_h5p', ['contentdesignerid' => $newcd->id], '*', MUST_EXIST);
        $this->assertEquals(0, $DB->count_records('cdelement_h5p_completion', ['instance' => $h5p->id]));
    }
}

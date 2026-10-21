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
require_once($CFG->dirroot . '/mod/contentdesigner/lib.php');

/**
 * Tests of the access checks of the fragments and the in-place editing of titles (lib.php).
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::contentdesigner_output_fragment_edit_element
 * @covers     ::contentdesigner_output_fragment_move_element
 * @covers     ::contentdesigner_output_fragment_move_chapter
 * @covers     ::contentdesigner_output_fragment_update_visibility
 * @covers     ::contentdesigner_output_fragment_load_elements
 * @covers     ::mod_contentdesigner_inplace_editable
 */
final class fragments_test extends \advanced_testcase {
    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var \stdClass The activity. */
    private \stdClass $cd;

    /** @var \stdClass Another activity, in another course. */
    private \stdClass $othercd;

    /** @var \stdClass A heading of the other activity. */
    private \stdClass $otherheading;

    /** @var \stdClass The chapter of the other activity. */
    private \stdClass $otherchapter;

    /** @var \stdClass A teacher of the course of the activity. */
    private \stdClass $teacher;

    /** @var \stdClass A student of the course of the activity. */
    private \stdClass $student;

    /**
     * Two activities in two courses, a teacher and a student in the first one.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $this->course = $this->getDataGenerator()->create_course();
        $this->cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $this->course->id]);
        $othercourse = $this->getDataGenerator()->create_course();
        $this->othercd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $othercourse->id]);
        $this->otherchapter = $gen->create_chapter($this->othercd);
        $this->otherheading = $gen->create_element($this->othercd, 'heading', ['heading' => 'Other'], $this->otherchapter);
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
    }

    /**
     * The fragment arguments for the activity.
     *
     * @param array $args The other arguments.
     * @return array
     */
    private function args(array $args): array {
        return ['context' => \context_module::instance($this->cd->cmid), 'cmid' => $this->cd->cmid] + $args;
    }

    /**
     * Teachers cannot delete or hide elements of another activity through their own activity.
     */
    public function test_elements_of_other_activity(): void {
        global $DB;
        $this->setUser($this->teacher);
        $headingid = $DB->get_field('contentdesigner_elements', 'id', ['shortname' => 'heading']);

        try {
            contentdesigner_output_fragment_edit_element($this->args(['elementid' => $headingid,
                'instanceid' => $this->otherheading->id, 'action' => 'delete']));
            $this->fail('Deleting an element of another activity must fail.');
        } catch (\dml_missing_record_exception $e) {
            $this->assertTrue($DB->record_exists('cdelement_heading', ['id' => $this->otherheading->id]));
        }

        try {
            contentdesigner_output_fragment_update_visibility($this->args(['element' => 'heading',
                'instance' => $this->otherheading->id, 'status' => 'false']));
            $this->fail('Hiding an element of another activity must fail.');
        } catch (\dml_missing_record_exception $e) {
            $this->assertEquals(1, $DB->get_field('cdelement_heading', 'visible', ['id' => $this->otherheading->id]));
        }

        try {
            contentdesigner_output_fragment_move_element($this->args(['chapterid' => $this->otherchapter->id,
                'contents' => (string) $this->otherheading->contentid]));
            $this->fail('Moving an element into a chapter of another activity must fail.');
        } catch (\dml_missing_record_exception $e) {
            $this->assertEquals(
                $this->otherchapter->id,
                $DB->get_field('contentdesigner_content', 'chapter', ['id' => $this->otherheading->contentid])
            );
        }

        $this->expectException(\moodle_exception::class);
        contentdesigner_output_fragment_move_chapter($this->args(['chapters' => (string) $this->otherchapter->id]));
    }

    /**
     * Teachers manage the elements of their activity; the visibility status of the editor is a string.
     */
    public function test_edit_own_elements(): void {
        global $DB;
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $chapter = $gen->create_chapter($this->cd);
        $heading = $gen->create_element($this->cd, 'heading', ['heading' => 'Mine'], $chapter);
        $this->setUser($this->teacher);

        contentdesigner_output_fragment_update_visibility($this->args(['element' => 'heading', 'instance' => $heading->id,
            'status' => 'false']));
        $this->assertEquals(0, $DB->get_field('cdelement_heading', 'visible', ['id' => $heading->id]));
        contentdesigner_output_fragment_update_visibility($this->args(['element' => 'heading', 'instance' => $heading->id,
            'status' => 'true']));
        $this->assertEquals(1, $DB->get_field('cdelement_heading', 'visible', ['id' => $heading->id]));

        $headingid = $DB->get_field('contentdesigner_elements', 'id', ['shortname' => 'heading']);
        contentdesigner_output_fragment_edit_element($this->args(['elementid' => $headingid, 'instanceid' => $heading->id,
            'action' => 'delete']));
        $this->assertFalse($DB->record_exists('cdelement_heading', ['id' => $heading->id]));
        $this->assertFalse($DB->record_exists('contentdesigner_content', ['id' => $heading->contentid]));
    }

    /**
     * Students cannot use the fragments of the editor.
     */
    public function test_student_cannot_edit(): void {
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $chapter = $gen->create_chapter($this->cd);
        $heading = $gen->create_element($this->cd, 'heading', ['heading' => 'Mine'], $chapter);
        $this->setUser($this->student);

        $this->expectException(\required_capability_exception::class);
        contentdesigner_output_fragment_update_visibility($this->args(['element' => 'heading', 'instance' => $heading->id,
            'status' => 'false']));
    }

    /**
     * Learners render the content only in their own attempts.
     */
    public function test_load_elements_attempt_of_other_user(): void {
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $attempt = $gen->create_attempt($this->cd, $other->id);
        $this->setUser($this->student);

        $this->assertIsString(contentdesigner_output_fragment_load_elements($this->args(['attemptid' => 0])));
        $this->expectException(\dml_missing_record_exception::class);
        contentdesigner_output_fragment_load_elements($this->args(['attemptid' => $attempt->id]));
    }

    /**
     * Titles are edited in place only by users who may edit the content of the element's activity.
     */
    public function test_inplace_editable(): void {
        global $DB;
        $itemtype = 'instance_title[heading][' . $this->otherheading->id . ']';

        $this->setUser($this->teacher);
        try {
            mod_contentdesigner_inplace_editable($itemtype, 0, 'Hacked');
            $this->fail('Teachers of another course must not edit the title.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('Heading', $DB->get_field('cdelement_heading', 'title', ['id' => $this->otherheading->id]));
        }

        $this->setAdminUser();
        $result = mod_contentdesigner_inplace_editable($itemtype, 0, '<b>New</b> title');
        $this->assertInstanceOf(\core\output\inplace_editable::class, $result);
        $this->assertEquals('New title', $DB->get_field('cdelement_heading', 'title', ['id' => $this->otherheading->id]));

        $this->expectException(\moodle_exception::class);
        mod_contentdesigner_inplace_editable('instance_title[user][1]', 0, 'x');
    }
}

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
 * Tests of the access checks of the fragments, the web services of the editor and the in-place editing of titles.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_contentdesigner\external\delete_element
 * @covers     \mod_contentdesigner\external\move_element
 * @covers     \mod_contentdesigner\external\move_chapter
 * @covers     \mod_contentdesigner\external\update_visibility
 * @covers     ::contentdesigner_output_fragment_editor_content
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
     * Teachers cannot delete, hide or move elements of another activity through their own activity.
     */
    public function test_elements_of_other_activity(): void {
        global $DB;
        $this->setUser($this->teacher);

        try {
            external\delete_element::execute($this->cd->cmid, 'heading', $this->otherheading->id);
            $this->fail('Deleting an element of another activity must fail.');
        } catch (\dml_missing_record_exception $e) {
            $this->assertTrue($DB->record_exists('cdelement_heading', ['id' => $this->otherheading->id]));
        }

        try {
            external\update_visibility::execute($this->cd->cmid, 'heading', $this->otherheading->id, false);
            $this->fail('Hiding an element of another activity must fail.');
        } catch (\dml_missing_record_exception $e) {
            $this->assertEquals(1, $DB->get_field('cdelement_heading', 'visible', ['id' => $this->otherheading->id]));
        }

        try {
            external\move_element::execute($this->cd->cmid, $this->otherchapter->id, (string) $this->otherheading->contentid);
            $this->fail('Moving an element into a chapter of another activity must fail.');
        } catch (\dml_missing_record_exception $e) {
            $this->assertEquals(
                $this->otherchapter->id,
                $DB->get_field('contentdesigner_content', 'chapter', ['id' => $this->otherheading->contentid])
            );
        }

        // Elements of another activity cannot be moved into a chapter of the own activity.
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $chapter = $gen->create_chapter($this->cd);
        try {
            external\move_element::execute($this->cd->cmid, $chapter->id, (string) $this->otherheading->contentid);
            $this->fail('Moving an element of another activity must fail.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('invalidrecord', $e->errorcode);
        }

        // The teacher cannot use the activity of another course at all.
        try {
            external\delete_element::execute($this->othercd->cmid, 'heading', $this->otherheading->id);
            $this->fail('Teachers of another course must not delete the element.');
        } catch (\moodle_exception $e) {
            $this->assertTrue($DB->record_exists('cdelement_heading', ['id' => $this->otherheading->id]));
        }

        $this->expectException(\moodle_exception::class);
        external\move_chapter::execute($this->cd->cmid, (string) $this->otherchapter->id);
    }

    /**
     * Teachers manage the elements of their activity through the web services; the editor renders the content again
     * with a fragment.
     */
    public function test_edit_own_elements(): void {
        global $DB;
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $chapter = $gen->create_chapter($this->cd, ['title' => 'First']);
        $second = $gen->create_chapter($this->cd, ['title' => 'Second']);
        $heading = $gen->create_element($this->cd, 'heading', ['heading' => 'Mine'], $chapter);
        $paragraph = $gen->create_element($this->cd, 'paragraph', ['content' => 'Text'], $chapter);
        $this->setUser($this->teacher);

        $this->assertTrue(external\update_visibility::execute($this->cd->cmid, 'heading', $heading->id, false));
        $this->assertEquals(0, $DB->get_field('cdelement_heading', 'visible', ['id' => $heading->id]));
        $this->assertTrue(external\update_visibility::execute($this->cd->cmid, 'heading', $heading->id, true));
        $this->assertEquals(1, $DB->get_field('cdelement_heading', 'visible', ['id' => $heading->id]));

        // Elements are moved into the second chapter, in the given order.
        $contents = $paragraph->contentid . ',' . $heading->contentid;
        $this->assertTrue(external\move_element::execute($this->cd->cmid, $second->id, $contents));
        $this->assertEquals($second->id, $DB->get_field('contentdesigner_content', 'chapter', ['id' => $heading->contentid]));
        $this->assertEquals(2, $DB->get_field('contentdesigner_content', 'position', ['id' => $heading->contentid]));
        $this->assertEquals(1, $DB->get_field('contentdesigner_content', 'position', ['id' => $paragraph->contentid]));

        $this->assertTrue(external\move_chapter::execute($this->cd->cmid, $second->id . ',' . $chapter->id));
        $this->assertEquals(1, $DB->get_field('cdelement_chapter', 'position', ['id' => $second->id]));
        $this->assertEquals(2, $DB->get_field('cdelement_chapter', 'position', ['id' => $chapter->id]));

        $html = contentdesigner_output_fragment_editor_content($this->args([]));
        $this->assertLessThan(strpos($html, 'First'), strpos($html, 'Second'));

        $this->assertTrue(external\delete_element::execute($this->cd->cmid, 'heading', $heading->id));
        $this->assertFalse($DB->record_exists('cdelement_heading', ['id' => $heading->id]));
        $this->assertFalse($DB->record_exists('contentdesigner_content', ['id' => $heading->contentid]));

        // Deleting a chapter deletes its elements.
        $this->assertTrue(external\delete_element::execute($this->cd->cmid, 'chapter', $second->id));
        $this->assertFalse($DB->record_exists('cdelement_chapter', ['id' => $second->id]));
        $this->assertFalse($DB->record_exists('cdelement_paragraph', ['id' => $paragraph->id]));
    }

    /**
     * The web services of the editor are write functions; the old fragments that changed data are gone.
     */
    public function test_services_are_write_functions(): void {
        $names = ['delete_element', 'move_chapter', 'move_element', 'update_visibility'];
        foreach ($names as $name) {
            $info = \core_external\external_api::external_function_info('mod_contentdesigner_' . $name);
            $this->assertEquals('write', $info->type);
            $this->assertTrue($info->allowed_from_ajax);
            $this->assertFalse(function_exists('contentdesigner_output_fragment_' . $name));
        }
        $this->assertFalse(function_exists('contentdesigner_output_fragment_edit_element'));
    }

    /**
     * Students cannot use the web services and the fragments of the editor.
     */
    public function test_student_cannot_edit(): void {
        global $DB;
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $chapter = $gen->create_chapter($this->cd);
        $heading = $gen->create_element($this->cd, 'heading', ['heading' => 'Mine'], $chapter);
        $this->setUser($this->student);

        $calls = [
            fn() => external\update_visibility::execute($this->cd->cmid, 'heading', $heading->id, false),
            fn() => external\delete_element::execute($this->cd->cmid, 'heading', $heading->id),
            fn() => external\move_element::execute($this->cd->cmid, $chapter->id, (string) $heading->contentid),
            fn() => external\move_chapter::execute($this->cd->cmid, (string) $chapter->id),
            fn() => contentdesigner_output_fragment_editor_content($this->args([])),
        ];
        foreach ($calls as $call) {
            try {
                $call();
                $this->fail('Students must not edit the content.');
            } catch (\required_capability_exception $e) {
                $this->assertEquals('nopermissions', $e->errorcode);
            }
        }
        $this->assertEquals(1, $DB->get_field('cdelement_heading', 'visible', ['id' => $heading->id]));
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

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

use mod_contentdesigner\local\access;
use mod_contentdesigner\local\attempts;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/contentdesigner/lib.php');

/**
 * Tests of guest access: all guests share one user account, so they view the content without saving progress.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(attempts::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(access::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\cdelement_h5p\external::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(content_display::class)]
final class guest_test extends \advanced_testcase {
    /** @var \stdClass The course, with guest access. */
    private \stdClass $course;

    /** @var \stdClass The activity. */
    private \stdClass $cd;

    /** @var \stdClass The H5P element. */
    private \stdClass $h5p;

    /**
     * A course with guest access and an activity with a chapter, a paragraph and an H5P element.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['courseid' => $this->course->id, 'enrol' => 'guest']);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $this->cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $this->course->id]);
        $chapter = $gen->create_chapter($this->cd, ['title' => 'Chapter 1']);
        $gen->create_element($this->cd, 'paragraph', ['content' => 'Text of chapter 1'], $chapter);
        $this->h5p = $gen->create_element($this->cd, 'h5p', ['title' => 'Exercise'], $chapter);
    }

    /**
     * Guests view the content without an attempt being stored; they get a notice and no button to complete chapters.
     */
    public function test_view_without_progress(): void {
        global $DB;
        $this->setGuestUser();
        [$course, $cm] = get_course_and_cm_from_cmid($this->cd->cmid);
        $instance = $DB->get_record('contentdesigner', ['id' => $this->cd->id], '*', MUST_EXIST);
        $display = new content_display($instance, $cm, $course, \context_module::instance($cm->id));

        $html = $display->view_content(true);
        $this->assertStringContainsString('Text of chapter 1', $html);
        $this->assertStringContainsString(get_string('guestnoprogress', 'mod_contentdesigner'), $html);
        $this->assertStringNotContainsString('complete-chapter', $html);
        $this->assertFalse($DB->record_exists('contentdesigner_attempts', []));

        $attempt = attempts::get_current($this->cd->id, guest_user()->id);
        $this->assertSame(0, $attempt->id);
        $this->assertFalse($DB->record_exists('contentdesigner_attempts', []));

        $html = contentdesigner_output_fragment_load_elements(['context' => \context_module::instance($cm->id),
            'attemptid' => 0]);
        $this->assertStringContainsString('Text of chapter 1', $html);
        $this->assertFalse($DB->record_exists('contentdesigner_attempts', []));

        $this->expectException(\moodle_exception::class);
        attempts::create($this->cd->id, guest_user()->id);
    }

    /**
     * Users who are logged in see no notice and the button to complete the chapter.
     */
    public function test_learner_sees_button(): void {
        global $DB;
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'student'));
        [$course, $cm] = get_course_and_cm_from_cmid($this->cd->cmid);
        $instance = $DB->get_record('contentdesigner', ['id' => $this->cd->id], '*', MUST_EXIST);
        $display = new content_display($instance, $cm, $course, \context_module::instance($cm->id));

        $html = $display->view_content(true);
        $this->assertStringNotContainsString(get_string('guestnoprogress', 'mod_contentdesigner'), $html);
        $this->assertStringContainsString('complete-chapter', $html);
        $this->assertTrue($DB->record_exists('contentdesigner_attempts', []));
    }

    /**
     * Guests cannot store H5P results, also not in an attempt of another user.
     */
    public function test_h5p_result_refused(): void {
        global $DB;
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $attempt = attempts::get_current($this->cd->id, $student->id);
        $this->setGuestUser();

        $result = ['completion' => 1, 'success' => 1, 'duration' => '', 'response' => '',
            'score' => ['min' => 0, 'max' => 1, 'raw' => 1, 'scaled' => 1]];
        foreach ([0, $attempt->id] as $attemptid) {
            try {
                \cdelement_h5p\external::store_result_data($this->cd->cmid, $this->h5p->id, $attemptid, $result);
                $this->fail('Guests must not store H5P results.');
            } catch (\moodle_exception $e) {
                $this->assertEquals('guestnoprogress', $e->errorcode);
            }
        }
        $this->assertFalse($DB->record_exists('cdelement_h5p_completion', []));
    }

    /**
     * The check refuses guests and users who are not logged in, and accepts other users.
     */
    public function test_require_not_guest(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        access::require_not_guest();

        $this->setUser(0);
        try {
            access::require_not_guest();
            $this->fail('Users who are not logged in must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('guestnoprogress', $e->errorcode);
        }

        $this->setGuestUser();
        $this->expectException(\moodle_exception::class);
        access::require_not_guest();
    }
}

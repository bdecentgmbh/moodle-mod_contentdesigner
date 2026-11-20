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

namespace cdelement_videotime;

use core_external\external_api;
use mod_contentdesigner\editor;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/completionlib.php');

/**
 * Tests of the web services and the form of the Video Time element.
 *
 * @package    cdelement_videotime
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\cdelement_videotime\external::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\cdelement_videotime\element::class)]
final class external_test extends \advanced_testcase {
    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var \stdClass The activity. */
    private \stdClass $cd;

    /** @var \stdClass A Video Time activity of the course, with manual completion. */
    private \stdClass $videotime;

    /** @var \stdClass A Video Time activity of another course. */
    private \stdClass $othervideotime;

    /** @var \stdClass The element of the activity. */
    private \stdClass $instance;

    /** @var \stdClass An element of an activity of the other course. */
    private \stdClass $otherinstance;

    /** @var \stdClass A teacher of the course. */
    private \stdClass $teacher;

    /** @var \stdClass A student of the course. */
    private \stdClass $student;

    /** @var \stdClass A student of the other course. */
    private \stdClass $outsider;

    /**
     * An activity with a Video Time element in a course, the same in another course.
     */
    protected function setUp(): void {
        parent::setUp();
        if (!\cdelement_videotime\element::videotime_available()) {
            $this->markTestSkipped('Video Time (mod_videotime) is not installed.');
        }
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $this->course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->videotime = $this->getDataGenerator()->create_module('videotime', ['course' => $this->course->id,
            'name' => 'Video <b>one</b>', 'completion' => COMPLETION_TRACKING_MANUAL]);
        $this->cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $this->course->id]);
        $this->instance = $gen->create_element($this->cd, 'videotime', ['videotimeid' => $this->videotime->id,
            'courseid' => $this->course->id, 'mandatory' => 1], $gen->create_chapter($this->cd));
        $othercourse = $this->getDataGenerator()->create_course();
        $this->othervideotime = $this->getDataGenerator()->create_module('videotime', ['course' => $othercourse->id]);
        $othercd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $othercourse->id]);
        $this->otherinstance = $gen->create_element(
            $othercd,
            'videotime',
            ['videotimeid' => $this->othervideotime->id],
            $gen->create_chapter($othercd)
        );
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->outsider = $this->getDataGenerator()->create_and_enrol($othercourse, 'student');
    }

    /**
     * Calls the activity selector.
     *
     * @param int $courseid The course of the Video Time activities.
     * @return array
     */
    private function instance_menu(int $courseid): array {
        return external_api::clean_returnvalue(
            external::get_instance_menu_returns(),
            external::get_instance_menu('', $this->cd->cmid, $courseid)
        );
    }

    /**
     * Calls the completion check.
     *
     * @param int $cmid The course module id.
     * @param int $instanceid The element.
     * @return bool
     */
    private function verify(int $cmid, int $instanceid): bool {
        return external_api::clean_returnvalue(
            external::verify_completion_returns(),
            external::verify_completion($cmid, $instanceid)
        );
    }

    public function test_instance_menu_for_an_editor(): void {
        $this->setUser($this->teacher);
        $menu = $this->instance_menu($this->course->id);
        $this->assertCount(1, $menu);
        $this->assertEquals($this->videotime->id, $menu[0]['id']);
        $this->assertStringNotContainsString('<b>', $menu[0]['name']);
    }

    public function test_instance_menu_refuses_courses_without_capability(): void {
        $this->setUser($this->teacher);
        try {
            $this->instance_menu($this->othervideotime->course);
            $this->fail('The activities of a course the user cannot update were listed.');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidvideotime', $e->errorcode);
        }
    }

    public function test_instance_menu_refused_for_a_learner(): void {
        $this->setUser($this->student);
        $this->expectException(\required_capability_exception::class);
        $this->instance_menu($this->course->id);
    }

    public function test_instance_menu_refused_for_a_user_of_another_course(): void {
        $this->setUser($this->outsider);
        $this->expectException(\require_login_exception::class);
        $this->instance_menu($this->course->id);
    }

    public function test_verify_completion(): void {
        $this->setUser($this->student);
        $this->assertFalse($this->verify($this->cd->cmid, $this->instance->id));
        $element = editor::get_element('videotime', $this->cd->cmid);
        $this->assertTrue($element->prevent_nextelements($element->get_instance($this->instance->id)));

        [$course, $cm] = get_course_and_cm_from_instance($this->videotime->id, 'videotime');
        (new \completion_info($course))->update_state($cm, COMPLETION_COMPLETE, $this->student->id);
        $this->assertTrue($this->verify($this->cd->cmid, $this->instance->id));
        $this->assertFalse($element->prevent_nextelements($element->get_instance($this->instance->id)));
    }

    public function test_verify_completion_refuses_an_element_of_another_activity(): void {
        $this->setUser($this->student);
        $this->expectException(\dml_missing_record_exception::class);
        $this->verify($this->cd->cmid, $this->otherinstance->id);
    }

    public function test_verify_completion_refuses_a_user_of_another_course(): void {
        $this->setUser($this->outsider);
        $this->expectException(\require_login_exception::class);
        $this->verify($this->cd->cmid, $this->instance->id);
    }

    public function test_verify_completion_refuses_a_hidden_element(): void {
        global $DB;
        $DB->set_field('cdelement_videotime', 'visible', 0, ['id' => $this->instance->id]);
        $this->setUser($this->student);
        $this->expectException(\dml_missing_record_exception::class);
        $this->verify($this->cd->cmid, $this->instance->id);
    }

    public function test_form_accepts_only_activities_the_user_may_embed(): void {
        global $DB;
        $this->setUser($this->teacher);
        $element = editor::get_element('videotime', $this->cd->cmid);
        $id = $element->update_instance((object) ['instanceid' => 0, 'videotimeid' => $this->videotime->id, 'mandatory' => 0]);
        $record = $DB->get_record('cdelement_videotime', ['id' => $id]);
        $this->assertEquals($this->cd->id, $record->contentdesignerid);
        $this->assertEquals($this->course->id, $record->courseid);

        $this->expectExceptionMessage(get_string('invalidvideotime', 'cdelement_videotime'));
        $element->update_instance((object) ['instanceid' => 0, 'videotimeid' => $this->othervideotime->id, 'mandatory' => 0]);
    }

    public function test_guest_access_requires_the_password(): void {
        global $DB, $USER;
        $this->setUser($this->outsider);
        $this->assertFalse(element::can_access_as_guest($this->course->id));

        $guest = $DB->get_record('enrol', ['courseid' => $this->course->id, 'enrol' => 'guest'], '*', MUST_EXIST);
        $DB->update_record('enrol', (object) ['id' => $guest->id, 'status' => ENROL_INSTANCE_ENABLED, 'password' => 'secret']);
        $this->assertFalse(element::can_access_as_guest($this->course->id));

        $USER->enrol_guest_passwords[$guest->id] = 'secret';
        $this->assertTrue(element::can_access_as_guest($this->course->id));

        unset($USER->enrol_guest_passwords);
        $DB->set_field('enrol', 'password', '', ['id' => $guest->id]);
        $this->assertTrue(element::can_access_as_guest($this->course->id));
    }
}

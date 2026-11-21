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

namespace mod_contentdesigner\local;

use mod_contentdesigner\editor;

/**
 * Tests of the access checks and of the attempts.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_contentdesigner\local\access::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_contentdesigner\local\attempts::class)]
final class access_test extends \advanced_testcase {
    /**
     * Ids from a request are only accepted for the activity whose access was checked.
     */
    public function test_ids_bound_to_activity(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $cd1 = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        $cd2 = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        $chapter2 = $gen->create_chapter($cd2);
        $heading2 = $gen->create_element($cd2, 'heading', ['heading' => 'Other'], $chapter2);

        $element = editor::get_element('heading', $cd1->cmid);
        $this->assertEquals($heading2->id, access::get_element_instance($element, $heading2->id, $cd2->id)->id);
        $this->assertEquals($chapter2->id, access::get_chapter($chapter2->id, $cd2->id)->id);
        $this->assertTrue(access::contents_belong_to([$heading2->contentid], $cd2->id));
        $this->assertFalse(access::contents_belong_to([$heading2->contentid], $cd1->id));

        $this->expectException(\dml_missing_record_exception::class);
        access::get_element_instance($element, $heading2->id, $cd1->id);
    }

    /**
     * Chapters of another activity are refused.
     */
    public function test_chapter_of_other_activity(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $cd1 = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        $cd2 = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        $chapter2 = $gen->create_chapter($cd2);

        $this->expectException(\dml_missing_record_exception::class);
        access::get_chapter($chapter2->id, $cd1->id);
    }

    /**
     * Elements are only known when installed.
     */
    public function test_unknown_element(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);

        $this->assertInstanceOf(\cdelement_heading\element::class, access::get_element('heading', $cd->cmid));
        $this->expectException(\moodle_exception::class);
        access::get_element('user} WHERE 1=1 --', $cd->cmid);
    }

    /**
     * The capability is checked in the module context, and only module contexts of Content Designer are accepted.
     */
    public function test_from_context(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $context = \context_module::instance($cd->cmid);
        $this->setUser($student);

        [, $cm] = access::from_context($context, access::VIEW);
        $this->assertEquals($cd->cmid, $cm->id);

        try {
            access::from_context(\context_course::instance($course->id), access::VIEW);
            $this->fail('A course context must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('invalidcoursemodule', $e->errorcode);
        }

        $this->expectException(\required_capability_exception::class);
        access::from_context($context, access::EDIT);
    }

    /**
     * Attempts of other users are refused; the current attempt is created once.
     */
    public function test_attempts(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $attempt = attempts::get_current($cd->id, $user1->id);
        $this->assertEquals(1, $attempt->attempt);
        $this->assertEquals($attempt->id, attempts::get_current($cd->id, $user1->id)->id);
        $this->assertEquals(2, attempts::create($cd->id, $user1->id)->attempt);
        $this->assertEquals(2, attempts::get_for_render($cd->id, $user1->id, 0)->attempt);
        $this->assertEquals($attempt->id, attempts::get_for_render($cd->id, $user1->id, $attempt->id)->id);

        $this->expectException(\dml_missing_record_exception::class);
        attempts::get_for_render($cd->id, $user2->id, $attempt->id);
    }
}

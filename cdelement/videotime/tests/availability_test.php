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

use mod_contentdesigner\editor;

/**
 * Tests of the Video Time element while Video Time (mod_videotime) is missing or disabled.
 *
 * The element has no hard dependency on mod_videotime. These tests run with and without it: without it they check the
 * real situation, with it they disable the module.
 *
 * @package    cdelement_videotime
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\cdelement_videotime\element::class)]
final class availability_test extends \advanced_testcase {
    /**
     * Makes Video Time unavailable: disables the module if it is installed.
     */
    private function make_videotime_unavailable(): void {
        global $DB;
        if (\core_component::get_component_directory('mod_videotime') !== null) {
            $DB->set_field('modules', 'visible', 0, ['name' => 'videotime']);
            \core_plugin_manager::reset_caches();
        }
    }

    /**
     * Without Video Time the element is not offered, cannot be stored, and its instances show a notice.
     */
    public function test_element_without_videotime(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $chapter = $gen->create_chapter($cd);
        // An instance that was added while Video Time was available.
        $instance = $gen->create_element($cd, 'videotime', ['videotimeid' => 12345, 'courseid' => $course->id], $chapter);

        $this->make_videotime_unavailable();
        $this->assertFalse(element::videotime_available());

        $element = editor::get_element('videotime', $cd->cmid);
        $this->assertFalse($element->is_available());
        $this->assertStringNotContainsString('data-element="videotime"', editor::get_elements_list($cd->cmid));

        $record = $DB->get_record('cdelement_videotime', ['id' => $instance->id]);
        $this->assertStringContainsString(get_string('videotimeunavailable', 'cdelement_videotime'), $element->render($record));
        $this->assertNull($element->is_videotime_completed($record, (int) get_admin()->id));
        $this->assertFalse($element->prevent_nextelements((object) (['mandatory' => 1] + (array) $record)));

        $this->expectException(\moodle_exception::class);
        $element->update_instance((object) ['videotimeid' => 1, 'instanceid' => 0]);
    }

    /**
     * The activity selector refuses to work without Video Time.
     */
    public function test_selector_without_videotime(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        $this->make_videotime_unavailable();

        $this->expectException(\moodle_exception::class);
        external::get_instance_menu('', $cd->cmid, $course->id);
    }
}

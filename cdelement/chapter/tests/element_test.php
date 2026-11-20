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

namespace cdelement_chapter;

use mod_contentdesigner\editor;

/**
 * Tests of the chapter element: editing and ordering chapters.
 *
 * @package    cdelement_chapter
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(element::class)]
final class element_test extends \advanced_testcase {
    /**
     * Editing a chapter keeps its creation time; moving elements and chapters stores their order.
     */
    public function test_update_and_order(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $course = $this->getDataGenerator()->create_course();
        $cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        $first = $gen->create_chapter($cd, ['timecreated' => 1000, 'timemodified' => 1000]);
        $second = $gen->create_chapter($cd);
        $one = $gen->create_element($cd, 'heading', ['heading' => 'One'], $first);
        $two = $gen->create_element($cd, 'heading', ['heading' => 'Two'], $first);
        $element = editor::get_element('chapter', $cd->cmid);

        $element->update_instance((object) ['instanceid' => $first->id, 'chapterid' => 0, 'title' => 'Renamed',
            'position' => $first->position, 'timecreated' => time()]);
        $record = $DB->get_record('cdelement_chapter', ['id' => $first->id]);
        $this->assertEquals('Renamed', $record->title);
        $this->assertEquals(1000, $record->timecreated);
        $this->assertGreaterThan(1000, $record->timemodified);

        $this->assertTrue($element->update_postion($first->id, $two->contentid . ',' . $one->contentid));
        $this->assertEquals(1, $DB->get_field('contentdesigner_content', 'position', ['id' => $two->contentid]));
        $this->assertEquals(2, $DB->get_field('contentdesigner_content', 'position', ['id' => $one->contentid]));

        $this->assertTrue($element->move_chapter($second->id . ',' . $first->id));
        $this->assertEquals(1, $DB->get_field('cdelement_chapter', 'position', ['id' => $second->id]));
        $this->assertEquals(2, $DB->get_field('cdelement_chapter', 'position', ['id' => $first->id]));
    }
}

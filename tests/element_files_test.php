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

/**
 * Tests of the files of elements: deleting an element deletes its files in all its areas.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(elements::class)]
final class element_files_test extends \advanced_testcase {
    /**
     * Deleting a rich text element or an outro deletes the files of their own areas, and only theirs.
     */
    public function test_delete_element_deletes_files(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $course = $this->getDataGenerator()->create_course();
        $cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        $chapter = $gen->create_chapter($cd);
        $richtext = $gen->create_element($cd, 'richtext', ['content' => 'One', 'contentformat' => FORMAT_HTML], $chapter);
        $other = $gen->create_element($cd, 'richtext', ['content' => 'Two', 'contentformat' => FORMAT_HTML], $chapter);
        $outro = $gen->create_element($cd, 'outro', ['outrocontent' => 'End', 'outrocontentformat' => FORMAT_HTML]);
        $context = \context_module::instance($cd->cmid);
        $fs = get_file_storage();
        $files = [
            ['cdelement_richtext_content', $richtext->id],
            ['cdelement_richtext_content', $other->id],
            ['richtextelementbg', $richtext->id],
            ['cdelement_outro_outroimage', $outro->id],
            ['cdelement_outro_outrocontent', $outro->id],
        ];
        foreach ($files as [$area, $itemid]) {
            $fs->create_file_from_string(['contextid' => $context->id, 'component' => 'mod_contentdesigner',
                'filearea' => $area, 'itemid' => $itemid, 'filepath' => '/', 'filename' => 'a.txt'], 'x');
        }

        editor::get_element('richtext', $cd->cmid)->delete_element($richtext->id);
        editor::get_element('outro', $cd->cmid)->delete_element($outro->id);

        $area = fn($name, $itemid) => $fs->get_area_files($context->id, 'mod_contentdesigner', $name, $itemid, '', false);
        $this->assertEmpty($area('cdelement_richtext_content', $richtext->id));
        $this->assertEmpty($area('richtextelementbg', $richtext->id));
        $this->assertEmpty($area('cdelement_outro_outroimage', $outro->id));
        $this->assertEmpty($area('cdelement_outro_outrocontent', $outro->id));
        $this->assertCount(1, $area('cdelement_richtext_content', $other->id));
    }
}

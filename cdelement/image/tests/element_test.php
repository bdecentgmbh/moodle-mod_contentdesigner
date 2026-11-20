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

namespace cdelement_image;

use mod_contentdesigner\editor;

/**
 * Tests of the image element.
 *
 * @package    cdelement_image
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\cdelement_image\element::class)]
final class element_test extends \advanced_testcase {
    public function test_render_and_delete(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $course = $this->getDataGenerator()->create_course();
        $cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        $image = $gen->create_element(
            $cd,
            'image',
            ['caption' => 'Boy <script>alert(1)</script>', 'lightbox' => 1],
            $gen->create_chapter($cd)
        );
        $context = \context_module::instance($cd->cmid);
        $fs = get_file_storage();
        $fs->create_file_from_string(['contextid' => $context->id, 'component' => 'mod_contentdesigner',
            'filearea' => 'cdelement_image_contentimages', 'itemid' => $image->id, 'filepath' => '/',
            'filename' => 'boy.jpg'], 'image');
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $element = editor::get_element('image', $cd->cmid);
        $html = $element->render($element->prepare_formdata($image->id));
        $this->assertStringContainsString('boy.jpg', $html);
        $this->assertStringContainsString('Boy', $html);
        $this->assertStringNotContainsString('<script>', $html);

        $element->delete_element($image->id);
        $this->assertTrue($fs->is_area_empty(
            $context->id,
            'mod_contentdesigner',
            'cdelement_image_contentimages',
            $image->id
        ));
    }
}

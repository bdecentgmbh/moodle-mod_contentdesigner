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
 * Tests of the element subplugins: which can be uninstalled, and what is deleted when one is.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(plugininfo\cdelement::class)]
final class plugininfo_test extends \advanced_testcase {
    /**
     * The chapter and the outro cannot be uninstalled, other elements can.
     */
    public function test_is_uninstall_allowed(): void {
        $manager = \core\plugin_manager::instance();
        $this->assertFalse($manager->get_plugin_info('cdelement_chapter')->is_uninstall_allowed());
        $this->assertFalse($manager->get_plugin_info('cdelement_outro')->is_uninstall_allowed());
        $this->assertTrue($manager->get_plugin_info('cdelement_heading')->is_uninstall_allowed());
        $this->assertTrue($manager->get_plugin_info('cdelement_richtext')->is_uninstall_allowed());
    }

    /**
     * Uninstalling an element deletes its contents, options and files in all activities, and keeps the other elements.
     */
    public function test_uninstall_cleanup(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $course = $this->getDataGenerator()->create_course();
        $cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        $chapter = $gen->create_chapter($cd);
        $richtext = $gen->create_element($cd, 'richtext', ['content' => 'Rich', 'contentformat' => FORMAT_HTML], $chapter);
        $heading = $gen->create_element($cd, 'heading', ['heading' => 'Stays'], $chapter);
        $context = \context_module::instance($cd->cmid);
        $fs = get_file_storage();
        foreach (['cdelement_richtext_content', 'richtextelementbg', 'headingelementbg'] as $area) {
            $itemid = $area == 'headingelementbg' ? $heading->id : $richtext->id;
            $fs->create_file_from_string(['contextid' => $context->id, 'component' => 'mod_contentdesigner',
                'filearea' => $area, 'itemid' => $itemid, 'filepath' => '/', 'filename' => 'a.txt'], 'x');
        }
        $elementid = $DB->get_field('contentdesigner_elements', 'id', ['shortname' => 'richtext'], MUST_EXIST);

        \core\plugin_manager::instance()->get_plugin_info('cdelement_richtext')->uninstall_cleanup();

        $this->assertFalse($DB->record_exists('contentdesigner_content', ['element' => $elementid]));
        $this->assertFalse($DB->record_exists('contentdesigner_options', ['element' => $elementid]));
        $this->assertFalse($DB->record_exists('contentdesigner_elements', ['id' => $elementid]));
        $this->assertEmpty($fs->get_area_files($context->id, 'mod_contentdesigner', 'cdelement_richtext_content'));
        $this->assertEmpty($fs->get_area_files($context->id, 'mod_contentdesigner', 'richtextelementbg'));
        // The other elements are kept.
        $this->assertNotEmpty($fs->get_area_files($context->id, 'mod_contentdesigner', 'headingelementbg'));
        $this->assertTrue($DB->record_exists('contentdesigner_content', ['id' => $heading->contentid]));
        $this->assertEquals(
            (string) $heading->contentid,
            $DB->get_field('cdelement_chapter', 'contents', ['id' => $chapter->id])
        );
    }
}

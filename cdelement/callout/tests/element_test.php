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

namespace cdelement_callout;

use mod_contentdesigner\editor;

/**
 * Tests of the callout element: rendering, the type whitelist, saving and deletion.
 *
 * @package    cdelement_callout
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\cdelement_callout\element::class)]
final class element_test extends \advanced_testcase {
    /**
     * Creates an activity with a chapter.
     *
     * @return array [\stdClass $course, \stdClass $cd, \stdClass $chapter]
     */
    private function create_activity(): array {
        $course = $this->getDataGenerator()->create_course();
        $cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        $chapter = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner')->create_chapter($cd);
        return [$course, $cd, $chapter];
    }

    public function test_installed(): void {
        global $DB;
        $this->resetAfterTest();
        $this->assertTrue($DB->record_exists('contentdesigner_elements', ['shortname' => 'callout']));
        $this->assertContains('callout', editor::get_element_names());
    }

    public function test_render_escapes_title_and_cleans_content(): void {
        $this->resetAfterTest();
        [$course, $cd, $chapter] = $this->create_activity();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $callout = $gen->create_element($cd, 'callout', [
            'callouttype' => 'warning',
            'callouttitle' => 'Mind <script>x</script>the gap',
            'content' => '<p>Body text</p><script>alert(2)</script><p onclick="alert(3)">Click</p>'
                . '<img src="@@PLUGINFILE@@/pic.png" alt="Picture">',
            'contentformat' => FORMAT_HTML,
        ], $chapter);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $element = editor::get_element('callout', $cd->cmid);
        $html = $element->render($element->prepare_formdata($callout->id));

        $this->assertStringContainsString('callout-type-warning', $html);
        $this->assertStringContainsString('fa-triangle-exclamation', $html);
        $this->assertStringContainsString('aria-label="Warning"', $html);
        $this->assertStringContainsString('Mind', $html);
        $this->assertStringContainsString('the gap', $html);
        $this->assertStringContainsString('Body text', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('alert(', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('@@PLUGINFILE@@', $html);
        $context = \context_module::instance($cd->cmid);
        $this->assertStringContainsString(
            "/pluginfile.php/{$context->id}/mod_contentdesigner/cdelement_callout_content/{$callout->id}/pic.png",
            $html
        );
    }

    public function test_render_without_title(): void {
        $this->resetAfterTest();
        [, $cd, $chapter] = $this->create_activity();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $callout = $gen->create_element($cd, 'callout', ['callouttype' => 'tip', 'content' => '<p>Only body</p>'], $chapter);

        $element = editor::get_element('callout', $cd->cmid);
        $html = $element->render($element->prepare_formdata($callout->id));
        $this->assertStringContainsString('callout-type-tip', $html);
        $this->assertStringNotContainsString('callout-title', $html);
        $this->assertStringContainsString('Only body', $html);
    }

    public function test_render_hidden(): void {
        $this->resetAfterTest();
        [, $cd, $chapter] = $this->create_activity();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $callout = $gen->create_element($cd, 'callout', ['visible' => 0, 'content' => '<p>Hidden</p>'], $chapter);

        $element = editor::get_element('callout', $cd->cmid);
        $this->assertSame('', $element->render($element->prepare_formdata($callout->id)));
    }

    public function test_render_unknown_stored_type(): void {
        $this->resetAfterTest();
        [, $cd, $chapter] = $this->create_activity();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $callout = $gen->create_element($cd, 'callout', [
            'callouttype' => 'x" onmouseover="alert(1)',
            'content' => '<p>Text</p>',
        ], $chapter);

        $element = editor::get_element('callout', $cd->cmid);
        $html = $element->render($element->prepare_formdata($callout->id));
        $this->assertStringContainsString('callout-type-info', $html);
        $this->assertStringNotContainsString('onmouseover', $html);
    }

    /**
     * Values of the type and whether they are valid.
     *
     * @return array
     */
    public static function type_provider(): array {
        $cases = [];
        foreach (array_keys(element::TYPES) as $type) {
            $cases[$type] = [$type, true];
        }
        return $cases + [
            'empty' => ['', false],
            'unknown' => ['note', false],
            'uppercase' => ['INFO', false],
            'markup' => ['info"><script>', false],
            'class injection' => ['info callout-type-danger', false],
        ];
    }

    /**
     * Only the known types pass the validation and the cleaning.
     *
     * @param string $type The submitted type.
     * @param bool $valid Whether the type is valid.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('type_provider')]
    public function test_type_whitelist(string $type, bool $valid): void {
        $this->resetAfterTest();
        [, $cd] = $this->create_activity();
        $element = editor::get_element('callout', $cd->cmid);

        $errors = $element->validation(['callouttype' => $type, 'callouttitle' => ''], []);
        $this->assertSame(!$valid, isset($errors['callouttype']));
        $this->assertSame($valid ? $type : element::DEFAULT_TYPE, element::clean_type($type));
    }

    public function test_validation_missing_type_and_long_title(): void {
        $this->resetAfterTest();
        [, $cd] = $this->create_activity();
        $element = editor::get_element('callout', $cd->cmid);

        $errors = $element->validation(['callouttitle' => str_repeat('a', 256)], []);
        $this->assertArrayHasKey('callouttype', $errors);
        $this->assertArrayHasKey('callouttitle', $errors);
        $this->assertSame([], $element->validation(['callouttype' => 'info', 'callouttitle' => str_repeat('a', 255)], []));
    }

    public function test_type_options_have_names(): void {
        $options = element::get_type_options();
        $this->assertSame(array_keys(element::TYPES), array_keys($options));
        foreach ($options as $name) {
            $this->assertNotEmpty($name);
        }
    }

    public function test_update_instance_cleans_values(): void {
        global $DB;
        $this->resetAfterTest();
        [, $cd] = $this->create_activity();
        $element = editor::get_element('callout', $cd->cmid);

        $id = $element->update_instance((object) [
            'instanceid' => 0,
            'contentdesignerid' => $cd->id,
            'title' => 'Callout',
            'visible' => 1,
            'callouttype' => 'evil',
            'callouttitle' => '<b>Bold</b> ' . str_repeat('x', 300),
            'content_editor' => ['text' => '<p>Text</p>', 'format' => FORMAT_HTML],
        ]);
        $record = $DB->get_record('cdelement_callout', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('info', $record->callouttype);
        $this->assertStringNotContainsString('<b>', $record->callouttitle);
        $this->assertLessThanOrEqual(255, \core_text::strlen($record->callouttitle));
        $this->assertSame('<p>Text</p>', $record->content);
        $this->assertEquals(FORMAT_HTML, $record->contentformat);
    }

    public function test_update_element_saves_files_and_delete_removes_them(): void {
        global $CFG, $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $cd, $chapter] = $this->create_activity();
        $context = \context_module::instance($cd->cmid);
        $element = editor::get_element('callout', $cd->cmid);

        $draftid = file_get_unused_draft_itemid();
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftid,
            'filepath' => '/',
            'filename' => 'pic.png',
        ], 'image');
        $draftsrc = "{$CFG->wwwroot}/draftfile.php/" . \context_user::instance($USER->id)->id . "/user/draft/{$draftid}/pic.png";

        $element->update_element((object) [
            'instanceid' => 0,
            'chapterid' => $chapter->id,
            'cmid' => $cd->cmid,
            'contextid' => $context->id,
            'course' => $course->id,
            'element' => $element->elementid,
            'elementshortname' => 'callout',
            'title' => 'Saved callout',
            'visible' => 1,
            'callouttype' => 'success',
            'callouttitle' => 'Well done',
            'content_editor' => ['text' => '<p>Done <img src="' . $draftsrc . '" alt="Pic"></p>', 'format' => FORMAT_HTML,
                'itemid' => $draftid],
        ]);

        $record = $DB->get_record('cdelement_callout', ['contentdesignerid' => $cd->id], '*', MUST_EXIST);
        $this->assertSame('success', $record->callouttype);
        $this->assertSame('Well done', $record->callouttitle);
        $this->assertStringContainsString('@@PLUGINFILE@@/pic.png', $record->content);
        $this->assertNotFalse($fs->get_file(
            $context->id,
            'mod_contentdesigner',
            'cdelement_callout_content',
            $record->id,
            '/',
            'pic.png'
        ));
        $this->assertNotFalse($element->get_instance_contentid($record->id));

        $element->delete_element($record->id);
        $this->assertFalse($DB->record_exists('cdelement_callout', ['id' => $record->id]));
        $this->assertTrue($fs->is_area_empty($context->id, 'mod_contentdesigner', 'cdelement_callout_content', $record->id));
    }

    /**
     * Saves a callout as a teacher who may or may not trust content, and renders it for a student.
     *
     * @param bool $trusted Whether the teacher may trust content.
     * @return array [stored record, rendered HTML]
     */
    private function save_and_render_as_teacher(bool $trusted): array {
        global $DB;
        [$course, $cd] = $this->create_activity();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $context = \context_module::instance($cd->cmid);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('moodle/site:trustcontent', $trusted ? CAP_ALLOW : CAP_PROHIBIT, $roleid, $context->id, true);
        $this->setUser($teacher);

        $element = editor::get_element('callout', $cd->cmid);
        $id = $element->update_instance((object) [
            'instanceid' => 0,
            'contentdesignerid' => $cd->id,
            'title' => 'Callout',
            'visible' => 1,
            'callouttype' => 'info',
            'content_editor' => [
                'text' => '<p>Hello</p><script>alert(1)</script><iframe src="https://example.com/embed"></iframe>',
                'format' => FORMAT_HTML,
            ],
        ]);
        $record = $DB->get_record('cdelement_callout', ['id' => $id], '*', MUST_EXIST);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
        return [$record, $element->render($element->prepare_formdata($id))];
    }

    public function test_untrusted_content_is_cleaned(): void {
        $this->resetAfterTest();
        [$record, $html] = $this->save_and_render_as_teacher(false);
        $this->assertEquals(0, $record->contenttrust);
        $this->assertStringContainsString('Hello', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<iframe', $html);
    }

    public function test_trusted_content_is_not_cleaned(): void {
        $this->resetAfterTest();
        [$record, $html] = $this->save_and_render_as_teacher(true);
        $this->assertEquals(1, $record->contenttrust);
        $this->assertStringContainsString('<iframe', $html);
    }

    public function test_unknown_format_is_not_stored(): void {
        global $DB;
        $this->resetAfterTest();
        [, $cd] = $this->create_activity();
        $id = editor::get_element('callout', $cd->cmid)->update_instance((object) [
            'instanceid' => 0,
            'contentdesignerid' => $cd->id,
            'callouttype' => 'info',
            'content_editor' => ['text' => '<p>Text</p>', 'format' => 99],
        ]);
        $this->assertEquals(FORMAT_HTML, $DB->get_field('cdelement_callout', 'contentformat', ['id' => $id]));
    }
}

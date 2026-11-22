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

namespace cdelement_richtext;

use mod_contentdesigner\editor;

/**
 * Tests of the rich text element: content is cleaned unless its author may trust content.
 *
 * @package    cdelement_richtext
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\cdelement_richtext\element::class)]
final class element_test extends \advanced_testcase {
    /** @var string Content with a script and an iframe. */
    private const CONTENT = '<p>Hello</p><script>alert(1)</script><iframe src="https://example.com/embed"></iframe>';

    /**
     * Saves a rich text element as a user and renders it.
     *
     * @param bool $trusted Whether the user may trust content.
     * @return array [stored record, rendered HTML]
     */
    private function save_and_render(bool $trusted): array {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $context = \context_module::instance($cd->cmid);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('moodle/site:trustcontent', $trusted ? CAP_ALLOW : CAP_PROHIBIT, $roleid, $context->id, true);
        $this->setUser($teacher);

        $element = editor::get_element('richtext', $cd->cmid);
        $id = $element->update_instance((object) [
            'instanceid' => 0,
            'contentdesignerid' => $cd->id,
            'title' => 'Rich text',
            'visible' => 1,
            'content_editor' => ['text' => self::CONTENT, 'format' => FORMAT_HTML],
        ]);
        $record = $DB->get_record('cdelement_richtext', ['id' => $id], '*', MUST_EXIST);
        $record->instance = $record->id;
        return [$record, $element->render($record)];
    }

    /**
     * Content of an author who may not trust content is cleaned.
     */
    public function test_untrusted_content_is_cleaned(): void {
        [$record, $html] = $this->save_and_render(false);
        $this->assertEquals(0, $record->contenttrust);
        $this->assertStringContainsString('Hello', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<iframe', $html);
    }

    /**
     * Content of an author who may trust content is shown as entered.
     */
    public function test_trusted_content_is_not_cleaned(): void {
        [$record, $html] = $this->save_and_render(true);
        $this->assertEquals(1, $record->contenttrust);
        $this->assertStringContainsString('<iframe', $html);
    }

    /**
     * Saving again keeps the creation time.
     */
    public function test_update_keeps_timecreated(): void {
        global $DB;
        [$record] = $this->save_and_render(true);
        $DB->set_field('cdelement_richtext', 'timecreated', 1000, ['id' => $record->id]);
        $cmid = get_coursemodule_from_instance('contentdesigner', $record->contentdesignerid)->id;
        editor::get_element('richtext', $cmid)->update_instance((object) [
            'instanceid' => $record->id,
            'content_editor' => ['text' => 'Changed', 'format' => FORMAT_HTML],
        ]);
        $this->assertEquals(1000, $DB->get_field('cdelement_richtext', 'timecreated', ['id' => $record->id]));
    }
}

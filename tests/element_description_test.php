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
 * Tests of the element description: it is formatted for display only, the edit form and copies get the stored text.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(elements::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(editor::class)]
final class element_description_test extends \advanced_testcase {
    /** @var string A description in two languages, for the multi-language content filter. */
    private const DESCRIPTION = '<span lang="en" class="multilang">English</span><span lang="de" class="multilang">Deutsch</span>';

    /** @var \stdClass The activity. */
    private \stdClass $cd;

    /** @var \stdClass A chapter of the activity. */
    private \stdClass $chapter;

    /**
     * An activity with one chapter, the multi-language filter on, as admin.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        filter_set_global_state('multilang', TEXTFILTER_ON);
        $course = $this->getDataGenerator()->create_course();
        $this->cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        $this->chapter = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner')->create_chapter($this->cd);
    }

    /**
     * Sets the description of an element instance.
     *
     * @param string $shortname The element.
     * @param int $instanceid The instance.
     */
    private function set_description(string $shortname, int $instanceid): void {
        global $DB;
        $elementid = $DB->get_field('contentdesigner_elements', 'id', ['shortname' => $shortname], MUST_EXIST);
        $DB->set_field(
            'contentdesigner_options',
            'description',
            self::DESCRIPTION,
            ['element' => $elementid, 'instance' => $instanceid]
        );
        $DB->set_field(
            'contentdesigner_options',
            'descriptionformat',
            FORMAT_HTML,
            ['element' => $elementid, 'instance' => $instanceid]
        );
    }

    /**
     * The stored description of an element instance.
     *
     * @param string $shortname The element.
     * @param int $instanceid The instance.
     * @return string
     */
    private function stored_description(string $shortname, int $instanceid): string {
        global $DB;
        $elementid = $DB->get_field('contentdesigner_elements', 'id', ['shortname' => $shortname], MUST_EXIST);
        return (string) $DB->get_field(
            'contentdesigner_options',
            'description',
            ['element' => $elementid, 'instance' => $instanceid],
            MUST_EXIST
        );
    }

    /**
     * Learners get the formatted description, the edit form the stored one.
     */
    public function test_formdata_keeps_the_stored_description(): void {
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $richtext = $gen->create_element(
            $this->cd,
            'richtext',
            ['content' => 'x', 'contentformat' => FORMAT_HTML],
            $this->chapter
        );
        $this->set_description('richtext', $richtext->id);
        $element = editor::get_element('richtext', $this->cd->cmid);

        $display = $element->prepare_formdata($richtext->id);
        $this->assertStringContainsString('English', $display->description);
        $this->assertStringNotContainsString('Deutsch', $display->description);

        $this->assertSame(self::DESCRIPTION, $element->prepare_formdata($richtext->id, false)->description);
        $this->assertSame(self::DESCRIPTION, $element->get_instance($richtext->id, false, false)->description);
    }

    /**
     * Duplicating an element or a chapter copies the stored description, in all its languages.
     */
    public function test_duplicate_keeps_the_stored_description(): void {
        global $DB;
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $richtext = $gen->create_element(
            $this->cd,
            'richtext',
            ['content' => 'x', 'contentformat' => FORMAT_HTML],
            $this->chapter
        );
        $this->set_description('richtext', $richtext->id);
        $this->set_description('chapter', $this->chapter->id);
        $editor = editor::get_editor($this->cd->cmid);

        $editor->duplicate($richtext->id, 'richtext');
        $copy = $DB->get_field_sql('SELECT MAX(id) FROM {cdelement_richtext}');
        $this->assertNotEquals($richtext->id, $copy);
        $this->assertSame(self::DESCRIPTION, $this->stored_description('richtext', $copy));

        $editor->chapter_duplicate($this->chapter->id);
        $chaptercopy = $DB->get_field_sql('SELECT MAX(id) FROM {cdelement_chapter}');
        $this->assertNotEquals($this->chapter->id, $chaptercopy);
        $this->assertSame(self::DESCRIPTION, $this->stored_description('chapter', $chaptercopy));
        // The source keeps its text as well.
        $this->assertSame(self::DESCRIPTION, $this->stored_description('richtext', $richtext->id));
    }
}

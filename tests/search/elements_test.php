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

namespace mod_contentdesigner\search;

use core_search\manager;

/**
 * Tests of the global search area of the elements.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_contentdesigner\search\elements
 * @covers     \mod_contentdesigner\elements
 */
final class elements_test extends \advanced_testcase {
    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var \stdClass The activity. */
    private \stdClass $cd;

    /** @var \stdClass[] The element instances by name. */
    private array $elements = [];

    /**
     * An activity with elements of several types (with and without text fields), one of them hidden.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $this->course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $chapter = $generator->create_chapter($this->cd, ['title' => 'First chapter']);
        $this->elements['heading'] = $generator->create_element(
            $this->cd,
            'heading',
            ['title' => 'Welcome', 'heading' => 'Heading text'],
            $chapter
        );
        $this->elements['richtext'] = $generator->create_element(
            $this->cd,
            'richtext',
            ['title' => 'Rich', 'content' => '<p>Rich text</p>', 'contentformat' => FORMAT_HTML],
            $chapter
        );
        $this->elements['hidden'] = $generator->create_element(
            $this->cd,
            'paragraph',
            ['title' => 'Hidden', 'content' => 'Secret', 'visible' => 0],
            $chapter
        );
        $this->elements['outro'] = $generator->create_element(
            $this->cd,
            'outro',
            ['title' => 'Outro', 'outrocontent' => '<p>Goodbye</p>', 'outrocontentformat' => FORMAT_HTML]
        );
    }

    /**
     * The documents of the area, by element and title.
     *
     * @param \context|null $context Restrict to this context.
     * @return \core_search\document[]
     */
    private function get_documents(?\context $context = null): array {
        $area = new elements();
        $documents = [];
        $recordset = $area->get_document_recordset(0, $context);
        if (!$recordset) {
            return [];
        }
        foreach ($recordset as $record) {
            $document = $area->get_document($record);
            $documents[$record->elementname . ':' . $document->get('title')] = $document;
        }
        $recordset->close();
        return $documents;
    }

    /**
     * The id of the general options of an element instance, the item id of its document.
     *
     * @param string $shortname The element.
     * @param int $instanceid The element instance.
     * @return int
     */
    private function get_itemid(string $shortname, int $instanceid): int {
        global $DB;
        $elementid = $DB->get_field('contentdesigner_elements', 'id', ['shortname' => $shortname]);
        return (int) $DB->get_field('contentdesigner_options', 'id', ['element' => $elementid, 'instance' => $instanceid]);
    }

    /**
     * Visible elements of all types are indexed with their texts; hidden ones are not.
     */
    public function test_documents(): void {
        $documents = $this->get_documents();

        $this->assertArrayHasKey('chapter:First chapter', $documents);
        $this->assertArrayNotHasKey('paragraph:Hidden', $documents);
        $this->assertStringContainsString('Heading text', $documents['heading:Welcome']->get('content'));
        $this->assertStringContainsString('Rich text', $documents['richtext:Rich']->get('content'));
        $this->assertStringNotContainsString('<p>', $documents['richtext:Rich']->get('content'));
        $this->assertStringContainsString('Goodbye', $documents['outro:Outro']->get('content'));

        $context = \context_module::instance($this->cd->cmid);
        $this->assertEquals($context->id, $documents['heading:Welcome']->get('contextid'));
        $this->assertEquals($this->course->id, $documents['heading:Welcome']->get('courseid'));
    }

    /**
     * Showing a hidden element and renaming an element in the editor index them again (delta indexing).
     */
    public function test_modified_elements_are_indexed_again(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/contentdesigner/lib.php');
        $DB->set_field('contentdesigner_options', 'timemodified', time() - 1000, []);
        $since = time() - 10;
        $area = new elements();
        $titles = function () use ($area, $since) {
            $titles = [];
            foreach ($area->get_document_recordset($since) as $record) {
                $titles[] = $record->title;
            }
            return $titles;
        };
        $this->assertSame([], $titles());

        $this->setAdminUser();
        $paragraph = \mod_contentdesigner\editor::get_element('paragraph', $this->cd->cmid);
        $paragraph->update_visibility($this->elements['hidden']->id, 1);
        mod_contentdesigner_inplace_editable('instance_title[heading][' . $this->elements['heading']->id . ']', 0, 'Renamed');
        $result = $titles();
        sort($result);
        $this->assertSame(['Hidden', 'Renamed'], $result);
    }

    /**
     * Restricting the documents to a context.
     */
    public function test_documents_in_context(): void {
        $this->assertCount(4, $this->get_documents(\context_module::instance($this->cd->cmid)));
        $this->assertCount(4, $this->get_documents(\context_course::instance($this->course->id)));

        $othercourse = $this->getDataGenerator()->create_course();
        $this->assertCount(0, $this->get_documents(\context_course::instance($othercourse->id)));
    }

    /**
     * Learners see the visible elements of visible activities; editors see all; deleted elements are reported.
     */
    public function test_check_access(): void {
        global $DB;
        $area = new elements();
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');

        $visible = $this->get_itemid('heading', $this->elements['heading']->id);
        $hidden = $this->get_itemid('paragraph', $this->elements['hidden']->id);

        $this->setUser($student);
        $this->assertEquals(manager::ACCESS_GRANTED, $area->check_access($visible));
        $this->assertEquals(manager::ACCESS_DENIED, $area->check_access($hidden));

        $this->setUser($teacher);
        $this->assertEquals(manager::ACCESS_GRANTED, $area->check_access($hidden));

        // Course access is checked by the search manager; the area checks the activity.
        set_coursemodule_visible($this->cd->cmid, 0);
        $this->assertEquals(manager::ACCESS_GRANTED, $area->check_access($visible));
        $this->setUser($student);
        $this->assertEquals(manager::ACCESS_DENIED, $area->check_access($visible));
        set_coursemodule_visible($this->cd->cmid, 1);

        $DB->delete_records('cdelement_heading', ['id' => $this->elements['heading']->id]);
        $this->setUser($student);
        $this->assertEquals(manager::ACCESS_DELETED, $area->check_access($visible));
        $this->assertEquals(manager::ACCESS_DELETED, $area->check_access(-1));
    }
}

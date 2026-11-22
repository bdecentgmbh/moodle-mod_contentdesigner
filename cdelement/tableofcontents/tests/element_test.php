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

namespace cdelement_tableofcontents;

use mod_contentdesigner\editor;

/**
 * Tests of the table of contents element.
 *
 * @package    cdelement_tableofcontents
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\cdelement_tableofcontents\element::class)]
final class element_test extends \advanced_testcase {
    public function test_progress_of_the_learner(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $course = $this->getDataGenerator()->create_course();
        $cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        $chapter1 = $gen->create_chapter($cd, ['title' => 'One <script>alert(1)</script>']);
        $chapter2 = $gen->create_chapter($cd, ['title' => 'Two']);
        $gen->create_element($cd, 'heading', ['heading' => 'A'], $chapter1);
        $gen->create_element($cd, 'heading', ['heading' => 'B'], $chapter2);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($user);

        $element = editor::get_element('tableofcontents', $cd->cmid);
        $this->assertEquals($chapter1->id, $element->incompleted_chapters_data());

        // Completions of another activity do not count.
        $othercd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        $otherchapter = $gen->create_chapter($othercd);
        foreach ([$chapter1, $otherchapter] as $chapter) {
            $DB->insert_record('cdelement_chapter_completion', ['instance' => $chapter->id, 'userid' => $user->id,
                'completion' => 1, 'timecreated' => time(), 'timemodified' => time()]);
        }
        $this->assertEquals(1, $element->get_completed_chapter_count());
        $this->assertEquals($chapter2->id, $element->incompleted_chapters_data());

        // All chapters completed: no chapter to resume.
        $DB->insert_record('cdelement_chapter_completion', ['instance' => $chapter2->id, 'userid' => $user->id,
            'completion' => 1, 'timecreated' => time(), 'timemodified' => time()]);
        $this->assertSame(0, $element->incompleted_chapters_data());

        // The table of contents of the activity, created for the editor, escapes the titles.
        $this->setAdminUser();
        $element->get_editor_data();
        $instance = $DB->get_record('cdelement_tableofcontents', ['contentdesignerid' => $cd->id], '*', MUST_EXIST);
        $DB->set_field('cdelement_tableofcontents', 'actiontstatus', element::ENABLED, ['id' => $instance->id]);
        $html = $element->render($element->prepare_formdata($instance->id));
        $this->assertStringContainsString('One', $html);
        $this->assertStringNotContainsString('<script>', $html);

        // A second table of contents is not created.
        $element->get_editor_data();
        $this->assertEquals(1, $DB->count_records('cdelement_tableofcontents', ['contentdesignerid' => $cd->id]));

        // The editor shows the title formatted, without HTML.
        $DB->set_field('cdelement_tableofcontents', 'title', 'Toc <img src=x onerror=alert(1)>', ['id' => $instance->id]);
        $html = $element->get_editor_data();
        $this->assertStringContainsString('Toc', $html);
        $this->assertStringNotContainsString('<img', $html);
    }
}

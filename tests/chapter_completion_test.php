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

use cdelement_chapter\external as chapter_external;
use mod_contentdesigner\hook\chapter_completion;
use mod_contentdesigner\hook\content_navigation;
use mod_contentdesigner\hook\extend_content;
use mod_contentdesigner\hook\progress_updated;
use mod_contentdesigner\hook\view_page;
use mod_contentdesigner\local\attempts;
use mod_contentdesigner\local\extensions;
use mod_contentdesigner\local\pages;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/contentdesigner/lib.php');

/**
 * Tests of the chapter completion on the server: learners complete only the chapters they can reach, and the
 * chapters after a chapter are loaded only if the learner can get past it.
 *
 * The tests named test_core_* run without addons (their hooks are redirected), the tests named test_pro_* with the
 * addon cdaddon_pro (sequential and free navigation) and are skipped without it.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(chapter_external::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\cdelement_chapter\element::class)]
#[\PHPUnit\Framework\Attributes\CoversFunction('contentdesigner_output_fragment_load_next_chapters')]
final class chapter_completion_test extends \advanced_testcase {
    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var \stdClass The learner. */
    private \stdClass $student;

    /** @var \stdClass The activity. */
    private \stdClass $cd;

    /** @var \stdClass[] The three chapters of the activity, by number. */
    private array $chapters = [];

    /** @var \stdClass The mandatory H5P element in the first chapter. */
    private \stdClass $h5p;

    /**
     * A course with a learner.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        extensions::reset_caches();
        $this->course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
    }

    /**
     * Creates the activity: three chapters with a paragraph each, the first one starting with a mandatory H5P element.
     *
     * @param array $settings Settings of the activity.
     */
    private function create_activity(array $settings = []): void {
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $this->cd = $this->getDataGenerator()->create_module('contentdesigner', $settings + ['course' => $this->course->id]);
        for ($i = 1; $i <= 3; $i++) {
            $chapter = $gen->create_chapter($this->cd, ['title' => "Chapter $i"]);
            if ($i == 1) {
                $this->h5p = $gen->create_element($this->cd, 'h5p', ['title' => 'Exercise', 'mandatory' => 1], $chapter);
            }
            $gen->create_element($this->cd, 'paragraph', ['content' => "Text of chapter $i"], $chapter);
            $this->chapters[$i] = $chapter;
        }
        $gen->create_element(
            $this->cd,
            'outro',
            ['outrocontent' => 'Well done', 'outrocontentformat' => FORMAT_HTML, 'primarybutton' => 0, 'secondarybutton' => 0]
        );
    }

    /**
     * Runs the test without addons: their hooks are redirected to callbacks that do nothing.
     *
     * @param bool $free Whether the navigation is free (as an addon may set it).
     */
    private function without_addons(bool $free = false): void {
        $this->redirectHook(
            content_navigation::class,
            fn(content_navigation $hook) => $free ? $hook->set_free() : null
        );
        $this->redirectHook(view_page::class, fn(view_page $hook) => null);
        $this->redirectHook(extend_content::class, fn(extend_content $hook) => null);
        $this->redirectHook(chapter_completion::class, fn(chapter_completion $hook) => null);
        $this->redirectHook(progress_updated::class, fn(progress_updated $hook) => null);
        extensions::reset_caches();
    }

    /**
     * Skips the test without the addon cdaddon_pro.
     */
    private function require_pro(): void {
        if (!\core_component::get_component_directory('cdaddon_pro') || !class_exists(\cdaddon_pro\local\navigation::class)) {
            $this->markTestSkipped('The addon cdaddon_pro is not installed.');
        }
    }

    /**
     * Marks a chapter as completed through the web service.
     *
     * @param int $number The number of the chapter.
     * @return bool
     */
    private function complete(int $number): bool {
        $result = chapter_external::update_completion($this->cd->cmid, $this->chapters[$number]->id);
        return \core_external\external_api::clean_returnvalue(chapter_external::update_completion_returns(), $result);
    }

    /**
     * Asserts that the web service refuses to complete a chapter.
     *
     * @param int $number The number of the chapter.
     */
    private function assert_cannot_complete(int $number): void {
        global $DB, $USER;
        try {
            $this->complete($number);
            $this->fail("Chapter $number must not be completed.");
        } catch (\moodle_exception $e) {
            $this->assertEquals('chapternotreached', $e->errorcode);
        }
        $this->assertFalse($DB->record_exists(
            'cdelement_chapter_completion',
            ['instance' => $this->chapters[$number]->id, 'userid' => $USER->id, 'completion' => 1]
        ));
    }

    /**
     * Loads the chapters after a chapter, as the fragment does.
     *
     * @param int $number The number of the chapter.
     * @return string HTML
     */
    private function load_next(int $number): string {
        return (string) contentdesigner_output_fragment_load_next_chapters([
            'context' => \context_module::instance($this->cd->cmid),
            'chapter' => $this->chapters[$number]->id,
            'attemptid' => 0,
        ]);
    }

    /**
     * Completes the mandatory H5P element in the current attempt of the user.
     */
    private function complete_h5p(): void {
        global $USER;
        $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner')
            ->create_h5p_completion($this->h5p, attempts::get_current($this->cd->id, $USER->id));
    }

    /**
     * Sequential navigation: the chapter with an incomplete mandatory element and the chapters after it cannot be
     * completed; the chapters after it are not loaded, even if the chapter was completed before.
     */
    public function test_core_bypass_is_closed(): void {
        global $DB;
        $this->without_addons();
        $this->create_activity();
        $this->setUser($this->student);

        $this->assert_cannot_complete(1);
        $this->assert_cannot_complete(2);
        $this->assert_cannot_complete(3);

        // A completion stored without the mandatory element (e.g. before the element was made mandatory) does not
        // let the learner load the chapters after it.
        $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner')
            ->create_chapter_completion($this->chapters[1], $this->student->id);
        $html = $this->load_next(1);
        $this->assertStringNotContainsString('Text of chapter 2', $html);
        $this->assertStringNotContainsString('Well done', $html);
        $this->assertSame('', $this->load_next(2));
        $this->assert_cannot_complete(2);
        $DB->delete_records('cdelement_chapter_completion', ['userid' => $this->student->id]);

        // Chapters of another activity are refused.
        $other = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $this->course->id]);
        $otherchapter = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner')->create_chapter($other);
        $this->expectException(\dml_missing_record_exception::class);
        chapter_external::update_completion($this->cd->cmid, $otherchapter->id);
    }

    /**
     * Scrolling display: the learner completes the chapters one after the other, and loads the next ones.
     */
    public function test_core_normal_progress_scroll(): void {
        $this->without_addons();
        $this->create_activity();
        $this->setUser($this->student);

        $this->complete_h5p();
        $this->assertTrue($this->complete(1));
        // Completing a chapter again is allowed.
        $this->assertTrue($this->complete(1));
        $this->assert_cannot_complete(3);

        $html = $this->load_next(1);
        $this->assertStringContainsString('Text of chapter 2', $html);
        $this->assertStringNotContainsString('Text of chapter 3', $html);
        $this->assertStringNotContainsString('Well done', $html);
        $this->assertSame('', $this->load_next(2));

        $this->assertTrue($this->complete(2));
        $html = $this->load_next(2);
        $this->assertStringContainsString('Text of chapter 3', $html);
        $this->assertStringNotContainsString('Text of chapter 2', $html);

        $this->assertTrue($this->complete(3));
        $this->assertStringContainsString('Well done', $this->load_next(3));
    }

    /**
     * Paged display: the learner completes the chapter of the page, which opens the next page; the chapters after a
     * chapter are not loaded by the fragment.
     */
    public function test_core_normal_progress_paged(): void {
        global $USER;
        $this->without_addons();
        $this->create_activity(['displaymode' => pages::DISPLAY_PAGES]);
        $this->setUser($this->student);
        $cm = get_fast_modinfo($this->course)->get_cm($this->cd->cmid);
        $pages = function (int $page = 0) use ($cm, $USER) {
            $attempt = attempts::get_current($this->cd->id, $USER->id);
            return new pages($cm, editor::get_element('chapter', $cm->id, $attempt), $attempt, $page);
        };

        $this->assert_cannot_complete(1);
        $this->complete_h5p();
        $this->assertTrue($this->complete(1));
        $this->assertTrue($pages(1)->can_go_next());
        $this->assertSame(2, $pages()->current);
        $this->assertSame('', $this->load_next(1));

        $this->assert_cannot_complete(3);
        $this->assertTrue($this->complete(2));
        $this->assertTrue($this->complete(3));
        $this->assertTrue($pages()->is_end_reachable());
    }

    /**
     * Free navigation (from an addon): mandatory elements do not prevent the completion, chapters that are not
     * reached still do.
     */
    public function test_core_free_navigation(): void {
        $this->without_addons(true);
        $this->create_activity();
        $this->setUser($this->student);

        $this->assert_cannot_complete(2);
        $this->assertTrue($this->complete(1));
        $this->assertStringContainsString('Text of chapter 2', $this->load_next(1));
        $this->assertTrue($this->complete(2));
    }

    /**
     * Guests cannot complete chapters.
     */
    public function test_core_guest(): void {
        global $DB;
        $this->without_addons(true);
        $this->create_activity();
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['courseid' => $this->course->id, 'enrol' => 'guest']);
        $this->setGuestUser();

        try {
            $this->complete(1);
            $this->fail('Guests must not complete chapters.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('guestnoprogress', $e->errorcode);
        }
        $this->assertFalse($DB->record_exists('cdelement_chapter_completion', []));
        $this->assertFalse($DB->record_exists('contentdesigner_attempts', []));
    }

    /**
     * Teachers are not held back by chapters they did not complete.
     */
    public function test_core_teacher(): void {
        $this->without_addons(true);
        $this->create_activity();
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));
        $this->assertTrue($this->complete(3));
    }

    /**
     * The chapter title is formatted for learners: filters run in the module context and "&" is escaped once.
     */
    public function test_core_chapter_title(): void {
        global $DB;
        $this->without_addons(true);
        $this->create_activity();
        $DB->set_field('cdelement_chapter', 'title', 'Q&A <b>bold</b>', ['id' => $this->chapters[1]->id]);
        $DB->set_field('cdelement_chapter', 'titlestatus', 1, ['id' => $this->chapters[1]->id]);
        $this->setUser($this->student);
        [$course, $cm] = get_course_and_cm_from_cmid($this->cd->cmid);

        $html = (new editor($cm, $course, attempts::get_current($this->cd->id, $this->student->id)))->render_elements();
        $this->assertStringContainsString('<h4>Q&amp;A bold</h4>', $html);
    }

    /**
     * Pro: with sequential navigation the mandatory element prevents the completion, with free navigation it does not.
     */
    public function test_pro_navigation(): void {
        global $DB;
        $this->require_pro();
        $this->create_activity(['navigation' => \cdaddon_pro\local\navigation::SEQUENTIAL]);
        $this->setUser($this->student);

        $this->assert_cannot_complete(1);
        $this->assert_cannot_complete(2);

        $DB->set_field('contentdesigner', 'navigation', \cdaddon_pro\local\navigation::FREE, ['id' => $this->cd->id]);
        $this->assertTrue($this->complete(1));
        $this->assertStringContainsString('Text of chapter 2', $this->load_next(1));
        $this->assert_cannot_complete(3);

        $DB->set_field('contentdesigner', 'navigation', \cdaddon_pro\local\navigation::SEQUENTIAL, ['id' => $this->cd->id]);
        $this->assertStringNotContainsString('Text of chapter 2', $this->load_next(1));
        $this->assert_cannot_complete(2);
        $this->complete_h5p();
        $this->assertTrue($this->complete(2));
    }
}

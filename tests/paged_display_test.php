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
 * Tests of the paged display: the page where learners continue, the pages they can reach, the attempts and the outro.
 *
 * The tests named test_core_* run without addons (their hooks are redirected), the tests named test_pro_* with the
 * addon cdaddon_pro (sequential navigation, graded attempts, completion rules) and are skipped without it.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(pages::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(editor::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(content_display::class)]
#[\PHPUnit\Framework\Attributes\CoversFunction('contentdesigner_output_fragment_load_elements')]
final class paged_display_test extends \advanced_testcase {
    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var \stdClass The learner. */
    private \stdClass $student;

    /** @var \stdClass The activity, with the paged display. */
    private \stdClass $cd;

    /** @var \stdClass[] The three chapters of the activity. */
    private array $chapters = [];

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
     * Creates the activity with three chapters, each with a paragraph, and the outro.
     *
     * @param array $settings Settings of the activity.
     * @param bool $mandatory Whether the first chapter starts with a mandatory H5P element.
     * @return \stdClass|null The mandatory H5P element.
     */
    private function create_activity(array $settings = [], bool $mandatory = false): ?\stdClass {
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $this->cd = $this->getDataGenerator()->create_module(
            'contentdesigner',
            $settings + ['course' => $this->course->id, 'displaymode' => pages::DISPLAY_PAGES]
        );
        $h5p = null;
        for ($i = 1; $i <= 3; $i++) {
            $chapter = $gen->create_chapter($this->cd, ['title' => "Chapter $i"]);
            if ($i == 1 && $mandatory) {
                $h5p = $gen->create_element($this->cd, 'h5p', ['title' => 'Exercise', 'mandatory' => 1], $chapter);
            }
            $gen->create_element($this->cd, 'paragraph', ['content' => "Text of chapter $i"], $chapter);
            $this->chapters[$i] = $chapter;
        }
        $gen->create_element(
            $this->cd,
            'outro',
            ['outrocontent' => 'Well done', 'outrocontentformat' => FORMAT_HTML, 'primarybutton' => 0, 'secondarybutton' => 0]
        );
        return $h5p;
    }

    /**
     * Runs the test without addons: their hooks are redirected to callbacks that do nothing.
     */
    private function without_addons(): void {
        $this->redirectHook(content_navigation::class, fn(content_navigation $hook) => null);
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
        if (!\core_component::get_component_directory('cdaddon_pro') || !class_exists(\cdaddon_pro\local\grading::class)) {
            $this->markTestSkipped('The addon cdaddon_pro is not installed.');
        }
    }

    /**
     * The activity page.
     *
     * @return content_display
     */
    private function display(): content_display {
        global $DB;
        [$course, $cm] = get_course_and_cm_from_cmid($this->cd->cmid);
        $instance = $DB->get_record('contentdesigner', ['id' => $this->cd->id], '*', MUST_EXIST);
        return new content_display($instance, $cm, $course, \context_module::instance($cm->id));
    }

    /**
     * The pages for the current user.
     *
     * @param int $page The requested page, 0 to continue where the progress stops.
     * @param \stdClass|null $attempt The attempt, null for the current one.
     * @return pages
     */
    private function pages(int $page = 0, ?\stdClass $attempt = null): pages {
        global $USER;
        $cm = get_fast_modinfo($this->course)->get_cm($this->cd->cmid);
        $attempt = $attempt ?? attempts::get_current($this->cd->id, $USER->id);
        return new pages($cm, editor::get_element('chapter', $cm->id, $attempt), $attempt, $page);
    }

    /**
     * Renders the content in the current attempt of the user.
     *
     * @param int $page The requested page, 0 to continue where the progress stops.
     * @param \stdClass|null $attempt The attempt, null for the current one.
     * @return string HTML
     */
    private function render(int $page = 0, ?\stdClass $attempt = null): string {
        global $USER;
        return $this->display()->render_attempt($attempt ?? attempts::get_current($this->cd->id, $USER->id), true, $page);
    }

    /**
     * Completes a chapter for the current user.
     *
     * @param int $number The number of the chapter.
     * @param int|null $time The time of the completion, null for now.
     */
    private function complete_chapter(int $number, ?int $time = null): void {
        global $USER;
        $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner')
            ->create_chapter_completion($this->chapters[$number], $USER->id, $time);
    }

    /**
     * Finishes an attempt with the addon cdaddon_pro.
     *
     * @param \stdClass $attempt The attempt.
     */
    private function finish_attempt(\stdClass $attempt): void {
        global $DB;
        \cdaddon_pro\local\grading::finish_attempt(
            get_fast_modinfo($this->course)->get_cm($this->cd->cmid),
            $this->display()->contentdesigner,
            $DB->get_record('contentdesigner_attempts', ['id' => $attempt->id], '*', MUST_EXIST)
        );
    }

    /**
     * Asserts that the HTML shows the chapter of a page and no other chapter.
     *
     * @param int $number The number of the chapter.
     * @param string $html The HTML of the content.
     */
    private function assert_shows_chapter(int $number, string $html): void {
        for ($i = 1; $i <= 3; $i++) {
            if ($i == $number) {
                $this->assertStringContainsString("Text of chapter $i", $html);
            } else {
                $this->assertStringNotContainsString("Text of chapter $i", $html);
            }
        }
        $this->assertStringContainsString(
            get_string('pageof', 'mod_contentdesigner', ['page' => $number, 'pages' => 3]),
            $html
        );
    }

    /**
     * The display mode is stored with the activity; the scrolling display is the default and shows all chapters.
     */
    public function test_core_display_modes(): void {
        global $DB;
        $this->without_addons();
        $this->create_activity(['displaymode' => pages::DISPLAY_SCROLL]);
        $this->assertFalse(pages::is_paged($DB->get_record('contentdesigner', ['id' => $this->cd->id])));
        $default = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $this->course->id]);
        $this->assertEquals(pages::DISPLAY_SCROLL, $DB->get_field('contentdesigner', 'displaymode', ['id' => $default->id]));

        $this->setUser($this->student);
        foreach ([1, 2] as $number) {
            $this->complete_chapter($number);
        }
        $html = $this->render();
        $this->assertStringContainsString('Text of chapter 1', $html);
        $this->assertStringContainsString('Text of chapter 3', $html);
        $this->assertStringNotContainsString('contentdesigner-pagenav', $html);

        $DB->set_field('contentdesigner', 'displaymode', pages::DISPLAY_PAGES, ['id' => $this->cd->id]);
        $this->assertTrue(pages::is_paged($DB->get_record('contentdesigner', ['id' => $this->cd->id])));
        $this->assert_shows_chapter(3, $this->render());
    }

    /**
     * Learners start on the first page; Next opens once they completed the chapter.
     */
    public function test_core_first_visit(): void {
        $this->without_addons();
        $this->create_activity();
        $this->setUser($this->student);

        $html = $this->render();
        $this->assert_shows_chapter(1, $html);
        $this->assertMatchesRegularExpression('/<button type="button" class="btn btn-primary" disabled>/', $html);
        $this->assertStringNotContainsString('outro-reached', $html);
        $pages = $this->pages();
        $this->assertSame(3, $pages->count());
        $this->assertSame(1, $pages->current);
        $this->assertFalse($pages->can_go_next());

        $this->complete_chapter(1);
        $pages = $this->pages(1);
        $this->assertTrue($pages->can_go_next());
        $this->assertStringContainsString('page=2', $pages->export_navigation()['nexturl']);
    }

    /**
     * Learners continue on the page of their progress; pages they cannot reach are not shown, also not by URL.
     */
    public function test_core_resume_on_page_of_progress(): void {
        $this->without_addons();
        $this->create_activity();
        $this->setUser($this->student);
        attempts::get_current($this->cd->id, $this->student->id);

        // The third page cannot be requested before the first chapter is completed.
        $this->assertSame(1, $this->pages(3)->current);

        $this->complete_chapter(1);
        $this->assert_shows_chapter(2, $this->render());
        $this->assertSame(2, $this->pages(3)->current);
        // Learners can go back to the pages they reached.
        $this->assert_shows_chapter(1, $this->render(1));

        $this->complete_chapter(2);
        $this->assert_shows_chapter(3, $this->render());
        $this->assertSame(3, $this->pages(3)->current);
    }

    /**
     * A new attempt starts on the first page, although the chapter completions of the learner are kept.
     */
    public function test_core_new_attempt_starts_on_first_page(): void {
        $this->without_addons();
        $this->create_activity();
        $this->setUser($this->student);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');

        $gen->create_attempt($this->cd, $this->student->id, ['timestart' => time() - 1000]);
        $this->complete_chapter(1, time() - 500);
        $this->complete_chapter(2, time() - 400);
        $this->assertSame(3, $this->pages()->current);

        $second = attempts::create($this->cd->id, $this->student->id);
        $this->assertSame(2, (int) $second->attempt);
        $this->assert_shows_chapter(1, $this->render(0, $second));
        $pages = $this->pages(0, $second);
        $this->assertSame(1, $pages->current);
        // The completed chapters still open the following pages.
        $this->assertTrue($pages->can_go_next());
        $this->assertSame(3, $this->pages(3, $second)->current);

        // Progress in the new attempt moves the page where the learner continues.
        $this->complete_chapter(1);
        $this->assertSame(2, $this->pages(0, $second)->current);
    }

    /**
     * A mandatory element that is not completed in the attempt keeps Next closed, on every page after it.
     */
    public function test_core_mandatory_element_gates_next(): void {
        $this->without_addons();
        $h5p = $this->create_activity([], true);
        $this->setUser($this->student);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $attempt = attempts::get_current($this->cd->id, $this->student->id);
        $this->complete_chapter(1);

        $pages = $this->pages(2);
        $this->assertSame(1, $pages->current);
        $this->assertSame(1, $pages->get_last_reachable_page());
        $this->assertFalse($pages->can_go_next());
        $this->assertSame(1, $this->pages()->current);
        $html = $this->render(2);
        $this->assertStringContainsString(get_string('completeprevelement', 'mod_contentdesigner'), $html);
        // The elements after the mandatory element stay hidden as well.
        $this->assertStringNotContainsString('Text of chapter 1', $html);

        // A completion in another attempt does not count.
        $other = $gen->create_attempt($this->cd, $this->student->id, ['timestart' => time() - 2000, 'attempt' => 0]);
        $gen->create_h5p_completion($h5p, $other);
        $this->assertFalse($this->pages(1, $attempt)->can_go_next());

        $gen->create_h5p_completion($h5p, $attempt);
        $pages = $this->pages(1, $attempt);
        $this->assertTrue($pages->can_go_next());
        $this->assertSame(2, $this->pages(0, $attempt)->current);
        $this->assert_shows_chapter(2, $this->render(2, $attempt));

        // Gating applies on the following pages too: the second chapter is not completed.
        $this->assertSame(2, $this->pages(3, $attempt)->current);
        $this->assertFalse($this->pages(2, $attempt)->can_go_next());
    }

    /**
     * With free navigation (from an addon), mandatory elements do not close Next, chapters still do.
     */
    public function test_core_free_navigation(): void {
        $this->without_addons();
        $this->redirectHook(content_navigation::class, fn(content_navigation $hook) => $hook->set_free());
        $this->create_activity([], true);
        $this->setUser($this->student);
        attempts::get_current($this->cd->id, $this->student->id);

        $this->assertFalse($this->pages(1)->can_go_next());
        $this->complete_chapter(1);
        $this->assertTrue($this->pages(1)->can_go_next());
        $this->assertSame(2, $this->pages()->current);
    }

    /**
     * The outro is on the last page, once the learner can reach the end.
     */
    public function test_core_outro_on_last_page(): void {
        $this->without_addons();
        $this->create_activity();
        $this->setUser($this->student);
        $this->complete_chapter(1);
        $this->complete_chapter(2);

        $html = $this->render(3);
        $this->assert_shows_chapter(3, $html);
        $this->assertStringNotContainsString('outro-reached', $html);
        $this->assertFalse($this->pages(3)->is_end_reachable());

        $this->complete_chapter(3);
        $pages = $this->pages();
        $this->assertSame(3, $pages->current);
        $this->assertTrue($pages->is_last());
        $this->assertTrue($pages->is_end_reachable());
        $this->assertFalse($pages->export_navigation()['hasnext']);
        $html = $this->render();
        $this->assertStringContainsString('outro-reached', $html);
        $this->assertStringContainsString('Well done', $html);

        // The other pages do not show the outro.
        $html = $this->render(1);
        $this->assert_shows_chapter(1, $html);
        $this->assertStringNotContainsString('outro-reached', $html);
    }

    /**
     * A hidden outro is not shown to learners, but the end of the content is still marked for the completion rule.
     */
    public function test_hidden_outro(): void {
        global $DB;
        $this->without_addons();
        $this->create_activity();
        $DB->set_field('cdelement_outro', 'visible', 0, ['contentdesignerid' => $this->cd->id]);
        $this->setUser($this->student);
        $this->complete_chapter(1);
        $this->complete_chapter(2);
        $this->complete_chapter(3);

        $html = $this->render();
        $this->assertStringNotContainsString('Well done', $html);
        $this->assertStringContainsString('outro-reached', $html);
    }

    /**
     * The links to the pages continue the attempt shown; refreshing the content keeps the page.
     */
    public function test_core_urls_and_refresh(): void {
        $this->without_addons();
        $this->create_activity();
        $this->setUser($this->student);
        $attempt = attempts::get_current($this->cd->id, $this->student->id);
        $this->complete_chapter(1);

        $nav = $this->pages(2)->export_navigation();
        $this->assertStringContainsString('action=continueattempt', $nav['previousurl']);
        $this->assertStringContainsString('attemptid=' . $attempt->id, $nav['previousurl']);
        $this->assertStringContainsString('page=1', $nav['previousurl']);
        $this->assertSame('', $nav['nexturl']);
        $this->assertCount(2, (array) json_decode($nav['chapterurls']));

        // The page of the request, as used by the links.
        $_GET['page'] = 1;
        $html = $this->display()->continue_attempt($attempt->id, true);
        unset($_GET['page']);
        $this->assert_shows_chapter(1, $html);

        $args = ['context' => \context_module::instance($this->cd->cmid), 'cmid' => $this->cd->cmid,
            'attemptid' => $attempt->id, 'page' => 1];
        $this->assert_shows_chapter(1, contentdesigner_output_fragment_load_elements($args));
        unset($args['page']);
        $this->assert_shows_chapter(2, contentdesigner_output_fragment_load_elements($args));
    }

    /**
     * Teachers reach every page.
     */
    public function test_core_teacher(): void {
        $this->without_addons();
        $this->create_activity();
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));
        $pages = $this->pages(3);
        $this->assertSame(3, $pages->current);
        $this->assertTrue($pages->is_end_reachable());
    }

    /**
     * Pro, graded attempts: the learner continues an attempt on the page of the progress, a new attempt starts on the
     * first page, the finish button is with the outro on the last page.
     */
    public function test_pro_graded_attempts(): void {
        global $DB;
        $this->require_pro();
        $this->create_activity(['enablegrading' => 1, 'grade' => 100, 'navigation' => \cdaddon_pro\local\navigation::SEQUENTIAL]);
        $this->setUser($this->student);
        $display = $this->display();
        $finish = get_string('finishattempt', 'cdaddon_pro');

        // The overview of the attempts, not the content.
        $this->assertStringNotContainsString('Text of chapter', $display->view_summary(true));

        $attempt = \cdaddon_pro\local\grading::start_attempt($display->contentdesigner);
        $html = $display->continue_attempt($attempt->id, true);
        $this->assert_shows_chapter(1, $html);
        $this->assertStringNotContainsString($finish, $html);

        $this->complete_chapter(1);
        $html = $display->continue_attempt($attempt->id, true);
        $this->assert_shows_chapter(2, $html);
        // The links continue the attempt, so that the addon shows the content and not the overview.
        $this->assertStringContainsString('action=continueattempt&amp;attemptid=' . $attempt->id . '&amp;page=1', $html);

        $this->complete_chapter(2);
        $this->complete_chapter(3);
        $html = $display->continue_attempt($attempt->id, true);
        $this->assert_shows_chapter(3, $html);
        $this->assertStringContainsString('outro-reached', $html);
        $this->assertStringContainsString($finish, $html);
        $this->assertStringNotContainsString($finish, $this->render(1, $attempt));

        $this->finish_attempt($attempt);
        // The chapters were completed before the new attempt.
        $DB->set_field('cdelement_chapter_completion', 'timemodified', time() - 100, ['userid' => $this->student->id]);
        $DB->set_field('contentdesigner_attempts', 'timestart', time() - 200, ['id' => $attempt->id]);

        $second = \cdaddon_pro\local\grading::start_attempt($display->contentdesigner);
        $this->assertNotEquals($attempt->id, $second->id);
        $html = $display->continue_attempt($second->id, true);
        $this->assert_shows_chapter(1, $html);
        $this->assertStringContainsString('attemptid=' . $second->id, $html);
        $this->assertSame(1, $this->pages(0, $second)->current);
    }

    /**
     * Pro, sequential navigation: a mandatory element closes Next until it is completed in the attempt; with free
     * navigation it does not.
     */
    public function test_pro_mandatory_sequential_navigation(): void {
        global $DB;
        $this->require_pro();
        $h5p = $this->create_activity(['enablegrading' => 1, 'grade' => 100,
            'navigation' => \cdaddon_pro\local\navigation::SEQUENTIAL], true);
        $this->setUser($this->student);
        $display = $this->display();
        $attempt = \cdaddon_pro\local\grading::start_attempt($display->contentdesigner);
        $this->complete_chapter(1);

        $this->assertFalse($this->pages(1, $attempt)->can_go_next());
        $_GET['page'] = 2;
        $html = $display->continue_attempt($attempt->id, true);
        unset($_GET['page']);
        $this->assertStringContainsString(get_string('pageof', 'mod_contentdesigner', ['page' => 1, 'pages' => 3]), $html);
        $this->assertStringContainsString(get_string('completeprevelement', 'mod_contentdesigner'), $html);

        $DB->set_field('contentdesigner', 'navigation', \cdaddon_pro\local\navigation::FREE, ['id' => $this->cd->id]);
        $this->assertTrue($this->pages(1, $attempt)->can_go_next());
        $DB->set_field('contentdesigner', 'navigation', \cdaddon_pro\local\navigation::SEQUENTIAL, ['id' => $this->cd->id]);

        $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner')->create_h5p_completion($h5p, $attempt);
        $this->assertTrue($this->pages(1, $attempt)->can_go_next());
        $this->assert_shows_chapter(2, $display->continue_attempt($attempt->id, true));

        // A new attempt closes the gate again and starts on the first page.
        $this->finish_attempt($attempt);
        $second = \cdaddon_pro\local\grading::start_attempt($display->contentdesigner);
        $pages = $this->pages(0, $second);
        $this->assertSame(1, $pages->current);
        $this->assertFalse($pages->can_go_next());
    }

    /**
     * Pro, completion rule "Reach the end": the end can be reported once the last page shows the outro.
     */
    public function test_pro_end_reached(): void {
        $this->require_pro();
        $this->create_activity(['completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionendreach' => 1]);
        $this->setUser($this->student);
        $attempt = attempts::get_current($this->cd->id, $this->student->id);
        $cm = get_fast_modinfo($this->course)->get_cm($this->cd->cmid);
        $state = fn() => (new \mod_contentdesigner\completion\custom_completion(
            get_fast_modinfo($this->course, $this->student->id)->get_cm($this->cd->cmid),
            $this->student->id
        ))->get_state('completionendreach');

        $this->complete_chapter(1);
        $this->complete_chapter(2);
        $this->assertStringNotContainsString('outro-reached', $this->render(3, $attempt));
        $this->assertFalse(\cdaddon_pro\external\end_reached::execute($cm->id));
        $this->assertEquals(COMPLETION_INCOMPLETE, $state());

        $this->complete_chapter(3);
        $this->assertStringNotContainsString('outro-reached', $this->render(2, $attempt));
        $html = $this->render(0, $attempt);
        $this->assert_shows_chapter(3, $html);
        $this->assertStringContainsString('outro-reached', $html);
        $this->assertTrue(\cdaddon_pro\external\end_reached::execute($cm->id));
        $this->assertEquals(COMPLETION_COMPLETE, $state());
    }
}

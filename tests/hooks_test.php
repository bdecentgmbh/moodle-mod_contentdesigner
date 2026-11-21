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

use mod_contentdesigner\hook\after_instance_saved;
use mod_contentdesigner\hook\before_instance_deleted;
use mod_contentdesigner\hook\completion_rules;
use mod_contentdesigner\hook\content_navigation;
use mod_contentdesigner\hook\supports_feature;
use mod_contentdesigner\hook\view_page;
use mod_contentdesigner\local\completion_rule;
use mod_contentdesigner\local\extensions;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/contentdesigner/lib.php');

/**
 * Tests of the extension points and of the activity life cycle (lib.php).
 *
 * The hooks are redirected, so the tests behave the same with and without the addons installed.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_contentdesigner\local\extensions::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_contentdesigner\completion\custom_completion::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_contentdesigner\content_display::class)]
#[\PHPUnit\Framework\Attributes\CoversFunction('contentdesigner_supports')]
#[\PHPUnit\Framework\Attributes\CoversFunction('contentdesigner_add_instance')]
#[\PHPUnit\Framework\Attributes\CoversFunction('contentdesigner_update_instance')]
#[\PHPUnit\Framework\Attributes\CoversFunction('contentdesigner_delete_instance')]
#[\PHPUnit\Framework\Attributes\CoversFunction('contentdesigner_reset_userdata')]
#[\PHPUnit\Framework\Attributes\CoversFunction('contentdesigner_get_coursemodule_info')]
final class hooks_test extends \advanced_testcase {
    /**
     * Redirects the hooks of the addons that decide features and rules, and forgets the cached answers.
     *
     * @param bool $grades Whether a plugin supports grades.
     * @param completion_rule[] $rules The completion rules of the plugins.
     */
    private function redirect_addons(bool $grades, array $rules = []): void {
        $this->redirectHook(supports_feature::class, function (supports_feature $hook) use ($grades) {
            if ($grades) {
                $hook->set_supported();
            }
        });
        $this->redirectHook(completion_rules::class, function (completion_rules $hook) use ($rules) {
            foreach ($rules as $rule) {
                $hook->add_rule($rule);
            }
        });
        extensions::reset_caches();
    }

    /**
     * A completion rule for the tests: complete when the activity name is "done".
     *
     * @return completion_rule
     */
    private function name_rule(): completion_rule {
        return new class implements completion_rule {
            /**
             * The name, the column completionendreach is used for the test.
             *
             * @return string
             */
            public function get_name(): string {
                return 'completionendreach';
            }

            /**
             * The description.
             *
             * @return string
             */
            public function get_description(): string {
                return 'Test rule';
            }

            /**
             * No form elements.
             *
             * @param \MoodleQuickForm $mform The form.
             * @param string $suffix The suffix.
             * @return array
             */
            public function add_form_elements(\MoodleQuickForm $mform, string $suffix): array {
                return [];
            }

            /**
             * Enabled by the column.
             *
             * @param array $data The data.
             * @param string $suffix The suffix.
             * @return bool
             */
            public function is_enabled(array $data, string $suffix): bool {
                return !empty($data['completionendreach' . $suffix]);
            }

            /**
             * Complete when the activity is named "done".
             *
             * @param \cm_info $cm The activity.
             * @param int $userid The user.
             * @return int
             */
            public function get_state(\cm_info $cm, int $userid): int {
                return $cm->name == 'done' ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
            }
        };
    }

    /**
     * Without addons, the activity has no grades and no custom completion rules.
     */
    public function test_features_without_addons(): void {
        $this->resetAfterTest();
        $this->redirect_addons(false);
        $this->assertFalse(contentdesigner_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertFalse(contentdesigner_supports(FEATURE_COMPLETION_HAS_RULES));
        $this->assertSame([], completion\custom_completion::get_defined_custom_rules());
        $this->assertTrue(contentdesigner_supports(FEATURE_COMPLETION_TRACKS_VIEWS));
        $this->assertEquals(MOD_PURPOSE_CONTENT, contentdesigner_supports(FEATURE_MOD_PURPOSE));
    }

    /**
     * Addons add grades and completion rules; the rules are evaluated through them.
     */
    public function test_features_of_addons(): void {
        $this->resetAfterTest();
        $this->redirect_addons(true, [$this->name_rule()]);
        $this->assertTrue(contentdesigner_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertTrue(contentdesigner_supports(FEATURE_COMPLETION_HAS_RULES));
        $this->assertSame(['completionendreach'], completion\custom_completion::get_defined_custom_rules());

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id, 'name' => 'done',
            'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionendreach' => 1]);
        $cm = get_fast_modinfo($course)->get_cm($cd->cmid);
        $this->assertEquals(['completionendreach' => 1], $cm->customdata['customcompletionrules']);

        $completion = new completion\custom_completion($cm, $user->id);
        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_state('completionendreach'));
        $this->assertEquals(['completionendreach' => 'Test rule'], $completion->get_custom_rule_descriptions());
    }

    /**
     * Unchecked completion rules are saved as disabled.
     */
    public function test_update_disables_unchecked_rules(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirect_addons(false, [$this->name_rule()]);
        $course = $this->getDataGenerator()->create_course();
        $cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id, 'completionendreach' => 1]);
        $this->assertEquals(1, $DB->get_field('contentdesigner', 'completionendreach', ['id' => $cd->id]));

        $data = $DB->get_record('contentdesigner', ['id' => $cd->id]);
        $data->instance = $cd->id;
        $data->coursemodule = $cd->cmid;
        unset($data->completionendreach);
        contentdesigner_update_instance($data, null);
        $this->assertEquals(0, $DB->get_field('contentdesigner', 'completionendreach', ['id' => $cd->id]));
    }

    /**
     * Creating, updating and deleting an activity dispatches the hooks; deleting removes all its data.
     */
    public function test_life_cycle(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirect_addons(false);
        $saved = [];
        $deleted = [];
        $this->redirectHook(after_instance_saved::class, function (after_instance_saved $hook) use (&$saved) {
            $saved[] = $hook->created;
        });
        $this->redirectHook(before_instance_deleted::class, function (before_instance_deleted $hook) use (&$deleted) {
            $deleted[] = $hook->instance->id;
        });

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        $this->assertSame([true], $saved);

        $chapter = $gen->create_chapter($cd);
        $gen->create_element($cd, 'heading', ['heading' => 'Hello'], $chapter);
        $gen->create_element($cd, 'paragraph', ['content' => 'Text'], $chapter);
        $gen->create_element($cd, 'outro', ['outrocontent' => 'Bye', 'primarybutton' => 0, 'secondarybutton' => 0]);
        $attempt = $gen->create_attempt($cd, $user->id);
        $DB->insert_record('cdelement_chapter_completion', ['instance' => $chapter->id, 'userid' => $user->id,
            'completion' => 1, 'timecreated' => time(), 'timemodified' => time()]);
        $DB->insert_record('contentdesigner_completion', ['contentdesignerid' => $cd->id, 'userid' => $user->id,
            'completion' => 1, 'mandatorycompletion' => 0, 'starttime' => time(), 'timecreated' => time()]);

        \core_courseformat\formatactions::cm($course)->delete($cd->cmid);
        $this->assertSame([$cd->id], $deleted);
        foreach (['cdelement_heading', 'cdelement_paragraph', 'cdelement_outro', 'cdelement_chapter'] as $table) {
            $this->assertEquals(0, $DB->count_records($table, ['contentdesignerid' => $cd->id]), $table);
        }
        $this->assertEquals(0, $DB->count_records('contentdesigner_content', ['contentdesignerid' => $cd->id]));
        $this->assertEquals(0, $DB->count_records('cdelement_chapter_completion', ['instance' => $chapter->id]));
        $this->assertEquals(0, $DB->count_records('contentdesigner_attempts', ['id' => $attempt->id]));
        $this->assertEquals(0, $DB->count_records('contentdesigner_completion', ['contentdesignerid' => $cd->id]));
        $this->assertEquals(0, $DB->count_records('contentdesigner_options'));
    }

    /**
     * A course reset deletes the progress of the learners, not the content.
     */
    public function test_reset_userdata(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirect_addons(false);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        $chapter = $gen->create_chapter($cd);
        $h5p = $gen->create_element($cd, 'h5p', ['mandatory' => 0, 'maxscore' => '1'], $chapter);
        $attempt = $gen->create_attempt($cd, $user->id);
        $DB->insert_record('cdelement_chapter_completion', ['instance' => $chapter->id, 'userid' => $user->id,
            'completion' => 1, 'timecreated' => time(), 'timemodified' => time()]);
        $DB->insert_record('cdelement_h5p_completion', ['instance' => $h5p->id, 'userid' => $user->id,
            'cdattemptid' => $attempt->id, 'completion' => 1, 'success' => 1, 'score' => '1', 'timemodified' => time()]);

        $status = contentdesigner_reset_userdata((object) ['courseid' => $course->id, 'reset_contentdesigner_progress' => 1]);
        $this->assertCount(1, $status);
        $this->assertEquals(0, $DB->count_records('contentdesigner_attempts', ['contentdesignerid' => $cd->id]));
        $this->assertEquals(0, $DB->count_records('cdelement_chapter_completion'));
        $this->assertEquals(0, $DB->count_records('cdelement_h5p_completion'));
        $this->assertEquals(1, $DB->count_records('cdelement_h5p', ['contentdesignerid' => $cd->id]));
    }

    /**
     * Addons can take over the page and let learners move freely.
     */
    public function test_view_and_navigation_hooks(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirect_addons(false);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        [$course, $cm] = get_course_and_cm_from_cmid($cd->cmid);
        $this->setUser($user);

        $instance = $DB->get_record('contentdesigner', ['id' => $cd->id]);
        $display = new content_display($instance, $cm, $course, \context_module::instance($cm->id));
        $this->redirectHook(view_page::class, function (view_page $hook) {
            if ($hook->action == 'summary') {
                $hook->set_output('SUMMARY');
            }
        });
        $this->assertSame('SUMMARY', $display->view_summary(true));
        // Without an addon for the action, the content is shown in the user's attempt, which is created.
        $this->assertStringContainsString('contentdesigner-content', $display->make_attempt(true));
        $this->assertEquals(1, $DB->count_records('contentdesigner_attempts', ['contentdesignerid' => $cd->id]));

        $chapter = editor::get_element('chapter', $cm->id);
        $this->assertTrue($chapter->is_sequential());
        $this->redirectHook(content_navigation::class, fn(content_navigation $hook) => $hook->set_free());
        $this->assertFalse(editor::get_element('chapter', $cm->id)->is_sequential());
    }
}

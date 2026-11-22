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

namespace mod_contentdesigner\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Tests of the privacy provider of the activity and of the chapter and H5P elements.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_contentdesigner\privacy\provider::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\cdelement_chapter\privacy\provider::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\cdelement_h5p\privacy\provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass The activity. */
    private \stdClass $cd;

    /** @var \stdClass A learner with data. */
    private \stdClass $user1;

    /** @var \stdClass Another learner with data. */
    private \stdClass $user2;

    /** @var \context_module The context of the activity. */
    private \context_module $context;

    /**
     * An activity with a chapter and an H5P element, two learners with attempts, completions and results.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $this->cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        $this->context = \context_module::instance($this->cd->cmid);
        $chapter = $gen->create_chapter($this->cd);
        $h5p = $gen->create_element($this->cd, 'h5p', ['mandatory' => 1, 'maxscore' => '2'], $chapter);
        foreach (['user1', 'user2'] as $name) {
            $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
            $this->$name = $user;
            $attempt = $gen->create_attempt($this->cd, $user->id, ['grade' => 50, 'timefinish' => time()]);
            $DB->insert_record('contentdesigner_completion', ['contentdesignerid' => $this->cd->id, 'userid' => $user->id,
                'completion' => 1, 'mandatorycompletion' => 1, 'starttime' => time(), 'timecreated' => time()]);
            $DB->insert_record('cdelement_chapter_completion', ['instance' => $chapter->id, 'userid' => $user->id,
                'completion' => 1, 'timecreated' => time(), 'timemodified' => time()]);
            $DB->insert_record('cdelement_h5p_completion', ['instance' => $h5p->id, 'userid' => $user->id,
                'cdattemptid' => $attempt->id, 'completion' => 1, 'success' => 1, 'score' => '1',
                'scoredata' => '{"max":2}', 'timecreated' => time(), 'timemodified' => time()]);
            $DB->insert_record('cdelement_h5p_xapistates', ['component' => 'cdaddon_pro', 'userid' => $user->id,
                'itemid' => $this->context->id, 'stateid' => 'state', 'statedata' => '{}',
                'registration' => 'cdelement_h5p-' . $h5p->id, 'timecreated' => time(), 'timemodified' => time()]);
        }
    }

    /**
     * The metadata declares the tables of the activity and the elements.
     */
    public function test_get_metadata(): void {
        $items = provider::get_metadata(new \core_privacy\local\metadata\collection('mod_contentdesigner'))->get_collection();
        $names = array_map(fn($item) => $item->get_name(), $items);
        $this->assertContains('contentdesigner_attempts', $names);
        $this->assertContains('contentdesigner_completion', $names);
        $this->assertContains('cdelement', $names);
    }

    /**
     * Contexts and users are found through the activity and the elements.
     */
    public function test_contexts_and_users(): void {
        $this->assertEquals([$this->context->id], provider::get_contexts_for_userid($this->user1->id)->get_contextids());

        $userlist = new userlist($this->context, 'mod_contentdesigner');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$this->user1->id, $this->user2->id], $userlist->get_userids());

        // An element alone is enough to find the context.
        $other = $this->getDataGenerator()->create_user();
        $this->assertEmpty(provider::get_contexts_for_userid($other->id)->get_contextids());
    }

    /**
     * The export contains the attempts, the completion, the chapters and the H5P results.
     */
    public function test_export_user_data(): void {
        $this->export_context_data_for_user($this->user1->id, $this->context, 'mod_contentdesigner');
        $writer = writer::with_context($this->context);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data([]);
        $this->assertCount(1, $data->attempts);
        $this->assertNotEmpty($data->completion);
        $chapters = $writer->get_data([get_string('pluginname', 'cdelement_chapter')]);
        $this->assertCount(1, $chapters->chapters);
        $h5p = $writer->get_data([get_string('pluginname', 'cdelement_h5p')]);
        $this->assertCount(1, $h5p->results);
        $this->assertCount(1, $h5p->savedstates);
    }

    /**
     * Deleting the data of one user keeps the data of the other.
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        provider::delete_data_for_user(new approved_contextlist($this->user1, 'mod_contentdesigner', [$this->context->id]));
        foreach (
            ['contentdesigner_attempts', 'contentdesigner_completion', 'cdelement_chapter_completion',
                'cdelement_h5p_completion', 'cdelement_h5p_xapistates'] as $table
        ) {
            $this->assertEquals(0, $DB->count_records($table, ['userid' => $this->user1->id]), $table);
            $this->assertEquals(1, $DB->count_records($table, ['userid' => $this->user2->id]), $table);
        }
    }

    /**
     * Deleting the data of users in a context, and of all users.
     */
    public function test_delete_data_for_users_and_all(): void {
        global $DB;
        provider::delete_data_for_users(new approved_userlist($this->context, 'mod_contentdesigner', [$this->user2->id]));
        $this->assertEquals(0, $DB->count_records('cdelement_h5p_completion', ['userid' => $this->user2->id]));
        $this->assertEquals(1, $DB->count_records('cdelement_h5p_completion', ['userid' => $this->user1->id]));

        provider::delete_data_for_all_users_in_context($this->context);
        foreach (
            ['contentdesigner_attempts', 'contentdesigner_completion', 'cdelement_chapter_completion',
                'cdelement_h5p_completion', 'cdelement_h5p_xapistates'] as $table
        ) {
            $this->assertEquals(0, $DB->count_records($table), $table);
        }
    }
}

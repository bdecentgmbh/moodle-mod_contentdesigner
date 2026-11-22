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

namespace mod_contentdesigner\local;

/**
 * Tests of the creation of attempts: under a required lock, with the checks of plugins.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(attempts::class)]
final class attempts_test extends \advanced_testcase {
    /** @var \stdClass The activity. */
    private \stdClass $cd;

    /** @var \stdClass The learner. */
    private \stdClass $user;

    /**
     * An activity and a learner.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        $this->user = $this->getDataGenerator()->create_and_enrol($course, 'student');
    }

    /**
     * Without the lock no attempt is created, also not the first one of the activity page.
     */
    public function test_no_attempt_without_lock(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/contentdesigner/tests/fixtures/busy_lock_factory.php');
        $CFG->lock_factory = '\\' . busy_lock_factory::class;

        $calls = [
            fn() => attempts::create($this->cd->id, $this->user->id),
            fn() => attempts::get_current($this->cd->id, $this->user->id),
        ];
        foreach ($calls as $call) {
            try {
                $call();
                $this->fail('An attempt must not be created without the lock.');
            } catch (\moodle_exception $e) {
                $this->assertEquals('locktimeout', $e->errorcode);
            }
        }
        $this->assertFalse($DB->record_exists('contentdesigner_attempts', []));
    }

    /**
     * The check of a plugin runs under the lock: it gets the attempts of the user, and may return one of them or
     * refuse a new attempt.
     */
    public function test_check(): void {
        global $DB;
        $first = attempts::create($this->cd->id, $this->user->id);
        $this->assertSame(1, (int) $first->attempt);

        // The check returns the unfinished attempt instead of a new one.
        $seen = null;
        $result = attempts::create($this->cd->id, $this->user->id, false, function (array $attempts) use (&$seen) {
            $seen = $attempts;
            foreach (array_reverse($attempts) as $attempt) {
                if (empty($attempt->timefinish)) {
                    return $attempt;
                }
            }
            return null;
        });
        $this->assertEquals($first->id, $result->id);
        $this->assertEquals([$first->id], array_keys($seen));
        $this->assertEquals(1, $DB->count_records('contentdesigner_attempts', []));

        // The check returns nothing: the next attempt is created.
        $second = attempts::create($this->cd->id, $this->user->id, false, fn(array $attempts) => null);
        $this->assertSame(2, (int) $second->attempt);

        // The check refuses the attempt.
        try {
            attempts::create($this->cd->id, $this->user->id, false, function (array $attempts) {
                throw new \moodle_exception('nopermissions', 'error', '', 'attempt');
            });
            $this->fail('The check must refuse the attempt.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }
        $this->assertEquals(2, $DB->count_records('contentdesigner_attempts', []));

        // The lock was released after the refusal.
        $this->assertSame(3, (int) attempts::create($this->cd->id, $this->user->id)->attempt);
    }
}

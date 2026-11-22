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

namespace cdelement_h5p;

use mod_contentdesigner\editor;

/**
 * Tests of the results of H5P elements: where learners can store them and how they count.
 *
 * @package    cdelement_h5p
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(external::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(element::class)]
final class external_test extends \advanced_testcase {
    /** @var \stdClass The activity. */
    private \stdClass $cd;

    /** @var \stdClass The chapter of the H5P element. */
    private \stdClass $chapter;

    /** @var \stdClass The H5P element. */
    private \stdClass $h5p;

    /** @var \stdClass A student. */
    private \stdClass $student;

    /** @var \stdClass The attempt of the student. */
    private \stdClass $attempt;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $course = $this->getDataGenerator()->create_course();
        $this->cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        $this->chapter = $gen->create_chapter($this->cd);
        $this->h5p = $gen->create_element($this->cd, 'h5p', ['title' => 'Exercise'], $this->chapter);
        $this->student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->attempt = $gen->create_attempt($this->cd, $this->student->id);
        $this->setUser($this->student);
    }

    /**
     * Stores a result as the browser of the learner does.
     *
     * @param float $raw The score.
     * @param float $max The maximum score.
     */
    private function store(float $raw, float $max): void {
        external::store_result_data($this->cd->cmid, $this->h5p->id, $this->attempt->id, [
            'completion' => true,
            'success' => true,
            'score' => ['min' => 0, 'max' => $max, 'scaled' => $max ? $raw / $max : 0, 'raw' => $raw],
        ]);
    }

    /**
     * The mark and the maximum mark of the student.
     *
     * @return float[]
     */
    private function marks(): array {
        $element = editor::get_element('h5p', $this->cd->cmid, $this->attempt);
        $instance = $element->get_instance($this->h5p->id);
        return [$element->get_mark($instance), $element->get_max_mark($instance)];
    }

    public function test_result_in_a_hidden_chapter_is_refused(): void {
        global $DB;
        $DB->set_field('cdelement_chapter', 'visible', 0, ['id' => $this->chapter->id]);
        $this->expectException(\dml_missing_record_exception::class);
        $this->store(5, 10);
    }

    public function test_reported_maximum_without_a_recorded_one(): void {
        $this->store(5, 10);
        $this->assertEquals([5, 10], $this->marks());
    }

    public function test_the_recorded_maximum_counts(): void {
        global $DB;
        $DB->set_field('cdelement_h5p', 'maxscore', 10, ['id' => $this->h5p->id]);
        // An inflated maximum does not give the element more weight, the share of it counts.
        $this->store(500, 1000);
        $this->assertEquals([5, 10], $this->marks());
        // Never more than the recorded maximum.
        $this->store(1000, 1000);
        $this->assertEquals([10, 10], $this->marks());
    }
}

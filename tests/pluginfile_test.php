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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/contentdesigner/lib.php');

/**
 * Tests of the file serving of the activity: files of hidden content are not served to learners.
 *
 * Serving a file ends the script (send_stored_file), so only refusals can be tested here: every test stores the file,
 * so a missing refusal would send it instead of returning false.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('contentdesigner_pluginfile')]
final class pluginfile_test extends \advanced_testcase {
    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var \stdClass The activity. */
    private \stdClass $cd;

    /** @var \stdClass The chapter. */
    private \stdClass $chapter;

    /** @var \stdClass The rich text element with a background image. */
    private \stdClass $richtext;

    /** @var \stdClass A student. */
    private \stdClass $student;

    /**
     * An activity with a rich text element that has a background image.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $this->course->id]);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $this->chapter = $gen->create_chapter($this->cd);
        $this->richtext = $gen->create_element(
            $this->cd,
            'richtext',
            ['content' => 'Text', 'contentformat' => FORMAT_HTML],
            $this->chapter
        );
        get_file_storage()->create_file_from_string([
            'contextid' => \context_module::instance($this->cd->cmid)->id,
            'component' => 'mod_contentdesigner',
            'filearea' => 'richtextelementbg',
            'itemid' => $this->richtext->id,
            'filepath' => '/',
            'filename' => 'background.png',
        ], 'image');
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
    }

    /**
     * Calls the file serving for the background image.
     *
     * @return mixed false when refused
     */
    private function serve() {
        [$course, $cm] = get_course_and_cm_from_cmid($this->cd->cmid, 'contentdesigner');
        return contentdesigner_pluginfile(
            $course,
            $cm,
            \context_module::instance($cm->id),
            'richtextelementbg',
            [$this->richtext->id, 'background.png'],
            false
        );
    }

    /**
     * A learner gets no files of a hidden element.
     */
    public function test_hidden_element_is_refused_to_learners(): void {
        global $DB;
        $DB->set_field('cdelement_richtext', 'visible', 0, ['id' => $this->richtext->id]);
        $this->setUser($this->student);
        $this->assertFalse($this->serve());
    }

    /**
     * A learner gets no files of an element in a hidden chapter.
     */
    public function test_element_in_hidden_chapter_is_refused_to_learners(): void {
        global $DB;
        $DB->set_field('cdelement_chapter', 'visible', 0, ['id' => $this->chapter->id]);
        $this->setUser($this->student);
        $this->assertFalse($this->serve());
    }

    /**
     * Files of elements of another activity are refused.
     */
    public function test_element_of_another_activity_is_refused(): void {
        $other = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $this->course->id]);
        [$course, $cm] = get_course_and_cm_from_cmid($other->cmid, 'contentdesigner');
        // As admin, so that only the binding of the item to the activity refuses.
        $this->setAdminUser();
        $this->assertFalse(contentdesigner_pluginfile(
            $course,
            $cm,
            \context_module::instance($cm->id),
            'richtextelementbg',
            [$this->richtext->id, 'background.png'],
            false
        ));
    }
}

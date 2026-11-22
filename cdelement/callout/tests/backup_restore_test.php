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

namespace cdelement_callout;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Tests of the backup and restore of callouts and the files of their content.
 *
 * @package    cdelement_callout
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\backup_cdelement_callout_subplugin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_cdelement_callout_subplugin::class)]
final class backup_restore_test extends \advanced_testcase {
    /**
     * Duplicates a course by backup and restore.
     *
     * @param \stdClass $course The course.
     * @return int The id of the new course.
     */
    private function duplicate_course(\stdClass $course): int {
        global $CFG, $USER;
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(false);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $bc->destroy();
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $CFG->tempdir . '/backup/' . $backupid);

        $newcourseid = \restore_dbops::create_new_course(
            $course->fullname . ' 2',
            $course->shortname . '_' . $backupid,
            $course->category
        );
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('users')->set_value(false);
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();
        return $newcourseid;
    }

    public function test_restore_callout_with_files(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        $chapter = $gen->create_chapter($cd);
        $callout = $gen->create_element($cd, 'callout', [
            'title' => 'First callout',
            'callouttype' => 'definition',
            'callouttitle' => 'Term',
            'content' => '<p>Meaning <img src="@@PLUGINFILE@@/pic.png" alt="Pic"></p>',
            'contentformat' => FORMAT_HTML,
        ], $chapter);
        // A type that is not known, as in a manipulated backup.
        $gen->create_element($cd, 'callout', [
            'title' => 'Second callout',
            'callouttype' => 'unknown',
            'content' => '<p>Other</p>',
            'contentformat' => FORMAT_HTML,
        ], $chapter);
        get_file_storage()->create_file_from_string([
            'contextid' => \context_module::instance($cd->cmid)->id,
            'component' => 'mod_contentdesigner',
            'filearea' => 'cdelement_callout_content',
            'itemid' => $callout->id,
            'filepath' => '/',
            'filename' => 'pic.png',
        ], 'image');

        $newcd = $DB->get_record('contentdesigner', ['course' => $this->duplicate_course($course)], '*', MUST_EXIST);
        $newcm = get_coursemodule_from_instance('contentdesigner', $newcd->id, 0, false, MUST_EXIST);
        $newcontext = \context_module::instance($newcm->id);

        $new = $DB->get_record(
            'cdelement_callout',
            ['contentdesignerid' => $newcd->id, 'title' => 'First callout'],
            '*',
            MUST_EXIST
        );
        $this->assertNotEquals($callout->id, $new->id);
        $this->assertSame('definition', $new->callouttype);
        $this->assertSame('Term', $new->callouttitle);
        $this->assertStringContainsString('@@PLUGINFILE@@/pic.png', $new->content);
        $this->assertEquals(FORMAT_HTML, $new->contentformat);
        // The trust of the restoring user (admin), not of the backup.
        $this->assertEquals(1, $new->contenttrust);
        $this->assertNotFalse(get_file_storage()->get_file(
            $newcontext->id,
            'mod_contentdesigner',
            'cdelement_callout_content',
            $new->id,
            '/',
            'pic.png'
        ));

        $second = $DB->get_record(
            'cdelement_callout',
            ['contentdesignerid' => $newcd->id, 'title' => 'Second callout'],
            '*',
            MUST_EXIST
        );
        $this->assertSame(element::DEFAULT_TYPE, $second->callouttype);

        $calloutelement = $DB->get_field('contentdesigner_elements', 'id', ['shortname' => 'callout'], MUST_EXIST);
        $this->assertEquals(2, $DB->count_records(
            'contentdesigner_content',
            ['contentdesignerid' => $newcd->id, 'element' => $calloutelement]
        ));
    }
}

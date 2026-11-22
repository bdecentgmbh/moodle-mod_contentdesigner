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

namespace cdelement_videotime;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Tests of the backup and restore of Video Time elements.
 *
 * @package    cdelement_videotime
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\backup_cdelement_videotime_subplugin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_cdelement_videotime_subplugin::class)]
final class backup_restore_test extends \advanced_testcase {
    public function test_restore_links_the_restored_videotime(): void {
        if (!\cdelement_videotime\element::videotime_available()) {
            $this->markTestSkipped('Video Time (mod_videotime) is not installed.');
        }
        global $CFG, $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner');
        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        // The content comes before the video in the course.
        $cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        $videotime = $this->getDataGenerator()->create_module('videotime', ['course' => $course->id]);
        $othervideotime = $this->getDataGenerator()->create_module('videotime', ['course' => $othercourse->id]);
        $chapter = $gen->create_chapter($cd);
        $gen->create_element($cd, 'videotime', ['videotimeid' => $videotime->id, 'courseid' => $course->id], $chapter);
        $gen->create_element(
            $cd,
            'videotime',
            ['videotimeid' => $othervideotime->id, 'courseid' => $othercourse->id],
            $chapter
        );

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        // Moodle 4.5 warns that mod_videotime does not declare FEATURE_USES_QUESTIONS; not ours to fix.
        $this->resetDebugging();
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $bc->destroy();
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $CFG->tempdir . '/backup/' . $backupid);
        $newcourseid = \restore_dbops::create_new_course('Copy', 'copy', $course->category);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        $newcd = $DB->get_record('contentdesigner', ['course' => $newcourseid], '*', MUST_EXIST);
        $newvideotime = $DB->get_record('videotime', ['course' => $newcourseid], '*', MUST_EXIST);
        $videotimeids = $DB->get_fieldset_select(
            'cdelement_videotime',
            'videotimeid',
            'contentdesignerid = ? ORDER BY id',
            [$newcd->id]
        );
        // The video of the course is the restored one, the video of the other course stays.
        $this->assertEquals([$newvideotime->id, $othervideotime->id], $videotimeids);
    }
}

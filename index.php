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

/**
 * List of all contentdesingers in course.
 *
 * @package    mod_contentdesigner
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");

$id = required_param('id', PARAM_INT); // Course id.
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course, true);
$PAGE->set_pagelayout('incourse');

// Trigger instances list viewed event.
$event = \mod_contentdesigner\event\course_module_instance_list_viewed::create(
    ['context' => \context_course::instance($course->id)]);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strcontentdesigner         = get_string('modulename', 'contentdesigner');
$strcontentdesigners        = get_string('modulenameplural', 'contentdesigner');
$strname         = get_string('name');
$strintro        = get_string('moduleintro');
$strlastmodified = get_string('lastmodified');

$PAGE->set_url('/mod/contentdesigner/index.php', ['id' => $id]);
$PAGE->set_title($course->shortname.': '.$strcontentdesigners);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add($strcontentdesigners);
echo $OUTPUT->header();
echo $OUTPUT->heading($strcontentdesigners);
if (!$contentdesigners = get_all_instances_in_course('contentdesigner', $course)) {
    notice(get_string('thereareno', 'moodle', $strcontentdesigners), "$CFG->wwwroot/course/view.php?id=$course->id");
    exit;
}

$usesections = course_format_uses_sections($course->format);

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($usesections) {
    $strsectionname = get_string('sectionname', 'format_'.$course->format);
    $table->head  = [$strsectionname, $strname, $strintro];
    $table->align = ['center', 'left', 'left'];
} else {
    $table->head  = [$strlastmodified, $strname, $strintro];
    $table->align = ['left', 'left', 'left'];
}

$modinfo = get_fast_modinfo($course);
$currentsection = '';
foreach ($contentdesigners as $contentdesigner) {
    $cm = $modinfo->cms[$contentdesigner->coursemodule];
    if ($usesections) {
        $printsection = '';
        if ($contentdesigner->section !== $currentsection) {
            if ($contentdesigner->section) {
                $printsection = get_section_name($course, $contentdesigner->section);
            }
            if ($currentsection !== '') {
                $table->data[] = 'hr';
            }
            $currentsection = $contentdesigner->section;
        }
    } else {
        $printsection = '<span class="smallinfo">'.userdate($contentdesigner->timemodified)."</span>";
    }

    $class = $contentdesigner->visible ? '' : 'class="dimmed"'; // Hidden modules are dimmed.

    $table->data[] = [
        $printsection,
        "<a $class href=\"view.php?id=$cm->id\">".format_string($contentdesigner->name)."</a>",
        format_module_intro('contentdesigner', $contentdesigner, $cm->id),
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();

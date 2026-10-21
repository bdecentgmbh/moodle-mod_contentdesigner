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
 * Content Designer elements add / edit instance form.
 *
 * @package    mod_contentdesigner
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/contentdesigner/lib.php');

// Course Module ID.
$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$instanceid = optional_param('instanceid', 0, PARAM_INT);
$element = optional_param('element', '', PARAM_ALPHANUM);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'contentdesigner');
$data = $DB->get_record('contentdesigner', ['id' => $cm->instance], '*', MUST_EXIST);

$PAGE->set_url('/mod/contentdesigner/editor.php', ['id' => $cm->id]);
require_course_login($course, false, $cm);
$context = \context_module::instance($cm->id);
require_capability('mod/contentdesigner:viewcontenteditor', $context);

$PAGE->set_title($course->shortname . ': ' . format_string($data->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($data);
$PAGE->add_body_class('limitedwidth');

$editor = new mod_contentdesigner\editor($cm, $course);

if ($action == 'copy') {
    require_sesskey();
    if ($element == 'chapter') {
        $editor->chapter_duplicate($instanceid);
    } else {
        $editor->duplicate($instanceid, $element);
    }
    redirect($PAGE->url);
}

echo $OUTPUT->header();

echo $editor->display();

$PAGE->requires->js_call_amd(
    'mod_contentdesigner/editor',
    'init',
    [
        'contextid' => $context->id,
        'cmid' => $cm->id,
        'contentdesignerid' => $cm->instance,
    ]
);
echo $OUTPUT->footer();

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
 * Content designer module content view page.
 *
 * @package    mod_contentdesigner
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT); // Course Module ID.
$action = optional_param('action', '', PARAM_ALPHA); // Action of an addon, e.g. starting an attempt.

[$course, $cm] = get_course_and_cm_from_cmid($id, 'contentdesigner');
$contentdesigner = $DB->get_record('contentdesigner', ['id' => $cm->instance], '*', MUST_EXIST);

$PAGE->set_url('/mod/contentdesigner/view.php', ['id' => $cm->id]);
require_course_login($course, false, $cm);
$context = \context_module::instance($cm->id);
require_capability('mod/contentdesigner:view', $context);

$PAGE->set_title($course->shortname . ': ' . format_string($contentdesigner->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($contentdesigner);
$PAGE->add_body_class('limitedwidth');

contentdesigner_view($contentdesigner, $course, $cm, $context);

$PAGE->requires->js_call_amd('mod_contentdesigner/elements', 'animateElements', []);

$display = new \mod_contentdesigner\content_display($contentdesigner, $cm, $course, $context);
$display->view($action);

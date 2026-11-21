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
 * Library of functions of the Content Designer activity.
 *
 * @package    mod_contentdesigner
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_contentdesigner\editor;
use mod_contentdesigner\hook\after_instance_saved;
use mod_contentdesigner\hook\before_instance_deleted;
use mod_contentdesigner\hook\extend_settings_navigation;
use mod_contentdesigner\hook\user_grades;
use mod_contentdesigner\local\access;
use mod_contentdesigner\local\attempts;
use mod_contentdesigner\local\extensions;
use mod_contentdesigner\local\pages;

/**
 * Chapter element shortname.
 */
define('CONTENTDESIGNER_CHAPTER', 'chapter');

/**
 * Add contentdesigner instance.
 *
 * @param stdClass $data
 * @param mod_contentdesigner_mod_form $mform
 * @return int instance id
 */
function contentdesigner_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = time();
    contentdesigner_prepare_completion_rules($data);
    $data->id = $DB->insert_record('contentdesigner', $data);

    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event(
        $data->coursemodule,
        'contentdesigner',
        $data->id,
        $completiontimeexpected
    );

    extensions::dispatch(new after_instance_saved($data, true));
    return $data->id;
}

/**
 * Update contentdesigner instance.
 *
 * @param stdClass $data
 * @param mod_contentdesigner_mod_form $mform
 * @return bool true
 */
function contentdesigner_update_instance($data, $mform) {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();
    contentdesigner_prepare_completion_rules($data);
    $DB->update_record('contentdesigner', $data);

    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event(
        $data->coursemodule,
        'contentdesigner',
        $data->id,
        $completiontimeexpected
    );

    extensions::dispatch(new after_instance_saved($data, false));
    return true;
}

/**
 * Sets the custom completion rules that the form did not submit (unchecked boxes) to disabled.
 *
 * @param stdClass $data The submitted data.
 */
function contentdesigner_prepare_completion_rules(stdClass $data): void {
    foreach (array_keys(extensions::completion_rules()) as $rule) {
        $data->$rule = empty($data->$rule) ? 0 : 1;
    }
}

/**
 * Delete contentdesigner instance and all its data.
 *
 * @param int $id Instance id.
 * @return bool
 */
function contentdesigner_delete_instance($id) {
    global $DB;

    if (!$record = $DB->get_record('contentdesigner', ['id' => $id])) {
        return false;
    }
    $cm = get_coursemodule_from_instance('contentdesigner', $record->id, $record->course, false, MUST_EXIST);

    extensions::dispatch(new before_instance_deleted($record));

    \core_completion\api::update_completion_date_event($cm->id, 'contentdesigner', $record->id, null);

    // The elements delete their instances with their files and user data.
    foreach (editor::get_element_names() as $elementname) {
        $element = editor::get_element($elementname, $cm->id);
        if (!$element->is_table_exists()) {
            continue;
        }
        foreach ($DB->get_fieldset_select($element->tablename(), 'id', 'contentdesignerid = ?', [$record->id]) as $instanceid) {
            $element->delete_element($instanceid);
        }
        $element->reset_userdata($record->id);
    }

    $DB->delete_records('contentdesigner_content', ['contentdesignerid' => $record->id]);
    $DB->delete_records('contentdesigner_completion', ['contentdesignerid' => $record->id]);
    $DB->delete_records('contentdesigner_attempts', ['contentdesignerid' => $record->id]);
    $DB->delete_records('contentdesigner', ['id' => $record->id]);

    contentdesigner_grade_item_delete($record);
    return true;
}

/**
 * List of features supported in contentdesigner module.
 *
 * Grades and custom completion rules are features of addons, see {@see extensions::supports()}.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know or string for the module purpose.
 */
function contentdesigner_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_INTRO:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return !empty(extensions::completion_rules());
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_GRADE_OUTCOMES:
            return extensions::supports($feature);
        default:
            return null;
    }
}

/**
 * Create or update the grade item of an activity.
 *
 * @param stdClass $contentdesigner The activity record, with cmidnumber when available.
 * @param mixed $grades Optional array/object of grade(s); 'reset' means reset grades in gradebook.
 * @return int 0 if ok, error code otherwise
 */
function contentdesigner_grade_item_update($contentdesigner, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = ['itemname' => $contentdesigner->name];
    if (isset($contentdesigner->cmidnumber)) {
        $params['idnumber'] = $contentdesigner->cmidnumber;
    }

    if ($contentdesigner->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = $contentdesigner->grade;
        $params['grademin'] = 0;
    } else if ($contentdesigner->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid'] = -$contentdesigner->grade;
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update(
        'mod/contentdesigner',
        $contentdesigner->course,
        'mod',
        'contentdesigner',
        $contentdesigner->id,
        0,
        $grades,
        $params
    );
}

/**
 * Delete the grade item of an activity.
 *
 * @param stdClass $contentdesigner The activity record.
 * @return int
 */
function contentdesigner_grade_item_delete($contentdesigner) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update(
        'mod/contentdesigner',
        $contentdesigner->course,
        'mod',
        'contentdesigner',
        $contentdesigner->id,
        0,
        null,
        ['deleted' => 1]
    );
}

/**
 * Update the gradebook grades of an activity, for one user or for all of them.
 *
 * The grades come from the addon that grades the activity (hook {@see user_grades}).
 *
 * @param stdClass $contentdesigner The activity record.
 * @param int $userid The user, 0 for all users.
 * @param bool $nullifnone Set the grade to null if the user has none.
 */
function contentdesigner_update_grades($contentdesigner, $userid = 0, $nullifnone = true) {
    if (!extensions::supports(FEATURE_GRADE_HAS_GRADE)) {
        return;
    }
    $grades = extensions::dispatch(new user_grades($contentdesigner, (int) $userid))->get_grades();
    if ($grades) {
        contentdesigner_grade_item_update($contentdesigner, $grades);
    } else if ($userid && $nullifnone) {
        contentdesigner_grade_item_update($contentdesigner, (object) ['userid' => $userid, 'rawgrade' => null]);
    } else {
        contentdesigner_grade_item_update($contentdesigner);
    }
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $data       data object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 */
function contentdesigner_view($data, $course, $cm, $context) {
    $event = \mod_contentdesigner\event\course_module_viewed::create([
        'context' => $context,
        'objectid' => $data->id,
    ]);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('contentdesigner', $data);
    $event->trigger();

    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param object $event
 * @param object $factory
 * @param int $userid
 * @return object|null
 */
function mod_contentdesigner_core_calendar_provide_event_action($event, $factory, $userid = 0) {
    global $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['contentdesigner'][$event->instance];
    $completion = new \completion_info($cm->get_course());
    $completiondata = $completion->get_data($cm, false, $userid);
    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    return $factory->create_instance(
        get_string('view'),
        new \moodle_url('/mod/contentdesigner/view.php', ['id' => $cm->id]),
        1,
        true
    );
}

/**
 * Adds the elements of the course reset form.
 *
 * @param MoodleQuickForm $mform The form.
 */
function contentdesigner_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'contentdesignerheader', get_string('modulenameplural', 'mod_contentdesigner'));
    $mform->addElement('advcheckbox', 'reset_contentdesigner_progress', get_string('resetprogress', 'mod_contentdesigner'));
}

/**
 * Default values of the course reset form.
 *
 * @param stdClass $course The course.
 * @return array
 */
function contentdesigner_reset_course_form_defaults($course) {
    return ['reset_contentdesigner_progress' => 1];
}

/**
 * Deletes the progress of all learners (attempts, completions, responses) when a course is reset.
 *
 * @param stdClass $data the data submitted from the reset course.
 * @return array status array
 */
function contentdesigner_reset_userdata($data) {
    global $DB;

    $status = [];
    if (empty($data->reset_contentdesigner_progress)) {
        return $status;
    }

    $instances = $DB->get_records('contentdesigner', ['course' => $data->courseid]);
    foreach ($instances as $instance) {
        $cm = get_coursemodule_from_instance('contentdesigner', $instance->id, $data->courseid);
        if (!$cm) {
            continue;
        }
        foreach (editor::get_element_names() as $elementname) {
            editor::get_element($elementname, $cm->id)->reset_userdata($instance->id);
        }
        $DB->delete_records('contentdesigner_completion', ['contentdesignerid' => $instance->id]);
        $DB->delete_records('contentdesigner_attempts', ['contentdesignerid' => $instance->id]);
        if (extensions::supports(FEATURE_GRADE_HAS_GRADE) && empty($data->reset_gradebook_grades)) {
            contentdesigner_grade_item_update($instance, 'reset');
        }
    }

    $status[] = [
        'component' => get_string('modulenameplural', 'mod_contentdesigner'),
        'item' => get_string('resetprogress', 'mod_contentdesigner'),
        'error' => false,
    ];
    return $status;
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * @return array
 */
function contentdesigner_get_view_actions() {
    return ['view', 'view all'];
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * @return array
 */
function contentdesigner_get_post_actions() {
    return ['update', 'add'];
}

/**
 * Get the names of the installed element plugins.
 *
 * @return string[] Element shortnames.
 */
function contentdesigner_get_element_pluginnames() {
    return editor::get_element_names();
}

/**
 * Fragment: the list of elements to insert, for the editor.
 *
 * @param array $args The fragment arguments, with the validated module context.
 * @return string
 */
function contentdesigner_output_fragment_get_elements_list($args) {
    [, $cm] = access::from_context($args['context'], access::EDIT);
    return editor::get_elements_list($cm->id);
}

/**
 * Fragment: the content of the activity for the learner, when the content is refreshed.
 *
 * @param array $args The fragment arguments, with the validated module context, the attempt id and the page shown
 *     (paged display).
 * @return string
 */
function contentdesigner_output_fragment_load_elements($args) {
    global $USER;
    [$course, $cm] = access::from_context($args['context'], access::VIEW);
    $attempt = attempts::get_for_render($cm->instance, $USER->id, (int) ($args['attemptid'] ?? 0));

    $editor = new editor($cm, $course, $attempt);
    $editor->initiate_js();
    return $editor->render_elements(false, (int) ($args['page'] ?? 0));
}

/**
 * Fragment: the chapters after a completed chapter, for the learner (scrolling display).
 *
 * The chapters that the learner can reach are computed from the start of the content, so the chapters after the
 * given one are returned only if the learner can get past it. Paged activities load their pages instead.
 *
 * @param array $args The fragment arguments, with the validated module context, the chapter and the attempt id.
 * @return string The HTML of the content with the chapters after the given chapter, empty if there are none.
 */
function contentdesigner_output_fragment_load_next_chapters($args) {
    global $DB, $USER;
    [$course, $cm] = access::from_context($args['context'], access::VIEW);
    $chapter = access::get_chapter((int) $args['chapter'], $cm->instance);
    $instance = $DB->get_record('contentdesigner', ['id' => $cm->instance], '*', MUST_EXIST);
    if (pages::is_paged($instance)) {
        return '';
    }
    $attempt = attempts::get_for_render($cm->instance, $USER->id, (int) ($args['attemptid'] ?? 0));

    $editor = new editor($cm, $course, $attempt);
    // The learner gets past the chapter if the content shows it and it does not hide the content after it.
    foreach ($editor->chapter->get_chapters_data(true, true, false, false) as $data) {
        if ($data['instancedata']->id == $chapter->id && !$data['prevent'] && !$data['chapterprevent']) {
            return $editor->render_elements($chapter->id);
        }
    }
    return '';
}

/**
 * Fragment: the content of the editor, rendered again after a change (see the web services in db/services.php).
 *
 * @param array $args The fragment arguments, with the validated module context.
 * @return string
 */
function contentdesigner_output_fragment_editor_content($args) {
    [$course, $cm] = access::from_context($args['context'], access::EDIT);
    return (new editor($cm, $course))->display();
}

/**
 * Update the edited title of the elements in the editor page to the respected elements instance.
 *
 * @param string $itemtype instance_title[<element shortname>][<instance id>]
 * @param int $itemid Id of the element
 * @param string $itemvalue Updated value
 * @return \core\output\inplace_editable
 */
function mod_contentdesigner_inplace_editable($itemtype, $itemid, $itemvalue) {
    global $DB;

    if (!preg_match('/^instance_title\[([a-z0-9]+)\]\[(\d+)\]$/', $itemtype, $matches)) {
        throw new moodle_exception('invalidparameter', 'debug');
    }
    [, $elementname, $instanceid] = $matches;
    if (!in_array($elementname, editor::get_element_names(), true)) {
        throw new moodle_exception('elementnotfound', 'mod_contentdesigner');
    }
    $contentdesignerid = $DB->get_field('cdelement_' . $elementname, 'contentdesignerid', ['id' => $instanceid], MUST_EXIST);
    $cm = get_coursemodule_from_instance('contentdesigner', $contentdesignerid, 0, false, MUST_EXIST);
    $context = context_module::instance($cm->id);
    \core_external\external_api::validate_context($context);
    require_capability(access::EDIT, $context);

    $element = editor::get_element($elementname, $cm->id);
    // The title columns of the elements hold 255 characters.
    $title = core_text::substr(clean_param($itemvalue, PARAM_TEXT), 0, 255);
    $DB->update_record($element->tablename(), (object) ['id' => $instanceid, 'title' => $title, 'timemodified' => time()]);
    $element->touch_options((int) $instanceid);

    $record = $DB->get_record($element->tablename(), ['id' => $instanceid], '*', MUST_EXIST);
    return $element->get_title_editable($record);
}

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $node The node to add module settings to
 */
function contentdesigner_extend_settings_navigation(settings_navigation $settings, navigation_node $node) {
    $cm = $settings->get_page()->cm;
    if (!$cm) {
        return;
    }
    if (has_capability(access::EDIT, $cm->context)) {
        $url = new moodle_url('/mod/contentdesigner/editor.php', ['id' => $cm->id]);
        $node->add(
            get_string('contenteditor', 'mod_contentdesigner'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'editorelement'
        );
    }
    extensions::dispatch(new extend_settings_navigation($node, $cm));
}

/**
 * Serves the files of the activity and its elements.
 *
 * @param stdClass $course The course.
 * @param stdClass $cm The course module.
 * @param context $context Context used in the file.
 * @param string $filearea Filearea the file stored
 * @param array $args Arguments
 * @param bool $forcedownload Force download the file instead of display.
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - just send the file
 */
function contentdesigner_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }
    require_course_login($course, true, $cm);
    require_capability(access::VIEW, $context);

    // Background images of the elements (<shortname>elementbg) and the file areas of the elements
    // (cdelement_<shortname>_<area>, for files stored in mod_contentdesigner). The item id is an instance id.
    $element = null;
    foreach (editor::get_element_names() as $elementname) {
        $areas = [$elementname . 'elementbg', $elementname . 'description'];
        $elementobj = editor::get_element($elementname, $cm->id);
        if (method_exists($elementobj, 'areafiles')) {
            foreach ($elementobj->areafiles() as $area) {
                $areas[] = 'cdelement_' . $elementname . '_' . $area;
            }
        }
        if (in_array($filearea, $areas, true)) {
            $element = $elementobj;
            break;
        }
    }
    if (!$element || count($args) < 2) {
        return false;
    }

    $itemid = (int) array_shift($args);
    $instance = $DB->get_record($element->tablename(), ['id' => $itemid, 'contentdesignerid' => $cm->instance]);
    if (!$instance) {
        return false;
    }
    // Files of hidden elements, and of elements in hidden chapters, are served to editors of the content only.
    if (!has_capability(access::EDIT, $context)) {
        if (isset($instance->visible) && !$instance->visible) {
            return false;
        }
        $chapterid = $DB->get_field('contentdesigner_content', 'chapter', ['element' => $element->elementid,
            'instance' => $itemid]);
        if ($chapterid && !$DB->record_exists('cdelement_chapter', ['id' => $chapterid, 'visible' => 1])) {
            return false;
        }
    }

    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $file = get_file_storage()->get_file($context->id, 'mod_contentdesigner', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, null, 0, $forcedownload, $options);
}

/**
 * Add a get_coursemodule_info function in case any pulse type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses will know about.
 */
function contentdesigner_get_coursemodule_info($coursemodule) {
    global $DB;

    if (!$contentdesigner = $DB->get_record('contentdesigner', ['id' => $coursemodule->instance])) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $contentdesigner->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $result->content = format_module_intro('contentdesigner', $contentdesigner, $coursemodule->id, false);
    }

    // Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        foreach (array_keys(extensions::completion_rules()) as $rule) {
            if (property_exists($contentdesigner, $rule)) {
                $result->customdata['customcompletionrules'][$rule] = $contentdesigner->$rule;
            }
        }
    }
    return $result;
}

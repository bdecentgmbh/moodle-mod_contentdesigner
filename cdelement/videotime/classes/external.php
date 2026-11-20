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
 * Videotime element external werbservice deifintion to manage the videotime completion.
 *
 * @package   cdelement_videotime
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace cdelement_videotime;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_contentdesigner\editor;
use mod_contentdesigner\local\access;

/**
 * Web services of the Video Time element: the activity selector of the form and the completion of the video.
 */
class external extends external_api {
    /**
     * Parameters of the method to get the Video Time activities of a course.
     *
     * @return external_function_parameters
     */
    public static function get_instance_menu_parameters() {
        return new external_function_parameters(
            [
                'query' => new external_value(PARAM_TEXT, 'Query string', VALUE_DEFAULT, ''),
                'cmid' => new external_value(PARAM_INT, 'Course module id of the Content Designer activity'),
                'courseid' => new external_value(PARAM_INT, 'Course id of the Video Time activities'),
                'limitfrom' => new external_value(PARAM_INT, 'limitfrom we are fetching the records from', VALUE_DEFAULT, 0),
                'limitnum' => new external_value(PARAM_INT, 'Number of records to fetch', VALUE_DEFAULT, 100),
            ]
        );
    }

    /**
     * The Video Time activities of a course the user may embed, for a user who edits the activity.
     *
     * @param string $query Only activities whose name contains this text.
     * @param int $cmid Course module id of the Content Designer activity.
     * @param int $courseid The course of the Video Time activities.
     * @param int $limitfrom Not used.
     * @param int $limitnum Number of records to fetch.
     * @return array
     */
    public static function get_instance_menu($query, $cmid, $courseid, $limitfrom = 0, $limitnum = 100) {
        global $DB;

        $params = self::validate_parameters(self::get_instance_menu_parameters(), [
            'query' => $query,
            'cmid' => $cmid,
            'courseid' => $courseid,
            'limitfrom' => $limitfrom,
            'limitnum' => $limitnum,
        ]);
        [, $cm] = access::from_cmid($params['cmid'], access::EDIT);
        $element = editor::get_element('videotime', $cm->id);
        if (!$element->is_available()) {
            throw new \moodle_exception('elementnotavailable', 'mod_contentdesigner');
        }
        if (!$params['courseid'] || !$element->can_use_course($params['courseid'])) {
            throw new \moodle_exception('invalidvideotime', 'cdelement_videotime');
        }

        $select = 'course = :course';
        $sqlparams = ['course' => $params['courseid']];
        if ($params['query'] !== '') {
            $select .= ' AND ' . $DB->sql_like('name', ':query', false);
            $sqlparams['query'] = '%' . $DB->sql_like_escape($params['query']) . '%';
        }
        $context = \context_course::instance($params['courseid']);
        $list = [];
        foreach (
            $DB->get_records_select_menu(
                'videotime',
                $select,
                $sqlparams,
                'name, id',
                'id, name',
                0,
                max(0, $params['limitnum'])
            ) as $id => $name
        ) {
            $list[] = ['id' => $id, 'name' => format_string($name, true, ['context' => $context])];
        }
        return $list;
    }

    /**
     * Returns the list of instances menu.
     *
     * @return external_multiple_structure
     */
    public static function get_instance_menu_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                [
                    'id' => new external_value(PARAM_INT, 'Video time ID'),
                    'name' => new external_value(PARAM_RAW, 'Video time name, formatted'),
                ]
            )
        );
    }

    /**
     * Parameters of the method to verify the completion of the video of an element.
     *
     * @return external_function_parameters
     */
    public static function verify_completion_parameters() {
        return new external_function_parameters(
            [
                'cmid' => new external_value(PARAM_INT, 'Course module id of the Content Designer activity'),
                'instanceid' => new external_value(PARAM_INT, 'Video Time element instance id'),
            ]
        );
    }

    /**
     * Whether the current user completed the Video Time activity of a visible element of the activity.
     *
     * @param int $cmid Course module id of the Content Designer activity.
     * @param int $instanceid Instance id of the element.
     * @return bool
     */
    public static function verify_completion($cmid, $instanceid) {
        global $USER;

        $params = self::validate_parameters(self::verify_completion_parameters(), [
            'cmid' => $cmid,
            'instanceid' => $instanceid,
        ]);
        [, $cm] = access::from_cmid($params['cmid'], access::VIEW);
        $element = editor::get_element('videotime', $cm->id);
        $instance = access::get_element_instance($element, $params['instanceid'], $cm->instance, true);

        $completed = (bool) $element->is_videotime_completed($instance, $USER->id);
        if ($completed) {
            $element->progress_updated($USER->id);
        }
        return $completed;
    }

    /**
     * Result the status of videotime completion
     *
     * @return external_value True if data updated otherwise  returns false.
     */
    public static function verify_completion_returns() {
        return new external_value(PARAM_BOOL, 'Result of videotime completion');
    }
}

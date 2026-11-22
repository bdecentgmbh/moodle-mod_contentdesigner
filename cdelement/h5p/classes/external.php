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
 * Chapter element external werbservice deifintion to manage the chapter completion.
 *
 * @package   cdelement_h5p
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace cdelement_h5p;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_contentdesigner\editor;
use mod_contentdesigner\local\access;

/**
 * Web services of the H5P element: the results of the learners.
 */
class external extends external_api {
    /**
     * Paramters definition for the methos update chapter progress of user.
     *
     * @return external_function_parameters
     */
    public static function store_result_data_parameters() {
        return new external_function_parameters(
            [
                'cmid' => new external_value(PARAM_INT, 'Course module id'),
                'instanceid' => new external_value(PARAM_INT, 'H5P element instance id'),
                'cdattemptid' => new external_value(PARAM_INT, 'Content designer attempt id'),
                'result' => new external_single_structure(
                    [
                        'completion' => new external_value(PARAM_BOOL, 'Whether the H5P content is completed'),
                        'success' => new external_value(PARAM_BOOL, 'Whether the H5P content is passed'),
                        'response' => new external_value(PARAM_TEXT, 'Response of the user attempt', VALUE_OPTIONAL),
                        'duration' => new external_value(PARAM_TEXT, 'Duration of the user attempt', VALUE_OPTIONAL),
                        'score' => new external_single_structure(
                            [
                                'max' => new external_value(PARAM_FLOAT, 'Maximum score'),
                                'min' => new external_value(PARAM_FLOAT, 'Minimum score'),
                                'scaled' => new external_value(PARAM_FLOAT, 'Scaled score'),
                                'raw' => new external_value(PARAM_FLOAT, 'Score'),
                            ]
                        ),
                    ]
                ),
            ]
        );
    }

    /**
     * Store the result of the current user in an H5P element, in an unfinished attempt of the user. Guests cannot
     * store results.
     *
     * @param int $cmid Course module id.
     * @param int $instanceid Instance id of the H5P.
     * @param int $cdattemptid Content designer attempt id.
     * @param array $result Result object form the H5P data statement.
     * @return bool
     */
    public static function store_result_data($cmid, $instanceid, $cdattemptid, $result) {
        global $DB, $USER;

        $params = self::validate_parameters(
            self::store_result_data_parameters(),
            ['cmid' => $cmid, 'instanceid' => $instanceid, 'cdattemptid' => $cdattemptid, 'result' => $result]
        );
        [, $cm] = access::from_cmid($params['cmid'], access::VIEW);
        access::require_not_guest();
        $element = editor::get_element('h5p', $cm->id);
        $instance = access::get_element_instance($element, $params['instanceid'], $cm->instance, true);
        $attempt = access::get_attempt($params['cdattemptid'], $cm->instance, $USER->id);
        if ($attempt->timefinish) {
            throw new \moodle_exception('attemptfinished', 'cdelement_h5p');
        }
        access::require_reachable($element, $instance->id, $cm, $attempt);

        $result = $params['result'];
        $score = max(0, min((float) $result['score']['raw'], (float) $result['score']['max']));
        $conditions = ['instance' => $instance->id, 'cdattemptid' => $attempt->id, 'userid' => $USER->id];
        $record = $DB->get_record('cdelement_h5p_completion', $conditions);
        // Store only highest scored results only.
        if ($record && $record->score > $score) {
            return true;
        }

        $data = (object) array_merge($conditions, [
            'completion' => $result['completion'] ? 1 : 0,
            'success' => $result['success'] ? 1 : 0,
            'duration' => $result['duration'] ?? '',
            'score' => $score,
            'scoredata' => json_encode(['min' => (float) $result['score']['min'], 'max' => (float) $result['score']['max'],
                'raw' => $score, 'scaled' => (float) $result['score']['scaled']]),
            'response' => $result['response'] ?? '',
            'timemodified' => time(),
        ]);
        if ($record) {
            $data->id = $record->id;
            $DB->update_record('cdelement_h5p_completion', $data);
        } else {
            $data->timecreated = time();
            $DB->insert_record('cdelement_h5p_completion', $data);
        }

        $element->progress_updated($USER->id);
        return true;
    }

    /**
     * Returns the updated result of store data.
     *
     * @return external_value True if data updated otherwise  returns false.
     */
    public static function store_result_data_returns() {
        return new external_value(PARAM_BOOL, 'Result of stored user response');
    }

    /**
     * Paramters definition for the methos update chapter progress of user.
     *
     * @return external_function_parameters
     */
    public static function store_max_score_parameters() {
        return new external_function_parameters(
            [
                'cmid' => new external_value(PARAM_INT, 'Course module id'),
                'instanceid' => new external_value(PARAM_INT, 'H5P element instance id'),
                'maxscore' => new external_value(PARAM_FLOAT, 'Max number of score'),
            ]
        );
    }

    /**
     * Stores the maximum score of an H5P element, reported by the player of a user who edits the content.
     *
     * Learners' results carry their own maximum score; this one counts for learners who have not answered yet.
     *
     * @param int $cmid Course module id.
     * @param int $instanceid Instance id of the H5P.
     * @param float $maxscore Max score of the H5P.
     * @return bool
     */
    public static function store_max_score($cmid, $instanceid, $maxscore) {
        global $DB;

        $params = self::validate_parameters(
            self::store_max_score_parameters(),
            ['cmid' => $cmid, 'instanceid' => $instanceid, 'maxscore' => $maxscore]
        );
        [, $cm] = access::from_cmid($params['cmid'], access::EDIT);
        $element = editor::get_element('h5p', $cm->id);
        $instance = access::get_element_instance($element, $params['instanceid'], $cm->instance);
        if ($params['maxscore'] >= 0 && $instance->maxscore != $params['maxscore']) {
            $DB->set_field('cdelement_h5p', 'maxscore', $params['maxscore'], ['id' => $instance->id]);
        }
        return true;
    }

    /**
     * Returns the updated result of store data.
     *
     * @return external_value True if data updated otherwise  returns false.
     */
    public static function store_max_score_returns() {
        return new external_value(PARAM_BOOL, 'Result of stored user response');
    }
}

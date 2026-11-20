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
 * @package    cdelement_chapter
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace cdelement_chapter;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use mod_contentdesigner\editor;
use mod_contentdesigner\local\access;

/**
 * Chapter external service methods.
 */
class external extends external_api {
    /**
     * Paramters definition for the methos update chapter progress of user.
     *
     * @return external_function_parameters
     */
    public static function update_completion_parameters() {
        return new external_function_parameters(
            [
                'cmid' => new external_value(PARAM_INT, 'Course module id'),
                'chapter' => new external_value(PARAM_INT, 'Chapter element instance id'),
            ]
        );
    }

    /**
     * Marks a chapter of the activity as completed by the current user. Guests cannot save progress.
     *
     * @param int $cmid Coursemodule id.
     * @param int $chapter Chapter id.
     * @return bool true if everything updated fine, false if not.
     */
    public static function update_completion($cmid, $chapter) {
        global $DB, $USER;

        $params = self::validate_parameters(
            self::update_completion_parameters(),
            ['cmid' => $cmid, 'chapter' => $chapter]
        );

        [, $cm] = access::from_cmid($params['cmid'], access::VIEW);
        access::require_not_guest();
        $chapter = access::get_chapter($params['chapter'], $cm->instance);
        if (!$chapter->visible) {
            throw new \moodle_exception('invalidrecord', 'error');
        }

        $record = $DB->get_record('cdelement_chapter_completion', ['instance' => $chapter->id, 'userid' => $USER->id]);
        $data = (object) [
            'instance' => $chapter->id,
            'userid' => $USER->id,
            'completion' => 1,
            'timemodified' => time(),
        ];
        if ($record) {
            $data->id = $record->id;
            $DB->update_record('cdelement_chapter_completion', $data);
        } else {
            $data->timecreated = time();
            $DB->insert_record('cdelement_chapter_completion', $data);
        }

        editor::get_element('chapter', $cm->id)->progress_updated($USER->id);
        return true;
    }

    /**
     * Returns the updated result of module completion.
     *
     * @return external_value True if state updated otherwise  returns false.s
     */
    public static function update_completion_returns() {
        return new external_value(PARAM_BOOL, 'Result of stored user response');
    }
}

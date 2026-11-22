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

namespace mod_contentdesigner\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use mod_contentdesigner\editor;
use mod_contentdesigner\local\access;

/**
 * Web service of the editor: sets the elements of a chapter of the activity, in their order (moving elements within
 * a chapter or into it from another chapter).
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class move_element extends external_api {
    /**
     * The parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the activity'),
            'chapterid' => new external_value(PARAM_INT, 'Id of the chapter'),
            'contents' => new external_value(PARAM_SEQUENCE, 'Ids of the contents of the chapter in their order, comma separated'),
        ]);
    }

    /**
     * Sets the elements of a chapter of the activity, in their order.
     *
     * @param int $cmid Course module id of the activity.
     * @param int $chapterid Id of the chapter.
     * @param string $contents Ids of contentdesigner_content in their order, comma separated.
     * @return bool
     */
    public static function execute(int $cmid, int $chapterid, string $contents): bool {
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'chapterid' => $chapterid, 'contents' => $contents]
        );
        [, $cm] = access::from_cmid($params['cmid'], access::EDIT);
        $chapter = access::get_chapter($params['chapterid'], $cm->instance);
        $contents = implode(',', array_filter(explode(',', $params['contents'])));
        if (!access::contents_belong_to(explode(',', $contents), $cm->instance)) {
            throw new \moodle_exception('invalidrecord', 'error');
        }
        editor::get_element('chapter', $cm->id)->update_postion($chapter->id, $contents);
        return true;
    }

    /**
     * The result.
     *
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_BOOL, 'Whether the elements were moved');
    }
}

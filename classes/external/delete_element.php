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
use mod_contentdesigner\local\access;

/**
 * Web service of the editor: deletes an element instance of the activity, with its files and the data of its users.
 *
 * Deleting a chapter deletes the elements in it.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_element extends external_api {
    /**
     * The parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the activity'),
            'element' => new external_value(PARAM_ALPHANUM, 'Shortname of the element'),
            'instanceid' => new external_value(PARAM_INT, 'Id of the element instance'),
        ]);
    }

    /**
     * Deletes an element instance of the activity.
     *
     * @param int $cmid Course module id of the activity.
     * @param string $element Shortname of the element.
     * @param int $instanceid Id of the element instance.
     * @return bool
     */
    public static function execute(int $cmid, string $element, int $instanceid): bool {
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'element' => $element, 'instanceid' => $instanceid]
        );
        [, $cm] = access::from_cmid($params['cmid'], access::EDIT);
        $elementobj = access::get_element($params['element'], $cm->id);
        $instance = access::get_element_instance($elementobj, $params['instanceid'], $cm->instance);
        return (bool) $elementobj->delete_element($instance->id);
    }

    /**
     * The result.
     *
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_BOOL, 'Whether the element was deleted');
    }
}

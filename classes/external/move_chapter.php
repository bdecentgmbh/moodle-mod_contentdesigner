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
 * Web service of the editor: sets the order of the chapters of the activity.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class move_chapter extends external_api {
    /**
     * The parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the activity'),
            'chapters' => new external_value(PARAM_SEQUENCE, 'Ids of the chapters in their order, comma separated'),
        ]);
    }

    /**
     * Sets the order of the chapters of the activity.
     *
     * @param int $cmid Course module id of the activity.
     * @param string $chapters Ids of the chapters in their order, comma separated.
     * @return bool
     */
    public static function execute(int $cmid, string $chapters): bool {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'chapters' => $chapters]);
        [, $cm] = access::from_cmid($params['cmid'], access::EDIT);
        $chapters = array_filter(explode(',', $params['chapters']));
        if ($chapters) {
            [$insql, $sqlparams] = $DB->get_in_or_equal($chapters, SQL_PARAMS_NAMED);
            $sqlparams['contentdesignerid'] = $cm->instance;
            $count = $DB->count_records_select(
                'cdelement_chapter',
                "id $insql AND contentdesignerid = :contentdesignerid",
                $sqlparams
            );
            if ($count != count(array_unique($chapters))) {
                throw new \moodle_exception('invalidrecord', 'error');
            }
        }
        editor::get_element('chapter', $cm->id)->move_chapter(implode(',', $chapters));
        return true;
    }

    /**
     * The result.
     *
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_BOOL, 'Whether the chapters were moved');
    }
}

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

namespace cdelement_h5p\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_contentdesigner\privacy\provider as activity_provider;

/**
 * Privacy provider of the H5P element: the results of the learners and the saved state of the H5P content.
 *
 * @package    cdelement_h5p
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \mod_contentdesigner\privacy\contentdesignerelements_provider {
    /**
     * The personal data stored by the element.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('cdelement_h5p_completion', [
            'instance' => 'privacy:metadata:completion:h5pid',
            'userid' => 'privacy:metadata:completion:userid',
            'cdattemptid' => 'privacy:metadata:completion:cdattemptid',
            'completion' => 'privacy:metadata:completion:completion',
            'success' => 'privacy:metadata:completion:success',
            'score' => 'privacy:metadata:completion:score',
            'scoredata' => 'privacy:metadata:completion:scoredata',
            'duration' => 'privacy:metadata:completion:duration',
            'response' => 'privacy:metadata:completion:response',
            'timecreated' => 'privacy:metadata:completion:timecreated',
            'timemodified' => 'privacy:metadata:completion:timemodified',
        ], 'privacy:metadata:h5pcompletion');
        $collection->add_database_table('cdelement_h5p_xapistates', [
            'userid' => 'privacy:metadata:xapistates:userid',
            'stateid' => 'privacy:metadata:xapistates:stateid',
            'statedata' => 'privacy:metadata:xapistates:statedata',
            'registration' => 'privacy:metadata:xapistates:registration',
            'timecreated' => 'privacy:metadata:xapistates:timecreated',
            'timemodified' => 'privacy:metadata:xapistates:timemodified',
        ], 'privacy:metadata:xapistates');
        return $collection;
    }

    /**
     * The SQL of the saved states of the H5P elements of an activity (registration cdelement_h5p-<instance id>).
     *
     * @return string The join condition between {cdelement_h5p} h and {cdelement_h5p_xapistates} xs.
     */
    protected static function state_join(): string {
        global $DB;
        return 'xs.registration = ' . $DB->sql_concat("'cdelement_h5p-'", 'h.id');
    }

    /**
     * Adds the contexts in which the user has results or saved states.
     *
     * @param contextlist $contextlist The list to add the contexts to.
     * @param int $userid The user.
     */
    public static function add_contexts_for_userid(contextlist $contextlist, int $userid): void {
        $params = ['contextlevel' => CONTEXT_MODULE, 'modname' => 'contentdesigner', 'userid' => $userid];
        $base = "SELECT c.id
                   FROM {context} c
                   JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                   JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                   JOIN {cdelement_h5p} h ON h.contentdesignerid = cm.instance";
        $contextlist->add_from_sql(
            $base . ' JOIN {cdelement_h5p_completion} hc ON hc.instance = h.id WHERE hc.userid = :userid',
            $params
        );
        $contextlist->add_from_sql($base . ' JOIN {cdelement_h5p_xapistates} xs ON ' . self::state_join() .
            ' WHERE xs.userid = :userid', $params);
    }

    /**
     * Adds the users who have results or saved states in the context.
     *
     * @param userlist $userlist The userlist, with a module context.
     */
    public static function get_users_in_context(userlist $userlist): void {
        if (!$instanceid = activity_provider::get_instanceid($userlist->get_context())) {
            return;
        }
        $params = ['instanceid' => $instanceid];
        $userlist->add_from_sql('userid', 'SELECT hc.userid FROM {cdelement_h5p_completion} hc
            JOIN {cdelement_h5p} h ON h.id = hc.instance WHERE h.contentdesignerid = :instanceid', $params);
        $userlist->add_from_sql('userid', 'SELECT xs.userid FROM {cdelement_h5p_xapistates} xs
            JOIN {cdelement_h5p} h ON ' . self::state_join() . ' WHERE h.contentdesignerid = :instanceid', $params);
    }

    /**
     * Exports the results and saved states of the user.
     *
     * @param approved_contextlist $contextlist The approved contexts and the user.
     */
    public static function export_element_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$instanceid = activity_provider::get_instanceid($context)) {
                continue;
            }
            $params = ['instanceid' => $instanceid, 'userid' => $userid];
            $results = $DB->get_records_sql('SELECT hc.*, h.title FROM {cdelement_h5p_completion} hc
                JOIN {cdelement_h5p} h ON h.id = hc.instance
                WHERE h.contentdesignerid = :instanceid AND hc.userid = :userid ORDER BY hc.id', $params);
            $states = $DB->get_records_sql('SELECT xs.*, h.title FROM {cdelement_h5p_xapistates} xs
                JOIN {cdelement_h5p} h ON ' . self::state_join() . '
                WHERE h.contentdesignerid = :instanceid AND xs.userid = :userid ORDER BY xs.id', $params);
            if (!$results && !$states) {
                continue;
            }
            $data = (object) [
                'results' => array_values(array_map(fn($result) => [
                    'element' => format_string($result->title ?? '', true, ['context' => $context]),
                    'attempt' => $result->cdattemptid,
                    'completed' => transform::yesno($result->completion),
                    'success' => transform::yesno($result->success),
                    'score' => $result->score,
                    'scoredata' => $result->scoredata,
                    'duration' => $result->duration,
                    'response' => $result->response,
                    'timemodified' => transform::datetime($result->timemodified),
                ], $results)),
                'savedstates' => array_values(array_map(fn($state) => [
                    'element' => format_string($state->title ?? '', true, ['context' => $context]),
                    'stateid' => $state->stateid,
                    'statedata' => $state->statedata,
                    'timemodified' => transform::datetime($state->timemodified ?: $state->timecreated),
                ], $states)),
            ];
            writer::with_context($context)->export_data([get_string('pluginname', 'cdelement_h5p')], $data);
        }
    }

    /**
     * Deletes the results and saved states of the users in the activity of a context.
     *
     * @param int $instanceid The activity instance id.
     * @param array $userids The users, empty for all.
     */
    protected static function delete_data(int $instanceid, array $userids = []): void {
        global $DB;
        $params = [$instanceid];
        $userselect = '';
        if ($userids) {
            [$insql, $userparams] = $DB->get_in_or_equal($userids);
            $userselect = " AND userid $insql";
            $params = array_merge($params, $userparams);
        }
        $DB->delete_records_select(
            'cdelement_h5p_completion',
            'instance IN (SELECT id FROM {cdelement_h5p} WHERE contentdesignerid = ?)' . $userselect,
            $params
        );
        $registration = $DB->sql_concat("'cdelement_h5p-'", 'h.id');
        $DB->delete_records_select(
            'cdelement_h5p_xapistates',
            "registration IN (SELECT $registration FROM {cdelement_h5p} h WHERE h.contentdesignerid = ?)" . $userselect,
            $params
        );
    }

    /**
     * Deletes the data of all users in a context.
     *
     * @param \context_module $context The context.
     */
    public static function delete_data_for_all_users_in_context(\context_module $context): void {
        if ($instanceid = activity_provider::get_instanceid($context)) {
            self::delete_data($instanceid);
        }
    }

    /**
     * Deletes the data of the user in the contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and the user.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($instanceid = activity_provider::get_instanceid($context)) {
                self::delete_data($instanceid, [$contextlist->get_user()->id]);
            }
        }
    }

    /**
     * Deletes the data of the users in the context.
     *
     * @param approved_userlist $userlist The approved users and the context.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $instanceid = activity_provider::get_instanceid($userlist->get_context());
        if ($instanceid && $userlist->get_userids()) {
            self::delete_data($instanceid, $userlist->get_userids());
        }
    }
}

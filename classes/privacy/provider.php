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

namespace mod_contentdesigner\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\manager;

/**
 * Privacy provider of Content Designer: the attempts and the completion state of the learners, plus the data of
 * the elements (subplugins implementing {@see contentdesignerelements_provider}).
 *
 * @package   mod_contentdesigner
 * @copyright 2022, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * The personal data stored by the activity.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('contentdesigner_attempts', [
            'contentdesignerid' => 'privacy:metadata:attempts:contentdesignerid',
            'userid' => 'privacy:metadata:attempts:userid',
            'attempt' => 'privacy:metadata:attempts:attempt',
            'grade' => 'privacy:metadata:attempts:grade',
            'timestart' => 'privacy:metadata:attempts:timestart',
            'timefinish' => 'privacy:metadata:attempts:timefinish',
        ], 'privacy:metadata:attempts');
        $collection->add_database_table('contentdesigner_completion', [
            'contentdesignerid' => 'privacy:metadata:completion:contentdesignerid',
            'userid' => 'privacy:metadata:completion:userid',
            'completion' => 'privacy:metadata:completion:completion',
            'mandatorycompletion' => 'privacy:metadata:completion:mandatorycompletion',
            'starttime' => 'privacy:metadata:completion:starttime',
            'timecreated' => 'privacy:metadata:completion:timecreated',
        ], 'privacy:metadata:contentdesignercompletion');
        $collection->add_subsystem_link('core_grades', [], 'privacy:metadata:core_grades');
        $collection->add_plugintype_link('cdelement', [], 'privacy:metadata:cdelement');
        return $collection;
    }

    /**
     * The contexts in which the user has attempts, a completion state or data of an element.
     *
     * @param int $userid The user.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $params = ['modname' => 'contentdesigner', 'contextlevel' => CONTEXT_MODULE, 'userid' => $userid];
        foreach (['contentdesigner_attempts', 'contentdesigner_completion'] as $table) {
            $sql = "SELECT c.id
                      FROM {context} c
                      JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                      JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                      JOIN {{$table}} t ON t.contentdesignerid = cm.instance
                     WHERE t.userid = :userid";
            $contextlist->add_from_sql($sql, $params);
        }
        manager::plugintype_class_callback(
            'cdelement',
            contentdesignerelements_provider::class,
            'add_contexts_for_userid',
            [$contextlist, $userid]
        );
        return $contextlist;
    }

    /**
     * The users who have data in a context.
     *
     * @param userlist $userlist The userlist with the context.
     */
    public static function get_users_in_context(userlist $userlist) {
        $instanceid = self::get_instanceid($userlist->get_context());
        if (!$instanceid) {
            return;
        }
        $params = ['instanceid' => $instanceid];
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {contentdesigner_attempts} WHERE contentdesignerid = :instanceid',
            $params
        );
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {contentdesigner_completion} WHERE contentdesignerid = :instanceid',
            $params
        );
        manager::plugintype_class_callback(
            'cdelement',
            contentdesignerelements_provider::class,
            'get_users_in_context',
            [$userlist]
        );
    }

    /**
     * Exports the data of the user in the contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and the user.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            $instanceid = self::get_instanceid($context);
            if (!$instanceid) {
                continue;
            }
            $data = helper::get_context_data($context, $user);
            helper::export_context_files($context, $user);

            $attempts = $DB->get_records(
                'contentdesigner_attempts',
                ['contentdesignerid' => $instanceid, 'userid' => $user->id],
                'attempt ASC'
            );
            $data->attempts = array_values(array_map(fn($attempt) => [
                'attempt' => $attempt->attempt,
                'grade' => $attempt->timefinish ? $attempt->grade : null,
                'timestart' => transform::datetime($attempt->timestart),
                'timefinish' => $attempt->timefinish ? transform::datetime($attempt->timefinish) : null,
            ], $attempts));

            $completion = $DB->get_record(
                'contentdesigner_completion',
                ['contentdesignerid' => $instanceid, 'userid' => $user->id],
                '*',
                IGNORE_MULTIPLE
            );
            if ($completion) {
                $data->completion = [
                    'reachedend' => transform::yesno($completion->completion),
                    'completedmandatory' => transform::yesno($completion->mandatorycompletion),
                    'starttime' => $completion->starttime ? transform::datetime($completion->starttime) : null,
                ];
            }
            writer::with_context($context)->export_data([], $data);
        }

        manager::plugintype_class_callback(
            'cdelement',
            contentdesignerelements_provider::class,
            'export_element_user_data',
            [$contextlist]
        );
    }

    /**
     * Deletes the data of all users in a context.
     *
     * @param \context $context The context.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        $instanceid = self::get_instanceid($context);
        if (!$instanceid) {
            return;
        }
        $DB->delete_records('contentdesigner_attempts', ['contentdesignerid' => $instanceid]);
        $DB->delete_records('contentdesigner_completion', ['contentdesignerid' => $instanceid]);
        manager::plugintype_class_callback(
            'cdelement',
            contentdesignerelements_provider::class,
            'delete_data_for_all_users_in_context',
            [$context]
        );
    }

    /**
     * Deletes the data of the user in the contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and the user.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($instanceid = self::get_instanceid($context)) {
                $DB->delete_records('contentdesigner_attempts', ['contentdesignerid' => $instanceid, 'userid' => $userid]);
                $DB->delete_records('contentdesigner_completion', ['contentdesignerid' => $instanceid, 'userid' => $userid]);
            }
        }
        manager::plugintype_class_callback(
            'cdelement',
            contentdesignerelements_provider::class,
            'delete_data_for_user',
            [$contextlist]
        );
    }

    /**
     * Deletes the data of the users in a context.
     *
     * @param approved_userlist $userlist The approved users and the context.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $instanceid = self::get_instanceid($userlist->get_context());
        $userids = $userlist->get_userids();
        if (!$instanceid || !$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['instanceid'] = $instanceid;
        foreach (['contentdesigner_attempts', 'contentdesigner_completion'] as $table) {
            $DB->delete_records_select($table, "contentdesignerid = :instanceid AND userid $insql", $params);
        }
        manager::plugintype_class_callback(
            'cdelement',
            contentdesignerelements_provider::class,
            'delete_data_for_users',
            [$userlist]
        );
    }

    /**
     * The id of the Content Designer activity of a module context.
     *
     * @param \context $context The context.
     * @return int The instance id, 0 if the context is not a Content Designer activity.
     */
    public static function get_instanceid(\context $context): int {
        if ($context->contextlevel != CONTEXT_MODULE) {
            return 0;
        }
        $cm = get_coursemodule_from_id('contentdesigner', $context->instanceid);
        return $cm ? (int) $cm->instance : 0;
    }
}

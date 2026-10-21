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

namespace cdelement_chapter\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_contentdesigner\privacy\provider as activity_provider;

/**
 * Privacy provider of the chapter element: the chapters completed by the learners.
 *
 * @package    cdelement_chapter
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
        $collection->add_database_table('cdelement_chapter_completion', [
            'instance' => 'privacy:metadata:completion:chapterid',
            'userid' => 'privacy:metadata:completion:userid',
            'completion' => 'privacy:metadata:completion:completion',
            'timecreated' => 'privacy:metadata:completion:timecreated',
            'timemodified' => 'privacy:metadata:completion:timemodified',
        ], 'privacy:metadata:chaptercompletion');
        return $collection;
    }

    /**
     * Adds the contexts in which the user completed chapters.
     *
     * @param contextlist $contextlist The list to add the contexts to.
     * @param int $userid The user.
     */
    public static function add_contexts_for_userid(contextlist $contextlist, int $userid): void {
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {cdelement_chapter} ch ON ch.contentdesignerid = cm.instance
                  JOIN {cdelement_chapter_completion} cc ON cc.instance = ch.id
                 WHERE cc.userid = :userid";
        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_MODULE, 'modname' => 'contentdesigner', 'userid' => $userid]);
    }

    /**
     * Adds the users who completed chapters in the context.
     *
     * @param userlist $userlist The userlist, with a module context.
     */
    public static function get_users_in_context(userlist $userlist): void {
        if (!$instanceid = activity_provider::get_instanceid($userlist->get_context())) {
            return;
        }
        $sql = "SELECT cc.userid
                  FROM {cdelement_chapter_completion} cc
                  JOIN {cdelement_chapter} ch ON ch.id = cc.instance
                 WHERE ch.contentdesignerid = :instanceid";
        $userlist->add_from_sql('userid', $sql, ['instanceid' => $instanceid]);
    }

    /**
     * Exports the chapters the user completed.
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
            $sql = "SELECT cc.id, ch.title, cc.completion, cc.timemodified
                      FROM {cdelement_chapter_completion} cc
                      JOIN {cdelement_chapter} ch ON ch.id = cc.instance
                     WHERE ch.contentdesignerid = :instanceid AND cc.userid = :userid
                  ORDER BY ch.position";
            $records = $DB->get_records_sql($sql, ['instanceid' => $instanceid, 'userid' => $userid]);
            if (!$records) {
                continue;
            }
            $chapters = array_values(array_map(fn($record) => [
                'chapter' => format_string($record->title, true, ['context' => $context]),
                'completed' => transform::yesno($record->completion),
                'timemodified' => transform::datetime($record->timemodified),
            ], $records));
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'cdelement_chapter')],
                (object) ['chapters' => $chapters]
            );
        }
    }

    /**
     * Deletes the chapter completions of all users in a context.
     *
     * @param \context_module $context The context.
     */
    public static function delete_data_for_all_users_in_context(\context_module $context): void {
        global $DB;
        if ($instanceid = activity_provider::get_instanceid($context)) {
            $DB->delete_records_select(
                'cdelement_chapter_completion',
                'instance IN (SELECT id FROM {cdelement_chapter} WHERE contentdesignerid = ?)',
                [$instanceid]
            );
        }
    }

    /**
     * Deletes the chapter completions of the user in the contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and the user.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($instanceid = activity_provider::get_instanceid($context)) {
                $DB->delete_records_select(
                    'cdelement_chapter_completion',
                    'userid = ? AND instance IN (SELECT id FROM {cdelement_chapter} WHERE contentdesignerid = ?)',
                    [$userid, $instanceid]
                );
            }
        }
    }

    /**
     * Deletes the chapter completions of the users in the context.
     *
     * @param approved_userlist $userlist The approved users and the context.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $instanceid = activity_provider::get_instanceid($userlist->get_context());
        if (!$instanceid || !$userlist->get_userids()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['instanceid'] = $instanceid;
        $DB->delete_records_select(
            'cdelement_chapter_completion',
            "userid $insql AND instance IN (SELECT id FROM {cdelement_chapter} WHERE contentdesignerid = :instanceid)",
            $params
        );
    }
}

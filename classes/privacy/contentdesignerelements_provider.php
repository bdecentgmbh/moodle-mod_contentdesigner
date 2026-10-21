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

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;

/**
 * Privacy API of the elements (subplugins of type cdelement) that store personal data.
 *
 * Content Designer calls these methods for every element plugin that implements this interface. Elements implement
 * it together with \core_privacy\local\metadata\provider, not with \core_privacy\local\request\plugin\provider.
 * All contexts are module contexts of Content Designer activities.
 *
 * @package   mod_contentdesigner
 * @copyright 2022, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface contentdesignerelements_provider extends \core_privacy\local\request\plugin\subplugin_provider {
    /**
     * Adds the contexts in which the user has data in this element.
     *
     * @param contextlist $contextlist The list to add the contexts to.
     * @param int $userid The user.
     */
    public static function add_contexts_for_userid(contextlist $contextlist, int $userid): void;

    /**
     * Adds the users who have data in this element in the context of the userlist.
     *
     * @param userlist $userlist The userlist, with a module context.
     */
    public static function get_users_in_context(userlist $userlist): void;

    /**
     * Exports the data of the user in this element, in each context of the list.
     *
     * @param approved_contextlist $contextlist The approved contexts and the user.
     */
    public static function export_element_user_data(approved_contextlist $contextlist): void;

    /**
     * Deletes the data of all users in this element in a context.
     *
     * @param \context_module $context The context.
     */
    public static function delete_data_for_all_users_in_context(\context_module $context): void;

    /**
     * Deletes the data of the user in this element in the contexts of the list.
     *
     * @param approved_contextlist $contextlist The approved contexts and the user.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void;

    /**
     * Deletes the data of the users of the list in this element in its context.
     *
     * @param approved_userlist $userlist The approved users and the context.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void;
}

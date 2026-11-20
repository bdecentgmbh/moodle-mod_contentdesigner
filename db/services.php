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
 * Web services of the Content Designer activity: the changes made in the editor.
 *
 * The editor renders the content again afterwards with the fragment editor_content.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_contentdesigner_delete_element' => [
        'classname' => \mod_contentdesigner\external\delete_element::class,
        'description' => 'Deletes an element of the activity in the editor.',
        'type' => 'write',
        'capabilities' => 'mod/contentdesigner:viewcontenteditor',
        'ajax' => true,
    ],
    'mod_contentdesigner_move_chapter' => [
        'classname' => \mod_contentdesigner\external\move_chapter::class,
        'description' => 'Sets the order of the chapters of the activity in the editor.',
        'type' => 'write',
        'capabilities' => 'mod/contentdesigner:viewcontenteditor',
        'ajax' => true,
    ],
    'mod_contentdesigner_move_element' => [
        'classname' => \mod_contentdesigner\external\move_element::class,
        'description' => 'Sets the elements of a chapter of the activity in their order in the editor.',
        'type' => 'write',
        'capabilities' => 'mod/contentdesigner:viewcontenteditor',
        'ajax' => true,
    ],
    'mod_contentdesigner_update_visibility' => [
        'classname' => \mod_contentdesigner\external\update_visibility::class,
        'description' => 'Shows or hides an element of the activity in the editor.',
        'type' => 'write',
        'capabilities' => 'mod/contentdesigner:viewcontenteditor',
        'ajax' => true,
    ],
];

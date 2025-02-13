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
 * Installation script that inserts the element in the elements list.
 *
 * @package   cdelement_chapter
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Install script, runs during the plugin installtion.
 *
 * @return bool
 */
function xmldb_cdelement_chapter_install() {
    $shortname = \cdelement_chapter\element::SHORTNAME;
    $result = \mod_contentdesigner\elements::insertelement($shortname);

    // Drop the table if it exists, rename the existing table.
    require_once(__DIR__ . '/upgrade.php');
    xmldb_cdelement_chapter_upgrade(0);

    return $result ? true : false;
}

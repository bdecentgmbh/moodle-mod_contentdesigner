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
 * Behat steps of the Video Time element.
 *
 * @package    cdelement_videotime
 * @category   test
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../../../lib/behat/behat_base.php');

/**
 * Behat steps of the Video Time element.
 */
class behat_cdelement_videotime extends behat_base {
    /**
     * Makes Video Time unavailable: disables mod_videotime if it is installed.
     *
     * @Given /^Video Time is not available$/
     */
    public function video_time_is_not_available(): void {
        global $DB;
        if (\core_component::get_component_directory('mod_videotime') !== null) {
            $DB->set_field('modules', 'visible', 0, ['name' => 'videotime']);
            \core_plugin_manager::reset_caches();
        }
    }
}

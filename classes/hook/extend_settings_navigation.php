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

namespace mod_contentdesigner\hook;

/**
 * Lets plugins add pages, for example reports, to the secondary navigation of a Content Designer activity.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to add pages to the navigation of a Content Designer activity.')]
#[\core\attribute\tags('mod_contentdesigner', 'navigation')]
final class extend_settings_navigation {
    /**
     * Creates the hook.
     *
     * @param \navigation_node $node The node of the activity.
     * @param \cm_info $cm The activity.
     */
    public function __construct(
        /** @var \navigation_node The node of the activity */
        public readonly \navigation_node $node,
        /** @var \cm_info The activity */
        public readonly \cm_info $cm,
    ) {
    }
}

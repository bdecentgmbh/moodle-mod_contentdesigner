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
 * Lets plugins add to the content of a Content Designer activity that a learner sees.
 *
 * Dispatched whenever the content is rendered for a learner, on the activity page and when the content is
 * refreshed. Plugins may add HTML below the outro (shown once the learner can reach the end) and load their
 * JavaScript through $PAGE->requires.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to add to the content of a Content Designer activity.')]
#[\core\attribute\tags('mod_contentdesigner', 'output')]
final class extend_content {
    /** @var string[] HTML added below the outro. */
    private array $html = [];

    /**
     * Creates the hook.
     *
     * @param \cm_info $cm The activity.
     * @param \stdClass $instance The activity record.
     * @param \stdClass $attempt The attempt of the learner.
     */
    public function __construct(
        /** @var \cm_info The activity */
        public readonly \cm_info $cm,
        /** @var \stdClass The activity record */
        public readonly \stdClass $instance,
        /** @var \stdClass The attempt of the learner */
        public readonly \stdClass $attempt,
    ) {
    }

    /**
     * Adds HTML below the outro.
     *
     * @param string $html The HTML (already escaped).
     */
    public function add_html(string $html): void {
        $this->html[] = $html;
    }

    /**
     * The HTML added by the plugins.
     *
     * @return string
     */
    public function get_html(): string {
        return implode('', $this->html);
    }
}

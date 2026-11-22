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
 * Lets plugins track the H5P elements of Content Designer with xAPI, for example to save their state.
 *
 * The H5P player sends xAPI statements and states to the component set here, which must implement the xAPI
 * handler (\<component>\xapi\handler) and its settings enablesavestate and savestatefreq. Without a component the
 * player only reports the results to the H5P element.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to track the H5P elements of Content Designer with xAPI.')]
#[\core\attribute\tags('mod_contentdesigner', 'h5p')]
final class h5p_player {
    /** @var string The xAPI component of the player. */
    private string $component = '';

    /**
     * Creates the hook.
     *
     * @param \cm_info $cm The activity.
     * @param \stdClass $instance The H5P element instance (record of cdelement_h5p).
     */
    public function __construct(
        /** @var \cm_info The activity */
        public readonly \cm_info $cm,
        /** @var \stdClass The H5P element instance (record of cdelement_h5p) */
        public readonly \stdClass $instance,
    ) {
    }

    /**
     * Sets the xAPI component of the player.
     *
     * @param string $component The component, which implements the xAPI handler.
     */
    public function set_component(string $component): void {
        $this->component = $component;
    }

    /**
     * The xAPI component of the player, empty for none.
     *
     * @return string
     */
    public function get_component(): string {
        return $this->component;
    }
}

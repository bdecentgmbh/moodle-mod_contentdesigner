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
 * Lets plugins render an outro button of a type they added in {@see outro_button_types}.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to render their button types in the outro of Content Designer.')]
#[\core\attribute\tags('mod_contentdesigner', 'output')]
final class outro_button implements \Psr\EventDispatcher\StoppableEventInterface {
    /** @var string|null The HTML of the button. */
    private ?string $html = null;

    /**
     * Creates the hook.
     *
     * @param \cm_info $cm The activity.
     * @param \stdClass|null $attempt The attempt of the learner, null in the editor.
     * @param int $type The button type.
     * @param bool $primary Whether it is the primary button (otherwise the secondary one).
     */
    public function __construct(
        /** @var \cm_info The activity */
        public readonly \cm_info $cm,
        /** @var \stdClass|null The attempt of the learner */
        public readonly ?\stdClass $attempt,
        /** @var int The button type */
        public readonly int $type,
        /** @var bool Whether it is the primary button */
        public readonly bool $primary,
    ) {
    }

    /**
     * Sets the HTML of the button.
     *
     * @param string $html The HTML (already escaped), empty for no button.
     */
    public function set_html(string $html): void {
        $this->html = $html;
    }

    /**
     * The HTML of the button, null when no plugin rendered it.
     *
     * @return string|null
     */
    public function get_html(): ?string {
        return $this->html;
    }

    /**
     * Stops the dispatch once a plugin has rendered the button.
     *
     * @return bool
     */
    public function isPropagationStopped(): bool {
        return $this->html !== null;
    }
}

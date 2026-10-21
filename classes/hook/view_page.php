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

use mod_contentdesigner\content_display;

/**
 * Lets plugins take over the activity page, for example to show a summary of attempts instead of the content.
 *
 * Dispatched by the activity page (and by course formats that show the activity in a popup) before the content
 * is rendered. A plugin that handles the action sets the HTML of the page; without one, the activity shows its
 * content in the user's attempt. Plugins render the content of a given attempt with
 * {@see content_display::render_attempt()}.
 *
 * Actions: '' (the page itself), 'summary' (the overview of the attempts), 'makeattempt', 'continueattempt' and
 * 'finishattempt' (with an attempt id), or any action of a plugin.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to take over the Content Designer activity page.')]
#[\core\attribute\tags('mod_contentdesigner')]
final class view_page implements \Psr\EventDispatcher\StoppableEventInterface {
    use \core\hook\stoppable_trait;

    /** @var string|null The HTML of the page, set by a plugin. */
    private ?string $output = null;

    /**
     * Creates the hook.
     *
     * @param content_display $display The activity page.
     * @param string $action The requested action.
     * @param int $attemptid The attempt of the action, 0 for none.
     * @param bool $noheader Whether the page is shown without header and footer (e.g. in a popup).
     */
    public function __construct(
        /** @var content_display The activity page */
        public readonly content_display $display,
        /** @var string The requested action */
        public readonly string $action,
        /** @var int The attempt of the action */
        public readonly int $attemptid,
        /** @var bool Whether the page is shown without header and footer */
        public readonly bool $noheader,
    ) {
    }

    /**
     * Sets the HTML of the page. Without header and footer when {@see $noheader} is set.
     *
     * @param string $output The HTML.
     */
    public function set_output(string $output): void {
        $this->output = $output;
        $this->stop_propagation();
    }

    /**
     * The HTML of the page, null when no plugin handled the action.
     *
     * @return string|null
     */
    public function get_output(): ?string {
        return $this->output;
    }
}

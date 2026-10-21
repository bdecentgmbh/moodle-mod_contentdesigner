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

namespace mod_contentdesigner;

use mod_contentdesigner\hook\view_page;
use mod_contentdesigner\local\attempts;
use mod_contentdesigner\local\extensions;

/**
 * The page of a Content Designer activity for learners.
 *
 * Without addons, the page shows the content in the learner's attempt. Addons can take the page over through the
 * {@see view_page} hook, for example to show the attempts of a graded activity. The public methods are also used
 * by course formats that show the activity in a popup (format_popups), with $noheader set.
 *
 * @package    mod_contentdesigner
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_display {
    /** @var \stdClass The contentdesigner record */
    public readonly \stdClass $contentdesigner;

    /** @var \cm_info The course module */
    public readonly \cm_info $cm;

    /** @var \stdClass The course record */
    public readonly \stdClass $course;

    /** @var \context_module The context */
    public readonly \context_module $context;

    /**
     * Constructor
     *
     * @param \stdClass $contentdesigner The contentdesigner record
     * @param \stdClass|\cm_info $cm The course module
     * @param \stdClass $course The course record
     * @param \context_module $context The context
     */
    public function __construct($contentdesigner, $cm, $course, $context) {
        $this->contentdesigner = $contentdesigner;
        $this->cm = \cm_info::create($cm);
        $this->course = $course;
        $this->context = $context;
    }

    /**
     * Prints the page for an action.
     *
     * @param string $action The action to perform
     * @param bool $noheader Whether to display the header or not
     * @return void
     */
    public function view($action, $noheader = false) {
        $attemptid = in_array($action, ['continueattempt', 'finishattempt']) ? required_param('attemptid', PARAM_INT) : 0;
        echo $this->dispatch((string) $action, $attemptid, $noheader) ?? $this->view_content($noheader);
    }

    /**
     * Lets the addons handle an action of the page.
     *
     * @param string $action The action.
     * @param int $attemptid The attempt of the action.
     * @param bool $noheader Whether to display the header or not
     * @return string|null The HTML of the page, null when no addon handled the action.
     */
    protected function dispatch(string $action, int $attemptid, bool $noheader): ?string {
        return extensions::dispatch(new view_page($this, $action, $attemptid, $noheader))->get_output();
    }

    /**
     * The overview of the learner's attempts, when an addon provides one; otherwise the content.
     *
     * @param bool $noheader Whether to display the header or not
     * @return string The HTML of the page
     */
    public function view_summary($noheader = false) {
        return $this->dispatch('summary', 0, $noheader) ?? $this->view_content($noheader);
    }

    /**
     * Starts a new attempt, when an addon allows more than one; otherwise the content.
     *
     * @param bool $noheader Whether to display the header or not
     * @return string The HTML of the page
     */
    public function make_attempt($noheader = false) {
        return $this->dispatch('makeattempt', 0, $noheader) ?? $this->view_content($noheader);
    }

    /**
     * Continues an attempt of the learner.
     *
     * @param int $attemptid The attempt ID
     * @param bool $noheader Whether to display the header or not
     * @return string The HTML of the page
     */
    public function continue_attempt($attemptid, $noheader = false) {
        global $USER;
        return $this->dispatch('continueattempt', (int) $attemptid, $noheader)
            ?? $this->render_attempt(attempts::get_for_render($this->cm->instance, $USER->id, (int) $attemptid), $noheader);
    }

    /**
     * Finishes an attempt of the learner, when an addon grades attempts.
     *
     * @param int $attemptid The attempt ID
     * @param bool $noheader Whether to display the header or not
     * @return string|null The HTML of the page, if the addon returned one.
     */
    public function finish_attempt($attemptid, $noheader = false) {
        return $this->dispatch('finishattempt', (int) $attemptid, $noheader);
    }

    /**
     * Displays the content in the current attempt of the learner.
     *
     * @param bool $noheader Whether to display the header or not
     * @return string The HTML content for the content designer
     */
    public function view_content($noheader = false) {
        global $USER;
        return $this->render_attempt(attempts::get_current($this->cm->instance, $USER->id), $noheader);
    }

    /**
     * Renders the content in an attempt of the learner.
     *
     * @param \stdClass $attempt The attempt, which must belong to the current user and the activity.
     * @param bool $noheader Whether to display the header or not
     * @return string The HTML of the page.
     */
    public function render_attempt(\stdClass $attempt, bool $noheader = false): string {
        global $OUTPUT;

        $content = $noheader ? '' : $OUTPUT->header();

        $editor = new editor($this->cm, $this->course, $attempt);
        $editor->initiate_js();
        $content .= $editor->render_elements();

        if (!$noheader) {
            $content .= $OUTPUT->footer();
        }
        return $content;
    }

    /**
     * Renders the content in an attempt, kept for addons written for Content Designer pro 1.x.
     *
     * @param \stdClass $cdattempt The attempt.
     * @param bool $noheader Whether to display the header or not
     * @return string The HTML of the page.
     */
    public function get_attempt_form($cdattempt, $noheader = false) {
        return $this->render_attempt($cdattempt, $noheader);
    }
}

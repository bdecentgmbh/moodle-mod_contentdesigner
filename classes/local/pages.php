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

namespace mod_contentdesigner\local;

use cdelement_chapter\element as chapter;

/**
 * The paged display of an activity: every chapter on its own page, with Previous and Next buttons.
 *
 * The pages follow the same rules as the scrolling display. The learner reaches the pages up to the first chapter
 * that hides the content after it: a chapter that is not completed, or a mandatory element that is not completed
 * (unless an addon makes the navigation free, hook content_navigation). The outro is on the last page, when the learner
 * can reach the end. Pages that the learner cannot reach yet are never shown, also not through the URL.
 *
 * Without a requested page, the learner continues where the progress in the attempt stops: on the first page whose
 * chapter the learner did not complete in this attempt. A new attempt therefore starts on the first page, although
 * chapter completions are kept per learner, not per attempt.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pages {
    /** @var int Display mode: all chapters on one scrolling page (default). */
    public const DISPLAY_SCROLL = 0;

    /** @var int Display mode: every chapter on its own page. */
    public const DISPLAY_PAGES = 1;

    /** @var int[] The ids of the chapters shown to learners, one per page, in their order. */
    public readonly array $chapterids;

    /** @var array[] The chapters that the learner can reach, as returned by chapter::get_chapters_data() (no HTML). */
    public readonly array $reached;

    /** @var int The page shown, from 1. */
    public readonly int $current;

    /**
     * Computes the pages of an activity for the current user.
     *
     * @param \cm_info $cm The activity.
     * @param chapter $chapter The chapter element, with the attempt of the user.
     * @param \stdClass|null $attempt The attempt of the user.
     * @param int $requested The requested page, 0 to continue where the progress stops.
     */
    public function __construct(
        /** @var \cm_info The activity */
        public readonly \cm_info $cm,
        chapter $chapter,
        /** @var \stdClass|null The attempt */
        public readonly ?\stdClass $attempt,
        int $requested = 0
    ) {
        $this->chapterids = array_map(fn($data) => (int) $data['instancedata']->id, $chapter->get_chapters_data(true));
        $this->reached = $chapter->get_chapters_data(true, true, false, false);

        if ($requested < 1) {
            $this->current = $this->get_resume_page();
        } else {
            $this->current = max(1, min($requested, $this->get_last_reachable_page()));
        }
    }

    /**
     * Whether an activity shows its chapters on pages.
     *
     * @param \stdClass $instance The activity record.
     * @return bool
     */
    public static function is_paged(\stdClass $instance): bool {
        return isset($instance->displaymode) && $instance->displaymode == self::DISPLAY_PAGES;
    }

    /**
     * The options of the display mode, for the activity form.
     *
     * @return string[]
     */
    public static function get_display_options(): array {
        return [
            self::DISPLAY_SCROLL => get_string('displaymode_scroll', 'mod_contentdesigner'),
            self::DISPLAY_PAGES => get_string('displaymode_pages', 'mod_contentdesigner'),
        ];
    }

    /**
     * The number of pages.
     *
     * @return int
     */
    public function count(): int {
        return count($this->chapterids);
    }

    /**
     * The last page that the learner can reach (at least 1).
     *
     * @return int
     */
    public function get_last_reachable_page(): int {
        return max(1, count($this->reached));
    }

    /**
     * Whether the learner can see the end of the content (the outro): no chapter hides the content after it.
     *
     * @return bool
     */
    public function is_end_reachable(): bool {
        if (count($this->reached) < $this->count()) {
            return false;
        }
        if (!$this->reached) {
            return true;
        }
        $last = $this->reached[array_key_last($this->reached)];
        return !$last['prevent'] && !$last['chapterprevent'];
    }

    /**
     * The page where the learner continues: the first page whose chapter the learner did not complete in the
     * attempt, or the last page that the learner can reach.
     *
     * @return int
     */
    public function get_resume_page(): int {
        global $DB, $USER;

        $since = (int) ($this->attempt->timestart ?? 0);
        foreach (array_values($this->reached) as $index => $data) {
            if ($data['prevent'] || $data['chapterprevent']) {
                return $index + 1;
            }
            $completed = $DB->record_exists_select(
                'cdelement_chapter_completion',
                'instance = :instance AND userid = :userid AND completion = 1 AND timemodified >= :since',
                ['instance' => $data['instancedata']->id, 'userid' => $USER->id, 'since' => $since]
            );
            if (!$completed) {
                return $index + 1;
            }
        }
        return $this->get_last_reachable_page();
    }

    /**
     * The chapter on the page shown, null if the activity has no chapters.
     *
     * @return int|null
     */
    public function get_current_chapterid(): ?int {
        return $this->chapterids[$this->current - 1] ?? null;
    }

    /**
     * Whether the page shown is the last page.
     *
     * @return bool
     */
    public function is_last(): bool {
        return $this->current >= $this->count();
    }

    /**
     * Whether the learner can go to the next page.
     *
     * @return bool
     */
    public function can_go_next(): bool {
        return !$this->is_last() && $this->current < count($this->reached);
    }

    /**
     * The URL of a page. It continues the attempt that is shown, so that addons that manage attempts (graded
     * attempts) show the content and not their overview.
     *
     * @param int $page The page.
     * @return \moodle_url
     */
    public function get_url(int $page): \moodle_url {
        $params = ['id' => $this->cm->id];
        if (!empty($this->attempt->id)) {
            $params += ['action' => 'continueattempt', 'attemptid' => $this->attempt->id];
        }
        return new \moodle_url('/mod/contentdesigner/view.php', $params + ['page' => $page]);
    }

    /**
     * The context of the navigation between the pages, for the template mod_contentdesigner/content.
     *
     * @return array
     */
    public function export_navigation(): array {
        $urls = [];
        foreach (array_slice($this->chapterids, 0, count($this->reached)) as $index => $chapterid) {
            $urls[$chapterid] = $this->get_url($index + 1)->out(false);
        }
        return [
            'page' => $this->current,
            'pages' => $this->count(),
            'pageof' => get_string('pageof', 'mod_contentdesigner', ['page' => $this->current, 'pages' => $this->count()]),
            'hasprevious' => $this->current > 1,
            'previousurl' => $this->current > 1 ? $this->get_url($this->current - 1)->out(false) : '',
            'hasnext' => !$this->is_last(),
            'cangonext' => $this->can_go_next(),
            'nexturl' => $this->can_go_next() ? $this->get_url($this->current + 1)->out(false) : '',
            'chapterurls' => json_encode((object) $urls),
        ];
    }
}

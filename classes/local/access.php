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

use mod_contentdesigner\editor;
use mod_contentdesigner\elements;

/**
 * Access checks shared by the pages, fragments and web services of Content Designer and its elements.
 *
 * Every id that comes with a request (element instance, chapter, content, attempt) is loaded through the
 * activity whose access was checked, never on its own.
 *
 * @package    mod_contentdesigner
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access {
    /** @var string Capability to view the content. */
    public const VIEW = 'mod/contentdesigner:view';

    /** @var string Capability to edit the content. */
    public const EDIT = 'mod/contentdesigner:viewcontenteditor';

    /**
     * The activity of a module context that core already validated (fragments, web services after
     * validate_context()), with the capability checked.
     *
     * @param \context $context The context, must be the module context of a Content Designer activity.
     * @param string $capability The capability required in the context.
     * @return array [\stdClass $course, \cm_info $cm, \context_module $context]
     */
    public static function from_context(\context $context, string $capability): array {
        if ($context->contextlevel != CONTEXT_MODULE) {
            throw new \moodle_exception('invalidcoursemodule', 'error');
        }
        [$course, $cm] = get_course_and_cm_from_cmid($context->instanceid, 'contentdesigner');
        require_capability($capability, $context);
        return [$course, $cm, $context];
    }

    /**
     * The activity of a course module id for a web service: validates the context and checks the capability.
     *
     * @param int $cmid The course module id.
     * @param string $capability The capability required in the module context.
     * @return array [\stdClass $course, \cm_info $cm, \context_module $context]
     */
    public static function from_cmid(int $cmid, string $capability): array {
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'contentdesigner');
        $context = \context_module::instance($cm->id);
        \core_external\external_api::validate_context($context);
        require_capability($capability, $context);
        return [$course, $cm, $context];
    }

    /**
     * Refuses guests: they share one user account, so they cannot save progress (attempts, chapter completions,
     * answers). Web services and other requests that store the progress of the current user call this.
     *
     * @throws \moodle_exception For the guest user and for users who are not logged in.
     */
    public static function require_not_guest(): void {
        if (!isloggedin() || isguestuser()) {
            throw new \moodle_exception('guestnoprogress', 'mod_contentdesigner');
        }
    }

    /**
     * An element instance of the activity.
     *
     * @param elements $element The element.
     * @param int $instanceid The instance id.
     * @param int $contentdesignerid The activity instance id.
     * @param bool $visibleonly Only a visible instance.
     * @return \stdClass The record of the element table.
     */
    public static function get_element_instance(
        elements $element,
        int $instanceid,
        int $contentdesignerid,
        bool $visibleonly = false
    ): \stdClass {
        global $DB;
        $conditions = ['id' => $instanceid, 'contentdesignerid' => $contentdesignerid];
        if ($visibleonly) {
            $conditions['visible'] = 1;
        }
        return $DB->get_record($element->tablename(), $conditions, '*', MUST_EXIST);
    }

    /**
     * A chapter of the activity.
     *
     * @param int $chapterid The chapter id.
     * @param int $contentdesignerid The activity instance id.
     * @return \stdClass The record of cdelement_chapter.
     */
    public static function get_chapter(int $chapterid, int $contentdesignerid): \stdClass {
        global $DB;
        return $DB->get_record(
            'cdelement_chapter',
            ['id' => $chapterid, 'contentdesignerid' => $contentdesignerid],
            '*',
            MUST_EXIST
        );
    }

    /**
     * Whether all the given content ids belong to the activity.
     *
     * @param int[] $contentids Ids of contentdesigner_content.
     * @param int $contentdesignerid The activity instance id.
     * @return bool
     */
    public static function contents_belong_to(array $contentids, int $contentdesignerid): bool {
        global $DB;
        $contentids = array_unique(array_filter(array_map('intval', $contentids)));
        if (!$contentids) {
            return true;
        }
        [$insql, $params] = $DB->get_in_or_equal($contentids, SQL_PARAMS_NAMED);
        $params['contentdesignerid'] = $contentdesignerid;
        $count = $DB->count_records_select(
            'contentdesigner_content',
            "id $insql AND contentdesignerid = :contentdesignerid",
            $params
        );
        return $count == count($contentids);
    }

    /**
     * An attempt of a user in the activity.
     *
     * @param int $attemptid The attempt id.
     * @param int $contentdesignerid The activity instance id.
     * @param int $userid The user who owns the attempt.
     * @return \stdClass The record of contentdesigner_attempts.
     */
    public static function get_attempt(int $attemptid, int $contentdesignerid, int $userid): \stdClass {
        global $DB;
        return $DB->get_record(
            'contentdesigner_attempts',
            ['id' => $attemptid, 'contentdesignerid' => $contentdesignerid, 'userid' => $userid],
            '*',
            MUST_EXIST
        );
    }

    /**
     * The element object of an installed element plugin.
     *
     * @param string|int $element The shortname or the id of the element.
     * @param int $cmid The course module id.
     * @param \stdClass|null $attempt The attempt of the learner.
     * @return elements
     */
    public static function get_element($element, int $cmid, ?\stdClass $attempt = null): elements {
        global $DB;
        if (is_number($element)) {
            $element = $DB->get_field('contentdesigner_elements', 'shortname', ['id' => $element], MUST_EXIST);
        }
        if (!in_array($element, editor::get_element_names(), true)) {
            throw new \moodle_exception('elementnotfound', 'mod_contentdesigner');
        }
        return editor::get_element($element, $cmid, $attempt);
    }
}

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
 * Search area for mod_contentdesigner elements.
 *
 * @package    mod_contentdesigner
 * @copyright  2025 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_contentdesigner\search;

use mod_contentdesigner\editor;

/**
 * Search area for mod_contentdesigner elements.
 */
class elements extends \core_search\base_mod {
    /**
     * Returns a recordset with the visible element instances of the activities, with their texts.
     *
     * Every installed element contributes its title and the text field it declares in search_area_list().
     *
     * @param int $modifiedfrom
     * @param \context|null $context Optional context to restrict scope of returned results
     * @return \moodle_recordset|null Recordset (or null if no results)
     */
    public function get_document_recordset($modifiedfrom = 0, ?\context $context = null) {
        global $DB;

        [$contextjoin, $contextparams] = $this->get_context_restriction_sql($context, 'contentdesigner', 'c');
        if ($contextjoin === null) {
            return null;
        }

        $parts = [];
        $params = [];
        foreach (editor::get_element_names() as $name) {
            $element = editor::get_element($name);
            $fields = array_map('trim', explode(',', (string) (array_values($element->search_area_list())[0] ?? '')));
            $content = !empty($fields[0]) ? 'ee.' . $fields[0] : 'NULL';
            $format = !empty($fields[1]) ? 'ee.' . $fields[1] : 'NULL';
            $parts[] = "SELECT co.id, co.instance, co.element, co.description, co.descriptionformat, co.timecreated,
                               co.timemodified, ce.shortname AS elementname, ee.title, $content AS content,
                               $format AS contentformat, c.id AS contentdesignerid, c.course
                          FROM {contentdesigner_options} co
                          JOIN {contentdesigner_elements} ce ON ce.id = co.element
                          JOIN {cdelement_$name} ee ON ee.id = co.instance
                          JOIN {contentdesigner} c ON c.id = ee.contentdesignerid
                               $contextjoin
                         WHERE ce.shortname = ? AND ee.visible = 1 AND co.timemodified >= ?";
            $params = array_merge($params, $contextparams, [$name, $modifiedfrom]);
        }
        if (!$parts) {
            return null;
        }
        $sql = 'SELECT * FROM (' . implode(' UNION ALL ', $parts) . ') docs ORDER BY timemodified ASC';
        return $DB->get_recordset_sql($sql, $params);
    }

    /**
     * Returns the document for an element instance.
     *
     * @param \stdClass $record A record of get_document_recordset()
     * @param array $options Options for document creation
     * @return \core_search\document|bool
     */
    public function get_document($record, $options = []) {
        try {
            $cm = $this->get_cm('contentdesigner', $record->contentdesignerid, $record->course);
            $context = \context_module::instance($cm->id);
        } catch (\dml_exception $ex) {
            debugging('Error retrieving ' . $this->areaid . ' ' . $record->id . ' document: ' . $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        }

        $doc = \core_search\document_factory::instance($record->id, $this->componentname, $this->areaname);
        $title = !empty($record->title) ? $record->title : get_string('pluginname', 'cdelement_' . $record->elementname);
        $doc->set('title', content_to_text($title, false));
        $doc->set('content', content_to_text((string) $record->content, $record->contentformat ?? FORMAT_HTML));
        $doc->set('description1', content_to_text((string) $record->description, $record->descriptionformat ?? FORMAT_HTML));
        $doc->set('contextid', $context->id);
        $doc->set('courseid', $record->course);
        $doc->set('owneruserid', \core_search\manager::NO_OWNER_ID);
        $doc->set('modified', $record->timemodified);

        // Check if this document should be considered new.
        if (isset($options['lastindexedtime']) && ($options['lastindexedtime'] < $record->timecreated)) {
            $doc->set_is_new(true);
        }
        return $doc;
    }

    /**
     * Whether the current user can see an element: the activity is available to the user, and the element and its
     * chapter are visible (or the user edits the content).
     *
     * @param int $id The id of the general options of the element instance.
     * @return int An access constant of \core_search\manager.
     */
    public function check_access($id) {
        global $DB;

        $sql = 'SELECT co.instance, ce.shortname
                  FROM {contentdesigner_options} co
                  JOIN {contentdesigner_elements} ce ON ce.id = co.element
                 WHERE co.id = ?';
        $option = $DB->get_record_sql($sql, [$id]);
        if (!$option || !in_array($option->shortname, editor::get_element_names(), true)) {
            return \core_search\manager::ACCESS_DELETED;
        }
        $instance = $DB->get_record('cdelement_' . $option->shortname, ['id' => $option->instance]);
        if (!$instance) {
            return \core_search\manager::ACCESS_DELETED;
        }
        $cm = get_coursemodule_from_instance('contentdesigner', $instance->contentdesignerid);
        if (!$cm) {
            return \core_search\manager::ACCESS_DELETED;
        }
        $cminfo = $this->get_cm('contentdesigner', $cm->instance, $cm->course);
        $context = \context_module::instance($cm->id);
        if (!$cminfo->uservisible || !has_capability('mod/contentdesigner:view', $context)) {
            return \core_search\manager::ACCESS_DENIED;
        }
        if (!has_capability('mod/contentdesigner:viewcontenteditor', $context)) {
            $hiddenchapter = $DB->record_exists_sql('SELECT 1
                  FROM {contentdesigner_content} cc
                  JOIN {cdelement_chapter} ch ON ch.id = cc.chapter
                 WHERE cc.element = (SELECT id FROM {contentdesigner_elements} WHERE shortname = ?)
                       AND cc.instance = ? AND ch.visible = 0', [$option->shortname, $option->instance]);
            if (empty($instance->visible) || $hiddenchapter) {
                return \core_search\manager::ACCESS_DENIED;
            }
        }
        return \core_search\manager::ACCESS_GRANTED;
    }

    /**
     * Returns a url to the chapter.
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_doc_url(\core_search\document $doc) {
        global $DB;

        $contextmodule = \context::instance_by_id($doc->get('contextid'));
        $url = new \moodle_url('/mod/contentdesigner/view.php', ['id' => $contextmodule->instanceid]);

        $chapter = $DB->get_field_sql('SELECT cc.chapter
                                         FROM {contentdesigner_options} co
                                         JOIN {contentdesigner_content} cc ON co.element = cc.element AND co.instance = cc.instance
                                        WHERE co.id = ?', [$doc->get('itemid')], IGNORE_MULTIPLE);
        if ($chapter) {
            $url->set_anchor('chapters-list-' . $chapter);
        }
        return $url;
    }

    /**
     * Returns a url to the content designer.
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_context_url(\core_search\document $doc) {
        $contextmodule = \context::instance_by_id($doc->get('contextid'));
        return new \moodle_url('/mod/contentdesigner/view.php', ['id' => $contextmodule->instanceid]);
    }

    /**
     * Returns the document title to display.
     *
     * @param \core_search\document $doc
     * @return string Document title to display in the search results page
     */
    public function get_document_display_title(\core_search\document $doc) {
        $context = \context::instance_by_id($doc->get('contextid'));
        $cm = get_coursemodule_from_id('contentdesigner', $context->instanceid);
        return format_string($cm->name, true, ['context' => $context]) . ': ' . $doc->get('title');
    }
}

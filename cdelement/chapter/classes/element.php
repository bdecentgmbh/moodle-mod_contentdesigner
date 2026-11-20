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
 * Extended class of elements for chapter. it contains major part of editor element content
 *
 * @package   cdelement_chapter
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace cdelement_chapter;

defined('MOODLE_INTERNAL') || die();

use mod_contentdesigner\editor;
use mod_contentdesigner\hook\chapter_completion;
use mod_contentdesigner\hook\content_navigation;
use mod_contentdesigner\local\extensions;
use moodle_exception;

require_once($CFG->dirroot . '/mod/contentdesigner/cdelement/chapter/lib.php');

/**
 * Definitions of chapter element and it behaviours.
 */
class element extends \mod_contentdesigner\elements {
    /**
     * Shortname of the element.
     */
    const SHORTNAME = 'chapter';

    /**
     * Chapter completion: Manual.
     */
    const CHAPTER_COMPLETION_MANUAL = 0;

    /**
     * Chapter completion: Auto.
     */
    const CHAPTER_COMPLETION_AUTO = 1;

    /**
     * Whether the navigation is sequential, see {@see is_sequential()}.
     *
     * @var bool|null
     */
    protected $sequential = null;

    /**
     * Element name which is visbile for the users
     *
     * @return string
     */
    public function element_name() {
        return get_string('pluginname', 'cdelement_chapter');
    }

    /**
     * Element shortname which is used as identical purpose.
     *
     * @return string
     */
    public function element_shortname() {
        return self::SHORTNAME;
    }

    /**
     * Icon of the element.
     *
     * @param renderer $output
     * @return string HTML fragment
     */
    public function icon($output) {
        return $output->pix_icon('i/folder', get_string('pluginname', 'cdelement_chapter'));
    }

    /**
     * Element form element definition.
     *
     * @param moodle_form $mform
     * @param genreal_element_form $formobj
     * @return void
     */
    public function element_form(&$mform, $formobj) {

        // General settigns.
        $strrequired = get_string('required');
        $mform->addElement('text', 'title', get_string('chaptertitle', 'mod_contentdesigner'), 'maxlength="100" size="30"');
        $mform->addRule('title', $strrequired, 'required', null, 'client');
        $mform->setType('title', PARAM_NOTAGS);

        // Display title.
        $default = get_config('cdelement_chapter', 'chaptertitlestatus');
        $mform->addElement('checkbox', 'titlestatus', get_string('titlestatus', 'mod_contentdesigner'));
        $mform->setDefault('titlestatus', $default ?: 0);
        $mform->addHelpButton('titlestatus', 'titlestatus', 'mod_contentdesigner');

        // Visibility for General element.
        $visibleoptions = [
            1 => get_string('visible'),
            0 => get_string('hidden', 'mod_contentdesigner'),
        ];
        $default = get_config('cdelement_chapter', 'visibility');
        $mform->addElement('select', 'visible', get_string('visibility', 'mod_contentdesigner'), $visibleoptions);
        $mform->addHelpButton('visible', 'visibility', 'mod_contentdesigner');
        $mform->setDefault('visible', $default);

        if (cdelement_chapter_has_learningtools()) {
            // Learning Tools setting.
            $learningtoolsoptions = [
                0 => get_string('disabled', 'mod_contentdesigner'),
                1 => get_string('enabled', 'mod_contentdesigner'),
            ];
            $default = get_config('cdelement_chapter', 'learningtools');
            $mform->addElement(
                'select',
                'learningtools',
                get_string('learningtools', 'mod_contentdesigner'),
                $learningtoolsoptions
            );
            $mform->addHelpButton('learningtools', 'learningtools', 'mod_contentdesigner');
            $mform->setDefault('learningtools', $default);
        }
    }

    /**
     * Verify the element is supports the content render method.
     *
     * @return bool
     */
    public function supports_content() {
        return false;
    }

    /**
     * Verify the elements the standard general options list.
     *
     * @return bool
     */
    public function supports_standard_elements() {
        // By default all the elmenets will supports the standard options.
        return false;
    }

    /**
     * Initiate the element js for the view page.
     *
     * @return void
     */
    public function initiate_js() {
        global $PAGE;
        $PAGE->requires->js_call_amd('cdelement_chapter/chapter', 'init', []);
    }

    /**
     * Get the default chapter instance for the current course module. if not found create new one and use it as default one.
     *
     * @param int $contentdesignerid Content designer instance id.
     * @param bool $create
     * @return bool
     */
    public function get_default($contentdesignerid, $create = false) {
        global $DB;
        if ($record = $DB->get_record('cdelement_chapter', ['contentdesignerid' => $contentdesignerid], '*', IGNORE_MULTIPLE)) {
            return $record->id;
        }

        if ($create) {
            $id = $this->create_basic_instance($contentdesignerid);
            return $id;
        }
        return false;
    }

    /**
     * Creates a chapter at the end of the activity, with the default general options of the site.
     *
     * @param int $contentdesignerid Content designer instance id.
     * @param array $record Values of the chapter table to set.
     * @return int Chapter id.
     */
    public function create_basic_instance($contentdesignerid, array $record = []) {
        global $DB;
        $lastposition = (int) $DB->get_field_sql(
            'SELECT MAX(position) FROM {cdelement_chapter} WHERE contentdesignerid = ?',
            [$contentdesignerid]
        );
        $record += ['position' => $lastposition + 1];
        return parent::create_basic_instance($contentdesignerid, $record);
    }

    /**
     * Update the element instance. Override the function in elements element class to add custom rules.
     *
     * @param stdclass $data
     * @return int Element instance id
     */
    public function update_instance($data) {
        global $DB;

        if ($data->instanceid == false) {
            $data->timemodified = time();
            $data->timecreated = time();
            if ($data->chapterid) {
                $lastelement = $DB->get_field('cdelement_chapter', 'position', ['id' => $data->chapterid]);
                $DB->execute('UPDATE {cdelement_chapter} SET position=position+1
                WHERE position > ? AND contentdesignerid = ?', [$lastelement, $this->cm->instance]);
            } else {
                $lastelement = (int) $DB->get_field_sql('SELECT max(position) from {cdelement_chapter}
                    WHERE contentdesignerid = ?', [$this->cm->instance]);
            }
            $data->position = $lastelement ? $lastelement + 1 : 1;
            return $DB->insert_record($this->tablename, $data);
        } else {
            $data->timecreated = time();
            $data->id = $data->instanceid;
            $data->titlestatus = $data->titlestatus ?? 0;
            if ($data->chapterid) {
                $lastelement = $DB->get_field('cdelement_chapter', 'position', ['id' => $data->chapterid]);
                $DB->execute('UPDATE {cdelement_chapter} SET position=position+1
                WHERE position > ? AND contentdesignerid = ?', [$lastelement, $this->cm->instance]);
            } else {
                $lastelement = (int) $DB->get_field_sql('SELECT max(position) from {cdelement_chapter}
                    WHERE contentdesignerid = ?', [$this->cm->instance]);
            }

            $data->position = $data->position ?: $lastelement + 1;
            if ($DB->update_record($this->tablename, $data)) {
                return $data->id;
            }
        }
    }

    /**
     * Set element instance for the given chapter as contents.
     *
     * @param int $chapterid Chapter id
     * @param int $contentid Content id
     * @return bool
     */
    public function set_elements($chapterid, $contentid) {
        global $DB;

        if ($chapter = $DB->get_record('cdelement_chapter', ['id' => $chapterid])) {
            $contents = isset($chapter->contents) ? array_filter(explode(',', $chapter->contents)) : [];
            $contents[] = $contentid;
            $DB->set_field('cdelement_chapter', 'contents', implode(',', array_filter($contents)), ['id' => $chapterid]);
            $DB->set_field('cdelement_chapter', 'timemodified', time(), ['id' => $chapterid]);
        }
        return false;
    }

    /**
     * Render the chapters for the course module.
     *
     * @param bool $visible Load only visible elements.
     * @param bool $render Return the elements with rendered html.
     * @param int|bool $chapterafter Only the chapters after the given chapter. The chapters up to it are checked from
     *     the start all the same: when the learner cannot get past the given chapter, no chapter is returned.
     * @param bool $html With $render, whether to render the HTML of the elements, or only to check which chapters and
     *     elements the learner can reach (paged display).
     * @return array
     */
    public function get_chapters_data($visible = false, $render = false, $chapterafter = false, $html = true) {
        global $DB, $USER;
        if (empty($this->cm)) {
            throw new \moodle_exception('invalidcoursemodule', 'error');
        }
        $list = []; // List of chapters.
        $condition = ['contentdesignerid' => $this->cm->instance];
        $condition += $visible ? ['visible' => 1] : [];
        if ($chapters = $DB->get_records('cdelement_chapter', $condition, 'position ASC')) {
            // With $chapterafter, the chapters up to the given one are checked but not returned.
            $chapterreached = !$chapterafter;
            foreach ($chapters as $chapterid => $chapter) {
                $before = !$chapterreached;
                if ($before && $chapterafter == $chapterid) {
                    $chapterreached = true;
                }
                $chapter->chaptertitle = $chapter->title;
                $chapter->title = $before ? $chapter->title : ($this->title_editable($chapter) ?: $this->info()->name);

                [$prevent, $contents, $learningtools] = $this->generate_chapter_content(
                    $chapter,
                    $visible,
                    $render,
                    $html && !$before
                );
                if ($visible && empty($contents) && !$chapter->titlestatus) {
                    continue;
                }
                if ($before) {
                    // The learner gets past this chapter only if it does not hide the chapters after it.
                    if ($render && ($prevent || $this->prevent_nextelements($chapter))) {
                        return [];
                    }
                    continue;
                }

                $completion = $DB->get_record('cdelement_chapter_completion', ['instance' => $chapter->id, 'userid' => $USER->id]);
                $chapterprevent = ($render && $this->prevent_nextelements($chapter));
                $editurl = new \moodle_url('/mod/contentdesigner/element.php', [
                    'cmid' => $this->cmid,
                    'element' => $this->shortname,
                    'id' => $chapter->id,
                ]);

                $copyurl = new \moodle_url('/mod/contentdesigner/editor.php', [
                    'id' => $this->cmid,
                    'instanceid' => $chapter->id,
                    'element' => $this->shortname,
                    'action' => 'copy',
                    'sesskey' => sesskey(),
                ]);

                // Learners complete the chapter with the button, unless an addon completes it automatically.
                $automatic = $render && extensions::dispatch(new chapter_completion($this->cm, $chapter))->is_automatic();

                $list[] = [
                    'instancedata' => $chapter,
                    'info' => $this->info(),
                    'editurl' => $editurl,
                    'contents' => $contents,
                    'count' => count($contents),
                    'prevent' => $prevent,
                    'chapterprevent' => $chapterprevent,
                    // Guests cannot save progress, so they get no button to complete the chapter.
                    'chaptercta' => $render && !isguestuser(),
                    'completion' => isset($completion->completion) && $completion->completion ? true : false,
                    'completionmode' => !$automatic,
                    'autocompletion' => $automatic,
                    'copyurl' => $copyurl,
                    'learningtools' => $learningtools, // Add the learning tools flag.
                    'hasbookmarkcapability' => $learningtools['show'] && $learningtools['canbookmark'],
                    'hasnotecapability' => $learningtools['show'] && $learningtools['cannote'],
                ];

                // Prevent the next chapters when user needs to complete any of activities.
                if ($prevent || $chapterprevent) {
                    break;
                }
            }
        }
        return $list;
    }

    /**
     * Whether the current user may mark a chapter as completed: the chapter is shown to the user, the user can reach
     * it (the chapters before it are completed and, with sequential navigation, their mandatory elements as well),
     * and, with sequential navigation, no mandatory element of the chapter is incomplete in the attempt.
     *
     * The same rules decide which chapters the content shows ({@see get_chapters_data()}) and which pages a learner
     * can open ({@see \mod_contentdesigner\local\pages}).
     *
     * @param int $chapterid The chapter id.
     * @return bool
     */
    public function can_complete(int $chapterid): bool {
        foreach ($this->get_chapters_data(true, true, false, false) as $data) {
            if ($data['instancedata']->id == $chapterid) {
                return !$data['prevent'];
            }
        }
        return false;
    }

    /**
     * Prevent the upcoming elements if the chapter not completed.
     *
     * @param stdclass $chapter Chapter element instance data.
     * @return bool
     */
    public function prevent_nextelements($chapter): bool {
        global $USER;

        if (has_capability('mod/contentdesigner:viewcontenteditor', $this->context)) {
            return false;
        }

        return !$this->is_chaptercompleted($chapter->id);
    }

    /**
     * Find the user is completed the chapter.
     * @param int $chapterid Instance data of chapter.
     * @return bool
     */
    public function is_chaptercompleted($chapterid): bool {
        global $USER, $DB;

        if ($record = $DB->get_record('cdelement_chapter_completion', ['instance' => $chapterid, 'userid' => $USER->id])) {
            return $record->completion ? true : false;
        }
        return false;
    }

    /**
     * Gerneate chapter related elements.
     *
     * @param stdclass $chapter
     * @param bool $visible Fetch only visible elements.
     * @param bool $render Render the element instance to student view.
     * @param bool $html With $render, whether to render the HTML of the elements, or only to check which elements the
     *     learner can reach.
     * @return array
     */
    public function generate_chapter_content($chapter, $visible = false, $render = false, $html = true) {
        global $DB, $USER, $PAGE;
        $list = [];
        $prevent = false;
        $record = $DB->get_record('contentdesigner', ['id' => $this->cm->instance]);
        $url = new \moodle_url('/mod/contentdesigner/view.php', ['id' => $this->cm->id]);

        // Learning tools data.
        $learningtools = [
            'show' => false,
            'canbookmark' => false,
            'cannote' => false,
            'chapterid' => $chapter->id,
            'bookmarked' => false,
            'notescount' => 0,
            'userid' => $USER->id,
            'sesskey' => sesskey(),
            'course' => $record->course,
            'coursemodule' => $this->cm->id,
            'contextlevel' => CONTEXT_MODULE,
            'pagetype' => $PAGE->pagetype,
            'pageurl' => $url . "#chapters-list-" . $chapter->id,
        ];

        // Check if learning tools should be displayed.
        if (!empty($chapter->learningtools) && cdelement_chapter_has_learningtools()) {
            $context = \context_module::instance($this->cmid);
            $hasbookmarkcapability = has_capability('ltool/bookmarks:createbookmarks', $context);
            $hasnotescapability = has_capability('ltool/note:createnote', $context);

            // Only show learning tools if user has at least one capability.
            if ($hasbookmarkcapability || $hasnotescapability) {
                $learningtools['show'] = true;
                $learningtools['canbookmark'] = $hasbookmarkcapability;
                $learningtools['cannote'] = $hasnotescapability;
                // Check if the chapter is bookmarked by the user.
                $learningtools['bookmarked'] = $DB->record_exists('ltool_bookmarks_data', [
                    'itemtype' => 'chapter',
                    'itemid' => $chapter->id,
                    'userid' => $USER->id,
                ]);

                $learningtools['notescount'] = $DB->count_records('ltool_note_data', [
                    'itemtype' => 'chapter',
                    'itemid' => $chapter->id,
                    'userid' => $USER->id,
                ]);
            }
        }

        if (empty($chapter->contents)) {
            return [$prevent, $list, $learningtools];
        }

        $contents = explode(',', $chapter->contents);
        $sql  = 'SELECT cc.*, ce.id as elementid, ce.shortname as elementname FROM {contentdesigner_content} cc
                JOIN {contentdesigner_elements} ce ON ce.id = cc.element
                WHERE cc.chapter = ? ORDER BY position ASC';
        $params = ['chapterid' => $chapter->id];

        $contents = $DB->get_records_sql($sql, $params);
        $editor = \mod_contentdesigner\editor::get_editor($this->cmid);
        $installed = editor::get_element_names();
        $sequential = $this->is_sequential();
        foreach ($contents as $content) {
            if ($content->contentdesignerid != $this->cm->instance || !in_array($content->elementname, $installed, true)) {
                continue;
            }
            $element = \mod_contentdesigner\editor::get_element($content->elementname, $this->cmid, $this->cdattempt);
            $instance = $element->get_instance($content->instance, $visible);
            if ($instance) {
                $instance->titleplain = $instance->title;
                $instance->title = $element->title_editable($instance) ?: $element->info()->name;
                $option = $editor->get_option($instance->id, $element->elementid);
                // Load the element options classes to instance.
                $element->generate_element_classes($instance, $option);

                $instance->uniqueid = uniqid('element-' . $content->elementname . '-');
                // Verify this element supports replace on refresh.
                $instance->replaceonrefresh = $element->supports_replace_onrefresh();

                // Use Mutation of instance in render function of element to add element classes.
                $contenthtml = ($render && $html) ? $element->render_element($instance) : '';
                $editurl = new \moodle_url('/mod/contentdesigner/element.php', [
                    'cmid' => $this->cmid,
                    'element' => $element->shortname,
                    'id' => $instance->id,
                ]);

                $copyurl = new \moodle_url('/mod/contentdesigner/editor.php', [
                    'id' => $this->cmid,
                    'instanceid' => $instance->id,
                    'element' => $element->shortname,
                    'action' => 'copy',
                    'sesskey' => sesskey(),
                ]);

                $reporturl = $element->supports_reports()
                    ? new \moodle_url('/mod/contentdesigner/cdelement/' . $element->shortname . '/report.php', [
                        'id' => $instance->id, 'cmid' => $this->cmid])
                    : null;

                $list[] = (array) $content + [
                    'info' => $element->info(),
                    'instancedata' => $instance,
                    'option' => $option,
                    'editurl' => $editurl,
                    'content' => $contenthtml,
                    'hidereport' => $element->supports_reports(),
                    'reporturl' => $reporturl ?? '',
                    'copyurl' => $copyurl,
                ];

                // Prevent the elements next to the manatory elements.
                if ($sequential && $render && $element->prevent_nextelements($instance)) {
                    $prevent = true;
                    break;
                }
            }
        }

        return [$prevent, $list, $learningtools];
    }

    /**
     * Whether the navigation is sequential: elements after an incomplete mandatory element stay hidden.
     *
     * Addons can let learners move freely (hook {@see content_navigation}).
     *
     * @return bool
     */
    public function is_sequential(): bool {
        global $DB;
        if ($this->sequential === null) {
            $instance = $DB->get_record('contentdesigner', ['id' => $this->cm->instance], '*', MUST_EXIST);
            $this->sequential = extensions::dispatch(new content_navigation($this->cm, $instance))->is_sequential();
        }
        return $this->sequential;
    }

    /**
     * Build the progress of the chapter completion.
     *
     * @return string HTML of the progress bar.
     */
    public function build_progress() {
        global $DB, $OUTPUT, $USER;

        $sql = "SELECT ec.*, ecc.completion FROM {cdelement_chapter} ec
            LEFT JOIN {cdelement_chapter_completion} ecc ON ec.id = ecc.instance AND ecc.userid=:userid
            WHERE ec.contentdesignerid=:contentdesignerid AND ec.visible = 1 AND ec.id IN (
                SELECT chapter FROM {contentdesigner_content} cc
            ) ORDER BY position ASC";
        $records = $DB->get_records_sql($sql, ['userid' => $USER->id, 'contentdesignerid' => $this->cm->instance]);

        $data = [
            'chapters' => array_values($records),
            'contentdesignerid' => $this->cm->instance,
            'cmid' => $this->cmid,
        ];
        return $OUTPUT->render_from_template('cdelement_chapter/progressbar', $data);
    }

    /**
     * Update the position of the content in chapters.
     *
     * @param int $chapterid  Chapter id.
     * @param string $contents Contents in order position.
     * @return bool
     */
    public function update_postion($chapterid, $contents) {
        global $DB;

        $instance = $this->get_instance($chapterid);
        $instance->contents = $contents;
        try {
            $transaction = $DB->start_delegated_transaction();
            if (!empty($contents)) {
                $list = explode(',', $contents);
                $position = 1;
                foreach ($list as $item) {
                    $record = (object) [
                        'id' => $item,
                        'position' => $position,
                        'chapter' => $chapterid,
                    ];
                    $DB->update_record('contentdesigner_content', $record);
                    $position += 1;
                }
            }
            // Update the chapter contents in cdelement_chapter table.
            $this->update_chapter_contents($chapterid);
            $transaction->allow_commit();
        } catch (moodle_exception $ex) {
            $transaction->rollback($ex);
        }
        return $DB->update_record('cdelement_chapter', $instance);
    }

    /**
     * Move the chapter position in the given position.
     *
     * @param string $chapters Chapters list in the position
     * @return bool
     */
    public function move_chapter($chapters) {
        global $DB;

        try {
            $transaction = $DB->start_delegated_transaction();
            if (!empty($chapters)) {
                $list = explode(',', $chapters);
                $position = 1;
                foreach ($list as $item) {
                    $record = (object) [
                        'id' => $item,
                        'position' => $position,
                    ];
                    $status = $DB->update_record('cdelement_chapter', $record);
                    $position += 1;
                }
            }
            $transaction->allow_commit();
            return true;
        } catch (moodle_exception $ex) {
            $transaction->rollback($ex);
        }
    }

    /**
     * Update contents of chapter.
     *
     * @param int $chapterid Chapter id.
     * @return void
     */
    public function update_chapter_contents($chapterid) {
        global $DB;

        if ($contents = $DB->get_records('contentdesigner_content', ['chapter' => $chapterid])) {
            $contents = array_column($contents, 'id');
            $DB->update_record('cdelement_chapter', ['id' => $chapterid, 'contents' => implode(',', $contents)]);
        }
    }

    /**
     * Render the view of element instance, Which is displayed in the student view.
     *
     * @param stdclass $instance
     * @return bool
     */
    public function render($instance) {
        return false;
    }

    /**
     * Deletes the completions, learning tools data and elements of a chapter before the chapter itself.
     *
     * @param int $instanceid The chapter id.
     */
    protected function delete_instance_data(int $instanceid): void {
        global $DB;

        $DB->delete_records('cdelement_chapter_completion', ['instance' => $instanceid]);

        // Delete the bookmarks and notes of Learning Tools on the chapter.
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('ltool_bookmarks_data') && $dbman->field_exists('ltool_bookmarks_data', 'itemid')) {
            $DB->delete_records('ltool_bookmarks_data', ['itemid' => $instanceid, 'itemtype' => 'chapter']);
        }
        if ($dbman->table_exists('ltool_note_data') && $dbman->field_exists('ltool_note_data', 'itemid')) {
            $DB->delete_records('ltool_note_data', ['itemid' => $instanceid, 'itemtype' => 'chapter']);
        }

        $installed = editor::get_element_names();
        $sql = 'SELECT cc.*, ce.shortname AS elementname
                  FROM {contentdesigner_content} cc
                  JOIN {contentdesigner_elements} ce ON ce.id = cc.element
                 WHERE cc.chapter = ?';
        foreach ($DB->get_records_sql($sql, [$instanceid]) as $content) {
            if (in_array($content->elementname, $installed, true)) {
                editor::get_element($content->elementname, $this->cmid)->delete_element($content->instance);
            } else {
                $DB->delete_records('contentdesigner_content', ['id' => $content->id]);
            }
        }
    }

    /**
     * Deletes the chapter completions of all learners in an activity.
     *
     * @param int $contentdesignerid The activity instance id.
     */
    public function reset_userdata(int $contentdesignerid): void {
        global $DB;
        $DB->delete_records_select(
            'cdelement_chapter_completion',
            'instance IN (SELECT id FROM {cdelement_chapter} WHERE contentdesignerid = ?)',
            [$contentdesignerid]
        );
    }
}

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
 * Base class for Content designer elements. Commonly used elements methods are defined here.
 *
 * @package    mod_contentdesigner
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_contentdesigner;

use mod_contentdesigner\hook\progress_updated;
use mod_contentdesigner\local\access;
use mod_contentdesigner\local\css;
use mod_contentdesigner\local\extensions;

/**
 * Base class for Content designer elements.
 */
abstract class elements {
    /**
     * Hard-coded value for the 'maxfiles' option.
     */
    const EDITOR_UNLIMITED_FILES = -1;

    /**
     * Context data for the current module.
     *
     * @var \context_module
     */
    public $context;

    /**
     * Course module ID.
     *
     * @var int $cmid
     */
    public $cmid;

    /**
     * Elment Short name.
     *
     * @var string
     */
    public $shortname;

    /**
     * Elment table name.
     *
     * @var string
     */
    public $tablename;

    /**
     * Element ID
     *
     * @var int $elementid
     */
    public $elementid;

    /**
     * Course module
     *
     * @var \cm_info $cm course module data
     */
    public $cm;

    /**
     * Course Objecet
     *
     * @var mixed $course Course object
     */
    public $course;

    /**
     * The attempt of the learner the element is rendered for, null in the editor.
     *
     * @var \stdClass|null $cdattempt
     */
    public $cdattempt;

    /**
     * This element is not mandatory.
     */
    public const ENABLE_MANDATORY = 1;

    /**
     * Disable the mandatory for this elemnet.
     */
    public const DISBLE_MANDATORY = 0;

    /**
     * Status visibility: visible.
     */
    public const STATUS_VISIBLE = 1;

    /**
     * Status visibility: hidden.
     */
    public const STATUS_DISABLE = 0;

    /**
     * Constructor method, Setup the element basic information and context.
     *
     * @param int $cmid
     * @param \stdClass|null $cdattempt
     */
    public function __construct($cmid, $cdattempt = null) {
        $this->cmid = $cmid;
        $this->shortname = $this->element_shortname();
        $this->tablename = 'cdelement_' . $this->shortname;
        $this->elementid = $this->element_id();
        $this->cdattempt = $cdattempt;
        if ($cmid) {
            [$course, $cm] = get_course_and_cm_from_cmid($cmid);
            $this->cm = $cm;
            $this->course = $course;
            $this->context = $this->get_context();
        }
    }

    /**
     * Element name which is visbile for the users
     *
     * @return string
     */
    abstract public function element_name();

    /**
     * Element shortname which is used as identical purpose.
     *
     * @return string
     */
    abstract public function element_shortname();

    /**
     * Element form element definition.
     *
     * @param \MoodleQuickForm $mform
     * @param \mod_contentdesigner\form\general_element_form $formobj
     * @return void
     */
    abstract public function element_form(&$mform, $formobj);

    /**
     * Validates the settings of the element in its form; the general settings are validated by the form.
     *
     * @param array $data The submitted data.
     * @param array $files The submitted files.
     * @return array Errors by form field.
     */
    public function validation(array $data, array $files): array {
        return [];
    }

    /**
     * Render the view of the element instance which is displayed to the users.
     *
     * @param \stdClass $instance
     * @return string
     */
    abstract public function render($instance);

    /**
     * Specify the searchable fields for the element, if search is supported.
     *
     * Return format:
     * ['tablename' => 'field1, contentformatfield']
     *
     * @return array
     */
    public function search_area_list(): array {
        return [];
    }

    /**
     * Verify the elements the standard general options list.
     *
     * @return bool
     */
    public function supports_standard_elements() {
        // By default all the elmenets will supports the standard options.
        return true;
    }

    /**
     * Verify the element is supports the content render method.
     *
     * @return bool
     */
    public function supports_content() {
        return true;
    }

    /**
     * Whether the element can be graded, see {@see get_mark()} and {@see get_max_mark()}.
     *
     * @return bool
     */
    public function supports_grade() {
        return false;
    }

    /**
     * Verify the element is supports top of the contents. ie(cdelement_tableofcontents)
     *
     * Such elements implement {@see get_editor_data()} and {@see render_module_topcontents()}.
     *
     * @return bool
     */
    public function supports_top_instance() {
        return false;
    }

    /**
     * Is the element supports the multiple instance for one activity instance. ie(cdelement_outro)
     *
     * @return bool
     */
    public function supports_multiple_instance() {
        return true;
    }

    /**
     * Is the element supports the custom form, then the element should contain the entire general_element_form and its definitions.
     *
     * Otherwise it shoudld contains simple field definitions.
     *
     * @return bool
     */
    public function supports_custom_form(): bool {
        return false;
    }

    /**
     * Is the element supports the reports.
     *
     * @return bool
     */
    public function supports_reports(): bool {
        return false;
    }

    /**
     * Whether the element can be used on this site. Elements that depend on an optional plugin return false while that
     * plugin is missing or disabled: they are not offered in the editor and cannot be added, but their existing
     * instances, backups and user data are kept.
     *
     * @return bool
     */
    public function is_available(): bool {
        return true;
    }

    /**
     * Icon of the element.
     *
     * @param \renderer_base $output
     * @return string HTML fragment
     */
    public function icon($output) {
        return $output->pix_icon('e/bullet_list', get_string('plugin'));
    }

    /**
     * Element description, By default description tried from plugin strings list.
     *
     * @return string
     */
    public function element_description() {
        return (get_string_manager()->string_exists('elementdescription', 'cdelement_' . $this->element_shortname()))
            ? get_string('elementdescription', 'cdelement_' . $this->element_shortname()) : '';
    }

    /**
     * Save the area files data after the element instance moodle_form submittted.
     * If the element override the method then should call the parent to save the baackgroung image files.
     *
     * @param \stdClass $data Submitted moodle_form data.
     */
    public function save_areafiles($data) {

        if (isset($data->bgimage)) {
            file_save_draft_area_files(
                $data->bgimage,
                $data->contextid,
                'mod_contentdesigner',
                $data->elementshortname . 'elementbg',
                $data->instance
            );
        }

        if (isset($data->description_editor)) {
            $editoroptions = $this->editor_options(\context_module::instance($data->cmid));
            $data = file_postupdate_standard_editor(
                $data,
                'description',
                $editoroptions,
                \context_module::instance($data->cmid),
                'mod_contentdesigner',
                $this->element_shortname() . 'description',
                $data->instance
            );
        }
    }

    /**
     * Prepare the form editor elements file data before render the elemnent form.
     *
     * @param \stdClass $formdata
     * @return \stdClass
     */
    public function prepare_standard_file_editor(&$formdata) {
        if (isset($formdata->instance)) {
            $draftitemid = file_get_submitted_draft_itemid('bgimage');
            file_prepare_draft_area(
                $draftitemid,
                $this->context->id,
                'mod_contentdesigner',
                $this->element_shortname() . 'elementbg',
                $formdata->instance,
                ['subdirs' => 0, 'maxfiles' => 1]
            );
            $formdata->bgimage = $draftitemid;

            // Description editor.
            $editoroptions = $this->editor_options($this->context);
            file_prepare_standard_editor(
                $formdata,
                'description',
                $editoroptions,
                $this->context,
                'mod_contentdesigner',
                $this->element_shortname() . 'description',
                $formdata->instance
            );
        }
        return $formdata;
    }

    /**
     * Options used in the editor defined.
     *
     * @param \context_module $context
     * @return array Filemanager options.
     */
    public function editor_options($context) {
        global $CFG;
        return [
            'subdirs' => 1,
            'maxbytes' => $CFG->maxbytes,
            'accepted_types' => '*',
            'context' => $context,
            'maxfiles' => self::EDITOR_UNLIMITED_FILES,
        ];
    }

    /**
     * Get the context instance for the course module.
     *
     * @return \context_module
     */
    public function get_context() {
        return \context_module::instance($this->cmid);
    }

    /**
     * Simple information about the element. Used in the element box.
     *
     * @return object
     */
    public function info() {
        global $OUTPUT;
        return (object) [
            'elementid' => $this->element_id(),
            'name' => $this->element_name(),
            'shortname' => $this->shortname,
            'icon' => $this->icon($OUTPUT),
            'description' => $this->element_description(),
        ];
    }

    /**
     * Fetch the record of the cotnent designer module instance.
     *
     * @return \stdClass
     */
    public function get_contentdesigner() {
        global $DB;
        return $DB->get_record('contentdesigner', ['id' => $this->cm->instance]);
    }

    /**
     * Get the course module data from the module instance.
     *
     * @param int $contentdesignerid
     * @return \stdClass Course module record.
     */
    public function get_cm_from_modinstance($contentdesignerid) {
        return get_coursemodule_from_instance('contentdesigner', $contentdesignerid);
    }

    /**
     * Vertify the element instance is prevents the loading of next element instance.
     * For example please check the cdelement_h5p
     *
     * @param \stdClass $instance Instance data of the element.
     * @return bool True if need to stop the next instance Otherwise false if render of next elements.
     */
    public function prevent_nextelements($instance): bool {
        return false;
    }

    /**
     * Replace the element on refersh the content. Some elements may need to update the content on refresh the elmenet.
     */
    public function supports_replace_onrefresh(): bool {
        return false;
    }

    /**
     * Initiate the element js for the view page.
     *
     * @return void
     */
    public function initiate_js() {
        global $PAGE;
        $data = [
            'cmid' => $this->cmid,
            'contextid' => $this->context->id,
            'contentdesignerid' => $this->cm->instance,
            'attemptid' => $this->cdattempt->id ?? 0,
        ];
        $PAGE->requires->js_call_amd('mod_contentdesigner/elements', 'contentDesignerElementsData', $data);
    }

    /**
     * Render the view of element instance, Which is displayed in the student view.
     *
     * @param \stdClass $instance
     * @return array
     */
    public function render_element($instance) {
        $data = $this->prepare_formdata($instance->id);
        $html = $this->render($data);
        return ['elementcontent' => $html, 'general' => $data];
    }

    /**
     * The in place editable title of an element instance, editable for users who may edit the content.
     *
     * @param \stdClass $instance Element Instance data.
     * @return \core\output\inplace_editable
     */
    public function get_title_editable($instance): \core\output\inplace_editable {
        $title = !empty($instance->title) ? $instance->title : $this->info()->name;
        $name = 'instance_title[' . $this->shortname . '][' . $instance->id . ']';
        $editable = $this->context && has_capability(access::EDIT, $this->context);
        return new \core\output\inplace_editable(
            'mod_contentdesigner',
            $name,
            $this->elementid . $instance->id,
            $editable,
            format_string($title, true, ['context' => $this->context]),
            $title,
            get_string('titleeditable', 'mod_contentdesigner'),
            get_string('newvalue', 'mod_contentdesigner') . format_string($title, true, ['context' => $this->context])
        );
    }

    /**
     * Load the element title into inline editable method.
     *
     * @param \stdClass $instance Element Instance data.
     * @return string
     */
    public function title_editable($instance) {
        global $OUTPUT;
        return $OUTPUT->render($this->get_title_editable($instance));
    }

    /**
     * Add elements in DB. During the plugin installtion elements will inserted and created id for elements.
     * Elmenets instance will identified usign the element id.
     *
     * @param string $shortname Shortname of the element.
     * @return bool
     */
    public static function insertelement(string $shortname) {
        global $DB;

        $record = ['shortname' => $shortname, 'timemodified' => time()];

        if (!$DB->record_exists('contentdesigner_elements', ['shortname' => $shortname])) {
            return $DB->insert_record('contentdesigner_elements', $record);
        }
        return true;
    }

    /**
     * Get the Id of the element in the list of available elements list, this id created during the element installation.
     *
     * @return int ID of the element.
     */
    public function element_id() {
        global $DB;
        return $DB->get_field('contentdesigner_elements', 'id', ['shortname' => $this->element_shortname()]);
    }

    /**
     * Get the table name of the current element. By default its the shortname followed by keyword (element_SHORTNAME)
     *
     * @return string Name of the table.
     */
    public function tablename() {
        return 'cdelement_' . $this->element_shortname();
    }

    /**
     * Verify the element table is exists in the DB.
     *
     * @return bool
     */
    public function is_table_exists() {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table($this->tablename());
        return $dbman->table_exists($table);
    }

    /**
     * Create the basic instance for the element, with the default general options of the site.
     * Override this function if need to add custom changes.
     *
     * @param int $contentdesignerid Content designer instance id.
     * @param array $record Values of the element table to set.
     * @return int Element instance id.
     */
    public function create_basic_instance($contentdesignerid, array $record = []) {
        global $DB;

        if (!$this->is_table_exists()) {
            throw new \moodle_exception('tablenotfound', 'mod_contentdesigner');
        }

        $record += [
            'contentdesignerid' => $contentdesignerid,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $instanceid = $DB->insert_record($this->tablename(), $record);

        if (!$DB->record_exists('contentdesigner_options', ['instance' => $instanceid, 'element' => $this->elementid])) {
            $options = [];
            foreach ($this->get_options_fields() as $field) {
                $default = get_config('mod_contentdesigner', $field);
                $options[$field] = ($default === false) ? null : $default;
            }
            $options['element'] = $this->elementid;
            $options['instance'] = $instanceid;
            $options['timecreated'] = time();
            $options['timemodified'] = time();
            $DB->insert_record('contentdesigner_options', $options);
        }
        return $instanceid;
    }

    /**
     * Get the element instance data, merged with its general options. Filter by the visible status.
     *
     * The description is formatted for display by default. Callers that edit or copy the data must ask for the
     * stored text ($formatted = false), otherwise saving would store the filter output (e.g. collapsed multilang).
     *
     * @param int $instanceid Instance id.
     * @param bool $visible Filter by visibility.
     * @param bool $formatted Whether to format the description for display.
     * @return \stdClass|bool Instance record
     */
    public function get_instance(int $instanceid, $visible = false, bool $formatted = true) {
        global $DB;

        if (!$this->is_table_exists()) {
            return false;
        }
        $conditions = ['id' => $instanceid];
        if ($visible) {
            $conditions['visible'] = 1;
        }
        if (!$instance = $DB->get_record($this->tablename(), $conditions)) {
            return false;
        }
        $options = $this->get_instance_options($instanceid);
        $record = (object) array_merge($options, (array) $instance);
        $record->optionid = $options['id'] ?? null;

        if ($formatted && !empty($record->description)) {
            $record->description = file_rewrite_pluginfile_urls(
                $record->description,
                'pluginfile.php',
                $this->context->id,
                'mod_contentdesigner',
                $this->element_shortname() . 'description',
                $instanceid
            );
            $record->description = format_text(
                $record->description,
                $record->descriptionformat ?? FORMAT_HTML,
                ['context' => $this->context]
            );
        }
        return $record;
    }

    /**
     * Get the content id of the elemnet instance.
     *
     * @param int $instanceid Element instance id.
     * @return int|bool ID of content.
     */
    public function get_instance_contentid(int $instanceid) {
        global $DB;
        $contentid = $DB->get_field('contentdesigner_content', 'id', ['element' => $this->elementid, 'instance' => $instanceid]);
        return ($contentid) ? $contentid : false;
    }

    /**
     * Get the element instance general options.
     *
     * @param int $instanceid Element instance id.
     * @return array Record of the general options for the elemnent isntnace.
     */
    public function get_instance_options($instanceid): array {
        global $DB;

        return (array) ($DB->get_record('contentdesigner_options', [
            'instance' => $instanceid, 'element' => $this->elementid,
        ], '*', IGNORE_MULTIPLE) ?: []);
    }

    /**
     * Prepare the data of an element instance, for its rendering or (with $formatted = false) for its edit form.
     *
     * @param int $instanceid Element instance id.
     * @param bool $formatted Whether to format the description for display; false for the edit form.
     * @return object
     */
    public function prepare_formdata($instanceid, bool $formatted = true) {
        $instancedata = (array) $this->get_instance($instanceid, false, $formatted);
        $instancedata['cmid'] = $this->cmid;
        return (object) ($instancedata);
    }

    /**
     * Process the update of element instance and genreal options.
     * If element doesn't have any chapters then create new default chpater and inserted into the chapter.
     *
     * The instance and the chapter must belong to the activity of the element; the caller has checked that the user
     * may edit its content.
     *
     * @param \stdClass $data Submitted element moodle form data
     * @return void
     */
    public function update_element($data) {
        global $DB;

        // Never write to an instance or a chapter of another activity.
        $data->contentdesignerid = $this->cm->instance;
        if (!empty($data->instanceid)) {
            access::get_element_instance($this, (int) $data->instanceid, $this->cm->instance);
        }
        if (!empty($data->chapterid)) {
            access::get_chapter((int) $data->chapterid, $this->cm->instance);
        }
        $this->clean_general_options($data);

        $transaction = $DB->start_delegated_transaction();
        try {
            $instanceid = $this->update_instance($data);
            // Setup instanceid if the elment is not inserted before.
            $data->instance = ($data->instanceid) ?: $instanceid;
            if ($this->supports_content() && $this->supports_multiple_instance()) {
                $editor = editor::get_editor($this->cmid);
                if (empty($data->chapterid)) {
                    $data->chapterid = $editor->chapter->get_default($this->cm->instance, true);
                }
                $position = (isset($data->position) && $data->position == 'top') ? 1 : 0;
                // Insert element in content section.
                $content = $editor->add_module_element($this, $data->instance, $data->chapterid, $position);
                $data->contentid = $content->id;
                // Add the content id of the element in chapter sequence.
                $editor->set_elements_inchapter($data->chapterid, $content->id);
            }

            $this->save_areafiles($data);

            // Update the element general options.
            $this->update_options($data);

            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
    }

    /**
     * Drops general options that are not valid CSS values, as they end up in style attributes.
     *
     * @param \stdClass $data Submitted element form data.
     */
    protected function clean_general_options(\stdClass $data): void {
        foreach (['margin', 'padding'] as $field) {
            if (isset($data->$field)) {
                $data->$field = css::clean_spacing($data->$field);
            }
        }
        foreach (['abovecolorbg', 'belowcolorbg'] as $field) {
            if (isset($data->$field)) {
                $data->$field = css::clean_background($data->$field);
            }
        }
    }

    /**
     * Update the general options of the element instance.
     *
     * @param \stdClass $data
     * @return void
     */
    public function update_options($data) {
        global $DB;

        if (empty($data->element)) {
            return false;
        }

        $optiondata = clone $data;
        $optiondata->element = $this->elementid;
        $optiondata->instance = $data->instance;
        $optiondata->timemodified = time();
        if ($options = $this->get_instance_options($data->instance)) {
            $optiondata->id = $options['id'];
            $DB->update_record('contentdesigner_options', $optiondata);
        } else {
            unset($optiondata->id);
            $optiondata->timecreated = time();
            $DB->insert_record('contentdesigner_options', $optiondata);
        }
    }

    /**
     * Update the element instance. Override the function in elements element class to add custom rules.
     *
     * @param \stdClass $data
     * @return int The instance id.
     */
    public function update_instance($data) {
        global $DB;

        $data->timemodified = time();
        if ($data->instanceid == false) {
            $data->timecreated = time();
            return $DB->insert_record($this->tablename, $data);
        } else {
            $data->id = $data->instanceid;
            $DB->update_record($this->tablename, $data);
            return $data->id;
        }
    }

    /**
     * Delete an element instance with its general options, files and the data of its users.
     *
     * The files of the instance in the areas of mod_contentdesigner are deleted here: the background image, the
     * description and the areas of {@see areafiles()} (item id: the instance id).
     * Elements with own data override {@see delete_instance_data()}.
     *
     * @param int $instanceid
     * @return bool status.
     */
    public function delete_element($instanceid) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        try {
            if ($this->is_table_exists() && $DB->record_exists($this->tablename(), ['id' => $instanceid])) {
                $this->delete_instance_data($instanceid);
                $DB->delete_records($this->tablename(), ['id' => $instanceid]);
            }
            $DB->delete_records('contentdesigner_content', ['element' => $this->elementid, 'instance' => $instanceid]);
            $DB->delete_records('contentdesigner_options', ['element' => $this->elementid, 'instance' => $instanceid]);
            if ($this->context) {
                // The general areas of the element and its own areas in mod_contentdesigner ({@see areafiles()}).
                $areas = [$this->shortname . 'elementbg', $this->shortname . 'description'];
                foreach (method_exists($this, 'areafiles') ? $this->areafiles() : [] as $area) {
                    $areas[] = 'cdelement_' . $this->shortname . '_' . $area;
                }
                $fs = get_file_storage();
                foreach ($areas as $area) {
                    $fs->delete_area_files($this->context->id, 'mod_contentdesigner', $area, $instanceid);
                }
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
        return true;
    }

    /**
     * Deletes the data of an element instance before the instance itself is deleted: user data, files, records of
     * other tables. The instance record still exists.
     *
     * @param int $instanceid The instance id.
     */
    protected function delete_instance_data(int $instanceid): void {
    }

    /**
     * Deletes the data of the learner in this element that belongs to an attempt, when the attempt is deleted.
     *
     * @param \stdClass $attempt The attempt (record of contentdesigner_attempts).
     */
    public function delete_attempt_data(\stdClass $attempt): void {
    }

    /**
     * Deletes the data of all learners in this element in an activity (course reset, activity deletion).
     *
     * @param int $contentdesignerid The activity instance id.
     */
    public function reset_userdata(int $contentdesignerid): void {
    }

    /**
     * Update the visibility of the elements instance.
     *
     * @param int $instanceid Element instance id.
     * @param int $visibility Status of the element visibility
     * @return bool Result of the DB update
     */
    public function update_visibility($instanceid, $visibility) {
        global $DB;
        $result = $DB->update_record($this->tablename(), (object) [
            'id' => $instanceid,
            'visible' => $visibility ? 1 : 0,
            'timemodified' => time(),
        ]);
        $this->touch_options($instanceid);
        return $result;
    }

    /**
     * Marks the general options of an instance as modified, so that global search indexes the instance again (its
     * documents are the general options).
     *
     * @param int $instanceid Element instance id.
     */
    public function touch_options(int $instanceid): void {
        global $DB;
        $DB->set_field(
            'contentdesigner_options',
            'timemodified',
            time(),
            ['element' => $this->elementid, 'instance' => $instanceid]
        );
    }

    /**
     * Generate the classes based on the genenral options.
     *
     * The style values are validated again here, as older versions stored them unchecked.
     *
     * @param \stdClass $instance Element instance data
     * @param \stdClass|false $option element general options data.
     * @return \stdClass $instance Instance data.
     */
    public function load_option_classes($instance, $option) {
        $class[] = !empty($instance->animation) ? 'animation' : '';
        $instance->entranceanimation = json_encode([
            'animation' => $instance->animation ?? '',
            'duration' => $instance->duration ?? '',
            'delay' => !empty($instance->delay) ? (string) (int) $instance->delay : '',
        ]);

        $class[] = !empty($instance->hidedesktop) ? 'd-lg-none' : 'd-lg-block';
        $class[] = !empty($instance->hidetablet) ? 'd-md-none' : 'd-md-block';
        $class[] = !empty($instance->hidemobile) ? 'd-none' : 'd-block';

        $style = [];
        if ($margin = css::clean_spacing($instance->margin ?? '')) {
            $style[] = 'margin: ' . $margin . ';';
        }
        if ($padding = css::clean_spacing($instance->padding ?? '')) {
            $style[] = 'padding: ' . $padding . ';';
        }
        $abovecolorbg = css::clean_background($option->abovecolorbg ?? '');
        $belowcolorbg = css::clean_background($option->belowcolorbg ?? '');
        $class[] = !empty($option->backimage) ? 'backimage' : '';
        $class[] = ($abovecolorbg || $belowcolorbg) ? 'backcolor' : '';
        $instance->classes = trim(implode(' ', array_filter($class)));
        $instance->style = implode('', $style);

        $instance->abovecolorbg = $abovecolorbg ? 'background: ' . $abovecolorbg . ';' : '';
        $instance->backimage = !empty($option->backimage)
            ? 'background-image: url(' . (new \moodle_url($option->backimage))->out(false) . ');' : '';
        $instance->belowcolorbg = $belowcolorbg ? 'background: ' . $belowcolorbg . ';' : '';
        if (!empty($instance->direction)) {
            $instance->scrolleffect = json_encode([
                'start' => (int) ($instance->viewport ?? 0),
                'direction' => $instance->direction,
                'speed' => (int) ($instance->speed ?? 0),
            ]);
        }
        return $instance;
    }

    /**
     * Load the classes from elements, If need to add classes in parent div then use this method
     * Otherwise use the render function to add classes.
     *
     * @param \stdClass $instance
     * @param \stdClass|false $option
     * @return void
     */
    public function generate_element_classes(&$instance, $option) {
        $instance = $this->load_option_classes($instance, $option);
    }

    /**
     * Whether the learner of the attempt has completed all mandatory elements of the activity.
     *
     * @return bool False if the activity has no mandatory elements or one is not completed.
     */
    public function get_mandatory_completion() {
        global $DB;

        $sql = 'SELECT cc.*, ce.shortname AS elementname
                  FROM {contentdesigner_content} cc
                  JOIN {contentdesigner_elements} ce ON ce.id = cc.element
                 WHERE cc.contentdesignerid = ?';
        $contents = $DB->get_records_sql($sql, [$this->cm->instance]);

        $elements = [];
        $hasmandatory = false;
        $installed = editor::get_element_names();
        foreach ($contents as $content) {
            if (!in_array($content->elementname, $installed, true)) {
                continue;
            }
            $elements[$content->elementname] ??= editor::get_element($content->elementname, $this->cmid, $this->cdattempt);
            $element = $elements[$content->elementname];
            $instance = $element->get_instance($content->instance, true);
            if (!$instance || empty($instance->mandatory)) {
                continue;
            }
            $hasmandatory = true;
            if ($element->prevent_nextelements($instance)) {
                return false;
            }
        }
        return $hasmandatory;
    }

    /**
     * Get the options fields for the elements.
     * @return array
     */
    public function get_options_fields() {
        return [
            'element', 'instance', 'margin', 'padding', 'abovecolorbg', 'belowcolorbg',
            'animation', 'duration', 'delay', 'direction', 'speed', 'viewport', 'hidedesktop', 'hidetablet',
            'hidemobile', 'timecreated', 'timemodified', 'description', 'showdescription',
        ];
    }

    /**
     * Prepares the grading of the element before {@see get_mark()} is called, for elements that support grades.
     */
    public function pre_process_grade() {
    }

    /**
     * The mark of the learner of the attempt in an instance, for elements that support grades.
     *
     * @param \stdClass $instance The element instance.
     * @return float
     */
    public function get_mark($instance) {
        return 0;
    }

    /**
     * The maximum mark of an instance, for elements that support grades.
     *
     * @param \stdClass $instance The element instance.
     * @param int|null $userid The learner.
     * @return float
     */
    public function get_max_mark($instance, $userid = null) {
        return 0;
    }

    /**
     * Calculate the share of a mark in the grade of the activity.
     *
     * @param float $qmark The mark obtained by the user.
     * @param float $qmaxmark The maximum mark for the question.
     * @param int $userid The ID of the user.
     * @return int The calculated grade.
     */
    public function calculate_grade($qmark, $qmaxmark, $userid) {
        global $DB;

        $contentdesigner = $DB->get_record('contentdesigner', ['id' => $this->cm->instance]);
        if (!$contentdesigner || $contentdesigner->grade <= 0) {
            return 0;
        }
        $maxmark = editor::get_contentdesigner_overallelement_maxmark($this->cm->instance, $this->cdattempt, $userid);
        if ($maxmark) {
            return (int) round(($qmark / $maxmark) * $contentdesigner->grade);
        }
        return 0;
    }

    /**
     * Adds the columns of the element to the report of the activity (cdaddon_report).
     *
     * @param array $columns The columns.
     * @param array $headers The headers.
     * @param \table_sql $table The report table.
     */
    public function define_report_columns(array &$columns, array &$headers, $table) {
    }

    /**
     * The value of a report column added in {@see define_report_columns()}.
     *
     * @param string $colname The column.
     * @param \stdClass $data The row.
     * @return string|null
     */
    public function col_report_field($colname, $data) {
        return null;
    }

    /**
     * The data of an element shown above the content (supports_top_instance()) in the editor.
     *
     * @return string|array
     */
    public function get_editor_data() {
        return '';
    }

    /**
     * The rendered element shown above the content (supports_top_instance()) for learners.
     *
     * @return array
     */
    public function render_module_topcontents() {
        return [];
    }

    /**
     * Reports that the progress of a learner in this element changed, for example after the learner answered it.
     *
     * Addons use this, for example, to update the activity completion.
     *
     * @param int $userid The learner.
     */
    public function progress_updated(int $userid): void {
        extensions::dispatch(new progress_updated($this->cm, $userid, $this->shortname));
    }

    /**
     * Prepare data for the duplicate element.
     *
     * @param \stdClass $record
     * @return \stdClass
     */
    public function prepare_duplicatedata($record) {
        return $record;
    }
}

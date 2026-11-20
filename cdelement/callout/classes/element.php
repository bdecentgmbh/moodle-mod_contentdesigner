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
 * Extended class of elements for Callout.
 *
 * @package    cdelement_callout
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace cdelement_callout;

use core_text;
use html_writer;

/**
 * Callout element: a coloured box with an icon, an optional title and rich text.
 */
class element extends \mod_contentdesigner\elements {
    /**
     * Shortname of the element.
     */
    public const SHORTNAME = 'callout';

    /**
     * The type used for new callouts and for unknown stored values.
     */
    public const DEFAULT_TYPE = 'info';

    /**
     * The types of callouts with their Font Awesome icon. The keys are the only values stored and rendered.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'info' => 'fa-circle-info',
        'tip' => 'fa-lightbulb',
        'warning' => 'fa-triangle-exclamation',
        'danger' => 'fa-circle-xmark',
        'success' => 'fa-circle-check',
        'speech' => 'fa-comment',
        'reflection' => 'fa-circle-question',
        'definition' => 'fa-book',
        'quote' => 'fa-quote-left',
        'example' => 'fa-pencil',
        'keytakeaway' => 'fa-star',
    ];

    /**
     * Element name which is visible for the users.
     *
     * @return string
     */
    public function element_name() {
        return get_string('pluginname', 'cdelement_callout');
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
     * @param \renderer_base $output
     * @return string HTML fragment
     */
    public function icon($output) {
        return html_writer::tag('i', '', ['class' => 'fa ' . self::TYPES['info'] . ' icon pluginicon', 'aria-hidden' => 'true']);
    }

    /**
     * List of areafiles which is used the mod_contentdesigner as component.
     *
     * @return array
     */
    public function areafiles() {
        return ['content'];
    }

    /**
     * Search area definition.
     *
     * @return array Table and fields to search.
     */
    public function search_area_list(): array {
        return ['cdelement_callout' => 'content, contentformat'];
    }

    /**
     * The type, if it is a known type, otherwise the default type.
     *
     * @param string $type A type of callout, from a form, the database or a backup.
     * @return string A key of {@see self::TYPES}.
     */
    public static function clean_type($type): string {
        return array_key_exists((string) $type, self::TYPES) ? (string) $type : self::DEFAULT_TYPE;
    }

    /**
     * The names of the types, for the form.
     *
     * @return array<string, string>
     */
    public static function get_type_options(): array {
        $options = [];
        foreach (array_keys(self::TYPES) as $type) {
            $options[$type] = get_string('callouttype_' . $type, 'cdelement_callout');
        }
        return $options;
    }

    /**
     * Options of the content editor: uploads limited by the course and the site, embedded content kept only for
     * authors who may trust content ({@see can_trust_content()}).
     *
     * @param \context $context The module context.
     * @return array
     */
    public function editor_options($context) {
        global $CFG;
        return [
            'subdirs' => 1,
            'maxbytes' => get_user_max_upload_file_size($context, $CFG->maxbytes, $this->course->maxbytes ?? 0),
            'accepted_types' => '*',
            'context' => $context,
            'noclean' => self::can_trust_content($context),
            'maxfiles' => self::EDITOR_UNLIMITED_FILES,
        ];
    }

    /**
     * Whether the current user may save content that is shown without cleaning (as in cdelement_richtext).
     *
     * @param \context $context The context of the activity.
     * @return bool
     */
    public static function can_trust_content(\context $context): bool {
        return has_capability('moodle/site:trustcontent', $context);
    }

    /**
     * A known text format, FORMAT_HTML otherwise.
     *
     * @param mixed $format The submitted, stored or restored format.
     * @return int
     */
    public static function clean_format($format): int {
        $format = (int) $format;
        return array_key_exists($format, format_text_menu()) ? $format : FORMAT_HTML;
    }

    /**
     * Element form element definition.
     *
     * @param \MoodleQuickForm $mform
     * @param \mod_contentdesigner\form\general_element_form $formobj
     * @return void
     */
    public function element_form(&$mform, $formobj) {
        $mform->addElement('select', 'callouttype', get_string('callouttype', 'cdelement_callout'), self::get_type_options());
        $mform->setType('callouttype', PARAM_ALPHA);
        $mform->setDefault('callouttype', self::DEFAULT_TYPE);
        $mform->addHelpButton('callouttype', 'callouttype', 'cdelement_callout');

        $mform->addElement('text', 'callouttitle', get_string('callouttitle', 'cdelement_callout'), ['size' => '50']);
        $mform->setType('callouttitle', PARAM_TEXT);
        $mform->addRule('callouttitle', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('callouttitle', 'callouttitle', 'cdelement_callout');

        $editoroptions = $this->editor_options($formobj->get_customdata()['context']);
        $mform->addElement('editor', 'content_editor', get_string('calloutcontent', 'cdelement_callout'), null, $editoroptions);
        $mform->setType('content_editor', PARAM_RAW);
        $mform->addRule('content_editor', null, 'required');
        $mform->addHelpButton('content_editor', 'calloutcontent', 'cdelement_callout');
    }

    /**
     * Validates the type and the title of the callout.
     *
     * @param array $data The submitted data.
     * @param array $files The submitted files.
     * @return array Errors by form field.
     */
    public function validation(array $data, array $files): array {
        $errors = [];
        if (!isset($data['callouttype']) || !array_key_exists((string) $data['callouttype'], self::TYPES)) {
            $errors['callouttype'] = get_string('error:invalidtype', 'cdelement_callout');
        }
        if (isset($data['callouttitle']) && core_text::strlen((string) $data['callouttitle']) > 255) {
            $errors['callouttitle'] = get_string('maximumchars', '', 255);
        }
        return $errors;
    }

    /**
     * Render the view of element instance, which is displayed in the student view.
     *
     * @param \stdClass $instance
     * @return string HTML
     */
    public function render($instance) {
        global $OUTPUT;

        if (empty($instance->visible)) {
            return '';
        }
        $context = $this->get_context();

        $content = file_rewrite_pluginfile_urls(
            (string) $instance->content,
            'pluginfile.php',
            $context->id,
            'mod_contentdesigner',
            'cdelement_callout_content',
            $instance->id
        );
        // Shown without cleaning only if the author could trust content (moodle/site:trustcontent) when saving it.
        $content = format_text($content, self::clean_format($instance->contentformat ?? FORMAT_HTML), [
            'context' => $context,
            'noclean' => !empty($instance->contenttrust),
        ]);

        // The stored values are restricted again, as backups may contain anything.
        $type = self::clean_type($instance->callouttype ?? '');
        $title = trim((string) ($instance->callouttitle ?? ''));

        return $OUTPUT->render_from_template('cdelement_callout/callout', [
            'type' => $type,
            'typename' => get_string('callouttype_' . $type, 'cdelement_callout'),
            'icon' => self::TYPES[$type],
            'title' => ($title !== '') ? format_string($title, true, ['context' => $context]) : '',
            'content' => $content,
        ]);
    }

    /**
     * Process the update of the element instance.
     *
     * @param \stdClass $data Submitted element moodle form data
     * @return int The instance id.
     */
    public function update_instance($data) {
        global $DB;

        $formdata = clone $data;
        if (isset($formdata->content_editor)) {
            $formdata->contentformat = self::clean_format($formdata->content_editor['format'] ?? FORMAT_HTML);
            $formdata->content = $formdata->content_editor['text'];
        }
        $formdata->contenttrust = (int) self::can_trust_content($this->get_context());
        $formdata->callouttype = self::clean_type($formdata->callouttype ?? '');
        $formdata->callouttitle = core_text::substr(clean_param((string) ($formdata->callouttitle ?? ''), PARAM_TEXT), 0, 255);
        $formdata->timemodified = time();
        if (empty($formdata->instanceid)) {
            $formdata->timecreated = time();
            return $DB->insert_record($this->tablename, $formdata);
        }
        $formdata->id = $formdata->instanceid;
        $DB->update_record($this->tablename, $formdata);
        return $formdata->id;
    }

    /**
     * Save the area files data after the element instance moodle_form submitted.
     *
     * @param \stdClass $data Submitted moodle_form data.
     */
    public function save_areafiles($data) {
        global $DB;

        parent::save_areafiles($data);
        if (empty($data->instance) || !isset($data->content_editor)) {
            return;
        }
        $context = !empty($data->contextid) ? \context::instance_by_id($data->contextid, MUST_EXIST) : $this->get_context();
        $data = file_postupdate_standard_editor(
            $data,
            'content',
            $this->editor_options($context),
            $context,
            'mod_contentdesigner',
            'cdelement_callout_content',
            $data->instance
        );
        $DB->update_record('cdelement_callout', (object) [
            'id' => $data->instance,
            'content' => $data->content,
            'contentformat' => self::clean_format($data->contentformat),
        ]);
    }

    /**
     * Prepare the form editor elements file data before render the element form.
     *
     * @param \stdClass $data
     * @return \stdClass
     */
    public function prepare_standard_file_editor(&$data) {
        $data = parent::prepare_standard_file_editor($data);
        $context = $this->get_context();

        if (!isset($data->id)) {
            $data->id = null;
            $data->contentformat = FORMAT_HTML;
            $data->content = '';
        }
        file_prepare_standard_editor(
            $data,
            'content',
            $this->editor_options($context),
            $context,
            'mod_contentdesigner',
            'cdelement_callout_content',
            $data->id
        );
        return $data;
    }

    /**
     * Prepare data for the duplicate element. The editor keeps the draft area with the copied files, if prepared.
     *
     * @param \stdClass $record
     * @return \stdClass
     */
    public function prepare_duplicatedata($record) {
        if (!isset($record->content_editor['itemid'])) {
            $record->content_editor = ['text' => $record->content, 'format' => $record->contentformat];
        }
        return $record;
    }

    /**
     * Deletes the files of the content before the instance itself.
     *
     * @param int $instanceid The instance id.
     */
    protected function delete_instance_data(int $instanceid): void {
        if ($this->context) {
            get_file_storage()->delete_area_files(
                $this->context->id,
                'mod_contentdesigner',
                'cdelement_callout_content',
                $instanceid
            );
        }
    }
}

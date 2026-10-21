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
 * @package    cdelement_h5p
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace cdelement_h5p;

use mod_contentdesigner\editor;
use mod_contentdesigner\hook\h5p_player;
use mod_contentdesigner\local\extensions;
use core_h5p\player;
use core_h5p\factory;
use html_writer;

/**
 * Element h5p definition.
 */
class element extends \mod_contentdesigner\elements {
    /**
     * Shortname of the element.
     */
    const SHORTNAME = 'h5p';

    /**
     * Element name which is visbile for the users
     *
     * @return string
     */
    public function element_name() {
        return get_string('pluginname', 'cdelement_h5p');
    }

    /**
     * Verify the element is supports the grade.
     *
     * @return bool
     */
    public function supports_grade() {
        return true;
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
        global $CFG;
        return (file_exists($CFG->dirroot . '/mod/h5pactivity/pix/monologo.png'))
            ? $output->pix_icon('monologo', '', 'mod_h5pactivity', ['class' => 'icon pluginicon'])
            : $output->pix_icon('icon', '', 'mod_h5pactivity', ['class' => 'icon pluginicon']);
    }

    /**
     * List of areafiles which is used the mod_contentdesigner as component.
     *
     * @return array
     */
    public function areafiles() {
        return ['package'];
    }

    /**
     * Save the area files data after the element instance moodle_form submittted.
     *
     * @param stdclas $data Submitted moodle_form data.
     */
    public function save_areafiles($data) {
        parent::save_areafiles($data);
        file_save_draft_area_files($data->package, $data->contextid, 'cdelement_h5p', 'package', $data->instance);
    }

    /**
     * Prepare the form editor elements file data before render the elemnent form.
     *
     * @param stdclass $formdata
     * @return stdclass
     */
    public function prepare_standard_file_editor(&$formdata) {
        $formdata = parent::prepare_standard_file_editor($formdata);

        if (isset($formdata->instance)) {
            $draftitemid = file_get_submitted_draft_itemid('package');
            file_prepare_draft_area(
                $draftitemid,
                $this->context->id,
                'cdelement_h5p',
                'package',
                $formdata->instance,
                ['subdirs' => 0, 'maxfiles' => 1]
            );
            $formdata->package = $draftitemid;
        }
        return $formdata;
    }

    /**
     * Analyze the H5P is mantory to view upcoming then check the instance is attempted.
     *
     * @param \stdClass $instance Instance data of the element.
     * @return bool True if need to stop the next instance Otherwise false if render of next elements.
     */
    public function prevent_nextelements($instance): bool {
        global $USER, $DB;
        if (empty($instance->mandatory) || empty($this->cdattempt)) {
            return false;
        }
        return !$DB->record_exists('cdelement_h5p_completion', [
            'instance' => $instance->id,
            'userid' => $USER->id,
            'cdattemptid' => $this->cdattempt->id,
            'completion' => 1,
        ]);
    }

    /**
     * Element form element definition.
     *
     * @param moodle_form $mform
     * @param genreal_element_form $formobj
     * @return void
     */
    public function element_form(&$mform, $formobj) {
        $options = [
            'accepted_types' => ['.h5p'],
            'maxbytes' => 0,
            'maxfiles' => 1,
            'subdirs' => 0,
        ];

        $mform->addElement('filemanager', 'package', get_string('package', 'mod_h5pactivity'), null, $options);
        $mform->addHelpButton('package', 'package', 'mod_h5pactivity');
        $mform->addRule('package', null, 'required');

        $options = [
            0 => get_string('no'),
            1 => get_string('yes'),
        ];
        $default = get_config('cdelement_h5p', 'mandatory');
        $mform->addElement('select', 'mandatory', get_string('mandatory', 'mod_contentdesigner'), $options);
        $mform->addHelpButton('mandatory', 'mandatory', 'mod_contentdesigner');
        $mform->setDefault('mandatory', $default ?: 0);
    }

    /**
     * Render the view of element instance, Which is displayed in the student view.
     *
     * @param \stdClass $data
     * @return string
     */
    public function render($data) {
        global $PAGE;

        if (!isset($data->id)) {
            return '';
        }

        $file = editor::get_editor($data->cmid)->get_element_areafiles('package', $data->id, 'cdelement_h5p');
        if (!$file) {
            return '';
        }
        $PAGE->requires->js_call_amd('cdelement_h5p/h5p', 'init', [
            'instance' => $data->instance,
            'cdattemptid' => $this->cdattempt->id ?? 0,
            'storemaxscore' => has_capability('mod/contentdesigner:viewcontenteditor', $this->get_context()),
        ]);

        // Convert display options to a valid object.
        $factory = new factory();
        $core = $factory->get_core();
        $config = \core_h5p\helper::decode_display_options($core, false);
        $config->export = false;
        $config->embed = false;
        $config->copyright = false;

        // Addons can track the content with xAPI, for example to save its state.
        $instance = (object) ['id' => $data->instance, 'contentdesignerid' => $this->cm->instance];
        $component = extensions::dispatch(new h5p_player($this->cm, $instance))->get_component();

        return html_writer::div(
            player::display($file, $config, true, $component, false),
            'h5p-element-instance',
            ['data-instanceid' => $data->instance]
        );
    }

    /**
     * Retrieves an H5P instance record from the database.
     *
     * @param int $instanceid The instance id.
     * @return \stdClass
     */
    public static function get_h5p_instance($instanceid) {
        global $DB;
        return $DB->get_record('cdelement_h5p', ['id' => $instanceid], '*', MUST_EXIST);
    }

    /**
     * Deletes the results, saved states and the package of an instance.
     *
     * @param int $instanceid The instance id.
     */
    protected function delete_instance_data(int $instanceid): void {
        global $DB;
        $DB->delete_records('cdelement_h5p_completion', ['instance' => $instanceid]);
        $DB->delete_records('cdelement_h5p_xapistates', ['registration' => 'cdelement_h5p-' . $instanceid]);
        if ($this->context) {
            get_file_storage()->delete_area_files($this->context->id, 'cdelement_h5p', 'package', $instanceid);
        }
    }

    /**
     * Deletes the results of the learner in an attempt.
     *
     * @param \stdClass $attempt The attempt.
     */
    public function delete_attempt_data(\stdClass $attempt): void {
        global $DB;
        $DB->delete_records('cdelement_h5p_completion', ['cdattemptid' => $attempt->id, 'userid' => $attempt->userid]);
    }

    /**
     * Deletes the results and saved states of all learners in an activity.
     *
     * @param int $contentdesignerid The activity instance id.
     */
    public function reset_userdata(int $contentdesignerid): void {
        global $DB;
        $DB->delete_records_select('cdelement_h5p_completion',
            'instance IN (SELECT id FROM {cdelement_h5p} WHERE contentdesignerid = ?)', [$contentdesignerid]);
        $registration = $DB->sql_concat("'cdelement_h5p-'", 'h.id');
        $DB->delete_records_select('cdelement_h5p_xapistates',
            "registration IN (SELECT $registration FROM {cdelement_h5p} h WHERE h.contentdesignerid = ?)", [$contentdesignerid]);
    }

    /**
     * The result of the learner of the attempt in an instance.
     *
     * @param \stdClass $instance The instance.
     * @return \stdClass|null The record of cdelement_h5p_completion.
     */
    protected function get_result(\stdClass $instance): ?\stdClass {
        global $DB;
        if (empty($this->cdattempt)) {
            return null;
        }
        return $DB->get_record('cdelement_h5p_completion',
            ['cdattemptid' => $this->cdattempt->id, 'instance' => $instance->id], '*', IGNORE_MULTIPLE) ?: null;
    }

    /**
     * The score of the learner of the attempt in an instance.
     *
     * @param \stdClass $instance The instance of the H5P element.
     * @return float
     */
    public function get_mark($instance) {
        $result = $this->get_result($instance);
        return $result ? (float) $result->score : 0;
    }

    /**
     * The maximum score of an instance: the one of the learner's result, otherwise the one reported to the teacher.
     *
     * @param \stdClass $instance The instance of the H5P element.
     * @param int|null $userid The ID of the user.
     * @return float The maximum mark.
     */
    public function get_max_mark($instance, $userid = null) {
        global $DB;
        $result = $this->get_result($instance);
        if ($result && ($scoredata = json_decode($result->scoredata ?? '')) && isset($scoredata->max)) {
            return (float) $scoredata->max;
        }
        return (float) $DB->get_field('cdelement_h5p', 'maxscore', ['id' => $instance->id]);
    }

    /**
     * Define the each h5p instance as different column for the grade report.
     *
     * @param array $columns
     * @param array $headers
     * @param stdClass $table
     * @return void
     */
    public function define_report_columns(array &$columns, array &$headers, $table) {
        global $DB;

        $records = $DB->get_records(
            'cdelement_h5p',
            ['contentdesignerid' => $this->cm->instance, 'visible' => self::STATUS_VISIBLE]
        );

        $i = 1;
        foreach ($records as $id => $record) {
            $columns[] = 'cdelement_h5p_' . $id;
            $headers[] = get_string('h5pnumber', 'mod_contentdesigner', $i);
            $table->no_sorting('cdelement_h5p_' . $id);
            $i++;
        }
    }

    /**
     * Get the grade for this question element.
     * This function is used to display the grade in the contentdesigner report.
     * The grade is calculated by the calculate_grade function.
     *
     * @param string $colname The name of the column.
     * @param stdClass $data The data of the column.
     * @return string
     */
    public function col_report_field($colname, $data) {
        global $DB;
        if (str_starts_with($colname, 'cdelement_h5p_')) {
            $h5pelementid = str_replace('cdelement_h5p_', '', $colname);
            $h5pcompletion = $DB->get_record('cdelement_h5p_completion', ['cdattemptid' => $data->attemptid, 'userid' => $data->id,
                'instance' => $h5pelementid]);
            $record = $DB->get_record('cdelement_h5p', ['id' => $h5pelementid]);

            $maxscore = 0;
            $score = 0;
            $h5pgrade = '';

            if ($data->attemptid !== null) {
                $h5pgrade = 0;
            }

            if ($record && $h5pcompletion) {
                $h5pgrade = $this->calculate_grade($h5pcompletion->score, $record->maxscore, $data->id);
                $score += $h5pcompletion->score;
                $maxscore += $record->maxscore;
            }

            $stateclass = $this->h5p_graded_state_class($score, $maxscore);
            if (is_numeric($h5pgrade)) {
                $gradehtml = html_writer::tag('h6', $score . ' / ' . $h5pgrade . '%', ['class' => 'h5p-gradestate ' . $stateclass]);
                $output = \html_writer::tag('div', $gradehtml, ['class' => 'h5p-grade']);
            }
            return $output ?? '-';
        }
    }

    /**
     * Get the grade class for this H5P element.
     *
     * @param int $h5pscore The score of the H5P element.
     * @param int $maxgrade The maximum grade of the H5P element.
     *
     * @return string The class of the grade.
     */
    public static function h5p_graded_state_class($h5pscore, $maxgrade) {
        if ($h5pscore <= 0) {
            return 'text-danger';
        } else if ($h5pscore >= $maxgrade) {
            return 'text-success';
        } else {
            return 'text-warning';
        }
    }
}

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
 * Extended class of elements for Videotime.
 *
 * @package   cdelement_videotime
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace cdelement_videotime;

use html_writer;
use cm_info;
use mod_videotime\videotime_instance;
use moodle_url;

/**
 * Videotime element instance extended the base elements.
 */
class element extends \mod_contentdesigner\elements {
    /**
     * Shortname of the element.
     */
    const SHORTNAME = 'videotime';

    /**
     * Element name which is visbile for the users
     *
     * @return string
     */
    public function element_name() {
        return get_string('pluginname', 'cdelement_videotime');
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
        if (!self::videotime_available()) {
            return $output->pix_icon('i/media', get_string('pluginname', 'cdelement_videotime'));
        }
        return $output->pix_icon('monologo', get_string('pluginname', 'cdelement_videotime'), 'videotime');
    }

    /**
     * Whether Video Time (mod_videotime) is installed and enabled. The element has no hard dependency on it: without it,
     * the element is not offered and its instances show a notice instead of the video.
     *
     * @return bool
     */
    public static function videotime_available(): bool {
        if (\core_component::get_component_directory('mod_videotime') === null) {
            return false;
        }
        $enabled = \core\plugininfo\mod::get_enabled_plugins();
        return isset($enabled['videotime']) && class_exists(videotime_instance::class);
    }

    /**
     * The element can only be added while Video Time is available.
     *
     * @return bool
     */
    public function is_available(): bool {
        return self::videotime_available();
    }

    /**
     * Element form element definition.
     *
     * @param moodle_form $mform
     * @param genreal_element_form $formobj
     * @return void
     */
    public function element_form(&$mform, $formobj) {
        global $DB, $PAGE;

        // The courses with Video Time activities that the user may use.
        $options = [];
        $courses = $DB->get_records_sql_menu('SELECT id, fullname FROM {course}
            WHERE id IN (SELECT course FROM {videotime})', []);
        foreach ($courses as $courseid => $coursename) {
            if ($this->can_use_course($courseid)) {
                $options[$courseid] = format_string($coursename, true, ['context' => \context_course::instance($courseid)]);
            }
        }

        $mform->addElement('autocomplete', 'videotimecourseid', get_string('course', 'core'), $options);
        $mform->addRule('videotimecourseid', null, 'required');
        $mform->setDefault('videotimecourseid', $this->course->id ?? $PAGE->course->id);

        // Cmid.
        $options = [
            'ajax' => 'cdelement_videotime/form-videotime-selector',
        ];

        // Add the selected videotime in the options list.
        if (!empty($formobj->get_customdata()['instanceid'])) {
            $videotimeelement = $DB->get_record('cdelement_videotime', ['id' => $formobj->get_customdata()['instanceid'],
                'contentdesignerid' => $this->cm->instance]);
            $videotime = $videotimeelement ? $DB->get_record('videotime', ['id' => $videotimeelement->videotimeid]) : false;
            if ($videotime) {
                $videotimeoptions = [$videotime->id => format_string(
                    $videotime->name,
                    true,
                    ['context' => \context_course::instance($videotime->course)]
                )];
            }
        }
        $mform->addElement(
            'autocomplete',
            'videotimeid',
            get_string('videotimemodules', 'cdelement_videotime'),
            $videotimeoptions ?? [],
            $options
        );
        $mform->addRule('videotimeid', null, 'required');
        $mform->addHelpButton('videotimeid', 'videotimemodules', 'cdelement_videotime');

        // Mandatory options.
        $options = [
            self::DISBLE_MANDATORY => get_string('no'),
            self::ENABLE_MANDATORY => get_string('yes'),
        ];
        $mform->addElement('select', 'mandatory', get_string('mandatory', 'mod_contentdesigner'), $options);
        $mform->addHelpButton('mandatory', 'mandatory', 'mod_contentdesigner');

        // Form video time selector.
        $PAGE->requires->js_call_amd(
            'cdelement_videotime/form-videotime-selector',
            'updateCourseID',
            ['videotimeid', 'videotimecourseid', $this->cmid]
        );
    }

    /**
     * Whether the current user may embed the Video Time activities of a course: those of the course of the activity, and
     * those of courses the user may update.
     *
     * @param int $courseid The course.
     * @return bool
     */
    public function can_use_course(int $courseid): bool {
        if (!empty($this->course->id) && $courseid == $this->course->id) {
            return true;
        }
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        return $context && has_capability('moodle/course:update', $context);
    }

    /**
     * Stores the element, with a Video Time activity the user may embed.
     *
     * @param \stdClass $data The submitted data.
     * @return int The instance id.
     */
    public function update_instance($data) {
        global $DB;
        if (!self::videotime_available()) {
            throw new \moodle_exception('elementnotavailable', 'mod_contentdesigner');
        }
        $data->contentdesignerid = $this->cm->instance;
        $videotime = $DB->get_record('videotime', ['id' => (int) ($data->videotimeid ?? 0)]);
        $current = !empty($data->instanceid) ? $DB->get_record($this->tablename(), ['id' => $data->instanceid]) : null;
        $unchanged = $current && $videotime && $current->videotimeid == $videotime->id;
        if (!$videotime || (!$unchanged && !$this->can_use_course($videotime->course))) {
            throw new \moodle_exception('invalidvideotime', 'cdelement_videotime');
        }
        $data->videotimeid = $videotime->id;
        $data->courseid = $videotime->course;
        return parent::update_instance($data);
    }

    /**
     * The completion of a learner in the Video Time activity of an element.
     *
     * @param \stdClass $instance The element instance.
     * @param int $userid The learner.
     * @return bool|null Whether the learner completed it, null if the activity does not track the completion.
     */
    public function is_videotime_completed(\stdClass $instance, int $userid): ?bool {
        if (!self::videotime_available()) {
            return null;
        }
        try {
            [$course, $cm] = get_course_and_cm_from_instance($instance->videotimeid, 'videotime');
        } catch (\moodle_exception $e) {
            return null;
        }
        $completion = new \completion_info($course);
        if (!$completion->is_enabled($cm)) {
            return null;
        }
        return $completion->get_data($cm, false, $userid)->completionstate == COMPLETION_COMPLETE;
    }

    /**
     * Prepare data for the element moodle form.
     *
     * @param int $instanceid Element instance id.
     * @param bool $formatted Whether to format the description for display; false for the edit form.
     * @return stdclass
     */
    public function prepare_formdata($instanceid, bool $formatted = true) {
        global $DB;

        $instancedata = parent::prepare_formdata($instanceid, $formatted);

        // Include the selected video time related courseid.
        if (!empty($instancedata->videotimeid) && self::videotime_available()) {
            $course = $DB->get_field('videotime', 'course', ['id' => $instancedata->videotimeid]);
            $videotimename = $DB->get_field('videotime', 'name', ['id' => $instancedata->videotimeid]);

            $instancedata->videotimecourseid = $course;
            $instancedata->videotimeoptions = [$instancedata->videotimeid => $videotimename];
        }

        return ($instancedata);
    }

    /**
     * Render the view of element instance, Which is displayed in the student view.
     *
     * @param stdclass $instance
     * @return string HTML
     */
    public function render($instance) {
        global $OUTPUT;
        if (!self::videotime_available()) {
            return html_writer::tag('div', $OUTPUT->notification(
                get_string('videotimeunavailable', 'cdelement_videotime'),
                \core\output\notification::NOTIFY_INFO,
                false
            ), ['class' => 'element-videotime']);
        }
        $content = $this->get_videotime_content($instance->videotimeid);
        return html_writer::tag('div', $content, ['class' => "element-videotime"]);
    }

    /**
     * Analyze the Videotime is mantory to view upcoming then check the instance is attempted.
     *
     * If this is manadatory, then includes the verification module to verify the completion of element.
     *
     * @param stdclass $instance Instance data of the element.
     * @return bool True if need to stop the next instance Otherwise false if render of next elements.
     */
    public function prevent_nextelements($instance): bool {
        global $USER, $PAGE;

        // A mandatory video blocks the next elements until the learner completed its activity, if it tracks completion.
        $result = !empty($instance->mandatory) && $this->is_videotime_completed($instance, $USER->id) === false;
        if ($result) {
            $PAGE->requires->js_call_amd(
                'cdelement_videotime/form-videotime-selector',
                'updateNextElements',
                [$this->cmid, $instance->id]
            );
        }
        return $result;
    }

    /**
     * Replace the element on refersh the content.
     *
     * @return bool
     */
    public function supports_replace_onrefresh(): bool {
        return true;
    }

    /**
     * Get the fallback message for the users to display the access denied message.
     *
     * @param int $courseid
     *
     * @return string
     */
    protected function get_fallback_message(int $courseid) {
        $enrolinstances = enrol_get_instances($courseid, true);
        $self = in_array('self', array_column((array) $enrolinstances, 'enrol'));

        if ($self) {
            $string = get_string('selfenroltoaccess', 'cdelement_videotime');
            $courseurl = new moodle_url('/enrol/index.php', ['id' => $courseid]);
            $string .= html_writer::link($courseurl, get_string('enrolme', 'enrol_self'), ['class' => 'btn btn-primary ms-4']);
        } else {
            $string = get_string('fallbackmessage', 'cdelement_videotime');
        }

        return $string;
    }

    /**
     * Get the video time embeded content,
     *
     * Modified from original filter_videotime/filter.
     *
     * @param int $videotimeid Videotime instance id.
     *
     * @return string
     */
    protected function get_videotime_content(int $videotimeid) {
        global $PAGE, $OUTPUT, $USER;

        // Video time renderer.
        $renderer = $PAGE->get_renderer('mod_videotime');

        try {
            if (!$cm = get_coursemodule_from_instance('videotime', $videotimeid)) {
                $content = $OUTPUT->notification(get_string('videotimemissing', 'cdelement_videotime'));
            } else {
                $context = \context_course::instance($cm->course);
                $canviewcourse = has_capability('moodle/course:view', $context)
                    || is_enrolled($context, $USER, '', true) || self::can_access_as_guest($cm->course);

                // Check if user can access activity.
                if (!$canviewcourse || !cm_info::create($cm)->uservisible) {
                    $content = $this->get_fallback_message($cm->course);
                } else if (get_config('filter_videotime', 'disableifediting') && $PAGE->user_is_editing()) {
                    $content = $OUTPUT->notification(
                        get_string(
                            'videodisabled',
                            'cdelement_videotime',
                            format_string($cm->name, true, ['context' => \context_module::instance($cm->id)])
                        ),
                        \core\output\notification::NOTIFY_SUCCESS
                    );
                } else {
                    $instance = videotime_instance::instance_by_id($cm->instance);
                    $instance->set_embed(true);

                    $defaultrenderer = $renderer;

                    // Allow any subplugin to override video time instance output.
                    foreach (
                        \core_component::get_component_classes_in_namespace(
                            null,
                            'videotime\\instance'
                        ) as $fullclassname => $classpath
                    ) {
                        if (is_subclass_of($fullclassname, videotime_instance::class)) {
                            if ($override = $fullclassname::get_instance($instance->id)) {
                                $instance = $override;
                            }
                            if ($override = $fullclassname::get_renderer($instance->id)) {
                                $renderer = $override;
                            }
                        }
                    }

                    $content = $renderer->render($instance);

                    // Set renderer back to default if override was used.
                    $renderer = $defaultrenderer;
                }
            }
        } catch (\Exception $e) {
            debugging($e->getMessage(), DEBUG_DEVELOPER);
            $content = $OUTPUT->notification(get_string('videotimeerror', 'cdelement_videotime'));
        }

        return $content;
    }

    /**
     * Whether the current user may enter a course as a guest: an enabled guest access without password, or with the
     * password the user entered in this session.
     *
     * @param int $courseid The course.
     * @return bool
     */
    public static function can_access_as_guest(int $courseid): bool {
        global $USER;
        if (!enrol_is_enabled('guest')) {
            return false;
        }
        foreach (enrol_get_instances($courseid, true) as $instance) {
            if ($instance->enrol !== 'guest') {
                continue;
            }
            if ((string) $instance->password === '') {
                return true;
            }
            if (
                isset($USER->enrol_guest_passwords[$instance->id])
                    && $USER->enrol_guest_passwords[$instance->id] === $instance->password
            ) {
                return true;
            }
        }
        return false;
    }
}

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
 * mod_contentdesigner data generator.
 *
 * @package    mod_contentdesigner
 * @category   test
 * @copyright  2013 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * mod_contentdesigner data generator class.
 *
 * @package    mod_contentdesigner
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_contentdesigner_generator extends testing_module_generator {
    /**
     * Create the contentdesigner module instance for testing.
     *
     * @param stdclass $record
     * @param array|null $options
     * @return stdclass
     */
    public function create_instance($record = null, $options = null) {
        $record = (object)(array)$record;
        if (!isset($record->timemodified)) {
            $record->timemodified = time();
        }

        return parent::create_instance($record, (array)$options);
    }

    /**
     * Creates a chapter at the end of an activity.
     *
     * @param stdClass $instance The activity (as returned by create_module).
     * @param array $record Values of cdelement_chapter.
     * @return stdClass The chapter.
     */
    public function create_chapter(stdClass $instance, array $record = []): stdClass {
        global $DB;
        $position = (int) $DB->get_field_sql(
            'SELECT MAX(position) FROM {cdelement_chapter} WHERE contentdesignerid = ?',
            [$instance->id]
        ) + 1;
        $record = (object) ($record + [
            'contentdesignerid' => $instance->id,
            'title' => 'Chapter ' . $position,
            'visible' => 1,
            'learningtools' => 0,
            'position' => $position,
            'titlestatus' => 1,
            'completionmode' => 0,
            'contents' => '',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $record->id = $DB->insert_record('cdelement_chapter', $record);
        $this->create_options($record->id, 'chapter');
        return $record;
    }

    /**
     * Creates an element instance in a chapter of an activity (or outside the chapters, without chapter).
     *
     * @param stdClass $instance The activity (as returned by create_module).
     * @param string $shortname The element.
     * @param array $record Values of the element table cdelement_<shortname>.
     * @param stdClass|null $chapter The chapter, null for elements outside chapters (outro).
     * @return stdClass The element instance.
     */
    public function create_element(
        stdClass $instance,
        string $shortname,
        array $record = [],
        ?stdClass $chapter = null
    ): stdClass {
        global $DB;
        $record = (object) ($record + [
            'contentdesignerid' => $instance->id,
            'title' => ucfirst($shortname),
            'visible' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $record->id = $DB->insert_record('cdelement_' . $shortname, $record);
        $this->create_options($record->id, $shortname);
        if ($chapter) {
            $elementid = $DB->get_field('contentdesigner_elements', 'id', ['shortname' => $shortname], MUST_EXIST);
            $position = (int) $DB->get_field_sql(
                'SELECT MAX(position) FROM {contentdesigner_content} WHERE chapter = ?',
                [$chapter->id]
            ) + 1;
            $record->contentid = $DB->insert_record('contentdesigner_content', [
                'contentdesignerid' => $instance->id,
                'element' => $elementid,
                'instance' => $record->id,
                'chapter' => $chapter->id,
                'position' => $position,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
            $contents = array_filter(explode(',', (string) $DB->get_field(
                'cdelement_chapter',
                'contents',
                ['id' => $chapter->id]
            )));
            $contents[] = $record->contentid;
            $DB->set_field('cdelement_chapter', 'contents', implode(',', $contents), ['id' => $chapter->id]);
        }
        return $record;
    }

    /**
     * Creates the general options of an element instance.
     *
     * @param int $instanceid The element instance.
     * @param string $shortname The element.
     * @param array $record Values of contentdesigner_options.
     * @return int The id of the options.
     */
    public function create_options(int $instanceid, string $shortname, array $record = []): int {
        global $DB;
        return $DB->insert_record('contentdesigner_options', $record + [
            'element' => $DB->get_field('contentdesigner_elements', 'id', ['shortname' => $shortname], MUST_EXIST),
            'instance' => $instanceid,
            'delay' => 0,
            'speed' => 0,
            'hidedesktop' => 0,
            'hidetablet' => 0,
            'hidemobile' => 0,
            'excludeungraded' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Creates an attempt of a user.
     *
     * @param stdClass $instance The activity.
     * @param int $userid The user.
     * @param array $record Values of contentdesigner_attempts.
     * @return stdClass The attempt.
     */
    public function create_attempt(stdClass $instance, int $userid, array $record = []): stdClass {
        global $DB;
        $number = $DB->count_records('contentdesigner_attempts', ['contentdesignerid' => $instance->id, 'userid' => $userid]) + 1;
        $record = (object) ($record + [
            'contentdesignerid' => $instance->id,
            'userid' => $userid,
            'attempt' => $number,
            'grade' => 0,
            'timestart' => time(),
            'timefinish' => 0,
        ]);
        $record->id = $DB->insert_record('contentdesigner_attempts', $record);
        return $record;
    }
}

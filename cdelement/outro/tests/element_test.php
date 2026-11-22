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

namespace cdelement_outro;

use mod_contentdesigner\editor;

/**
 * Tests of the outro element.
 *
 * @package    cdelement_outro
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(element::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(editor::class)]
final class element_test extends \advanced_testcase {
    /** @var \stdClass The activity. */
    private \stdClass $cd;

    /** @var \stdClass The outro. */
    private \stdClass $outro;

    /**
     * An activity with an outro with a custom primary button.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->cd = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        $this->outro = $this->getDataGenerator()->get_plugin_generator('mod_contentdesigner')->create_element(
            $this->cd,
            'outro',
            [
                'outrocontent' => 'Well done',
                'outrocontentformat' => FORMAT_HTML,
                'primarybutton' => element::OUTRO_BUTTON_CUSTOM,
                'primarytext' => 'Continue',
                'primaryurl' => 'https://example.com/next',
                'secondarybutton' => 0,
            ]
        );
    }

    /**
     * Renders the outro.
     *
     * @return string HTML
     */
    private function render_outro(): string {
        $element = editor::get_element('outro', $this->cd->cmid);
        return $element->render($element->prepare_formdata($this->outro->id));
    }

    /**
     * The URL of a custom button is validated when it is shown: a URL that is not valid (e.g. from a backup) gives
     * no button.
     */
    public function test_custom_button_url(): void {
        global $DB;
        $this->setAdminUser();
        $html = $this->render_outro();
        $this->assertStringContainsString('href="https://example.com/next"', $html);
        $this->assertStringContainsString('Continue', $html);

        foreach (['javascript:alert(1)', "javascript:%0aalert(1)", ''] as $url) {
            $DB->set_field('cdelement_outro', 'primaryurl', $url, ['id' => $this->outro->id]);
            $html = $this->render_outro();
            $this->assertStringNotContainsString('javascript:', $html);
            $this->assertStringNotContainsString('Continue', $html);
        }
    }

    /**
     * The editor shows the title of the outro formatted, without HTML.
     */
    public function test_editor_title(): void {
        global $DB;
        $this->setAdminUser();
        $DB->set_field('cdelement_outro', 'title', 'End <img src=x onerror=alert(1)>', ['id' => $this->outro->id]);
        $html = editor::get_editor($this->cd->cmid)->get_module_outro();
        $this->assertStringContainsString('End', $html);
        $this->assertStringNotContainsString('<img', $html);
    }
}

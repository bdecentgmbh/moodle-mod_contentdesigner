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
 * User steps definition.
 *
 * @package    mod_contentdesigner
 * @category   test
 * @copyright  2023 bdecent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;
use Behat\Gherkin\Node\TableNode;

/**
 * Steps definitions for users.
 *
 * @package    mod_contentdesigner
 * @category   test
 * @copyright  2023 bdecent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_contentdesigner extends behat_base {
    /**
     * Create a content designer element.
     *
     * @Given /^I create "(?P<element>(?:[^"]|\\")*)" cdelement with the following fields to these values:$/
     * @throws ElementNotFoundException Thrown by behat_base::find
     * @param string $element
     * @param TableNode $data
     */
    public function i_create_cdelement_with_the_following_fields_to_these_values($element, TableNode $data) {
        $this->execute('behat_general::i_click_on_in_the', ['Editor', 'link', '.secondary-navigation', 'css_element']);
        $this->execute('behat_general::i_click_on', ['.contentdesigner-addelement .fa-plus', 'css_element']);
        $this->execute('behat_general::i_click_on', ['.elements-list li[data-element=' . $element . ']', 'css_element']);
        $this->execute('behat_forms::i_set_the_following_fields_to_these_values', [$data]);
        $this->execute('behat_general::i_click_on', ['Create element', 'button']);
    }

    /**
     * Edit a content designer element.
     *
     * @Given /^I edit "(?P<elem>(?:[^"]|\\")*)" cdelement "(?P<title>(?:[^"]|\\")*)" with the following fields to these values:$/
     * @throws ElementNotFoundException Thrown by behat_base::find
     * @param string $elem
     * @param string $title
     * @param TableNode $data
     */
    public function i_edit_cdelement_with_the_title_and_set_the_following_fields_to_these_values($elem, $title, TableNode $data) {
        $this->execute('behat_general::i_click_on_in_the', ['Editor', 'link', '.secondary-navigation', 'css_element']);

        // Find the element item by element type and title.
        $xpath = "//li[contains(@class, 'element-item element-{$elem}') and @data-title=\"{$title}\"]//li[@data-action='edit']/a";

        $editlink = $this->find('xpath', $xpath);
        $editlink->click();

        // Set the fields in the edit form.
        $this->execute('behat_forms::i_set_the_following_fields_to_these_values', [$data]);
        $this->execute('behat_general::i_click_on', ['Update element', 'button']);
    }

    /**
     * Scrolls to the bottom of the page: the window and the scrolling page region of Boost.
     *
     * @When /^I scroll to the bottom of page$/
     */
    public function i_scroll_down_page() {
        $this->evaluate_script(
            "window.scrollTo(0, document.body.scrollHeight);
            const page = document.querySelector('#page');
            if (page) {
                page.scrollTop = page.scrollHeight;
            }"
        );
    }
}

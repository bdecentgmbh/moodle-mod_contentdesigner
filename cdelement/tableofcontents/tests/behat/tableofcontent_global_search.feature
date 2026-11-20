@mod @mod_contentdesigner @cdelement_tableofcontents  @javascript
Feature: Verify the content designer global search support in table of contents element
  In order to verify the content designer search support
  As a teacher
  I need to add contentdesigner activities to courses
  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
    And the following config values are set as admin:
      | enableglobalsearch | 1 |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 			 | 0 				|
    And the following "course enrolments" exist:
      | user 		 | course | role 					 |
      | teacher1 | C1 		| editingteacher |
      | student1 | C1 		| student 			 |
    And the following "activity" exists:
      | activity | contentdesigner              |
      | name     | Demo content                 |
      | intro    | Contentdesigner Description  |
      | course   | C1                           |

  Scenario: Contentdesigner: Search: Add and edit tableofcontent element title and description
    Given I am on the "Demo content" "contentdesigner activity" page logged in as admin
    Then I should see "Content editor"
    And I click on "Content editor" "link"
    And I click on ".course-content-list .topinstance.element-item .action-item[data-action=edit] a" "css_element"
    And I reload the page
    And "Intro text" "text" should exist
    And I set the following fields to these values:
      | Intro text  | Table of contents intro text |
      | Title       | TOC title                    |
    And I press "Update element"
    And I am on site homepage
    Then I update the global search index
    And I search for "content" using the header global search box
    And I should see "Demo content" in the ".search-results" "css_element"
    Then I search for "title" using the header global search box
    And I should see "Demo content: TOC title" in the ".search-results" "css_element"
    Then I search for "intro text" using the header global search box
    And I should see "Demo content: TOC title" in the ".search-results" "css_element"
    And I am on the "Demo content" "contentdesigner activity" page
    Then I should see "Content editor"
    And I click on "Content editor" "link"
    And I click on ".course-content-list .topinstance.element-item .action-item[data-action=edit] a" "css_element"
    And I set the following fields to these values:
      | Intro text  | TOC intro text    |
      | Title       | TOC title 2       |
    And I press "Update element"
    And I am on site homepage
    And I update the global search index
    And I search for "title 2" using the header global search box
    And I should see "Demo content: TOC title 2" in the ".search-results" "css_element"
    And I search for "TOC intro" using the header global search box
    And I should see "TOC intro text" in the ".search-results" "css_element"
    Then I click on "Demo content: TOC title" "link" in the ".search-results" "css_element"
    And "#page-mod-contentdesigner-view" "css_element" should exist
    And I should see "Demo content" in the "#page-header" "css_element"

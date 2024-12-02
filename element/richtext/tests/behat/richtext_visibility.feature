@mod @mod_contentdesigner @element_richtext  @javascript
Feature: Check content designer richtext element settings
  In order to content elements settings of multiple responses
  As a teacher
  I need to add contentdesigner activities to courses
  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Content Designer" to section "1" and I fill the form with:
      | Name      | Demo content  |
      | Description     | Contentdesigner Description |
    And I log out

  Scenario: Add a Richtext element
    Given I am on the "Demo content" "contentdesigner activity" page logged in as teacher1
    And I click on "Content editor" "link"
    And I click on ".contentdesigner-addelement .fa-plus" "css_element"
    And I click on ".elements-list li[data-element=richtext]" "css_element" in the ".modal-body" "css_element"
    And I set the following fields to these values:
      | Rich Text  | <b style='color: #7c7cff; background-color: #ffffff;'>Hard to read</>|
      | Title                | Richtext 01 |
    And I press "Create element"
    And I click on "Content Designer" "link"
    Then I should see "Hard to read" in the ".chapter-elements-list li .richtext-content" "css_element"
    Then ".chapter-elements-list li .richtext-content b" "css_element" should exist

@mod @mod_contentdesigner @element_outro @_file_upload @javascript
Feature: Check content designer outro element settings
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

  Scenario: Add a outro element
    Given I am on the "Demo content" "contentdesigner activity" page logged in as teacher1
    And I click on "Content editor" "link"
    Then ".course-content-list .item-outro" "css_element" should exist
    And I click on ".course-content-list .item-outro .action-item[data-action=edit]" "css_element"
    Then I should see "Outro element settings"
    And I upload "mod/contentdesigner/element/outro/tests/behat/assets/c1.jpg" file to "Image" filemanager
    And I set the following fields to these values:
    | primary button text  | Button 01               |
    | primary button URL   | https://www.example.com |
    | Secondary button text| Button 02               |
    | Secondary button URL | https://www.example.com |
    | Title                | Demo Outro              |
    And I press "Update element"
    And I click on "Content Designer" "link"
    Then I should see "Button 01"
    Then I should see "Button 02"
    Then I check outro image

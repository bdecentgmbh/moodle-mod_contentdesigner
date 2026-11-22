@mod @mod_contentdesigner @cdelement_videotime @javascript
Feature: Video Time element with and without Video Time
  In order to embed videos in Content Designer
  As a teacher
  I can use the Video Time element only while Video Time is available

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activity" exists:
      | activity | contentdesigner |
      | name     | Demo content    |
      | course   | C1              |

  @mod_videotime
  Scenario: CDElement: Video Time: The element is offered while Video Time is installed
    Given I am on the "Demo content" "contentdesigner activity" page logged in as teacher1
    And I click on "Editor" "link" in the ".secondary-navigation" "css_element"
    And I click on ".contentdesigner-addelement .fa-plus" "css_element"
    Then ".elements-list li[data-element=videotime]" "css_element" should exist in the ".modal-body" "css_element"

  Scenario: CDElement: Video Time: The element is not offered without Video Time
    Given Video Time is not available
    And I am on the "Demo content" "contentdesigner activity" page logged in as teacher1
    And I click on "Editor" "link" in the ".secondary-navigation" "css_element"
    And I click on ".contentdesigner-addelement .fa-plus" "css_element"
    Then ".elements-list li[data-element=heading]" "css_element" should exist in the ".modal-body" "css_element"
    And ".elements-list li[data-element=videotime]" "css_element" should not exist in the ".modal-body" "css_element"

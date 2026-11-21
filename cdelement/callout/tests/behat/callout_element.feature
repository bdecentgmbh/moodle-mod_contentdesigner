@mod @mod_contentdesigner @cdelement_callout @javascript
Feature: Callout element of Content Designer
  In order to highlight notes, tips and warnings in the content
  As a teacher
  I need to add callouts that learners see in the activity

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activity" exists:
      | activity | contentdesigner             |
      | name     | Demo content                |
      | intro    | Contentdesigner Description |
      | course   | C1                          |

  Scenario: A teacher adds a callout in the editor and a student sees it
    Given I am on the "Demo content" "contentdesigner activity" page logged in as teacher1
    And I click on "Editor" "link" in the ".secondary-navigation" "css_element"
    And I click on ".contentdesigner-addelement .fa-plus" "css_element"
    And I click on ".elements-list li[data-element=callout]" "css_element" in the ".modal-body" "css_element"
    And I set the following fields to these values:
      | Callout type    | Warning                         |
      | Callout title   | Mind the gap                    |
      | Callout content | Please read this before you go. |
      | Title           | Callout 01                      |
    And I press "Create element"
    And I log out
    When I am on the "Demo content" "contentdesigner activity" page logged in as student1
    Then ".cdelement-callout.callout-type-warning[role=note]" "css_element" should exist
    And I should see "Mind the gap" in the ".cdelement-callout .callout-title" "css_element"
    And I should see "Please read this before you go." in the ".cdelement-callout .callout-body" "css_element"

  Scenario: A teacher changes the type of a callout
    Given the following "mod_contentdesigner > chapters" exist:
      | contentdesigner | title        |
      | Demo content    | Introduction |
    And the following "mod_contentdesigner > elements" exist:
      | contentdesigner | chapter      | element | title      | callouttype | content            | contentformat |
      | Demo content    | Introduction | callout | Callout 02 | info        | <p>Good to know</p> | 1             |
    And I am on the "Demo content" "contentdesigner activity" page logged in as teacher1
    When I edit "callout" cdelement "Callout 02" with the following fields to these values:
      | Callout type | Tip |
    And I log out
    And I am on the "Demo content" "contentdesigner activity" page logged in as student1
    Then ".cdelement-callout.callout-type-tip" "css_element" should exist
    And ".cdelement-callout.callout-type-info" "css_element" should not exist
    And ".cdelement-callout .callout-title" "css_element" should not exist
    And I should see "Good to know" in the ".cdelement-callout .callout-body" "css_element"

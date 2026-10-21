@mod @mod_contentdesigner @cdelement_richtext  @javascript
Feature: Check content designer richtext element settings
  In order to content elements settings of multiple responses
  As a teacher
  I need to add contentdesigner activities to courses
  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following config values are set as admin:
      | enableglobalsearch | 1 |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activity" exists:
      | activity | contentdesigner              |
      | name     | Demo content                 |
      | intro    | Contentdesigner Description  |
      | course   | C1                           |

  Scenario: Add a Richtext element
    Given I am on the "Demo content" "contentdesigner activity" page logged in as teacher1
    And I click on "Content editor" "link"
    And I click on ".contentdesigner-addelement .fa-plus" "css_element"
    And I click on ".elements-list li[data-element=richtext]" "css_element" in the ".modal-body" "css_element"
    And I set the field "Rich Text Content" to "<b style='color: #7c7cff; background-color: #ffffff;'>Hard to read</b>"
    And I expand all fieldsets
    And I set the field "Title" to "Richtext 01"
    And I click on "Create element" "button"
    And I click on "Content Designer" "link"
    Then I should see "Hard to read" in the ".chapter-elements-list li .richtext-content" "css_element"
    Then ".chapter-elements-list li .richtext-content" "css_element" should exist

  Scenario: Contentdesigner: Search: Add and edit richtext element title and description
    Given I am on the "Demo content" "contentdesigner activity" page logged in as admin
    Then I create "richtext" cdelement with the following fields to these values:
      | Rich Text Content | Example richtext content|
      | Title             | Rich text Title         |
      | Description       | Rich text Description   |
    And I am on site homepage
    And I update the global search index
    Then I search for "content" using the header global search box
    And I should see "Demo content: Rich text Title" in the ".search-results" "css_element"
    And I search for "title" using the header global search box
    And I should see "Demo content: Rich text Title" in the ".search-results" "css_element"
    And I search for "Description" using the header global search box
    And I should see "Demo content: Rich text Title" in the ".search-results" "css_element"
    And I should see "Rich text Description" in the ".search-results" "css_element"
    And I search for "Example" using the header global search box
    And I should see "Demo content: Rich text Title" in the ".search-results" "css_element"
    And I should see "Example richtext content" in the ".search-results" "css_element"
    And I am on the "Demo content" "contentdesigner activity" page
    Then I edit "richtext" cdelement "Rich text Title" with the following fields to these values:
      | Rich Text Content | Numeric content for richtext         |
      | Title             | Rich text Title 2                    |
      | Description       | This is demo description of richtext |
    And I am on site homepage
    And I update the global search index
    And I search for "title 2" using the header global search box
    And I should see "Demo content: Rich text Title 2" in the ".search-results" "css_element"
    Then I search for "description" using the header global search box
    And I should see "Demo content: Rich text Title 2" in the ".search-results" "css_element"
    And I should not see "Rich text Description" in the ".search-results" "css_element"
    And I should see "This is demo description of richtext" in the ".search-results" "css_element"
    Then I search for "Numeric" using the header global search box
    And I should see "Numeric content for richtext" in the ".search-results" "css_element"
    And I should see "Demo content: Rich text Title 2" in the ".search-results" "css_element"
    Then I click on "Demo content: Rich text Title 2" "link" in the ".search-results" "css_element"
    And "#page-mod-contentdesigner-view" "css_element" should exist
    And I should see "Demo content" in the "#page-header" "css_element"

  Scenario: Contentdesigner: Search: Richtext element title and description show and hide
    Given I am on the "Demo content" "contentdesigner activity" page logged in as admin
    Then I create "richtext" cdelement with the following fields to these values:
      | Rich Text Content | Example richtext content  |
      | Title             | Rich text Title           |
      | Description       | Rich text Description     |
      | showdescription   | 1                         |
    And I click on "Content Designer" "link"
    And "Rich text Description" "text" should exist in the ".contentdesigner-wrapper .chapters-list .element-item .element-description" "css_element"
    And "Example richtext content" "text" should exist
    Then I edit "richtext" cdelement "Rich text Title" with the following fields to these values:
      | showdescription   | 0 |
    And I click on "Content Designer" "link"
    And "Rich text Description" "text" should not exist

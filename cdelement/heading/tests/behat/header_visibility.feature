@mod @mod_contentdesigner @cdelement_heading  @javascript
Feature: Check content designer header element settings
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

  Scenario: Add a heading element workflow
    Given I am on the "Demo content" "contentdesigner activity" page logged in as teacher1
    And I click on "Content editor" "link"
    And I click on ".contentdesigner-addelement .fa-plus" "css_element"
    And I click on ".elements-list li[data-element=heading]" "css_element" in the ".modal-body" "css_element"
    And I set the following fields to these values:
      | Heading URL          | https://www.example.com |
      | Heading              | Main heading (h2)       |
      | Heading text         | Heading 01              |
      | Title                | Heading 01              |
      | Target               | Open a same window      |
      | Horizontal Alignment |  Right                  |
      | Vertical Alignment   |  Middle                 |
    And I press "Create element"
    And I click on "Content Designer" "link"
    Then I should see "Heading 01" in the ".chapter-elements-list li.element-item" "css_element"
    Then ".chapter-elements-list li.element-item h2.element-heading.hl-right.vl-middle" "css_element" should exist
    Then ".chapter-elements-list li.element-item a[target=_self]" "css_element" should exist
    And I click on "Content editor" "link"
    And I click on ".chapters-list:nth-child(2) li.element-item:nth-child(1) .action-item[data-action=edit] a" "css_element"
    And I set the following fields to these values:
      | Heading URL          | https://www.example.com |
      | Heading text         | Heading First           |
      | Title                | Heading First           |
      | Target               | Open a new window       |
      | Horizontal Alignment |  Center                 |
      | Vertical Alignment   |  Top                    |
    And I press "Update element"
    And I click on "Content Designer" "link"
    Then I should not see "Heading 01" in the ".chapter-elements-list li.element-item" "css_element"
    Then I should see "Heading First" in the ".chapter-elements-list li.element-item" "css_element"
    Then ".chapter-elements-list li.element-item h2.element-heading.hl-center.vl-top" "css_element" should exist
    Then ".chapter-elements-list li.element-item a[target=_blank]" "css_element" should exist

  @javascript
  Scenario: Contentdesigner: Search: Heading element title and description
    Given I am on the "Demo content" "contentdesigner activity" page logged in as admin
    Then I create "heading" cdelement with the following fields to these values:
      | Heading text    | Testing the Heading |
      | Title           | Heading Title       |
      | Description     | Heading Description |
    And I am on site homepage
    And I update the global search index
    And I search for "content" using the header global search box
    And I should see "Demo content" in the ".search-results" "css_element"
    And I search for "title" using the header global search box
    And I should see "Demo content: Heading Title" in the ".search-results" "css_element"
    And I search for "Description" using the header global search box
    And I should see "Demo content: Heading Title" in the ".search-results" "css_element"
    And I search for "Testing the Heading" using the header global search box
    And I should see "Testing the Heading" in the ".search-results" "css_element"
    And I am on the "Demo content" "contentdesigner activity" page
    Then I edit "heading" cdelement "Heading Title" with the following fields to these values:
      | Heading text    | Updated heading       |
      | Title           | Heading Title 2       |
      | Description     | Heading Description 2 |
    And I update the global search index
    And I search for "title 2" using the header global search box
    And I should see "Demo content: Heading Title 2" in the ".search-results" "css_element"
    And I search for "Description 2" using the header global search box
    And I should see "Demo content: Heading Title 2" in the ".search-results" "css_element"
    And I search for "Updated" using the header global search box
    And I should see "Updated heading" in the ".search-results" "css_element"
    Then I click on "Demo content: Heading Title 2" "link" in the ".search-results" "css_element"
    And "#page-mod-contentdesigner-view" "css_element" should exist
    And I should see "Demo content" in the "#page-header" "css_element"

  @javascript
  Scenario: Contentdesigner: Heading element title and description show and hide
    Given I am on the "Demo content" "contentdesigner activity" page logged in as admin
    Then I create "heading" cdelement with the following fields to these values:
      | Heading text    | Heading 01          |
      | Title           | Heading Title       |
      | Description     | Heading Description |
      | showdescription | 1                   |
    And I click on "Content Designer" "link"
    And "Heading Description" "text" should exist in the ".contentdesigner-wrapper .chapters-list .element-item .element-description" "css_element"
    Then I should see "Heading 01"
    Then I edit "heading" cdelement "Heading Title" with the following fields to these values:
      | showdescription | 0 |
    And I click on "Content Designer" "link"
    And "Heading Description" "text" should not exist

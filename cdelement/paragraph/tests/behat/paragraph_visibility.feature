@mod @mod_contentdesigner @cdelement_paragraph  @javascript
Feature: Check content designer paragraph element settings
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

  Scenario: Add a Paragraph element
    Given I am on the "Demo content" "contentdesigner activity" page logged in as teacher1
    And I click on "Editor" "link" in the ".secondary-navigation" "css_element"
    And I click on ".contentdesigner-addelement .fa-plus" "css_element"
    And I click on ".elements-list li[data-element=paragraph]" "css_element" in the ".modal-body" "css_element"
    And I set the following fields to these values:
      | Content              | Lorem Ipsum is simply dummy text of the printing and typesetting industry.|
      | Horizontal Alignment | Center       |
      | Vertical Alignment   | Middle       |
      | Title                | Paragraph 01 |
    And I press "Create element"
    And I click on "Content" "link" in the ".secondary-navigation" "css_element"
    Then I should see "Lorem Ipsum" in the ".chapter-elements-list li.element-item" "css_element"
    Then ".chapter-elements-list li.element-item .hl-center.vl-middle" "css_element" should exist
    Then ".chapter-elements-list li.element-item p.element-paragraph" "css_element" should exist
    And I click on "Editor" "link" in the ".secondary-navigation" "css_element"
    And I click on ".chapters-list:nth-child(2) li.element-item:nth-child(1) .action-item[data-action=edit] a" "css_element"
    And I set the following fields to these values:
      | Content              | Various versions have evolved over the years.|
      | Horizontal Alignment | Left |
      | Vertical Alignment   | Top  |
    And I press "Update element"
    And I click on "Content" "link" in the ".secondary-navigation" "css_element"
    Then I should not see "Lorem Ipsum" in the ".chapter-elements-list li.element-item" "css_element"
    Then I should see "Various versions" in the ".chapter-elements-list li.element-item" "css_element"
    Then ".chapter-elements-list li.element-item .hl-left.vl-top" "css_element" should exist

  Scenario: Contentdesigner: Search: Paragraph element title and description
    Given I am on the "Demo content" "contentdesigner activity" page logged in as admin
    Then I create "paragraph" cdelement with the following fields to these values:
      | Content         | Example paragraph content. |
      | Title           | Paragraph Title            |
      | Description     | This is paragraph element  |
      | showdescription | 1                          |

    And I am on site homepage
    And I update the global search index
    And I search for "content" using the header global search box
    And I should see "Demo content: Paragraph Title" in the ".search-results" "css_element"
    And I search for "title" using the header global search box
    And I should see "Demo content: Paragraph Title" in the ".search-results" "css_element"
    And I search for "example" using the header global search box
    And I should see "Demo content: Paragraph Title" in the ".search-results" "css_element"
    And I should see "Example paragraph content" in the ".search-results" "css_element"
    And I search for "paragraph" using the header global search box
    And I should see "Demo content: Paragraph Title" in the ".search-results" "css_element"
    Then I click on "Demo content: Paragraph Title" "link" in the ".search-results" "css_element"
    And "#page-mod-contentdesigner-view" "css_element" should exist
    And I should see "Demo content" in the "#page-header" "css_element"

  Scenario: Contentdesigner: Search: Paragraph element title and description show and hide
    Given I am on the "Demo content" "contentdesigner activity" page logged in as admin
    Then I create "paragraph" cdelement with the following fields to these values:
      | Content         | Lorem Ipsum is simply dummy text of the printing and typesetting industry.|
      | Title           | Paragraph Title       |
      | Description     | Paragraph Description |
      | showdescription | 1                     |
    And I click on "Content" "link" in the ".secondary-navigation" "css_element"
    And "Paragraph Description" "text" should exist in the ".contentdesigner-wrapper .chapters-list .element-item .element-description" "css_element"
    Then I edit "paragraph" cdelement "Paragraph Title" with the following fields to these values:
      | showdescription | 0 |
    And I click on "Content" "link" in the ".secondary-navigation" "css_element"
    And "Paragraph Description" "text" should not exist

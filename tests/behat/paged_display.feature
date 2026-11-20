@mod @mod_contentdesigner @javascript
Feature: Paged display of Content Designer activities
  In order to work through long content step by step
  As a learner
  I need to see one chapter per page, continue where I stopped and reach the next page only after completing the current one

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | enablecompletion |
      | Course 1 | C1        | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  Scenario: Teachers choose the paged display in the settings of the activity
    Given the following "activities" exist:
      | activity        | name          | course |
      | contentdesigner | Paged content | C1     |
    And the following "mod_contentdesigner > chapters" exist:
      | contentdesigner | title        |
      | Paged content   | Introduction |
      | Paged content   | Practice     |
    And the following "mod_contentdesigner > elements" exist:
      | contentdesigner | chapter      | element   | title | content              |
      | Paged content   | Introduction | paragraph | P1    | Text of Introduction |
      | Paged content   | Practice     | paragraph | P2    | Text of Practice     |
    When I am on the "Paged content" "contentdesigner activity" page logged in as "teacher1"
    Then I should see "Text of Practice"
    And I should not see "Page 1 of 2"
    And I am on the "Paged content" "activity editing" page
    And the field "Display mode" matches value "Scroll"
    And I set the field "Display mode" to "Pages"
    And I press "Save and display"
    And I should see "Page 1 of 2"
    And I should see "Text of Introduction"
    And I should not see "Text of Practice"
    # Teachers reach every page.
    And I click on "Next page" "link"
    And I should see "Text of Practice"

  Scenario: Learners go through the pages and see the outro on the last page
    Given the following "activities" exist:
      | activity        | name          | course | displaymode |
      | contentdesigner | Paged content | C1     | 1           |
    And the following "mod_contentdesigner > chapters" exist:
      | contentdesigner | title        |
      | Paged content   | Introduction |
      | Paged content   | Practice     |
      | Paged content   | Summary      |
    And the following "mod_contentdesigner > elements" exist:
      | contentdesigner | chapter      | element   | title | content              | outrocontent | outrocontentformat |
      | Paged content   | Introduction | paragraph | P1    | Text of Introduction |              | 1                  |
      | Paged content   | Practice     | paragraph | P2    | Text of Practice     |              | 1                  |
      | Paged content   | Summary      | paragraph | P3    | Text of Summary      |              | 1                  |
      | Paged content   |              | outro     | Outro |                      | Well done    | 1                  |
    When I am on the "Paged content" "contentdesigner activity" page logged in as "student1"
    Then I should see "Page 1 of 3"
    And I should see "Text of Introduction"
    And I should not see "Text of Practice"
    And "Previous page" "link" should not exist
    And the "Next page" "button" should be disabled
    And I press "Mark as done"
    And I click on "Next page" "link"
    And I should see "Page 2 of 3"
    And I should see "Text of Practice"
    And I should not see "Text of Introduction"
    And I click on "Previous page" "link"
    And I should see "Text of Introduction"
    And I click on "Next page" "link"
    And I press "Mark as done"
    And I click on "Next page" "link"
    And I should see "Page 3 of 3"
    And "Next page" "link" should not exist
    And "Next page" "button" should not exist
    And I should not see "Well done"
    And I press "Mark as done"
    And I should see "Well done"
    And I click on "Previous page" "link"
    And I should see "Text of Practice"
    And I should not see "Well done"

  Scenario: Learners continue on the page where their progress stops
    Given the following "activities" exist:
      | activity        | name          | course | displaymode |
      | contentdesigner | Paged content | C1     | 1           |
    And the following "mod_contentdesigner > chapters" exist:
      | contentdesigner | title        |
      | Paged content   | Introduction |
      | Paged content   | Practice     |
      | Paged content   | Summary      |
    And the following "mod_contentdesigner > elements" exist:
      | contentdesigner | chapter      | element   | title | content              |
      | Paged content   | Introduction | paragraph | P1    | Text of Introduction |
      | Paged content   | Practice     | paragraph | P2    | Text of Practice     |
      | Paged content   | Summary      | paragraph | P3    | Text of Summary      |
    And the following "mod_contentdesigner > attempts" exist:
      | contentdesigner | user     | timestart      |
      | Paged content   | student1 | ##2 days ago## |
    And the following "mod_contentdesigner > chapter completions" exist:
      | contentdesigner | chapter      | user     | timemodified  |
      | Paged content   | Introduction | student1 | ##1 day ago## |
    When I am on the "Paged content" "contentdesigner activity" page logged in as "student1"
    Then I should see "Page 2 of 3"
    And I should see "Text of Practice"
    And the "Next page" "button" should be disabled
    And I press "Mark as done"
    And "Next page" "link" should exist
    And I am on the "Paged content" "contentdesigner activity" page
    And I should see "Page 3 of 3"
    And I should see "Text of Summary"

  Scenario: A new attempt starts on the first page
    Given the following "activities" exist:
      | activity        | name          | course | displaymode |
      | contentdesigner | Paged content | C1     | 1           |
    And the following "mod_contentdesigner > chapters" exist:
      | contentdesigner | title        |
      | Paged content   | Introduction |
      | Paged content   | Practice     |
      | Paged content   | Summary      |
    And the following "mod_contentdesigner > elements" exist:
      | contentdesigner | chapter      | element   | title | content              |
      | Paged content   | Introduction | paragraph | P1    | Text of Introduction |
      | Paged content   | Practice     | paragraph | P2    | Text of Practice     |
      | Paged content   | Summary      | paragraph | P3    | Text of Summary      |
    And the following "mod_contentdesigner > attempts" exist:
      | contentdesigner | user     | timestart       |
      | Paged content   | student1 | ##2 days ago##  |
      | Paged content   | student1 | ##1 hour ago##  |
    And the following "mod_contentdesigner > chapter completions" exist:
      | contentdesigner | chapter      | user     | timemodified  |
      | Paged content   | Introduction | student1 | ##1 day ago## |
      | Paged content   | Practice     | student1 | ##1 day ago## |
    When I am on the "Paged content" "contentdesigner activity" page logged in as "student1"
    Then I should see "Page 1 of 3"
    And I should see "Text of Introduction"
    # The chapters completed in the first attempt still open the next pages.
    And I click on "Next page" "link"
    And I should see "Page 2 of 3"
    And I click on "Next page" "link"
    And I should see "Page 3 of 3"
    And the "Next page" "button" should not exist

  Scenario: Mandatory elements keep the next page closed until they are completed in the attempt
    Given the following "activities" exist:
      | activity        | name          | course | displaymode |
      | contentdesigner | Paged content | C1     | 1           |
    And the following "mod_contentdesigner > chapters" exist:
      | contentdesigner | title        |
      | Paged content   | Introduction |
      | Paged content   | Practice     |
    And the following "mod_contentdesigner > elements" exist:
      | contentdesigner | chapter      | element   | title    | content              | mandatory |
      | Paged content   | Introduction | h5p       | Exercise |                      | 1         |
      | Paged content   | Introduction | paragraph | P1       | Text of Introduction | 0         |
      | Paged content   | Practice     | paragraph | P2       | Text of Practice     | 0         |
    When I am on the "Paged content" "contentdesigner activity" page logged in as "student1"
    Then I should see "Page 1 of 2"
    And I should see "Complete the element above to continue."
    And I should not see "Text of Introduction"
    And the "Next page" "button" should be disabled
    And the following "mod_contentdesigner > h5p completions" exist:
      | contentdesigner | h5p      | user     |
      | Paged content   | Exercise | student1 |
    And I reload the page
    And I should see "Text of Introduction"
    And the "Next page" "button" should be disabled
    And I press "Mark as done"
    And I click on "Next page" "link"
    And I should see "Page 2 of 2"
    And I should see "Text of Practice"

  @cdaddon_pro
  Scenario: Graded attempts continue on the page of the progress and start again on the first page
    Given the "cdaddon_pro" plugin is installed
    And the following "activities" exist:
      | activity        | name          | course | displaymode | enablegrading | grade | attemptsallowed | navigation |
      | contentdesigner | Paged content | C1     | 1           | 1             | 100   | 2               | 0          |
    And the following "mod_contentdesigner > chapters" exist:
      | contentdesigner | title        |
      | Paged content   | Introduction |
      | Paged content   | Practice     |
      | Paged content   | Summary      |
    And the following "mod_contentdesigner > elements" exist:
      | contentdesigner | chapter      | element   | title | content              | outrocontent | outrocontentformat |
      | Paged content   | Introduction | paragraph | P1    | Text of Introduction |              | 1                  |
      | Paged content   | Practice     | paragraph | P2    | Text of Practice     |              | 1                  |
      | Paged content   | Summary      | paragraph | P3    | Text of Summary      |              | 1                  |
      | Paged content   |              | outro     | Outro |                      | Well done    | 1                  |
    When I am on the "Paged content" "contentdesigner activity" page logged in as "student1"
    And I press "Start attempt"
    Then I should see "Page 1 of 3"
    And I press "Mark as done"
    And I click on "Next page" "link"
    And I should see "Page 2 of 3"
    And I should see "Text of Practice"
    # Leaving and continuing the attempt opens the page of the progress.
    And I am on the "Paged content" "contentdesigner activity" page
    And I press "Continue attempt"
    And I should see "Page 2 of 3"
    And I press "Mark as done"
    And I click on "Next page" "link"
    And I should see "Page 3 of 3"
    And "Finish attempt" "button" should not exist
    And I press "Mark as done"
    And I should see "Well done"
    And I click on "Previous page" "link"
    And "Finish attempt" "button" should not exist
    And I click on "Next page" "link"
    And I press "Finish attempt"
    And I should see "Attempt finished"
    And I press "Start a new attempt"
    And I should see "Page 1 of 3"
    And I should see "Text of Introduction"
    And I should not see "Well done"

  @cdaddon_pro
  Scenario: With sequential navigation, mandatory elements keep the next page of graded attempts closed
    Given the "cdaddon_pro" plugin is installed
    And the following "activities" exist:
      | activity        | name          | course | displaymode | enablegrading | grade | autostartattempts | navigation |
      | contentdesigner | Paged content | C1     | 1           | 1             | 100   | 1                 | 0          |
    And the following "mod_contentdesigner > chapters" exist:
      | contentdesigner | title        |
      | Paged content   | Introduction |
      | Paged content   | Practice     |
    And the following "mod_contentdesigner > elements" exist:
      | contentdesigner | chapter      | element   | title    | content              | mandatory |
      | Paged content   | Introduction | h5p       | Exercise |                      | 1         |
      | Paged content   | Introduction | paragraph | P1       | Text of Introduction | 0         |
      | Paged content   | Practice     | paragraph | P2       | Text of Practice     | 0         |
    When I am on the "Paged content" "contentdesigner activity" page logged in as "student1"
    Then I should see "Page 1 of 2"
    And I should see "Complete the element above to continue."
    And the "Next page" "button" should be disabled
    And the following "mod_contentdesigner > h5p completions" exist:
      | contentdesigner | h5p      | user     |
      | Paged content   | Exercise | student1 |
    And I reload the page
    And I press "Mark as done"
    And I click on "Next page" "link"
    And I should see "Page 2 of 2"
    And I should see "Text of Practice"

  @cdaddon_pro
  Scenario: The activity is completed when the learner reaches the end on the last page
    Given the "cdaddon_pro" plugin is installed
    And the following "activities" exist:
      | activity        | name          | course | displaymode | completion | completionendreach |
      | contentdesigner | Paged content | C1     | 1           | 2          | 1                  |
    And the following "mod_contentdesigner > chapters" exist:
      | contentdesigner | title        |
      | Paged content   | Introduction |
      | Paged content   | Summary      |
    And the following "mod_contentdesigner > elements" exist:
      | contentdesigner | chapter      | element   | title | content              | outrocontent | outrocontentformat |
      | Paged content   | Introduction | paragraph | P1    | Text of Introduction |              | 1                  |
      | Paged content   | Summary      | paragraph | P2    | Text of Summary      |              | 1                  |
      | Paged content   |              | outro     | Outro |                      | Well done    | 1                  |
    When I am on the "Paged content" "contentdesigner activity" page logged in as "student1"
    Then the "Reach the end of the contents" completion condition of "Paged content" is displayed as "todo"
    And I press "Mark as done"
    And I click on "Next page" "link"
    And I should see "Page 2 of 2"
    And I should not see "Well done"
    And I reload the page
    And the "Reach the end of the contents" completion condition of "Paged content" is displayed as "todo"
    And I press "Mark as done"
    And I should see "Well done"
    And I scroll to the bottom of page
    And I reload the page
    And the "Reach the end of the contents" completion condition of "Paged content" is displayed as "done"

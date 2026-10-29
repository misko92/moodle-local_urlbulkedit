@local @local_urlbulkedit @javascript
Feature: Bulk edit link settings
  In order to save time
  As a teacher
  I need to change settings of many links on one page

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | One      |
    And the following "courses" exist:
      | fullname | shortname | enablecompletion |
      | Course 1 | C1        | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name         | course | externalurl                           | display |
      | url      | Notes & tips | C1     | https://docs.google.com/document/d/OLD1 | 0       |
      | url      | Worksheet    | C1     | https://docs.google.com/document/d/OLD2 | 0       |
      | url      | Video        | C1     | https://youtube.com/watch?v=abc       | 3       |

  Scenario: Set how selected links open and replace part of their addresses
    Given I am on the "Course 1" course page logged in as teacher1
    When I navigate to "Bulk edit links" in current page administration
    Then I should see "Notes & tips"
    And I should not see "Notes &amp; tips"
    And I click on "Select Notes & tips" "checkbox"
    And I click on "Select Worksheet" "checkbox"
    And I set the field "Setting" to "Display"
    And I set the field "Value" to "In pop-up"
    And I press "Apply to selected"
    And I set the field "Find" to "/d/OLD"
    And I set the field "Replace with" to "/d/NEW"
    And I press "Replace in selected"
    And I should see "Replaced in 2 link(s)."
    And I press "Save changes"
    And I should see "4 change(s) to 2 link(s):" in the "Review changes" "dialogue"
    And I click on "Save" "button" in the "Review changes" "dialogue"
    And I should see "2 link(s) updated."
    And the field "URL: Notes & tips" matches value "https://docs.google.com/document/d/NEW1"
    And the field "URL: Worksheet" matches value "https://docs.google.com/document/d/NEW2"
    And the field "URL: Video" matches value "https://youtube.com/watch?v=abc"
    And the field "Display: Worksheet" matches value "In pop-up"
    And the field "Display: Video" matches value "New window"

  Scenario: Invalid values are shown and nothing is saved
    Given I am on the "Course 1" course page logged in as teacher1
    When I navigate to "Bulk edit links" in current page administration
    And I set the field "URL: Video" to "not a link"
    And I set the field "Display: Worksheet" to "Open"
    And I press "Save changes"
    And I click on "Save" "button" in the "Review changes" "dialogue"
    Then I should see "Nothing was saved."
    And I should see "Enter a valid web address"
    And I am on the "Course 1" course page
    And I navigate to "Bulk edit links" in current page administration
    And the field "Display: Worksheet" matches value "Automatic"

  Scenario: Turn on view completion for selected links
    Given I am on the "Course 1" course page logged in as teacher1
    When I navigate to "Bulk edit links" in current page administration
    And I click on "Select Video" "checkbox"
    And I set the field "Setting" to "Completion"
    And I set the field "Value" to "Student must view"
    And I press "Apply to selected"
    And I press "Save changes"
    And I should see "Changing completion resets" in the "Review changes" "dialogue"
    And I click on "Save" "button" in the "Review changes" "dialogue"
    Then I should see "1 link(s) updated."
    And the field "Completion: Video" matches value "Student must view"
    And the field "Completion: Notes & tips" matches value "None"

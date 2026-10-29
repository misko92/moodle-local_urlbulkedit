@local @local_urlbulkedit @javascript
Feature: Add many links at once
  In order to save time
  As a teacher
  I need to create several links from a pasted list

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | One      |
    And the following "courses" exist:
      | fullname | shortname | numsections | initsections |
      | Course 1 | C1        | 2           | 1            |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name  | course | externalurl         | display | popupwidth | popupheight | section |
      | url      | Model | C1     | https://example.com | 6       | 900        | 700         | 1       |

  Scenario: Paste a list, preview and create links copying a model link's settings
    Given I am on the "Course 1" course page logged in as teacher1
    When I navigate to "Bulk edit links" in current page administration
    And I click on "Add links" "link"
    And I set the field "Settings" to "Model"
    And I set the field "Paste a list of links" to multiline:
      """
      Unit 1 notes | https://docs.google.com/document/d/one
      Unit 2 notes docs.google.com/document/d/two
      """
    And I press "Add to the table"
    And I set the field with xpath "//tr[1]//select[@data-field='section']" to "Section 2"
    And I set the field with xpath "//tr[2]//select[@data-field='section']" to "Section 2"
    And I press "Preview"
    Then I should see "2 link(s) will be created."
    And I should see "Opens: In pop-up"
    And I should see "http://docs.google.com/document/d/two"
    And I press "Create links"
    And I should see "2 link(s) created."
    And the field "Display: Unit 1 notes" matches value "In pop-up"
    And the field "URL: Unit 2 notes" matches value "http://docs.google.com/document/d/two"
    And I am on "Course 1" course homepage
    And I should see "Unit 1 notes" in the "Section 2" "section"

  Scenario: Rows with errors are shown and nothing is created
    Given I am on the "Course 1" course page logged in as teacher1
    When I navigate to "Bulk edit links" in current page administration
    And I click on "Add links" "link"
    And I set the field with xpath "//tr[1]//input[@data-field='name']" to "No address"
    And I press "Preview"
    Then I should see "Enter a valid web address"
    And I should not see "Create links"

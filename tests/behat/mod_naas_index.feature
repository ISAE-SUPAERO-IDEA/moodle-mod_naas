@mod @mod_naas
Feature: Course NaaS index
  In order to see all Nuggets in a course
  As a teacher
  I need the mod_naas index page to list activities or show an empty state

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
      | Course 2 | C2        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email          |
      | teacher1 | Teacher   | One      | t1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher1 | C2     | editingteacher |
    And the following "activities" exist:
      | activity | course | section | name     |
      | naas     | C1     | 1       | Nugget A |

  Scenario: Index lists NaaS activities in the course
    When I am on the "Course 1" "naas index" page logged in as teacher1
    Then I should see "Nuggets"
    And I should see "Nugget A"
    And I should see "Back to Course Index"
    And "table.mod_index" "css_element" should exist

  Scenario: Index shows a message when the course has no NaaS activities
    When I am on the "Course 2" "naas index" page logged in as teacher1
    Then I should see "This course has no NaaS module included."
    And I should see "Nuggets"

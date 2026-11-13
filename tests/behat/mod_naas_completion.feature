@mod @mod_naas @javascript
Feature: NaaS activity completion
  In order to track student progress
  As a teacher
  I need to be able to set completion conditions on NaaS activities

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 1 | C1        | 0        | 1                |
    And the following "users" exist:
      | username | firstname | lastname | email          |
      | student1 | Student   | One      | s1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | course | section | name     | completion | completionview |
      | naas     | C1     | 1       | Nugget A | 2          | 1              |

  Scenario: Student automatically completes the activity by viewing it
    When I am on the "Course 1" course page logged in as student1
    And the "View" completion condition of "Nugget A" is displayed as "todo"
    And I am on the "Nugget A" "naas activity" page
    And I am on the "Course 1" course page
    Then the "View" completion condition of "Nugget A" is displayed as "done"

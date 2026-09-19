@mod @mod_naas
Feature: NaaS LTI launch entry
  In order to open a Nugget in full launch mode
  As a teacher
  I need launch.php to respond with either an LTI form or a clear error

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email          |
      | teacher1 | Teacher   | One      | t1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | course | section | name     |
      | naas     | C1     | 1       | Nugget A |

  Scenario: Teacher can load the launch page for a NaaS activity
    When I log in as "teacher1"
    And I visit the launch page for "Nugget A" naas activity
    Then the naas launch page should show an LTI form or a load error

@mod @mod_naas
Feature: View a NaaS activity
  In order to use a Nugget in a course
  As a participant
  I need the activity view page to load without errors

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | One      | t1@example.com   |
      | student1 | Student   | One      | s1@example.com   |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | course | section | name     |
      | naas     | C1     | 1       | Nugget A |

  Scenario: A student can open the NaaS activity view
    When I am on the "Nugget A" "naas activity" page logged in as student1
    Then I should see "Back to Course Index"
    And "#naas_widget" "css_element" should exist

  Scenario: A teacher can open the NaaS activity view
    When I am on the "Nugget A" "naas activity" page logged in as teacher1
    Then I should see "Back to Course Index"
    And "#naas_widget" "css_element" should exist

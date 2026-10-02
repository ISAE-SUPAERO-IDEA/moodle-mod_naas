@mod @mod_naas
Feature: NaaS plugin roles and capabilities
  In order to ensure security
  As an administrator
  I need to restrict NaaS activity access based on capabilities

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email            |
      | student1 | Student   | One      | s1@example.com   |
      | student2 | Student   | Two      | s2@example.com   |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "activities" exist:
      | activity | course | section | name     |
      | naas     | C1     | 1       | Nugget A |
    And the following "permission overrides" exist:
      | capability | permission | role | contextlevel | reference |
      | mod/naas:view | Prohibit | student | Course | C1 |
    And the following "role assigns" exist:
      | user  | role           | contextlevel | reference |
      | student1 | student     | Course       | C1        |

  Scenario: A user without mod/naas:view capability cannot see the NaaS activity
    When I am on the "Nugget A" "naas activity" page logged in as student1
    Then I should not see "Back to Course Index"
    And "#naas_widget" "css_element" should not exist

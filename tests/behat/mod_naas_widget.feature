@mod @mod_naas @javascript
Feature: Choose a Nugget with the search widget and play it
  In order to add NaaS content to a course
  As a teacher
  I need to find a Nugget, preview it and save it as an activity

  # NaaS is replaced by tests/behat/fixtures/behat_naas_client.php on the Behat site.
  Background:
    Given the following config values are set as admin:
      | naas_endpoint     | https://naas.behat.test/api | naas |
      | naas_username     | behat                       | naas |
      | naas_password     | behat                       | naas |
      | naas_structure_id | isae-supaero                | naas |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  Scenario: A teacher searches, selects and saves a Nugget
    Given I log in as "teacher1"
    When I open the form to add a naas activity to course "C1"
    And I wait until ".search-field-input" "css_element" exists
    And I set the field with xpath "//input[contains(@class, 'search-field-input')]" to "aircraft"
    Then I should see "Aircraft design basics"
    And I should not see "Composite materials"
    When I click on "Select" "button" in the "//div[contains(@class, 'nugget-post')][.//h4[@title='Aircraft design basics']]" "xpath_element"
    Then the field "Nugget display title" matches value "Aircraft design basics"
    When I set the field with xpath "//input[@id='id_cgu_agreement']" to "1"
    And I press "Save and return to course"
    Then I should see "Aircraft design basics"
    And the naas activity "Aircraft design basics" should use nugget "n-aircraft"

  Scenario: Saving without a Nugget explains what is missing
    Given I log in as "teacher1"
    When I open the form to add a naas activity to course "C1"
    And I wait until ".search-field-input" "css_element" exists
    And I press "Save and return to course"
    Then I should see "Select a Nugget before saving the activity."

  Scenario: The preview opens and closes with the Escape key
    Given I log in as "teacher1"
    When I open the form to add a naas activity to course "C1"
    And I wait until ".search-field-input" "css_element" exists
    And I set the field with xpath "//input[contains(@class, 'search-field-input')]" to "orbital"
    And I click on "Preview" "button" in the "//div[contains(@class, 'nugget-post')][.//h4[@title='Orbital mechanics in five minutes']]" "xpath_element"
    Then "#nugget-preview-modal" "css_element" should be visible
    When I press the escape key
    # The dialog fades out before Vue removes it.
    Then I wait until "#nugget-preview-modal" "css_element" does not exist

  Scenario: A student opens the Nugget activity
    Given the following "activities" exist:
      | activity | course | section | name                   | nugget_id  |
      | naas     | C1     | 1       | Aircraft design basics | n-aircraft |
    When I am on the "Aircraft design basics" "naas activity" page logged in as student1
    Then "#lti-frame" "css_element" should exist
    And I should see "About"

  @accessibility
  Scenario: The search widget, its preview and the learner view meet accessibility standards
    Given the following "activities" exist:
      | activity | course | section | name                   | nugget_id  |
      | naas     | C1     | 1       | Aircraft design basics | n-aircraft |
    And I log in as "teacher1"
    When I open the form to add a naas activity to course "C1"
    And I wait until ".search-field-input" "css_element" exists
    And I set the field with xpath "//input[contains(@class, 'search-field-input')]" to "aircraft"
    Then I should see "Aircraft design basics"
    And the page should meet accessibility standards
    When I click on "Preview" "button" in the "//div[contains(@class, 'nugget-post')][.//h4[@title='Aircraft design basics']]" "xpath_element"
    Then "#nugget-preview-modal" "css_element" should be visible
    And I wait "1" seconds
    # The preview frame loads from the fake NaaS host, which axe cannot enter.
    And the "#nugget-preview-modal .nugget-modal-header" "css_element" should meet accessibility standards
    And I log out
    When I am on the "Aircraft design basics" "naas activity" page logged in as student1
    Then "#lti-frame" "css_element" should exist
    # The whole learner page cannot be checked: its LTI frame posts to the fake NaaS host,
    # which does not resolve, so axe never gets into it.
    # Moodle 4.x shows About in the activity's secondary navigation.
    When I click on "About" "link" in the ".secondary-navigation" "css_element"
    Then I should see "About: Aircraft design basics"
    # Let the dialog finish fading in: axe would measure the colours half-way.
    And I wait "1" seconds
    And the "[role='dialog']" "css_element" should meet accessibility standards

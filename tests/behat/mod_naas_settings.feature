@mod @mod_naas
Feature: NaaS plugin administration settings
  As an administrator
  I need to configure the Nugget plugin from site administration

  Scenario: Admin can open NaaS activity module settings
    When I log in as "admin"
    And I navigate to "Plugins > Activity modules > Nugget" in site administration
    Then I should see "About the Nugget plugin"
    And I should see "Connection"
    And I should see "NaaS API URL"
    And I should see "API username"
    And I should see "API password"
    And I should see "Institute ID"
    And I should see "Privacy"
    And I should see "Send learner email to NaaS"
    And I should see "Send learner name to NaaS"
    And I should see "Learner experience"
    And I should see "Catalogue"
    And I should see "Commercial use"
    And I should see "Restricted / unrestricted use"
    And I should see "Appearance"
    And I should see "Advanced"
    And "Test connection" "button" should exist

  Scenario: Test connection reports a result from saved settings
    When I log in as "admin"
    And I navigate to "Plugins > Activity modules > Nugget" in site administration
    And I click on "Test connection" "button"
    And I wait until "#connection-result.alert" "css_element" exists
    Then "#connection-result" "css_element" should be visible

@mod @mod_naas
Feature: Launch a Nugget with LTI 1.1
  In order to play a Nugget
  As a student
  I need launch.php to post a signed LTI 1.1 launch to NaaS, or to explain why it cannot

  # NaaS is replaced by tests/behat/fixtures/behat_naas_client.php on the Behat site.
  # No @javascript here: the launch form submits itself to the fake NaaS host as soon as its script runs,
  # so these scenarios read the form the browser would post.
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
      | student1 | Student   | One      | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity | course | section | name                   | nugget_id  |
      | naas     | C1     | 1       | Aircraft design basics | n-aircraft |
      | naas     | C1     | 1       | Retired Nugget         | n-unknown  |

  Scenario: A student launches a Nugget with a signed LTI 1.1 form
    Given the following config values are set as admin:
      | naas_privacy_learner_name | 1 | naas |
      | naas_privacy_learner_mail | 1 | naas |
    When I log in as "student1"
    And I visit the launch page for "Aircraft design basics" naas activity
    Then the naas launch form should post to "https://naas.behat.test/lti"
    And the naas launch form should contain the following fields:
      | name                             | value                       |
      | lti_version                      | LTI-1p0                     |
      | lti_message_type                 | basic-lti-launch-request    |
      | oauth_consumer_key               | behat-key                   |
      | oauth_version                    | 1.0                         |
      | oauth_signature_method           | HMAC-SHA1                   |
      | oauth_callback                   | about:blank                 |
      | oauth_nonce                      | /^[0-9a-f]+[.][0-9]+$/      |
      | oauth_timestamp                  | /^[0-9]{10}$/               |
      | resource_link_id                 | /^[A-Za-z0-9+/]{54}==$/     |
      | lis_result_sourcedid             | /^[0-9a-f]{32}$/            |
      | launch_presentation_locale       | en                          |
      | lis_person_name_full             | Student One                 |
      | lis_person_contact_email_primary | student1@example.com        |
      | custom_naas                      | /"is_authenticated":true/   |
    And the naas launch form should send the outcome of "student1" to "Aircraft design basics"
    And the naas launch form should be signed with secret "behat-secret"
    And ".naas-launch-error" "css_element" should not exist

  Scenario: The launch leaves out the learner name and email when the site keeps them private
    Given the following config values are set as admin:
      | naas_privacy_learner_name | 0 | naas |
      | naas_privacy_learner_mail | 0 | naas |
    When I log in as "student1"
    And I visit the launch page for "Aircraft design basics" naas activity
    Then the naas launch form should post to "https://naas.behat.test/lti"
    And the naas launch form should not contain the "lis_person_name_full" field
    And the naas launch form should not contain the "lis_person_contact_email_primary" field
    And the naas launch form should send the outcome of "student1" to "Aircraft design basics"
    And the naas launch form should be signed with secret "behat-secret"

  Scenario: A Nugget that NaaS does not know shows an error instead of the launch form
    When I log in as "student1"
    And I visit the launch page for "Retired Nugget" naas activity
    Then I should see "The requested resource was not found on the NaaS server."
    And ".naas-launch-error" "css_element" should exist
    And "#ltiLaunchForm" "css_element" should not exist

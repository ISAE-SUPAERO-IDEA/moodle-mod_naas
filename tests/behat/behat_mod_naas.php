<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Step definitions for mod_naas.
 *
 * @package    mod_naas
 * @category   test
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ExpectationException;

/**
 * Behat steps for the NaaS activity.
 *
 * @package    mod_naas
 * @category   test
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/).
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
class behat_mod_naas extends behat_base {
    /**
     * Opens /mod/naas/launch.php for the given activity instance name.
     *
     * @When /^I visit the launch page for "(?P<activityname>(?:[^"]|\\")*)" naas activity$/
     * @param string $activityname
     */
    public function i_visit_the_launch_page_for_naas_activity(string $activityname): void {
        $cm = $this->get_cm_by_activity_name('naas', $activityname);
        $url = new moodle_url('/mod/naas/launch.php', ['id' => $cm->id]);
        $this->execute('behat_general::i_visit', [$url]);
    }

    /**
     * Open the form that adds a Nugget activity to the first section of a course.
     *
     * Goes straight to modedit.php: the activity chooser differs between Moodle versions.
     *
     * @When /^I open the form to add a naas activity to course "(?P<shortname>(?:[^"]|\\")*)"$/
     * @param string $shortname
     */
    public function i_open_the_form_to_add_a_naas_activity_to_course(string $shortname): void {
        global $DB;
        $course = $DB->get_record('course', ['shortname' => $shortname], 'id', MUST_EXIST);
        $url = new moodle_url('/course/modedit.php', ['add' => 'naas', 'course' => $course->id, 'section' => 1, 'return' => 0]);
        $this->execute('behat_general::i_visit', [$url]);
    }

    /**
     * Check which NaaS Nugget a saved activity points to.
     *
     * @Then /^the naas activity "(?P<activityname>(?:[^"]|\\")*)" should use nugget "(?P<nuggetid>(?:[^"]|\\")*)"$/
     * @param string $activityname
     * @param string $nuggetid
     */
    public function the_naas_activity_should_use_nugget(string $activityname, string $nuggetid): void {
        global $DB;
        $actual = $DB->get_field('naas', 'nugget_id', ['name' => $activityname]);
        if ($actual !== $nuggetid) {
            throw new ExpectationException(
                "The naas activity \"{$activityname}\" uses nugget \"" . (string) $actual . "\", expected \"{$nuggetid}\".",
                $this->getSession()
            );
        }
    }

    /**
     * Check where the LTI 1.1 launch form rendered by launch.php posts to.
     *
     * Run it without the javascript tag: the form submits itself as soon as a browser runs its script.
     *
     * @Then /^the naas launch form should post to "(?P<url>(?:[^"]|\\")*)"$/
     * @param string $url Expected form action.
     */
    public function the_naas_launch_form_should_post_to(string $url): void {
        $form = $this->get_naas_launch_form();
        $method = strtoupper((string) $form->getAttribute('method'));
        if ($method !== 'POST') {
            throw new ExpectationException(
                "The naas launch form uses method \"{$method}\", expected \"POST\".",
                $this->getSession()
            );
        }
        $action = (string) $form->getAttribute('action');
        if ($action !== $url) {
            throw new ExpectationException(
                "The naas launch form posts to \"{$action}\", expected \"{$url}\".",
                $this->getSession()
            );
        }
    }

    /**
     * Check the values of some LTI launch parameters.
     *
     * Each value must match exactly, unless it is wrapped in slashes: "/^[0-9a-f]{32}$/" is a regular expression.
     *
     * @Then /^the naas launch form should contain the following fields:$/
     * @param TableNode $table Two columns: name, value.
     */
    public function the_naas_launch_form_should_contain_the_following_fields(TableNode $table): void {
        $fields = $this->get_naas_launch_fields();
        foreach ($table->getColumnsHash() as $row) {
            $name = $row['name'];
            $expected = $row['value'];
            if (!array_key_exists($name, $fields)) {
                throw new ExpectationException("The naas launch form has no \"{$name}\" field.", $this->getSession());
            }
            $actual = $fields[$name];
            $isregex = strlen($expected) > 1 && $expected[0] === '/' && substr($expected, -1) === '/';
            if ($isregex) {
                $matches = preg_match('~' . str_replace('~', '\~', substr($expected, 1, -1)) . '~', $actual) === 1;
            } else {
                $matches = $actual === $expected;
            }
            if (!$matches) {
                throw new ExpectationException(
                    "The naas launch field \"{$name}\" is \"{$actual}\", expected \"{$expected}\".",
                    $this->getSession()
                );
            }
        }
    }

    /**
     * Check that the LTI launch form does not send a parameter (eg. personal data the site keeps private).
     *
     * @Then /^the naas launch form should not contain the "(?P<name>(?:[^"]|\\")*)" field$/
     * @param string $name LTI parameter name.
     */
    public function the_naas_launch_form_should_not_contain_the_field(string $name): void {
        if (array_key_exists($name, $this->get_naas_launch_fields())) {
            throw new ExpectationException(
                "The naas launch form should not send the \"{$name}\" field.",
                $this->getSession()
            );
        }
    }

    /**
     * Check the OAuth 1.0 HMAC-SHA1 signature of the LTI launch form against a consumer secret.
     *
     * The signature is computed again from the posted fields and the form action, as the tool provider does.
     *
     * @Then /^the naas launch form should be signed with secret "(?P<secret>(?:[^"]|\\")*)"$/
     * @param string $secret OAuth consumer secret.
     */
    public function the_naas_launch_form_should_be_signed_with_secret(string $secret): void {
        $fields = $this->get_naas_launch_fields();
        if (empty($fields['oauth_signature'])) {
            throw new ExpectationException('The naas launch form has no oauth_signature field.', $this->getSession());
        }
        $signature = $fields['oauth_signature'];
        unset($fields['oauth_signature']);

        // OAuth Core 1.0, section 9.1: parameters sorted by name, then the signature base string.
        ksort($fields, SORT_STRING);
        $pairs = [];
        foreach ($fields as $name => $value) {
            $pairs[] = rawurlencode($name) . '=' . rawurlencode($value);
        }
        $url = (string) $this->get_naas_launch_form()->getAttribute('action');
        $basestring = 'POST&' . rawurlencode($url) . '&' . rawurlencode(implode('&', $pairs));
        $expected = base64_encode(hash_hmac('sha1', $basestring, rawurlencode($secret) . '&', true));

        if (!hash_equals($expected, $signature)) {
            throw new ExpectationException(
                "The naas launch signature \"{$signature}\" does not match secret \"{$secret}\" (expected \"{$expected}\").",
                $this->getSession()
            );
        }
    }

    /**
     * Check that the LTI launch reports its outcome to the activity, for a session recorded for the user.
     *
     * NaaS posts the grade back to lis_outcome_service_url with lis_result_sourcedid,
     * which must match the naas_activity_outcome row written at launch.
     *
     * @Then /^the naas launch form should send the outcome of "(?P<username>(?:[^"]|\\")*)" to "(?P<activityname>(?:[^"]|\\")*)"$/
     * @param string $username Learner who launched the Nugget.
     * @param string $activityname Name of the naas activity.
     */
    public function the_naas_launch_form_should_send_the_outcome_of_to(string $username, string $activityname): void {
        global $CFG, $DB;
        $cm = $this->get_cm_by_activity_name('naas', $activityname);
        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $fields = $this->get_naas_launch_fields();

        $expected = [
            'context_id' => (string) $cm->id,
            'lis_outcome_service_url' => $CFG->wwwroot . '/mod/naas/outcome.php?id=' . $cm->id,
        ];
        foreach ($expected as $name => $value) {
            $actual = $fields[$name] ?? null;
            if ($actual !== $value) {
                throw new ExpectationException(
                    "The naas launch field \"{$name}\" is \"" . (string) $actual . "\", expected \"{$value}\".",
                    $this->getSession()
                );
            }
        }

        $sourcedid = $fields['lis_result_sourcedid'] ?? '';
        $recorded = $sourcedid !== '' && $DB->record_exists('naas_activity_outcome', [
            'sourced_id' => $sourcedid,
            'activity_id' => $cm->id,
            'user_id' => $userid,
        ]);
        if (!$recorded) {
            throw new ExpectationException(
                "No naas launch session \"{$sourcedid}\" was recorded for \"{$username}\" on \"{$activityname}\".",
                $this->getSession()
            );
        }
    }

    /**
     * The LTI launch form rendered by launch.php.
     *
     * @return NodeElement
     * @throws ExpectationException When the page has no launch form.
     */
    protected function get_naas_launch_form(): NodeElement {
        $form = $this->getSession()->getPage()->find('css', 'form#ltiLaunchForm');
        if ($form === null) {
            throw new ExpectationException('The page has no naas LTI launch form (#ltiLaunchForm).', $this->getSession());
        }
        return $form;
    }

    /**
     * The hidden fields of the LTI launch form, by name.
     *
     * @return array Field values keyed by field name.
     */
    protected function get_naas_launch_fields(): array {
        $fields = [];
        foreach ($this->get_naas_launch_form()->findAll('css', 'input[type=hidden]') as $input) {
            $fields[(string) $input->getAttribute('name')] = (string) $input->getAttribute('value');
        }
        return $fields;
    }
}

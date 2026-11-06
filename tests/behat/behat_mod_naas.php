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
     * launch.php either renders the LTI auto-post form or a load error when the API is unavailable.
     *
     * @Then /^the naas launch page should show an LTI form or a load error$/
     */
    public function the_naas_launch_page_should_show_an_lti_form_or_a_load_error(): void {
        $content = $this->getSession()->getPage()->getContent();
        if (strpos($content, 'ltiLaunchForm') !== false) {
            return;
        }
        $needle = get_string('cannot_get_nugget', 'naas');
        if (strpos($content, $needle) !== false) {
            return;
        }
        // Any NaaS API failure (credentials, endpoint, not found) uses this marker.
        if (strpos($content, 'naas-launch-error') !== false) {
            return;
        }
        throw new ExpectationException(
            'Neither LTI launch form nor nugget load error was found on the naas launch page.',
            $this->getSession()
        );
    }
}

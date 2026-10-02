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
        $needle2 = 'error:naas_api:invalid_endpoint';
        if (strpos($content, $needle2) !== false) {
            return;
        }
        $needle3 = get_string('error:naas_api:invalid_endpoint', 'naas');
        if (strpos($content, $needle3) !== false) {
            return;
        }
        throw new ExpectationException(
            'Neither LTI launch form nor nugget load error was found on the naas launch page.',
            $this->getSession()
        );
    }
}

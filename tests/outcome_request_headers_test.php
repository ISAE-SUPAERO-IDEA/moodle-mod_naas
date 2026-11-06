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
 * Tests the getallheaders() fallback of the outcome handler.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

defined('MOODLE_INTERNAL') || die();

// The preserveGlobalState tag, read by PHPUnit 9, is unknown to moodle-cs.
// phpcs:disable moodle.Commenting.ValidTags.Invalid

global $CFG;
require_once($CFG->dirroot . '/mod/naas/outcome.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas_outcome::class)]
/**
 * Authorization header read through getallheaders() when the server did not export it.
 *
 * The PHP CLI has no getallheaders(): the test declares one, so it runs in its own process.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_naas_outcome
 */
final class outcome_request_headers_test extends \advanced_testcase {
    /**
     * A signed post seen only through getallheaders() is recognised, whatever the header case.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function test_signed_outcome_header_read_from_getallheaders(): void {
        global $CFG;
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/mod/naas/tests/fixtures/getallheaders_stub.php');
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id, 'grade_method' => NAAS_ATTEMPTLAST]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        /** @var \mod_naas_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_naas');
        $generator->create_activity_outcome($user->id, $naas->cmid, 'src-headers-1');
        $generator->create_activity_outcome($user->id, $naas->cmid, 'src-headers-2');
        $url = 'https://moodle.example.test/mod/naas/outcome.php';
        $credentials = (object) ['key' => 'k', 'secret' => 's'];

        // No Authorization among the request headers: graded, not remembered as signed.
        $GLOBALS['mod_naas_test_request_headers'] = ['Host' => 'moodle.example.test'];
        \mod_naas_outcome::process($this->xml('src-headers-1', '0.2'), null, $url, $credentials);
        $this->assertFalse(get_config('naas', 'naas_outcome_signed_seen'));

        // The header name is matched case-insensitively.
        $xml = $this->xml('src-headers-2', '0.8');
        $header = trim(substr(lti_outcome_oauth::sign('k', 's', $xml, $url), strlen('Authorization:')));
        $GLOBALS['mod_naas_test_request_headers'] = ['Host' => 'moodle.example.test', 'authorization' => $header];
        // Moodle 5.0 already ships a getallheaders() polyfill (lib/ralouphie/getallheaders), which
        // takes precedence over the stub: it rebuilds the header from PHP_AUTH_DIGEST.
        $_SERVER['PHP_AUTH_DIGEST'] = $header;
        \mod_naas_outcome::process($xml, null, $url, $credentials);
        unset($_SERVER['PHP_AUTH_DIGEST']);

        $this->assertGreaterThan(0, (int) get_config('naas', 'naas_outcome_signed_seen'));
        $grades = grade_get_grades($course->id, 'mod', 'naas', $naas->id, $user->id);
        $this->assertEquals(80.0, (float) $grades->items[0]->grades[$user->id]->grade);
    }

    /**
     * Minimal replaceResult request.
     *
     * @param string $sourcedid
     * @param string $score
     * @return string
     */
    private function xml(string $sourcedid, string $score): string {
        return '<?xml version="1.0" encoding="UTF-8"?><imsx_POXEnvelopeRequest><imsx_POXBody><replaceResultRequest>'
            . '<resultRecord><sourcedGUID><sourcedId>' . $sourcedid . '</sourcedId></sourcedGUID>'
            . '<result><resultScore><score>' . $score . '</score></resultScore></result></resultRecord>'
            . '</replaceResultRequest></imsx_POXBody></imsx_POXEnvelopeRequest>';
    }
}

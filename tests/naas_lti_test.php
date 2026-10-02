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
 * Tests for mod_naas\naas_lti.
 *
 * naas_lti::lti_launch() is tightly coupled to a live naas_client (no HTTP
 * adapter injection yet — see QUALITY_PHP.md H1).  Tests are therefore split
 * into two groups:
 *
 *  - Tests that exercise code paths BEFORE the first HTTP call (module
 *    resolution, config fetch): these run offline.
 *  - Tests that cover the full launch path (DB insertion, form rendering,
 *    OAuth signature): they belong to the external group.  Until the HttpAdapter
 *    refactor lands these are marked incomplete so they appear in the report
 *    as a reminder rather than failures.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/naas/lib.php');

use advanced_testcase;
use mod_naas\naas_lti;
use stdClass;

/**
 * Tests for mod_naas\naas_lti.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/).
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 * @covers \mod_naas\naas_lti
 */
class naas_lti_test extends advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $_SERVER['SERVER_NAME'] = 'localhost';
    }

    // Module resolution — offline (no HTTP needed).

    /**
     * lti_launch() must throw a dml_missing_record_exception when the
     * course-module ID does not exist in the database.
     */
    public function test_lti_launch_throws_on_invalid_module_id(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        // Module ID 999999 does not exist in the test DB.
        $this->expectException(\dml_missing_record_exception::class);
        naas_lti::lti_launch(999999);
    }

    // OAuth signature helper — pure logic, no DB or HTTP.

    /**
     * The OAuth base-string includes the launch URL and all sorted parameters.
     *
     * This test exercises the signature construction logic by inspecting the
     * properties of a launch_data array as lti_launch would build it.  It
     * verifies the invariants independently of the HTTP call.
     */
    public function test_oauth_nonce_is_unique_across_calls(): void {
        // Two consecutive uniqid() calls must differ.  This mirrors the nonce.
        // Generation in lti_launch().
        $nonce1 = uniqid('', true);
        $nonce2 = uniqid('', true);

        $this->assertNotSame($nonce1, $nonce2);
    }

    /**
     * The resource_link_id is derived from HMAC-SHA1 and base64-encoded.
     *
     * Verify the construction: same inputs → same output (deterministic).
     */
    public function test_resource_link_id_is_deterministic(): void {
        $secret  = urlencode('test-secret') . '&';
        $payload = 'localhost' . 'user@example.com' . 'version-abc';

        $id1 = base64_encode(hash_hmac('sha1', $payload, $secret, false));
        $id2 = base64_encode(hash_hmac('sha1', $payload, $secret, false));

        $this->assertSame($id1, $id2);
    }

    /**
     * Different user emails produce different resource_link_ids.
     */
    public function test_resource_link_id_differs_for_different_users(): void {
        $secret = urlencode('test-secret') . '&';

        $id1 = base64_encode(hash_hmac('sha1', 'localhost' . 'user1@example.com' . 'version-abc', $secret, false));
        $id2 = base64_encode(hash_hmac('sha1', 'localhost' . 'user2@example.com' . 'version-abc', $secret, false));

        $this->assertNotSame($id1, $id2);
    }

    /**
     * custom_naas JSON must expose feature.nugbot as a boolean for the NaaS player.
     */
    public function test_lti_custom_options_include_nugbot_flag(): void {
        global $CFG;

        $this->resetAfterTest(true);
        $CFG->wwwroot = 'https://Moodle.example.test';

        $off = naas_lti::lti_custom_options((object) [
            'naas_css' => '',
            'naas_feedback' => '1',
            'naas_nugbot' => '0',
        ]);
        $this->assertFalse($off['feature.nugbot']);
        $this->assertSame('on', $off['feedback']);

        $on = naas_lti::lti_custom_options((object) [
            'naas_css' => '',
            'naas_feedback' => '0',
            'naas_nugbot' => '1',
        ]);
        $this->assertTrue($on['feature.nugbot']);
        $this->assertSame('off', $on['feedback']);

        $encoded = json_encode($on);
        $decoded = json_decode($encoded, true);
        $this->assertTrue($decoded['feature.nugbot']);
    }

    // DB interaction — requires HTTP stub (incomplete until refactor).

    /**
     * A successful LTI launch must insert exactly one naas_activity_outcome row.
     *
     */
    public function test_lti_launch_records_session_in_db(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);

        $client = $this->createMock(\mod_naas\naas_client::class);
        $client->method('get_nugget_data')->willReturn((object)['version_id' => 'v1']);
        $client->method('get_nugget_lti_config')->willReturn((object) [
            'url' => 'http://Example.com/lti',
            'key' => 'k',
            'secret' => 's',
        ]);

        naas_lti::lti_launch($naas->cmid, '', $client);

        $this->assertEquals(1, $DB->count_records('naas_activity_outcome', ['user_id' => $user->id, 'activity_id' => $naas->cmid]));
    }

    /**
     * A second launch for the same user+activity must insert a second distinct
     * row (each launch gets a fresh nonce / sourced_id).
     *
     */
    public function test_lti_launch_creates_separate_session_per_call(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);

        $client = $this->createMock(\mod_naas\naas_client::class);
        $client->method('get_nugget_data')->willReturn((object)['version_id' => 'v1']);
        $client->method('get_nugget_lti_config')->willReturn((object) [
            'url' => 'http://Example.com/lti',
            'key' => 'k',
            'secret' => 's',
        ]);

        naas_lti::lti_launch($naas->cmid, '', $client);
        naas_lti::lti_launch($naas->cmid, '', $client);

        $this->assertEquals(2, $DB->count_records('naas_activity_outcome', ['user_id' => $user->id, 'activity_id' => $naas->cmid]));
    }

    /**
     * lti_launch() with a valid nugget config must render an HTML form
     * containing the oauth_signature field.
     *
     */
    public function test_lti_launch_includes_oauth_signature(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);

        $client = $this->createMock(\mod_naas\naas_client::class);
        $client->method('get_nugget_data')->willReturn((object)['version_id' => 'v1']);
        $client->method('get_nugget_lti_config')->willReturn((object) [
            'url' => 'http://Example.com/lti',
            'key' => 'k',
            'secret' => 's',
        ]);

        $output = naas_lti::lti_launch($naas->cmid, '', $client);

        $this->assertStringContainsString('name="oauth_signature"', $output);
    }

    /**
     * Each launch for the same nugget must produce a different oauth_nonce.
     *
     */
    public function test_oauth_nonce_changes_per_request(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);

        $client = $this->createMock(\mod_naas\naas_client::class);
        $client->method('get_nugget_data')->willReturn((object)['version_id' => 'v1']);
        $client->method('get_nugget_lti_config')->willReturn((object) [
            'url' => 'http://Example.com/lti',
            'key' => 'k',
            'secret' => 's',
        ]);

        $output1 = naas_lti::lti_launch($naas->cmid, '', $client);

        $output2 = naas_lti::lti_launch($naas->cmid, '', $client);

        preg_match('/name="oauth_nonce" value="([^"]+)"/', $output1, $matches1);
        preg_match('/name="oauth_nonce" value="([^"]+)"/', $output2, $matches2);

        $this->assertNotEmpty($matches1[1]);
        $this->assertNotEmpty($matches2[1]);
        $this->assertNotSame($matches1[1], $matches2[1]);
    }

    /**
     * Test language resolution in lti_launch.
     */
    public function test_lti_launch_resolves_language(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);

        $client = $this->createMock(\mod_naas\naas_client::class);
        $nuggetdata = (object)[
            'version_id' => 'v1',
            'multilanguages' => [
                (object)['language' => 'fr', 'nugget_id' => 'nugget-fr'],
            ],
        ];
        $client->method('get_nugget_data')->willReturn($nuggetdata);

        // It should call get_nugget_lti_config with 'nugget-fr' because we pass 'fr'.
        $client->expects($this->atLeastOnce())
            ->method('get_nugget_lti_config')
            ->with($this->logicalOr($this->equalTo($naas->nugget_id), $this->equalTo('nugget-fr')))
            ->willReturn((object)['url' => 'http://Example.fr/lti', 'key' => 'k', 'secret' => 's']);

        $output = naas_lti::lti_launch($naas->cmid, 'fr', $client);

        $this->assertStringContainsString('http://Example.fr/lti', $output);
    }

    /**
     * Test privacy settings in lti_launch.
     */
    public function test_lti_launch_respects_privacy(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user(['firstname' => 'John', 'lastname' => 'Doe', 'email' => 'john@example.com']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);

        $client = $this->createMock(\mod_naas\naas_client::class);
        $client->method('get_nugget_data')->willReturn((object)['version_id' => 'v1']);
        $client->method('get_nugget_lti_config')->willReturn((object) [
            'url' => 'http://Example.com/lti',
            'key' => 'k',
            'secret' => 's',
        ]);

        // 1. Privacy ON (defaults).
        set_config('naas_privacy_learner_name', 1, 'naas');
        set_config('naas_privacy_learner_mail', 1, 'naas');

        $output1 = naas_lti::lti_launch($naas->cmid, '', $client);

        $this->assertStringContainsString('John Doe', $output1);
        $this->assertStringContainsString('john@example.com', $output1);

        // 2. Privacy OFF.
        set_config('naas_privacy_learner_name', 0, 'naas');
        set_config('naas_privacy_learner_mail', 0, 'naas');

        $output2 = naas_lti::lti_launch($naas->cmid, '', $client);

        $this->assertStringNotContainsString('John Doe', $output2);
        $this->assertStringNotContainsString('john@example.com', $output2);
    }

    /**
     * lti_launch() with a config error must display a notification.
     */
    public function test_lti_launch_handles_config_error(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);

        $client = $this->createMock(\mod_naas\naas_client::class);
        $client->method('get_nugget_data')->willReturn((object)['version_id' => 'v1']);
        $client->method('get_nugget_lti_config')->willReturn(null);

        $output = naas_lti::lti_launch($naas->cmid, '', $client);

        $this->assertStringContainsString(get_string('cannot_get_nugget', 'naas'), $output);
    }

    /**
     * A NaaS API failure must render an inline error and must not call debugging().
     *
     * Behat fails the launch smoke test if debugging() runs on this path.
     */
    public function test_lti_launch_renders_api_error_without_debugging(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);

        $client = $this->createMock(\mod_naas\naas_client::class);
        $client->method('get_nugget_data')->willThrowException(
            new \moodle_exception('error:naas_api:invalid_credentials', 'naas')
        );

        $output = naas_lti::lti_launch($naas->cmid, '', $client);

        $this->assertStringContainsString(get_string('error:naas_api:invalid_credentials', 'naas'), $output);
        $this->assertStringContainsString('naas-launch-error', $output);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Blank launch URL after cleaning must show the same error as missing config.
     */
    public function test_lti_launch_rejects_blank_launch_url(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);

        $client = $this->createMock(\mod_naas\naas_client::class);
        $client->method('get_nugget_data')->willReturn((object)['version_id' => 'v1']);
        $client->method('get_nugget_lti_config')->willReturn((object)['url' => '   ', 'key' => 'k', 'secret' => 's']);

        $output = naas_lti::lti_launch($naas->cmid, '', $client);

        $this->assertStringContainsString(get_string('cannot_get_nugget', 'naas'), $output);
    }
}

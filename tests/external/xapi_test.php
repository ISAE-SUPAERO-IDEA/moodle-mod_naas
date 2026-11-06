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
 * Tests for mod_naas\external\xapi.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/naas/lib.php');

use advanced_testcase;
use mod_naas\external\xapi;

require_once(__DIR__ . '/../fixtures/stub_naas_client_for_xapi.php');
require_once(__DIR__ . '/../fixtures/testable_naas_client.php');
require_once(__DIR__ . '/../fixtures/xapi_enrol_test_proxy.php');

/**
 * Tests for the xapi external service.
 *
 * No test performs outbound HTTP: the NaaS client is replaced through
 * xapi::make_naas_client() (see xapi_using()) with a stub that records the
 * statement it receives, so the payload sent to NaaS can be asserted.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/).
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 * @coversDefaultClass \mod_naas\external\xapi
 * @covers \mod_naas\external\xapi
 */
class xapi_test extends advanced_testcase {
    // Parameter schema.

    /**
     * post_xapi_statement_parameters() must return an external_function_parameters.
     */
    public function test_parameters_returns_correct_type(): void {
        $params = xapi::post_xapi_statement_parameters();
        $this->assertTrue($params instanceof \external_function_parameters);
    }

    /**
     * post_xapi_statement_returns() must return an external_single_structure.
     */
    public function test_returns_descriptor_is_single_structure(): void {
        $returns = xapi::post_xapi_statement_returns();
        $this->assertTrue($returns instanceof \external_single_structure);
    }

    /**
     * The return structure must declare statusCode and statusMessage keys.
     */
    public function test_returns_descriptor_has_expected_keys(): void {
        $returns = xapi::post_xapi_statement_returns();
        $keys    = array_keys($returns->keys);
        $this->assertContains('statusCode', $keys);
        $this->assertContains('statusMessage', $keys);
    }

    // Verb validation.

    /**
     * An invalid verb must throw invalid_parameter_exception before any DB or
     * network access.
     */
    public function test_invalid_verb_throws_invalid_parameter_exception(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $naas   = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $this->setUser($user);

        $this->expectException(\invalid_parameter_exception::class);
        xapi::post_xapi_statement('delete_everything', 'v1', $naas->cmid, null);
    }

    /**
     * Each of the three allowed verbs must pass validation and reach the NaaS client.
     *
     * @dataProvider allowed_verbs_provider
     * @param string $verb Allowed xAPI verb under test.
     */
    public function test_allowed_verbs_pass_validation(string $verb): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $naas   = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $this->setUser($user);

        $client = $this->capturing_client();
        $xapi = $this->xapi_using($client);
        $result = $xapi::post_xapi_statement($verb, 'v1', $naas->cmid, null);

        $this->assertSame(202, $result['statusCode']);
        $this->assertSame("/versions/v1/records/{$verb}", $client->captured['service']);
    }

    /**
     * Verbs the xAPI endpoint accepts.
     *
     * @return array
     */
    public static function allowed_verbs_provider(): array {
        return [
            'experienced' => ['experienced'], 'completed'   => ['completed'], 'rated'       => ['rated'], ];
    }

    // Version_id validation.

    /**
     * A version_id containing path-traversal characters must be rejected.
     */
    public function test_invalid_version_id_throws_invalid_parameter_exception(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $naas   = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $this->setUser($user);

        $this->expectException(\invalid_parameter_exception::class);
        xapi::post_xapi_statement('experienced', '../../etc/passwd', $naas->cmid, null);
    }

    /**
     * A version_id that is too long (> 128 chars) must be rejected.
     */
    public function test_version_id_too_long_throws(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $naas   = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $this->setUser($user);

        $this->expectException(\invalid_parameter_exception::class);
        xapi::post_xapi_statement('experienced', str_repeat('a', 129), $naas->cmid, null);
    }

    // Body size validation.

    /**
     * A body larger than 4 096 bytes must be rejected before any network call.
     */
    public function test_body_too_large_throws_invalid_parameter_exception(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $user    = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $naas    = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $this->setUser($user);

        $oversized = str_repeat('x', 4097);

        $this->expectException(\invalid_parameter_exception::class);
        xapi::post_xapi_statement('experienced', 'version-1', $naas->cmid, $oversized);
    }

    /**
     * A body exactly at the 4 096-byte limit must not be rejected for size.
     */
    public function test_body_at_size_limit_is_not_rejected_for_size(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $user    = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $naas    = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $this->setUser($user);

        // Valid JSON, exactly 4 096 bytes: {"a":"x…x"}.
        $exactsize = '{"a":"' . str_repeat('x', 4096 - 8) . '"}';
        $this->assertSame(4096, strlen($exactsize));

        $client = $this->capturing_client();
        $xapi = $this->xapi_using($client);
        $result = $xapi::post_xapi_statement('experienced', 'version-1', $naas->cmid, $exactsize);

        $this->assertSame(202, $result['statusCode']);
        $this->assertSame('/versions/version-1/records/experienced', $client->captured['service']);
    }

    // Access control.

    /**
     * An unauthenticated call must throw a require_login exception.
     */
    public function test_unauthenticated_call_throws(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $naas   = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);

        // No setUser() call — request is unauthenticated.
        $this->setUser(null);

        $this->expectException(\Exception::class);
        xapi::post_xapi_statement('experienced', 'version-1', $naas->cmid, null);
    }

    /**
     * A logged-in user who is not enrolled must get a moodle_exception.
     */
    public function test_unenrolled_user_throws_moodle_exception(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $naas   = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);

        // User exists but is not enrolled.
        $this->setUser($user);

        $this->expectException(\moodle_exception::class);
        xapi::post_xapi_statement('experienced', 'version-1', $naas->cmid, null);
    }

    /**
     * Role at course context without active enrolment must not access the activity:
     * validate_context() runs require_login first, which throws requireloginerror.
     */
    public function test_post_xapi_throws_not_enrolled_when_capable_but_not_actively_enrolled(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $naas   = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $user   = $this->getDataGenerator()->create_user();

        $roles = get_archetype_roles('student');
        $this->assertNotEmpty($roles);
        $role = reset($roles);
        \role_assign($role->id, $user->id, \context_course::instance($course->id)->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($user);

        try {
            xapi::post_xapi_statement('experienced', 'version-1', $naas->cmid, null);
            $this->fail('Expected access exception (enrolment enforced before plugin check)');
        } catch (\moodle_exception $e) {
            // Validate_context() → require_login(..., $preventredirect=true) throws require_login_exception.
            // Note: (errorcode requireloginerror) before our is_enrolled / error:not_enrolled branch runs.
            $this->assertSame('requireloginerror', $e->errorcode);
        }
    }

    /**
     * A site admin passes require_login and every capability check, so only the
     * enrolment gate can stop them: removing it must make this test fail.
     */
    public function test_post_xapi_rejects_a_non_enrolled_admin(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $this->setAdminUser();

        $client = $this->capturing_client();
        $xapi = $this->xapi_using($client);
        try {
            $xapi::post_xapi_statement('experienced', 'version-1', $naas->cmid, null);
            $this->fail('Expected error:not_enrolled for an admin without enrolment');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:not_enrolled', $e->errorcode);
        }
        $this->assertSame([], $client->captured);
    }

    /**
     * A non-null JSON body must be decoded into an object on the payload sent to the client.
     */
    public function test_post_xapi_with_json_body_decodes_for_payload(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $naas   = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $this->setUser($user);

        $client = $this->capturing_client();
        $xapi = $this->xapi_using($client);
        $xapi::post_xapi_statement('experienced', 'version-1', $naas->cmid, '{"actor":{"mbox":"mailto:a@b"}}');

        $body = $client->captured['data']['body'];
        $this->assertIsObject($body);
        $this->assertSame('mailto:a@b', $body->actor->mbox);
    }

    /**
     * When $_SESSION contains resource_link_id it must be forwarded on the payload.
     */
    public function test_post_xapi_includes_resource_link_id_from_session(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $naas   = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $this->setUser($user);

        $client = $this->capturing_client();
        $xapi = $this->xapi_using($client);
        $_SESSION['resource_link_id'] = 'lti-link-xyz';
        try {
            $xapi::post_xapi_statement('completed', 'version-1', $naas->cmid, null);
        } finally {
            unset($_SESSION['resource_link_id']);
        }

        $this->assertSame('lti-link-xyz', $client->captured['data']['resource_link_id']);
    }

    /**
     * Privacy flags off: learner name and email must be anonymised before the outbound call.
     */
    public function test_post_xapi_privacy_anonymous_when_flags_disabled(): void {
        $this->resetAfterTest(true);
        set_config('naas_privacy_learner_name', 0, 'naas');
        set_config('naas_privacy_learner_mail', 0, 'naas');

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $naas   = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $this->setUser($user);

        $client = $this->capturing_client();
        $xapi = $this->xapi_using($client);
        $xapi::post_xapi_statement('rated', 'version-1', $naas->cmid, null);

        $sent = $client->captured['data']['user'];
        $this->assertSame('Anonymous User', $sent->name);
        $this->assertSame('anonymous@naas-edu.eu', $sent->email);
    }

    /**
     * Privacy flags on: real name and email are used before the outbound call.
     */
    public function test_post_xapi_privacy_uses_profile_when_flags_enabled(): void {
        $this->resetAfterTest(true);
        set_config('naas_privacy_learner_name', 1, 'naas');
        set_config('naas_privacy_learner_mail', 1, 'naas');

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $naas   = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $this->setUser($user);

        $client = $this->capturing_client();
        $xapi = $this->xapi_using($client);
        $xapi::post_xapi_statement('experienced', 'version-1', $naas->cmid, null);

        $sent = $client->captured['data']['user'];
        $this->assertSame($user->firstname . ' ' . $user->lastname, $sent->name);
        $this->assertSame($user->email, $sent->email);
    }

    /**
     * Empty string body must follow the same path as null (default empty statement object).
     */
    public function test_post_xapi_empty_string_body_uses_default_statement_object(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $naas   = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $this->setUser($user);

        $client = $this->capturing_client();
        $xapi = $this->xapi_using($client);
        $xapi::post_xapi_statement('experienced', 'version-1', $naas->cmid, '');

        $this->assertEquals(new \stdClass(), $client->captured['data']['body']);
    }

    /**
     * Successful NaaS response must be returned as statusCode / statusMessage.
     */
    public function test_post_xapi_statement_returns_backend_status(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $this->setUser($user);

        $xapi = $this->xapi_using(new stub_naas_client_for_xapi());
        $result = $xapi::post_xapi_statement('experienced', 'version-1', $naas->cmid, null);

        $this->assertSame(202, $result['statusCode']);
        $this->assertSame('Accepted', $result['statusMessage']);
    }

    /**
     * A body that is not valid JSON must be refused before anything is sent to NaaS.
     */
    public function test_post_xapi_invalid_json_body_throws_without_calling_naas(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $this->setUser($user);

        $client = $this->capturing_client();
        $xapi = $this->xapi_using($client);
        try {
            $xapi::post_xapi_statement('experienced', 'version-1', $naas->cmid, '{"actor":');
            $this->fail('Expected invalid_parameter_exception for a malformed body');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString(get_string('error:xapi_body_invalid', 'naas'), $e->getMessage());
        }
        $this->assertSame([], $client->captured);
    }

    /**
     * An empty or error answer from NaaS must raise a clear exception, not a PHP warning.
     *
     * @dataProvider unusable_naas_response_provider
     * @param string $stubjson Raw NaaS answer.
     */
    public function test_post_xapi_unusable_naas_response_throws(string $stubjson): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $this->setUser($user);

        $client = $this->capturing_client();
        $client->stubjson = $stubjson;
        $xapi = $this->xapi_using($client);
        try {
            $xapi::post_xapi_statement('experienced', 'version-1', $naas->cmid, null);
            $this->fail('Expected moodle_exception for an unusable NaaS answer');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:naas_server_unexpected', $e->errorcode);
        }
    }

    /**
     * NaaS answers that carry no status code.
     *
     * @return array
     */
    public static function unusable_naas_response_provider(): array {
        return [
            'empty body' => [''],
            'error envelope' => ['{"error":"boom"}'],
            'payload without status' => ['{"payload":{"foo":"bar"}}'],
        ];
    }

    /**
     * make_naas_client must build naas_client from merged plugin + global config (no HTTP).
     */
    public function test_make_naas_client_returns_naas_client(): void {
        global $CFG;
        $m = new \ReflectionMethod(xapi::class, 'make_naas_client');
        $m->setAccessible(true);
        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        $client = $m->invoke(null, $config);
        $this->assertInstanceOf(\mod_naas\naas_client::class, $client);
    }

    /**
     * require_active_course_enrolment must not throw when the user has an active enrolment.
     *
     * @covers \mod_naas\external\xapi::require_active_course_enrolment
     */
    public function test_require_active_course_enrolment_passes_when_enrolled(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        // The gate returns nothing and throws on refusal: getting past it is the check.
        $this->expectNotToPerformAssertions();
        xapi_enrol_test_proxy::invoke_require_active_course_enrolment((int) $course->id);
    }

    /**
     * require_active_course_enrolment must throw error:not_enrolled when there is no active enrolment.
     *
     * @covers \mod_naas\external\xapi::require_active_course_enrolment
     */
    public function test_require_active_course_enrolment_throws_when_not_actively_enrolled(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        try {
            xapi_enrol_test_proxy::invoke_require_active_course_enrolment((int) $course->id);
            $this->fail('Expected moodle_exception with error:not_enrolled');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:not_enrolled', $e->errorcode);
        }
    }

    /**
     * Client stub that records the outbound statement and answers like an accepting NaaS.
     *
     * @return \mod_naas\testable_naas_client
     */
    private function capturing_client(): \mod_naas\testable_naas_client {
        $client = new \mod_naas\testable_naas_client((object) [
            'naas_endpoint' => 'https://Stub.example',
            'naas_username' => 'u',
            'naas_password' => 'p',
            'naas_structure_id' => 's',
        ]);
        $client->stubjson = '{"payload":{"statusCode":202,"statusMessage":"Accepted"}}';
        return $client;
    }

    /**
     * Name of an xapi subclass whose HTTP client is $client.
     *
     * Every call sets the injected client, so a value left over from an
     * earlier test is never used.
     *
     * @param \mod_naas\naas_client $client
     * @return string
     */
    private function xapi_using(\mod_naas\naas_client $client): string {
        $injectable = new class extends xapi {
            /** @var \mod_naas\naas_client|null */
            public static $naasinjection = null;

            /**
             * Return the injected NaaS client.
             *
             * @param object $config
             * @return \mod_naas\naas_client
             */
            protected static function make_naas_client(object $config): \mod_naas\naas_client {
                return self::$naasinjection ?? parent::make_naas_client($config);
            }
        };
        $classname = \get_class($injectable);
        $classname::$naasinjection = $client;
        return $classname;
    }
}

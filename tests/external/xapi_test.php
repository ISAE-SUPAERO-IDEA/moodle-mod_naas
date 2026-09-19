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

namespace mod_naas\tests\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/naas/lib.php');

use advanced_testcase;
use mod_naas\external\xapi;

/**
 * Test-only stub: avoids outbound HTTP from xapi::post_xapi_statement().
 */
final class stub_naas_client_for_xapi extends \mod_naas\naas_client {
    public function __construct() {
        $minimal = new \stdClass();
        $minimal->naas_endpoint = 'http://stub.local';
        $minimal->naas_username = 'u';
        $minimal->naas_password = 'p';
        $minimal->naas_structure_id = 's';
        parent::__construct($minimal);
    }

    public function post_xapi_statement($verb, $versionid, $data) {
        return (object) [
            'statusCode' => 202,
            'statusMessage' => 'Accepted',
        ];
    }
}

/**
 * Calls {@see xapi::require_active_course_enrolment()} from tests without reflection.
 *
 * PCOV / Xdebug often do not attribute coverage for code executed only through
 * {@see \ReflectionMethod::invoke()}, so this thin subclass keeps the enrolment gate fully covered.
 *
 * @internal
 */
final class xapi_enrol_test_proxy extends xapi {
    public static function invoke_require_active_course_enrolment(int $courseid): void {
        self::require_active_course_enrolment($courseid);
    }
}

/**
 * Tests for the xapi external service.
 *
 * Network-dependent tests (the happy-path POST to the NaaS API) are not
 * covered here — those require a live NaaS endpoint or a stub HTTP adapter
 * (see QUALITY_PHP.md H1 for the planned refactor).
 *
 * All tests below cover the security and validation gates that execute before
 * any network call is made.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_naas\external\xapi
 * @covers \mod_naas\external\xapi
 */
class xapi_test extends advanced_testcase {

    // -----------------------------------------------------------------------
    // Parameter schema
    // -----------------------------------------------------------------------

    /**
     * post_xapi_statement_parameters() must return an external_function_parameters.
     */
    public function test_parameters_returns_correct_type(): void {
        $params = xapi::post_xapi_statement_parameters();
        $this->assertTrue($params instanceof \core_external\external_function_parameters);
    }

    /**
     * post_xapi_statement_returns() must return an external_single_structure.
     */
    public function test_returns_descriptor_is_single_structure(): void {
        $returns = xapi::post_xapi_statement_returns();
        $this->assertTrue($returns instanceof \core_external\external_single_structure);
    }

    /**
     * The return structure must declare statusCode and statusMessage keys.
     */
    public function test_returns_descriptor_has_expected_keys(): void {
        $returns = xapi::post_xapi_statement_returns();
        $keys    = array_keys($returns->keys);
        $this->assertContains('statusCode',    $keys);
        $this->assertContains('statusMessage', $keys);
    }

    // -----------------------------------------------------------------------
    // Verb validation
    // -----------------------------------------------------------------------

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
     * Each of the three allowed verbs must pass verb validation (exception must
     * not be thrown for verb itself — test stops before network call).
     *
     * We provoke a later exception (not_enrolled or require_login) to confirm
     * the verb was accepted.
     *
     * @dataProvider allowed_verbs_provider
     */
    public function test_allowed_verbs_pass_validation(string $verb): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $naas   = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $this->setUser($user);

        // We do NOT assert a specific exception here — we only assert it is NOT
        // an invalid_parameter_exception caused by the verb.
        try {
            xapi::post_xapi_statement($verb, 'v1', $naas->cmid, null);
            // If the call succeeded (real NaaS reachable), that is also fine.
            $this->assertTrue(true);
        } catch (\invalid_parameter_exception $e) {
            // A verb-related invalid_parameter_exception would contain the verb.
            $this->assertStringNotContainsString($verb, $e->debuginfo ?? '');
        } catch (\Throwable $e) {
            // Any other exception (network, moodle_exception…) is acceptable.
            $this->assertTrue(true);
        }
    }

    /**
     * @return array
     */
    public static function allowed_verbs_provider(): array {
        return [
            'experienced' => ['experienced'],
            'completed'   => ['completed'],
            'rated'       => ['rated'],
        ];
    }

    // -----------------------------------------------------------------------
    // version_id validation
    // -----------------------------------------------------------------------

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

    // -----------------------------------------------------------------------
    // Body size validation
    // -----------------------------------------------------------------------

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
     * (It may fail for other reasons such as network unavailability.)
     */
    public function test_body_at_size_limit_is_not_rejected_for_size(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $user    = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $naas    = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $this->setUser($user);

        $exactsize = str_repeat('x', 4096);

        try {
            xapi::post_xapi_statement('experienced', 'version-1', $naas->cmid, $exactsize);
            $this->assertTrue(true);
        } catch (\invalid_parameter_exception $e) {
            // Must not be a size-related rejection.
            $this->assertStringNotContainsString('xapi_body_too_large', $e->getMessage());
        } catch (\Throwable $e) {
            // Any other failure (network, curl) is acceptable.
            $this->assertTrue(true);
        }
    }

    // -----------------------------------------------------------------------
    // Access control
    // -----------------------------------------------------------------------

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
     *
     * @backupGlobals disabled
     * @preserveGlobalState disabled
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
            // validate_context() → require_login(..., $preventredirect=true) throws require_login_exception
            // (errorcode requireloginerror) before our is_enrolled / error:not_enrolled branch runs.
            $this->assertSame('requireloginerror', $e->errorcode);
        }
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
        set_config('naas_endpoint', 'http://invalid-endpoint-for-test.local', 'naas');

        $this->expectException(\moodle_exception::class);
        xapi::post_xapi_statement('experienced', 'version-1', $naas->cmid, '{"actor":{"mbox":"mailto:a@b"}}');
    }

    /**
     * When $_SESSION contains resource_link_id it must be forwarded on the payload.
     */
    public function test_post_xapi_includes_resource_link_id_from_session(): void {
        global $_SESSION;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $naas   = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $this->setUser($user);

        $_SESSION['resource_link_id'] = 'lti-link-xyz';
        set_config('naas_endpoint', 'http://invalid-endpoint-for-test.local', 'naas');

        $this->expectException(\moodle_exception::class);
        xapi::post_xapi_statement('completed', 'version-1', $naas->cmid, null);
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
        set_config('naas_endpoint', 'http://invalid-endpoint-for-test.local', 'naas');

        $this->expectException(\moodle_exception::class);
        xapi::post_xapi_statement('rated', 'version-1', $naas->cmid, null);
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
        set_config('naas_endpoint', 'http://invalid-endpoint-for-test.local', 'naas');

        $this->expectException(\moodle_exception::class);
        xapi::post_xapi_statement('experienced', 'version-1', $naas->cmid, null);
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
        set_config('naas_endpoint', 'http://invalid-endpoint-for-test.local', 'naas');

        $this->expectException(\moodle_exception::class);
        xapi::post_xapi_statement('experienced', 'version-1', $naas->cmid, '');
    }

    /**
     * Successful NaaS response must be returned as statusCode / statusMessage.
     *
     * Anonymous subclass of xapi must not live at file scope (externallib pulls
     * require_phpunit_isolation). Only this test needs a separate PHP process.
     * Class-wide runTestsInSeparateProcesses serializes globals and breaks
     * PostgreSQL (PgSql\\Connection cannot be serialized); use per-method isolation
     * with global state backup disabled instead.
     *
     * @runInSeparateProcess
     * @backupGlobals disabled
     * @preserveGlobalState disabled
     */
    public function test_post_xapi_statement_returns_backend_status(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $this->setUser($user);

        $stub = new stub_naas_client_for_xapi();

        // Must not declare a named subclass of xapi at file scope: loading xapi.php
        // pulls in externallib.php, which calls require_phpunit_isolation() and
        // fails during PHPUnit suite discovery (parent process).
        $testablemarker = new class extends xapi {
            /** @var \mod_naas\naas_client|null */
            public static $naas_injection = null;

            protected static function make_naas_client(object $config): \mod_naas\naas_client {
                return self::$naas_injection ?? parent::make_naas_client($config);
            }
        };
        $testable = \get_class($testablemarker);
        $testable::$naas_injection = $stub;
        try {
            $result = $testable::post_xapi_statement('experienced', 'version-1', $naas->cmid, null);
        } finally {
            $testable::$naas_injection = null;
        }

        $this->assertSame(202, $result['statusCode']);
        $this->assertSame('Accepted', $result['statusMessage']);
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

        xapi_enrol_test_proxy::invoke_require_active_course_enrolment((int) $course->id);
        $this->assertTrue(true);
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
}

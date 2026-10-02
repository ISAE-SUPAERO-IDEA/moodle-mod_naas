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
 * Tests for mod_naas\external\proxy_naas_api.
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
use mod_naas\external\proxy_naas_api;

require_once(__DIR__ . '/../fixtures/proxy_naas_api_enrol_test_proxy.php');

/**
 * Tests for the proxy_naas_api external service.
 *
 * Scope: security gates (auth, enrolment, capability) and input validation
 * that execute before any outbound HTTP call.  Network-dependent happy paths
 * are excluded until an HttpAdapter injection point exists in naas_client
 * (see QUALITY_PHP.md H1).
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/).
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 * @coversDefaultClass \mod_naas\external\proxy_naas_api
 * @covers \mod_naas\external\proxy_naas_api
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class proxy_naas_api_test extends advanced_testcase {
    // Parameter schemas.

    /**
     * get_nugget_parameters() must return an external_function_parameters.
     */
    public function test_get_nugget_parameters_type(): void {
        $this->assertInstanceOf(
            \core_external\external_function_parameters::class,
            proxy_naas_api::get_nugget_parameters()
        );
    }

    /**
     * search_nuggets_parameters() must return an external_function_parameters.
     */
    public function test_search_nuggets_parameters_type(): void {
        $this->assertInstanceOf(
            \core_external\external_function_parameters::class,
            proxy_naas_api::search_nuggets_parameters()
        );
    }

    /**
     * get_domain_parameters() must return an external_function_parameters.
     */
    public function test_get_domain_parameters_type(): void {
        $this->assertInstanceOf(
            \core_external\external_function_parameters::class,
            proxy_naas_api::get_domain_parameters()
        );
    }

    /**
     * get_structure_parameters() must return an external_function_parameters.
     */
    public function test_get_structure_parameters_type(): void {
        $this->assertInstanceOf(
            \core_external\external_function_parameters::class,
            proxy_naas_api::get_structure_parameters()
        );
    }

    public function test_test_config_parameters_type(): void {
        $this->assertInstanceOf(\core_external\external_function_parameters::class, proxy_naas_api::test_config_parameters());
    }

    public function test_test_config_returns_type(): void {
        $this->assertInstanceOf(\core_external\external_value::class, proxy_naas_api::test_config_returns());
    }

    public function test_get_nugget_returns_type(): void {
        $this->assertInstanceOf(\core_external\external_value::class, proxy_naas_api::get_nugget_returns());
    }

    public function test_view_nugget_returns_type(): void {
        $this->assertInstanceOf(\core_external\external_value::class, proxy_naas_api::view_nugget_returns());
    }

    public function test_view_nugget_parameters_type(): void {
        $this->assertInstanceOf(\core_external\external_function_parameters::class, proxy_naas_api::view_nugget_parameters());
    }

    public function test_get_nugget_preview_parameters_type(): void {
        $this->assertInstanceOf(
            \core_external\external_function_parameters::class,
            proxy_naas_api::get_nugget_preview_parameters()
        );
    }

    public function test_get_person_parameters_type(): void {
        $this->assertInstanceOf(\core_external\external_function_parameters::class, proxy_naas_api::get_person_parameters());
    }

    public function test_get_nugget_preview_returns_type(): void {
        $this->assertInstanceOf(\core_external\external_value::class, proxy_naas_api::get_nugget_preview_returns());
    }

    public function test_get_domain_returns_type(): void {
        $this->assertInstanceOf(\core_external\external_value::class, proxy_naas_api::get_domain_returns());
    }

    public function test_get_structure_returns_type(): void {
        $this->assertInstanceOf(\core_external\external_value::class, proxy_naas_api::get_structure_returns());
    }

    public function test_get_person_returns_type(): void {
        $this->assertInstanceOf(\core_external\external_value::class, proxy_naas_api::get_person_returns());
    }

    public function test_search_nuggets_returns_type(): void {
        $this->assertInstanceOf(\core_external\external_value::class, proxy_naas_api::search_nuggets_returns());
    }

    // Test_config.

    public function test_test_config_requires_admin(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->expectException(\required_capability_exception::class);
        proxy_naas_api::test_config();
    }

    public function test_test_config_executes(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        set_config('naas_endpoint', 'http://Invalid-endpoint-for-test.local', 'naas');
        $this->expectException(\moodle_exception::class);
        proxy_naas_api::test_config();
    }

    // Get_nugget – authentication gate.

    /**
     * get_nugget() called by a guest (not logged in) must throw an exception.
     */
    public function test_get_nugget_requires_login(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $this->setUser(null);

        $this->expectException(\Exception::class);
        proxy_naas_api::get_nugget($course->id, 'valid-uuid-123');
    }

    /**
     * get_nugget() called by a user without addinstance capability must throw.
     */
    public function test_get_nugget_requires_addinstance_capability(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        // Enrol as a plain student (no addinstance).
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $this->expectException(\Exception::class);
        proxy_naas_api::get_nugget($course->id, 'valid-uuid-123');
    }

    // Get_nugget – input validation.

    /**
     * A nuggetId containing path-traversal sequences must be rejected.
     */
    public function test_get_nugget_path_traversal_rejected(): void {
        $this->resetAfterTest(true);

        $course   = $this->getDataGenerator()->create_course();
        $teacher  = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\invalid_parameter_exception::class);
        proxy_naas_api::get_nugget($course->id, '../../etc/passwd');
    }

    /**
     * A nuggetId containing SQL injection characters must be rejected.
     */
    public function test_get_nugget_sql_injection_rejected(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\invalid_parameter_exception::class);
        proxy_naas_api::get_nugget($course->id, "'; DROP TABLE naas; --");
    }

    /**
     * A nuggetId containing spaces must be rejected.
     */
    public function test_get_nugget_requires_capability(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        proxy_naas_api::get_nugget($course->id, 'valid-uuid-123');
    }

    public function test_get_nugget_executes(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);

        // Curl exception when it tries to connect to the dummy API.
        $this->expectException(\moodle_exception::class);
        proxy_naas_api::get_nugget($course->id, 'valid-uuid-123');
    }

    public function test_get_nugget_preview_requires_capability(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        proxy_naas_api::get_nugget_preview($course->id, 'valid-uuid-123');
    }

    public function test_get_nugget_preview_executes(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);
        set_config('naas_endpoint', 'http://Invalid-endpoint-for-test.local', 'naas');

        $this->expectException(\moodle_exception::class);
        proxy_naas_api::get_nugget_preview($course->id, 'valid-uuid-123');
    }

    public function test_get_nugget_spaces_rejected(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\invalid_parameter_exception::class);
        proxy_naas_api::get_nugget($course->id, 'valid uuid with spaces');
    }

    /**
     * A nuggetId that exceeds 128 characters must be rejected.
     */
    public function test_get_nugget_too_long_id_rejected(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\invalid_parameter_exception::class);
        proxy_naas_api::get_nugget($course->id, str_repeat('a', 129));
    }

    // View_nugget – access control.

    /**
     * view_nugget() for an unauthenticated user must throw.
     */
    public function test_view_nugget_requires_login(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $naas   = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $this->setUser(null);

        $this->expectException(\Exception::class);
        proxy_naas_api::view_nugget($naas->cmid);
    }

    /**
     * view_nugget() must reject users who are not actively enrolled, even if they
     * hold the capability via a direct role assignment. Core validate_context() runs
     * require_login first (requireloginerror), before the plugin is_enrolled check.
     */
    public function test_view_nugget_requires_enrolment(): void {
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
            proxy_naas_api::view_nugget($naas->cmid);
            $this->fail('Expected access exception (enrolment enforced before plugin check)');
        } catch (\moodle_exception $e) {
            // Validate_context() → require_login(..., $preventredirect=true) throws require_login_exception.
            // Note: (errorcode requireloginerror) before our is_enrolled / error:not_enrolled branch runs.
            $this->assertSame('requireloginerror', $e->errorcode);
        }
    }

    public function test_view_nugget_requires_capability(): void {
        global $DB;
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability('mod/naas:view', CAP_PROHIBIT, $roleid, \context_course::instance($course->id)->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($user);

        $this->expectException(\require_login_exception::class);
        proxy_naas_api::view_nugget($naas->cmid);
    }

    public function test_view_nugget_executes(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $this->expectException(\moodle_exception::class);
        proxy_naas_api::view_nugget($naas->cmid);
    }

    // Get_domain – validation.

    /**
     * A domainKey with path-traversal characters must be rejected.
     */
    public function test_get_domain_path_traversal_rejected(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(\invalid_parameter_exception::class);
        proxy_naas_api::get_domain($course->id, '../secret');
    }

    /**
     * A domainKey with special characters must be rejected.
     */
    public function test_get_domain_requires_capability(): void {
        global $DB;
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability('mod/naas:view', CAP_PROHIBIT, $roleid, \context_course::instance($course->id)->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        proxy_naas_api::get_domain($course->id, 'domainkey');
    }

    public function test_get_domain_executes(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);

        $this->expectException(\moodle_exception::class);
        proxy_naas_api::get_domain($course->id, 'domainkey');
    }

    public function test_get_domain_special_chars_rejected(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(\invalid_parameter_exception::class);
        proxy_naas_api::get_domain($course->id, 'domain key with spaces!');
    }

    public function test_get_domain_accepts_dotted_vocabulary_codes(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(\moodle_exception::class);
        proxy_naas_api::get_domain($course->id, '01.02.03');
    }

    // Get_structure – validation.

    /**
     * A structureKey with path-traversal characters must be rejected.
     */
    public function test_get_structure_path_traversal_rejected(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(\invalid_parameter_exception::class);
        proxy_naas_api::get_structure($course->id, '../etc/passwd');
    }

    public function test_get_structure_requires_capability(): void {
        global $DB;
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability('mod/naas:view', CAP_PROHIBIT, $roleid, \context_course::instance($course->id)->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        proxy_naas_api::get_structure($course->id, 'uuid123');
    }

    public function test_get_structure_executes(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);

        $this->expectException(\moodle_exception::class);
        proxy_naas_api::get_structure($course->id, 'uuid123');
    }

    /**
     * A structureKey that is too long must be rejected.
     */
    public function test_get_structure_too_long_key_rejected(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(\invalid_parameter_exception::class);
        proxy_naas_api::get_structure($course->id, str_repeat('a', 129));
    }

    // Get_person – validation.

    /**
     * A personKey with special characters must be rejected.
     */
    public function test_get_person_requires_capability(): void {
        global $DB;
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability('mod/naas:view', CAP_PROHIBIT, $roleid, \context_course::instance($course->id)->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        proxy_naas_api::get_person($course->id, 'uuid123');
    }

    public function test_get_person_executes(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);

        $this->expectException(\moodle_exception::class);
        proxy_naas_api::get_person($course->id, 'uuid123');
    }

    public function test_get_person_special_chars_rejected(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(\invalid_parameter_exception::class);
        proxy_naas_api::get_person($course->id, 'person/with/slashes');
    }

    public function test_get_person_accepts_relationship_prefixed_hash(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(\moodle_exception::class);
        proxy_naas_api::get_person(
            $course->id,
            'authored_by:person:abcdef0123456789abcdef0123456789'
        );
    }

    // Get_nugget_preview – validation.

    /**
     * A versionId with path-traversal characters must be rejected.
     */
    public function test_get_nugget_preview_path_traversal_rejected(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\invalid_parameter_exception::class);
        proxy_naas_api::get_nugget_preview($course->id, '../../etc/passwd');
    }

    // Search_nuggets – access control.

    /**
     * search_nuggets() called by an unauthenticated user must throw.
     */
    public function test_search_nuggets_requires_login(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $this->setUser(null);

        $this->expectException(\Exception::class);
        proxy_naas_api::search_nuggets($course->id, []);
    }

    /**
     * search_nuggets() called by a plain student (no addinstance cap) must throw.
     */
    public function test_search_nuggets_requires_capability(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        proxy_naas_api::search_nuggets($course->id, ['fulltext' => 'foo']);
    }

    public function test_search_nuggets_executes(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);
        set_config('naas_endpoint', 'http://Invalid-endpoint-for-test.local', 'naas');

        $this->expectException(\moodle_exception::class);
        proxy_naas_api::search_nuggets($course->id, ['fulltext' => 'foo']);
    }

    /**
     * search_nuggets() called by a plain student (no addinstance cap) must throw.
     */
    public function test_search_nuggets_requires_addinstance_capability(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(\Exception::class);
        proxy_naas_api::search_nuggets($course->id, []);
    }

    /**
     * Test that invalid IDs (containing path traversal or special chars) are rejected.
     *
     * @dataProvider invalid_ids_provider
     * @param string $badid Nugget id that must be rejected.
     */
    public function test_invalid_ids_throw_exception(string $badid): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);

        $this->expectException(\invalid_parameter_exception::class);
        proxy_naas_api::get_nugget($course->id, $badid);
    }

    /**
     * Data provider for invalid IDs.
     */
    public static function invalid_ids_provider(): array {
        return [
            'path traversal' => ['../etc/passwd'],
            'null byte' => ["nugget\0id"],
            'space' => ['nugget id'],
            'slash' => ['nugget/id'],
            'too long' => [str_repeat('a', 129)],
        ];
    }

    // MUC cache short-circuit (no outbound HTTP when entry exists).

    /**
     * When the vocabulary cache already holds a domain payload, get_domain must return it as-is.
     */
    public function test_get_domain_returns_cached_entry_without_http(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cache->set('domain_cachedkey', '{"cached":true}');

        $this->assertSame('{"cached":true}', proxy_naas_api::get_domain($course->id, 'cachedkey'));
    }

    /**
     * Cache miss: must call the API client, store the JSON in MUC, then return it.
     */
    public function test_get_domain_cache_miss_fetches_and_stores(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $domainkey = 'domcover1';
        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cache->delete('domain_' . $domainkey);

        $stub = new class extends \mod_naas\naas_client {
            /**
             * Create the stub.
             */
            public function __construct() {
                $cfg = new \stdClass();
                $cfg->naas_endpoint = 'https://Stub.example';
                $cfg->naas_username = 'u';
                $cfg->naas_password = 'p';
                $cfg->naas_structure_id = 's';
                parent::__construct($cfg);
            }

            /**
             * Return the stubbed HTTP body.
             *
             * @param string $protocol
             * @param string $service
             * @param object|null $data
             * @param array|null $params
             * @SuppressWarnings(PHPMD.UnusedFormalParameter)
             */
            public function request_raw($protocol, $service, $data = null, $params = null) {
                return '{"domain":"cover"}';
            }
        };

        $injectable = new class extends proxy_naas_api {
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
        $proxycls = \get_class($injectable);
        $proxycls::$naasinjection = $stub;
        try {
            $json = $proxycls::get_domain($course->id, $domainkey);
        } finally {
            $proxycls::$naasinjection = null;
        }

        $this->assertSame('{"domain":"cover"}', $json);
        $this->assertSame('{"domain":"cover"}', proxy_naas_api::get_domain($course->id, $domainkey));
    }

    public function test_get_structure_returns_cached_entry_without_http(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cache->set(
            proxy_naas_api::structure_cache_key('structA'),
            '{"name":"ISAE-SUPAERO","acronym":"ISAE"}'
        );

        $this->assertSame(
            '{"name":"ISAE-SUPAERO","acronym":"ISAE"}',
            proxy_naas_api::get_structure($course->id, 'structA')
        );
    }

    /**
     * Cache miss: must call the API client, store the JSON in MUC, then return it.
     */
    public function test_get_structure_cache_miss_fetches_and_stores(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $structurekey = 'structcover1';
        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cache->delete(proxy_naas_api::structure_cache_key($structurekey));

        $stub = new class extends \mod_naas\naas_client {
            /**
             * Create the stub.
             */
            public function __construct() {
                $cfg = new \stdClass();
                $cfg->naas_endpoint = 'https://Stub.example';
                $cfg->naas_username = 'u';
                $cfg->naas_password = 'p';
                $cfg->naas_structure_id = 's';
                parent::__construct($cfg);
            }

            /**
             * Return the stubbed HTTP body.
             *
             * @param string $protocol
             * @param string $service
             * @param object|null $data
             * @param array|null $params
             * @SuppressWarnings(PHPMD.UnusedFormalParameter)
             */
            public function request_raw($protocol, $service, $data = null, $params = null) {
                return '{"name":"Cover Structure","acronym":"COV"}';
            }
        };

        $injectable = new class extends proxy_naas_api {
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
        $proxycls = \get_class($injectable);
        $proxycls::$naasinjection = $stub;
        try {
            $json = $proxycls::get_structure($course->id, $structurekey);
        } finally {
            $proxycls::$naasinjection = null;
        }

        $this->assertSame('{"name":"Cover Structure","acronym":"COV"}', $json);
        $this->assertSame(
            '{"name":"Cover Structure","acronym":"COV"}',
            proxy_naas_api::get_structure($course->id, $structurekey)
        );
    }

    /**
     * Nameless MUC entries must be deleted and refetched, not returned as-is.
     */
    public function test_get_structure_ignores_nameless_cache(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $structurekey = 'struct-nameless';
        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cache->set(
            proxy_naas_api::structure_cache_key($structurekey),
            '{"payload":{"structure_id":"struct-nameless"}}'
        );

        $stub = new class extends \mod_naas\naas_client {
            /**
             * Create the stub.
             */
            public function __construct() {
                $cfg = new \stdClass();
                $cfg->naas_endpoint = 'https://Stub.example';
                $cfg->naas_username = 'u';
                $cfg->naas_password = 'p';
                $cfg->naas_structure_id = 's';
                parent::__construct($cfg);
            }

            /**
             * Return the stubbed HTTP body.
             *
             * @param string $protocol
             * @param string $service
             * @param object|null $data
             * @param array|null $params
             * @SuppressWarnings(PHPMD.UnusedFormalParameter)
             */
            public function request_raw($protocol, $service, $data = null, $params = null) {
                return '{"name":"ISAE-SUPAERO","acronym":"ISAE"}';
            }
        };

        $injectable = new class extends proxy_naas_api {
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
        $proxycls = \get_class($injectable);
        $proxycls::$naasinjection = $stub;
        try {
            $json = $proxycls::get_structure($course->id, $structurekey);
        } finally {
            $proxycls::$naasinjection = null;
        }

        $this->assertStringContainsString('ISAE-SUPAERO', $json);
    }

    /**
     * GET /structures/{id} 404s still resolve names from the producer listing.
     */
    public function test_get_structure_uses_producer_catalog_on_not_found(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $structurekey = '06d37c13-6ffe-4c4a-a9e3-ac227652f98c';
        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cache->delete(proxy_naas_api::structure_cache_key($structurekey));
        $cache->delete('producer_catalog_v3');

        $stub = new class extends \mod_naas\naas_client {
            /**
             * Create the stub.
             */
            public function __construct() {
                $cfg = new \stdClass();
                $cfg->naas_endpoint = 'https://Stub.example';
                $cfg->naas_username = 'u';
                $cfg->naas_password = 'p';
                $cfg->naas_structure_id = 's';
                parent::__construct($cfg);
            }

            /**
             * Return the stubbed HTTP body.
             *
             * @param string $protocol
             * @param string $service
             * @param object|null $data
             * @param array|null $params
             * @SuppressWarnings(PHPMD.UnusedFormalParameter)
             */
            public function request_raw($protocol, $service, $data = null, $params = null) {
                if (str_starts_with((string) $service, '/structures/')) {
                    throw new \moodle_exception('error:naas_api:not_found', 'naas');
                }
                return json_encode([
                    'payload' => [
                        'items' => [
                            [
                                'uuid' => '06d37c13-6ffe-4c4a-a9e3-ac227652f98c',
                                'structure_id' => 'isae-supaero',
                                'name' => 'ISAE-SUPAERO',
                                'acronym' => 'ISAE',
                            ],
                        ],
                        'pages' => 1,
                    ],
                ]);
            }
        };

        $injectable = new class extends proxy_naas_api {
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
        $proxycls = \get_class($injectable);
        $proxycls::$naasinjection = $stub;
        try {
            $json = $proxycls::get_structure($course->id, $structurekey);
        } finally {
            $proxycls::$naasinjection = null;
        }

        $this->assertStringContainsString('ISAE-SUPAERO', $json);
        $this->assertStringContainsString('"acronym":"ISAE"', $json);
    }

    public function test_get_structure_prefers_catalogue_snapshot_over_nameless_cache(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        set_config('naas_endpoint', 'https://api.example.test/api', 'naas');
        set_config('naas_username', 'user', 'naas');
        set_config('naas_password', 'secret', 'naas');
        set_config('naas_structure_id', 'struct-1', 'naas');

        $structurekey = '06d37c13-6ffe-4c4a-a9e3-ac227652f98c';
        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cache->set(
            proxy_naas_api::structure_cache_key($structurekey),
            '{"payload":{"structure_id":"' . $structurekey . '"}}'
        );

        \mod_naas\catalogue_cache::store([
            'fingerprint' => \mod_naas\catalogue_cache::fingerprint((object) get_config('naas')),
            'producers' => [[
                'structure_id' => 'isae-supaero',
                'uuid' => $structurekey,
                'name' => 'ISAE-SUPAERO',
                'acronym' => 'ISAE',
            ]],
        ]);

        $json = proxy_naas_api::get_structure($course->id, $structurekey);
        $this->assertStringContainsString('"acronym":"ISAE"', $json);
    }

    public function test_structure_json_has_name_ignores_uuid_only_payloads(): void {
        $m = new \ReflectionMethod(proxy_naas_api::class, 'structure_json_has_name');
        $m->setAccessible(true);
        $this->assertTrue($m->invoke(null, '{"payload":{"acronym":"ISAE","name":"ISAE-SUPAERO"}}'));
        $this->assertFalse($m->invoke(
            null,
            '{"payload":{"structure_id":"06d37c13-6ffe-4c4a-a9e3-ac227652f98c"}}'
        ));
    }

    public function test_structure_record_matches_uuid_or_structure_id(): void {
        $m = new \ReflectionMethod(proxy_naas_api::class, 'structure_record_matches');
        $m->setAccessible(true);
        $item = (object) [
            'uuid' => '06d37c13-6ffe-4c4a-a9e3-ac227652f98c',
            'structure_id' => 'isae-supaero',
            'acronym' => 'ISAE',
        ];
        $this->assertTrue($m->invoke(null, $item, '06D37C13-6FFE-4C4A-A9E3-AC227652F98C'));
        $this->assertTrue($m->invoke(null, $item, 'isae-supaero'));
        $this->assertFalse($m->invoke(null, $item, 'other-id'));

        $nuxeo = (object) [
            'uid' => '06d37c13-6ffe-4c4a-a9e3-ac227652f98c',
            'title' => 'ISAE-SUPAERO',
            'properties' => (object) [
                'structure:acronym' => 'ISAE',
                'structure:structure_id' => 'isae-supaero',
            ],
        ];
        $normalise = new \ReflectionMethod(proxy_naas_api::class, 'normalise_structure_record');
        $normalise->setAccessible(true);
        $flat = $normalise->invoke(null, $nuxeo);
        $this->assertTrue($m->invoke(null, $flat, '06d37c13-6ffe-4c4a-a9e3-ac227652f98c'));
        $this->assertSame('ISAE', $flat->acronym);
        $this->assertSame('ISAE-SUPAERO', $flat->name);
    }

    public function test_get_person_returns_cached_entry_without_http(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cache->set('person_person99', '{"firstname":"Ada","lastname":"Lovelace"}');

        $this->assertSame(
            '{"firstname":"Ada","lastname":"Lovelace"}',
            proxy_naas_api::get_person($course->id, 'person99')
        );
    }

    /**
     * Cache miss: must call the API client, store the JSON in MUC, then return it.
     */
    public function test_get_person_cache_miss_fetches_and_stores(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $personkey = 'personcover1';
        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cache->delete('person_' . $personkey);

        $stub = new class extends \mod_naas\naas_client {
            /**
             * Create the stub.
             */
            public function __construct() {
                $cfg = new \stdClass();
                $cfg->naas_endpoint = 'https://Stub.example';
                $cfg->naas_username = 'u';
                $cfg->naas_password = 'p';
                $cfg->naas_structure_id = 's';
                parent::__construct($cfg);
            }

            /**
             * Return the stubbed HTTP body.
             *
             * @param string $protocol
             * @param string $service
             * @param object|null $data
             * @param array|null $params
             * @SuppressWarnings(PHPMD.UnusedFormalParameter)
             */
            public function request_raw($protocol, $service, $data = null, $params = null) {
                return '{"firstname":"Ada","lastname":"Lovelace"}';
            }
        };

        $injectable = new class extends proxy_naas_api {
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
        $proxycls = \get_class($injectable);
        $proxycls::$naasinjection = $stub;
        try {
            $json = $proxycls::get_person($course->id, $personkey);
        } finally {
            $proxycls::$naasinjection = null;
        }

        $this->assertSame('{"firstname":"Ada","lastname":"Lovelace"}', $json);
        $this->assertSame(
            '{"firstname":"Ada","lastname":"Lovelace"}',
            proxy_naas_api::get_person($course->id, $personkey)
        );
    }

    /**
     * A Nuxeo person document keeps the name under properties, not firstname.
     */
    public function test_get_person_lifts_nuxeo_properties(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $personkey = 'abcdef0123456789abcdef0123456789';
        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cache->delete('person_' . $personkey);

        $stub = new class extends \mod_naas\naas_client {
            /**
             * Create the stub.
             */
            public function __construct() {
                $cfg = new \stdClass();
                $cfg->naas_endpoint = 'https://Stub.example';
                $cfg->naas_username = 'u';
                $cfg->naas_password = 'p';
                $cfg->naas_structure_id = 's';
                parent::__construct($cfg);
            }

            /**
             * Return the stubbed HTTP body.
             *
             * @param string $protocol
             * @param string $service
             * @param object|null $data
             * @param array|null $params
             * @SuppressWarnings(PHPMD.UnusedFormalParameter)
             */
            public function request_raw($protocol, $service, $data = null, $params = null) {
                return json_encode([
                    'uid' => 'person-doc',
                    'properties' => [
                        'person:firstname' => 'Ada',
                        'person:lastname' => 'Lovelace',
                        'person:email' => 'abcdef0123456789abcdef0123456789',
                    ],
                ]);
            }
        };

        $injectable = new class extends proxy_naas_api {
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
        $proxycls = \get_class($injectable);
        $proxycls::$naasinjection = $stub;
        try {
            $json = $proxycls::get_person($course->id, $personkey);
        } finally {
            $proxycls::$naasinjection = null;
        }

        $this->assertStringContainsString('"firstname":"Ada"', $json);
        $this->assertStringContainsString('"lastname":"Lovelace"', $json);
    }

    /**
     * GET /persons/{id} 404s still resolve names from the person listing.
     * Author aggregation keys are the encrypted email stored on each row.
     */
    public function test_get_person_uses_person_catalog_on_not_found(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $personkey = 'abcdef0123456789abcdef0123456789';
        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cache->delete('person_' . $personkey);
        $cache->delete('person_catalog_v1');

        $stub = new class extends \mod_naas\naas_client {
            /**
             * Create the stub.
             */
            public function __construct() {
                $cfg = new \stdClass();
                $cfg->naas_endpoint = 'https://Stub.example';
                $cfg->naas_username = 'u';
                $cfg->naas_password = 'p';
                $cfg->naas_structure_id = 's';
                parent::__construct($cfg);
            }

            /**
             * Return the stubbed HTTP body.
             *
             * @param string $protocol
             * @param string $service
             * @param object|null $data
             * @param array|null $params
             * @SuppressWarnings(PHPMD.UnusedFormalParameter)
             */
            public function request_raw($protocol, $service, $data = null, $params = null) {
                if (str_starts_with((string) $service, '/persons/')) {
                    if ($service === '/persons/search') {
                        return json_encode([
                            'items' => [
                                [
                                    'email' => 'abcdef0123456789abcdef0123456789',
                                    'firstname' => 'Ada',
                                    'lastname' => 'Lovelace',
                                ],
                            ],
                            'pages' => 1,
                        ]);
                    }
                    throw new \moodle_exception('error:naas_api:not_found', 'naas');
                }
                throw new \moodle_exception('error:naas_api:not_found', 'naas');
            }
        };

        $injectable = new class extends proxy_naas_api {
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
        $proxycls = \get_class($injectable);
        $proxycls::$naasinjection = $stub;
        try {
            $json = $proxycls::get_person(
                $course->id,
                'authored_by:person:' . $personkey
            );
        } finally {
            $proxycls::$naasinjection = null;
        }

        $this->assertStringContainsString('"firstname":"Ada"', $json);
        $this->assertStringContainsString('"lastname":"Lovelace"', $json);
    }

    // Search_nuggets – query shaping.

    /**
     * With no page_size in options, the proxy must default to 6 before calling the client.
     */
    public function test_search_nuggets_applies_default_page_size(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);
        set_config('naas_endpoint', 'http://Invalid-endpoint-for-test.local', 'naas');

        $this->expectException(\moodle_exception::class);
        proxy_naas_api::search_nuggets($course->id, ['fulltext' => 'nugget']);
    }

    /**
     * When naas_filter is configured, search must add an encoded nql parameter.
     */
    public function test_search_nuggets_adds_nql_when_filter_configured(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);
        set_config('naas_endpoint', 'http://Invalid-endpoint-for-test.local', 'naas');
        set_config('naas_filter', 'type:video', 'naas');

        $this->expectException(\moodle_exception::class);
        proxy_naas_api::search_nuggets($course->id, []);
    }

    /**
     * Combined site filters must appear on the NaaS search URL.
     */
    public function test_search_url_includes_licence_and_nql_filters(): void {
        $options = \mod_naas\catalogue_filters::apply(
            ['page_size' => 6, 'is_default_version' => true],
            (object) [
                'naas_filter' => 'type:video',
                'naas_license_filter' => \mod_naas\catalogue_filters::LICENSE_COMMERCIAL,
            ]
        );
        $url = proxy_naas_api::search_url($options);
        $this->assertStringContainsString('nql=', $url);
        $this->assertStringContainsString('access_licences', $url);
        $this->assertStringNotContainsString('license=1', $url);
        $this->assertStringNotContainsString('%2528', $url);
    }

    // Active enrolment gate (shared with view_nugget).

    /**
     * An enrolled user passes the active enrolment gate.
     *
     * @covers \mod_naas\external\proxy_naas_api::require_active_course_enrolment
     */
    public function test_require_active_course_enrolment_passes_when_enrolled(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        proxy_naas_api_enrol_test_proxy::invoke_require_active_course_enrolment((int) $course->id);
        $this->assertTrue(true);
    }

    /**
     * A user who is not actively enrolled is rejected.
     *
     * @covers \mod_naas\external\proxy_naas_api::require_active_course_enrolment
     */
    public function test_require_active_course_enrolment_throws_when_not_actively_enrolled(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        try {
            proxy_naas_api_enrol_test_proxy::invoke_require_active_course_enrolment((int) $course->id);
            $this->fail('Expected moodle_exception with error:not_enrolled');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:not_enrolled', $e->errorcode);
        }
    }

    // Private helpers (reflection — no HTTP).

    /**
     * sanitise_json_response must return the original string when it is not valid JSON.
     */
    public function test_sanitise_json_response_returns_original_when_invalid(): void {
        $raw = "not-json-{";
        $this->assertSame($raw, $this->invoke_sanitise_json_response($raw));
    }

    /**
     * sanitise_json_response must re-encode valid JSON (normalised representation).
     */
    public function test_sanitise_json_response_reencodes_valid_json(): void {
        $out = $this->invoke_sanitise_json_response('{"b":2,"a":1}');
        $this->assertEquals(['b' => 2, 'a' => 1], json_decode($out, true));
    }

    public function test_validate_id_param_accepts_slug(): void {
        $this->invoke_validate_id_param('valid_slug-01', 'testParam');
        $this->assertTrue(true);
    }

    public function test_validate_id_param_rejects_invalid(): void {
        $this->expectException(\invalid_parameter_exception::class);
        $this->invoke_validate_id_param('bad/id', 'testParam');
    }

    /**
     * make_naas_client must build naas_client from merged plugin + global config (no HTTP).
     */
    public function test_make_naas_client_returns_naas_client(): void {
        global $CFG;
        $m = new \ReflectionMethod(proxy_naas_api::class, 'make_naas_client');
        $m->setAccessible(true);
        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        $client = $m->invoke(null, $config);
        $this->assertInstanceOf(\mod_naas\naas_client::class, $client);
    }

    /**
     * Call the private JSON sanitiser.
     *
     * @param string $json
     * @return string
     */
    private function invoke_sanitise_json_response(string $json): string {
        $m = new \ReflectionMethod(proxy_naas_api::class, 'sanitise_json_response');
        $m->setAccessible(true);
        return $m->invoke(null, $json);
    }

    /**
     * Call the private id parameter check.
     *
     * @param string $value
     * @param string $paramname
     */
    private function invoke_validate_id_param(string $value, string $paramname): void {
        $m = new \ReflectionMethod(proxy_naas_api::class, 'validate_id_param');
        $m->setAccessible(true);
        $m->invoke(null, $value, $paramname);
    }
}

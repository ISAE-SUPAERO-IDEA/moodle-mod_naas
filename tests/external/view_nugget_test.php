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
 * Tests for the mod_naas_view_nugget web service.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\external;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/naas_client_stubs.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\view_nugget::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\naas_external_base::class)]
/**
 * Tests for view_nugget: login, enrolment and capability gates, and the stored Nugget id check.
 *
 * NaaS is never reached: the NaaS client is injected through proxy_using().
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\external\view_nugget
 * @covers \mod_naas\external\naas_external_base
 */
final class view_nugget_test extends \advanced_testcase {
    use naas_client_stubs;

    /**
     * view_nugget for an unauthenticated user must throw.
     */
    public function test_view_nugget_requires_login(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $naas   = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $this->setUser(null);

        $this->expectException(\Exception::class);
        view_nugget::execute($naas->cmid);
    }

    /**
     * view_nugget must reject users who are not actively enrolled, even if they
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
            view_nugget::execute($naas->cmid);
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
    public function test_view_nugget_rejects_a_non_enrolled_admin(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $this->setAdminUser();

        try {
            view_nugget::execute($naas->cmid);
            $this->fail('Expected error:not_enrolled for an admin without enrolment');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:not_enrolled', $e->errorcode);
        }
    }

    /**
     * A student whose role prohibits mod/naas:view cannot open the activity.
     */
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
        view_nugget::execute($naas->cmid);
    }

    /**
     * An enrolled student reaches NaaS, whose failure is reported.
     */
    public function test_view_nugget_executes(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $proxy = $this->proxy_using($this->failing_client());
        try {
            $proxy::execute($naas->cmid);
            $this->fail('The NaaS failure must reach the caller');
        } catch (\moodle_exception $e) {
            // Errors other than "not found" are passed on unchanged.
            $this->assertSame('error:proxy_naas_api:curl', $e->errorcode);
        }
    }

    /**
     * NaaS 404 on the default version means the Nugget was unpublished or deleted.
     */
    public function test_view_nugget_reports_a_missing_nugget(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $client = $this->routing_client(function (): string {
            throw new \moodle_exception('error:naas_api:not_found', 'naas', '', null, '{"code":404,"error":""}');
        });
        try {
            $this->proxy_using($client)::execute($naas->cmid);
            $this->fail('A missing Nugget must be reported');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_nugget_not_found', $e->errorcode);
            // With debugging on, Moodle appends the debug info to the message.
            $this->assertStringStartsWith(get_string('error_nugget_not_found', 'naas'), $e->getMessage());
            $this->assertSame('{"code":404,"error":""}', $e->debuginfo);
        }
    }

    /**
     * A stored nugget_id with path characters is rejected before any NaaS request.
     */
    public function test_view_nugget_rejects_path_in_stored_nugget_id(): void {
        global $DB;
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $DB->set_field('naas', 'nugget_id', '../auth', ['id' => $naas->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $client = $this->answering_client('{}');
        $proxy = $this->proxy_using($client);
        try {
            $proxy::execute($naas->cmid);
            $this->fail('A nugget_id containing a path must be rejected');
        } catch (\invalid_parameter_exception $e) {
            $this->assertSame([], $client->captured);
        }
    }

    /**
     * Name of a view_nugget subclass whose NaaS client is $client.
     *
     * @param \mod_naas\naas_client $client
     * @return string
     */
    private function proxy_using(\mod_naas\naas_client $client): string {
        $proxy = new class extends view_nugget {
            use injected_naas_client;
        };
        return $proxy::using($client);
    }
}

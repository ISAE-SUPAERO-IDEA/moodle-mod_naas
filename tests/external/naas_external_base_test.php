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
 * Tests for mod_naas\external\naas_external_base.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\external;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/naas_external_base_test_proxy.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\naas_external_base::class)]
/**
 * Tests for the helpers shared by the NaaS web services: no HTTP request is sent.
 *
 * The protected helpers are called through naas_external_base_test_proxy.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\external\naas_external_base
 */
final class naas_external_base_test extends \advanced_testcase {
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
        $url = naas_external_base::search_url($options);
        $this->assertStringContainsString('nql=', $url);
        $this->assertStringContainsString('access_licences', $url);
        $this->assertStringNotContainsString('license=1', $url);
        $this->assertStringNotContainsString('%2528', $url);
    }

    // Active enrolment gate (used by view_nugget).

    /**
     * An enrolled user passes the active enrolment gate.
     */
    public function test_require_active_course_enrolment_passes_when_enrolled(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        // The gate returns nothing and throws on refusal: getting past it is the check.
        $this->expectNotToPerformAssertions();
        naas_external_base_test_proxy::invoke_require_active_course_enrolment((int) $course->id);
    }

    /**
     * A user who is not actively enrolled is rejected.
     */
    public function test_require_active_course_enrolment_throws_when_not_actively_enrolled(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        try {
            naas_external_base_test_proxy::invoke_require_active_course_enrolment((int) $course->id);
            $this->fail('Expected moodle_exception with error:not_enrolled');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:not_enrolled', $e->errorcode);
        }
    }

    // JSON sanitiser.

    /**
     * sanitise_json_response must return the original string when it is not valid JSON.
     */
    public function test_sanitise_json_response_returns_original_when_invalid(): void {
        $raw = "not-json-{";
        $this->assertSame($raw, naas_external_base::sanitise_json_response($raw));
    }

    /**
     * sanitise_json_response must re-encode valid JSON (normalised representation).
     */
    public function test_sanitise_json_response_returns_a_compact_canonical_encoding(): void {
        $raw = " {\n  \"b\" : 2,\n  \"a\" : \"x\\/y\",\n  \"c\" : \"\\u00e9t\\u00e9\"\n} ";
        $this->assertSame('{"b":2,"a":"x/y","c":"été"}', naas_external_base::sanitise_json_response($raw));
    }

    /**
     * sanitise_json_response keeps every field of valid JSON.
     */
    public function test_sanitise_json_response_reencodes_valid_json(): void {
        $out = naas_external_base::sanitise_json_response('{"b":2,"a":1}');
        $this->assertEquals(['b' => 2, 'a' => 1], json_decode($out, true));
    }

    // Identifier check.

    /**
     * Slugs and UUIDs pass the identifier check.
     */
    public function test_validate_id_param_accepts_slug(): void {
        // The validator returns nothing and throws on refusal: getting past it is the check.
        $this->expectNotToPerformAssertions();
        naas_external_base_test_proxy::invoke_validate_id_param('valid_slug-01', 'testParam');
        naas_external_base_test_proxy::invoke_validate_id_param('3f2b8c4e-1d2a-4b5c-9e8f-0a1b2c3d4e5f', 'testParam');
    }

    /**
     * A value with a path separator fails the identifier check.
     */
    public function test_validate_id_param_rejects_invalid(): void {
        $this->expectException(\invalid_parameter_exception::class);
        naas_external_base_test_proxy::invoke_validate_id_param('bad/id', 'testParam');
    }

    // Client factory.

    /**
     * make_naas_client must build naas_client from merged plugin + global config (no HTTP).
     */
    public function test_make_naas_client_returns_naas_client(): void {
        $config = naas_external_base_test_proxy::invoke_naas_config();
        $client = naas_external_base_test_proxy::invoke_make_naas_client($config);
        $this->assertInstanceOf(\mod_naas\naas_client::class, $client);
    }

    /**
     * The configuration given to the client holds the plugin settings and the site configuration.
     */
    public function test_naas_config_merges_plugin_settings_into_site_config(): void {
        global $CFG;
        $this->resetAfterTest(true);
        set_config('naas_endpoint', 'https://api.naas.example/api', 'naas');

        $config = naas_external_base_test_proxy::invoke_naas_config();

        $this->assertSame('https://api.naas.example/api', $config->naas_endpoint);
        $this->assertSame($CFG->wwwroot, $config->wwwroot);
    }
}

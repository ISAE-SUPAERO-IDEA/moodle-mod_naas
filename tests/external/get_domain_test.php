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
 * Tests for the mod_naas_get_domain web service.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\external;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/naas_client_stubs.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\get_domain::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\naas_external_base::class)]
/**
 * Tests for get_domain: capability, domain key check, the request sent and the vocabulary cache.
 *
 * NaaS is never reached: the NaaS client is injected through proxy_using().
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\external\get_domain
 * @covers \mod_naas\external\naas_external_base
 */
final class get_domain_test extends \advanced_testcase {
    use naas_client_stubs;

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
        get_domain::execute($course->id, '../secret');
    }

    /**
     * A student whose role prohibits mod/naas:view cannot read a domain.
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
        get_domain::execute($course->id, 'domainkey');
    }

    /**
     * A teacher reaches NaaS, whose failure is reported.
     */
    public function test_get_domain_executes(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);

        $proxy = $this->proxy_using($this->failing_client());
        $this->expectException(\moodle_exception::class);
        $proxy::execute($course->id, 'domainkey');
    }

    /**
     * A domainKey with special characters must be rejected.
     */
    public function test_get_domain_special_chars_rejected(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(\invalid_parameter_exception::class);
        get_domain::execute($course->id, 'domain key with spaces!');
    }

    /**
     * Hierarchical vocabulary codes keep their dots in the request path.
     */
    public function test_get_domain_accepts_dotted_vocabulary_codes(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $client = $this->failing_client();
        $proxy = $this->proxy_using($client);
        try {
            $proxy::execute($course->id, '01.02.03');
            $this->fail('Expected the stubbed transport failure');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:proxy_naas_api:curl', $e->errorcode);
        }
        $this->assertSame('/vocabularies/nugget_domains_vocabulary/01.02.03', $client->captured['service']);
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
        $cache->set(\mod_naas\vocabulary_lookup::cache_key('domain_', 'cachedkey'), '{"cached":true}');

        $this->assertSame('{"cached":true}', get_domain::execute($course->id, 'cachedkey'));
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
        $cache->delete(\mod_naas\vocabulary_lookup::cache_key('domain_', $domainkey));

        $proxy = $this->proxy_using($this->answering_client('{"domain":"cover"}'));
        $json = $proxy::execute($course->id, $domainkey);

        $this->assertSame('{"domain":"cover"}', $json);
        // The real service, without a stubbed client, now answers from the cache.
        $this->assertSame('{"domain":"cover"}', get_domain::execute($course->id, $domainkey));
    }

    /**
     * Name of a get_domain subclass whose NaaS client is $client.
     *
     * @param \mod_naas\naas_client $client
     * @return string
     */
    private function proxy_using(\mod_naas\naas_client $client): string {
        $proxy = new class extends get_domain {
            use injected_naas_client;
        };
        return $proxy::using($client);
    }
}

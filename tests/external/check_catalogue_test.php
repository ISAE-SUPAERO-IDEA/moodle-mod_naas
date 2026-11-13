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
 * Tests for the mod_naas_check_catalogue web service.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\external;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/naas_client_stubs.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\check_catalogue::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\naas_external_base::class)]
/**
 * Tests for the landing probe: live answer, short-lived probe cache and fallback when NaaS fails.
 *
 * NaaS is never reached: the NaaS client is injected through proxy_using().
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\external\check_catalogue
 * @covers \mod_naas\external\naas_external_base
 */
final class check_catalogue_test extends \advanced_testcase {
    use naas_client_stubs;

    /** A NaaS search answer with one Nugget and one producer bucket. */
    private const SEARCH = '{"payload":{"items":[{"nugget_id":"n-1","name":"First","modification_date":"2026-05-01"}],'
        . '"aggregations":{"producers":{"buckets":[{"key":"managed_by:structure:isae","docCount":1}]}},"results_count":1}}';

    /**
     * NaaS down: check_catalogue answers with the cached catalogue instead of an error.
     */
    public function test_check_catalogue_falls_back_to_the_cached_catalogue_when_naas_fails(): void {
        global $CFG;
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);
        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        \mod_naas\catalogue_cache::store([
            'fingerprint' => \mod_naas\catalogue_cache::fingerprint($config),
            'search' => ['items' => [], 'aggregations' => [], 'results_count' => 42],
            'producers' => [],
            // Too old for the short-lived probe cache: the live call is attempted.
            'stamp_checked_at' => 0,
        ]);
        $proxy = $this->proxy_using($this->failing_client());

        $result = json_decode($proxy::execute($course->id), true);

        $this->assertTrue($result['from_cache']);
        $this->assertSame(42, $result['results_count']);
    }

    /**
     * NaaS down and nothing cached: the error still reaches the widget.
     */
    public function test_check_catalogue_without_cache_reports_the_naas_error(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);
        \mod_naas\catalogue_cache::purge();
        $proxy = $this->proxy_using($this->failing_client());

        $this->expectException(\moodle_exception::class);
        $proxy::execute($course->id);
    }

    /**
     * The probe records the stamp and producers, and a second probe within a minute is answered from the cache.
     */
    public function test_check_catalogue_records_the_probe_and_reuses_it(): void {
        $this->resetAfterTest(true);
        set_config('naas_endpoint', 'https://api.naas.example/api', 'naas');
        \mod_naas\catalogue_cache::purge();
        \mod_naas\search_cache::purge();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);
        $naas = $this->routing_client(function (): string {
            return self::SEARCH;
        });
        $proxy = $this->proxy_using($naas);

        $live = json_decode($proxy::execute($course->id), true);
        $cached = json_decode($proxy::execute($course->id), true);

        $this->assertFalse($live['from_cache']);
        $this->assertSame(['results_count' => 1, 'newest_modification_date' => '2026-05-01'], $live['stamp']);
        $this->assertSame('isae', $live['producers'][0]['structure_id']);
        $this->assertTrue($cached['from_cache']);
        $this->assertSame($live['producers_digest'], $cached['producers_digest']);
        $this->assertCount(1, $naas->calls);
        $this->assertStringContainsString('sort_by=modification_date', $naas->calls[0]['service']);
    }

    /**
     * Name of a check_catalogue subclass whose NaaS client is $client.
     *
     * @param \mod_naas\naas_client $client
     * @return string
     */
    private function proxy_using(\mod_naas\naas_client $client): string {
        $proxy = new class extends check_catalogue {
            use injected_naas_client;
        };
        return $proxy::using($client);
    }
}

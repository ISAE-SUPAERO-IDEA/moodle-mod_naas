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
 * Tests for the mod_naas_search_nuggets web service.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\external;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/naas_client_stubs.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\search_nuggets::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\naas_external_base::class)]
/**
 * Tests for search_nuggets: access control, the search URL sent and the search cache.
 *
 * NaaS is never reached: the NaaS client is injected through proxy_using().
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\external\search_nuggets
 * @covers \mod_naas\external\naas_external_base
 */
final class search_nuggets_test extends \advanced_testcase {
    use naas_client_stubs;

    /** A NaaS search answer with one Nugget and one producer bucket. */
    private const SEARCH = '{"payload":{"items":[{"nugget_id":"n-1","name":"First","modification_date":"2026-05-01"}],'
        . '"aggregations":{"producers":{"buckets":[{"key":"managed_by:structure:isae","docCount":1}]}},"results_count":1}}';

    // Access control.

    /**
     * search_nuggets called by an unauthenticated user must throw.
     */
    public function test_search_nuggets_requires_login(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $this->setUser(null);

        $this->expectException(\Exception::class);
        search_nuggets::execute($course->id, []);
    }

    /**
     * search_nuggets called by a plain student (no addinstance cap) must throw.
     */
    public function test_search_nuggets_requires_capability(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        search_nuggets::execute($course->id, ['fulltext' => 'foo']);
    }

    /**
     * A teacher reaches NaaS, whose failure is reported.
     */
    public function test_search_nuggets_executes(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);
        $proxy = $this->proxy_using($this->failing_client());

        $this->expectException(\moodle_exception::class);
        $proxy::execute($course->id, ['fulltext' => 'foo']);
    }

    /**
     * search_nuggets called by a plain student (no addinstance cap) must throw.
     */
    public function test_search_nuggets_requires_addinstance_capability(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(\Exception::class);
        search_nuggets::execute($course->id, []);
    }

    // Query shaping.

    /**
     * With no page_size in options, the proxy must default to 6 before calling the client.
     */
    public function test_search_nuggets_applies_default_page_size(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);
        $client = $this->failing_client();
        $proxy = $this->proxy_using($client);

        try {
            $proxy::execute($course->id, ['fulltext' => 'nugget']);
            $this->fail('Expected the stubbed transport failure');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:proxy_naas_api:curl', $e->errorcode);
        }
        $this->assertStringContainsString('page_size=6', $client->captured['service']);
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
        set_config('naas_filter', 'type:video', 'naas');
        $client = $this->failing_client();
        $proxy = $this->proxy_using($client);

        try {
            $proxy::execute($course->id, []);
            $this->fail('Expected the stubbed transport failure');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:proxy_naas_api:curl', $e->errorcode);
        }
        $this->assertStringContainsString('nql=type%3Avideo', $client->captured['service']);
    }

    // Search cache.

    /**
     * The landing search is stored in the catalogue snapshot and in the search cache, then served from it.
     */
    public function test_search_nuggets_caches_the_landing_page(): void {
        $course = $this->log_in_as_teacher_with_clean_caches();
        $naas = $this->routing_client(function (): string {
            return self::SEARCH;
        });
        $proxy = $this->proxy_using($naas);

        $live = json_decode($proxy::execute($course->id, []), true);
        $cached = json_decode($proxy::execute($course->id, []), true);

        $this->assertFalse($live['_cache']['hit']);
        $this->assertTrue($cached['_cache']['hit']);
        $this->assertSame('First', $cached['items'][0]['name']);
        $this->assertCount(1, $naas->calls);
        $this->assertSame(1, \mod_naas\catalogue_cache::get()['search']['results_count']);
    }

    /**
     * A producer clicked by id is served from the page cached under its uuid.
     */
    public function test_search_nuggets_serves_a_producer_page_cached_under_another_id(): void {
        global $CFG;
        $course = $this->log_in_as_teacher_with_clean_caches();
        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        \mod_naas\catalogue_cache::store([
            'fingerprint' => \mod_naas\catalogue_cache::fingerprint($config),
            'producers' => [['structure_id' => 'isae', 'uuid' => 'uuid-isae']],
        ]);
        $naas = $this->routing_client(function (): string {
            return self::SEARCH;
        });
        $proxy = $this->proxy_using($naas);

        $proxy::execute($course->id, ['producers' => ['uuid-isae']]);
        $result = json_decode($proxy::execute($course->id, ['producers' => ['isae']]), true);

        $this->assertTrue($result['_cache']['hit']);
        $this->assertCount(1, $naas->calls, 'The alias hit must not reach NaaS.');
        $this->assertStringContainsString('producers=uuid-isae', $naas->calls[0]['service']);
    }

    /**
     * A search answer that cannot be decoded is passed through and not cached.
     */
    public function test_search_nuggets_passes_an_unusable_answer_through(): void {
        $course = $this->log_in_as_teacher_with_clean_caches();
        $naas = $this->routing_client(function (): string {
            return 'upstream error';
        });
        $proxy = $this->proxy_using($naas);

        $this->assertSame('upstream error', $proxy::execute($course->id, []));
        $this->assertSame('upstream error', $proxy::execute($course->id, []));
        $this->assertCount(2, $naas->calls);
        $this->assertNull(\mod_naas\catalogue_cache::get());
    }

    /**
     * Log in as an editing teacher of a fresh course, with empty catalogue and search caches.
     *
     * @return \stdClass The course.
     */
    private function log_in_as_teacher_with_clean_caches(): \stdClass {
        $this->resetAfterTest(true);
        set_config('naas_endpoint', 'https://api.naas.example/api', 'naas');
        \mod_naas\catalogue_cache::purge();
        \mod_naas\search_cache::purge();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);
        return $course;
    }

    /**
     * Name of a search_nuggets subclass whose NaaS client is $client.
     *
     * @param \mod_naas\naas_client $client
     * @return string
     */
    private function proxy_using(\mod_naas\naas_client $client): string {
        $proxy = new class extends search_nuggets {
            use injected_naas_client;
        };
        return $proxy::using($client);
    }
}

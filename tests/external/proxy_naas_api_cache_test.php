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
 * Tests for the cached paths of the NaaS proxy web services.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\external;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/routing_naas_client.php');

use mod_naas\routing_naas_client;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\proxy_naas_api::class)]
/**
 * Tests for proxy_naas_api answers that succeed: test connection, cache refresh, Nugget, search and probe caches.
 *
 * NaaS is never reached: make_naas_client() returns a routing_naas_client.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\external\proxy_naas_api
 */
final class proxy_naas_api_cache_test extends \advanced_testcase {
    /** A NaaS search answer with one Nugget and one producer bucket. */
    private const SEARCH = '{"payload":{"items":[{"nugget_id":"n-1","name":"First","modification_date":"2026-05-01"}],'
        . '"aggregations":{"producers":{"buckets":[{"key":"managed_by:structure:isae","docCount":1}]}},"results_count":1}}';

    /** @var \stdClass Course the services are called in. */
    private $course;

    /**
     * Log in as an editing teacher of a fresh course, with clean caches.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('naas_endpoint', 'https://api.naas.example/api', 'naas');
        \mod_naas\catalogue_cache::purge();
        \mod_naas\search_cache::purge();
        \mod_naas\nugget_cache::purge();
        $this->course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, 'editingteacher');
        $this->setUser($user);
    }

    /**
     * A NaaS client answering every request with $route($service, $params).
     *
     * @param callable $route fn(string $service, array $params): string.
     * @return routing_naas_client
     */
    private function naas(callable $route): routing_naas_client {
        return new routing_naas_client(function (string $protocol, string $service, $params) use ($route): string {
            return $route($service, (array) $params);
        });
    }

    /**
     * A NaaS client answering every request with the same body.
     *
     * @param string $body
     * @return routing_naas_client
     */
    private function answering(string $body): routing_naas_client {
        return $this->naas(function () use ($body): string {
            return $body;
        });
    }

    /**
     * Name of a proxy_naas_api subclass whose HTTP client is $client.
     *
     * @param \mod_naas\naas_client $client
     * @return string
     */
    private function proxy_using(\mod_naas\naas_client $client): string {
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
                return self::$naasinjection;
            }
        };
        $classname = \get_class($injectable);
        $classname::$naasinjection = $client;
        return $classname;
    }

    /**
     * Test connection returns the compacted NaaS answer and warms the catalogue snapshot.
     */
    public function test_test_config_returns_the_answer_and_warms_the_catalogue(): void {
        $this->setAdminUser();
        $naas = $this->naas(function (string $service): string {
            if (strpos($service, '/nuggets/search') === 0) {
                return str_replace('{"items"', '{ "items"', self::SEARCH);
            }
            return '{"payload":{"items":[{"structure_id":"isae","acronym":"ISAE"}]}}';
        });

        $result = $this->proxy_using($naas)::test_config();

        $this->assertSame(self::SEARCH, $result);
        $this->assertSame('/nuggets/search?is_default_version=1&page_size=2', $naas->calls[0]['service']);
        $snapshot = \mod_naas\catalogue_cache::get();
        $this->assertSame('ISAE', $snapshot['producers'][0]['acronym']);
        $this->assertSame(1, $snapshot['search']['results_count']);
    }

    /**
     * Only an administrator may refresh the caches.
     */
    public function test_refresh_cache_requires_the_admin_capability(): void {
        $this->expectException(\required_capability_exception::class);
        proxy_naas_api::refresh_cache();
    }

    /**
     * An administrator reaches the refresh, which refuses to run without a saved API URL.
     */
    public function test_refresh_cache_runs_the_refresh_for_an_admin(): void {
        $this->setAdminUser();
        set_config('naas_endpoint', '', 'naas');

        try {
            proxy_naas_api::refresh_cache();
            $this->fail('The refresh must refuse an empty API URL');
        } catch (\moodle_exception $e) {
            $this->assertSame('cache_refresh_failed', $e->errorcode);
        }
    }

    /**
     * get_nugget stores the live document, answers the next call from the cache, and revalidates on request.
     */
    public function test_get_nugget_caches_the_document(): void {
        $naas = $this->answering(
            '{"payload":{"nugget_id":"n-1","name":"First","modification_date":"2026-05-01","version_id":"v-1"}}'
        );
        $proxy = $this->proxy_using($naas);

        $live = json_decode($proxy::get_nugget($this->course->id, 'n-1'), true);
        $cached = json_decode($proxy::get_nugget($this->course->id, 'n-1'), true);
        $revalidated = json_decode($proxy::get_nugget($this->course->id, 'n-1', 'revalidate'), true);

        $this->assertSame(['First', false], [$live['name'], $live['_cache']['hit']]);
        $this->assertSame(['First', true], [$cached['name'], $cached['_cache']['hit']]);
        $this->assertSame(['modification_date' => '2026-05-01', 'version_id' => 'v-1'], $cached['_cache']['digest']);
        $this->assertFalse($revalidated['_cache']['hit']);
        $this->assertSame(['/nuggets/n-1/default_version', '/nuggets/n-1/default_version'], $naas->services());
    }

    /**
     * A NaaS answer that is not a Nugget document is passed through and not cached.
     */
    public function test_get_nugget_passes_an_unusable_answer_through(): void {
        $naas = $this->answering('{"error":"gone"}');
        $proxy = $this->proxy_using($naas);

        $this->assertSame('{"error":"gone"}', $proxy::get_nugget($this->course->id, 'n-1'));
        $this->assertSame('{"error":"gone"}', $proxy::get_nugget($this->course->id, 'n-1'));
        $this->assertCount(2, $naas->calls);
    }

    /**
     * An e-mail address is a valid person key.
     */
    public function test_get_person_accepts_an_email_key(): void {
        $naas = $this->answering('{"payload":{"firstname":"Ada","email":"ada@example.test"}}');

        $result = json_decode($this->proxy_using($naas)::get_person($this->course->id, 'ada@example.test'));

        $this->assertSame('Ada', $result->payload->firstname);
        $this->assertSame(['/persons/ada%40example.test'], $naas->services());
    }

    /**
     * The landing search is stored in the catalogue snapshot and in the search cache, then served from it.
     */
    public function test_search_nuggets_caches_the_landing_page(): void {
        $naas = $this->answering(self::SEARCH);
        $proxy = $this->proxy_using($naas);

        $live = json_decode($proxy::search_nuggets($this->course->id, []), true);
        $cached = json_decode($proxy::search_nuggets($this->course->id, []), true);

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
        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        \mod_naas\catalogue_cache::store([
            'fingerprint' => \mod_naas\catalogue_cache::fingerprint($config),
            'producers' => [['structure_id' => 'isae', 'uuid' => 'uuid-isae']],
        ]);
        $naas = $this->answering(self::SEARCH);
        $proxy = $this->proxy_using($naas);

        $proxy::search_nuggets($this->course->id, ['producers' => ['uuid-isae']]);
        $result = json_decode($proxy::search_nuggets($this->course->id, ['producers' => ['isae']]), true);

        $this->assertTrue($result['_cache']['hit']);
        $this->assertCount(1, $naas->calls, 'The alias hit must not reach NaaS.');
        $this->assertStringContainsString('producers=uuid-isae', $naas->calls[0]['service']);
    }

    /**
     * A search answer that cannot be decoded is passed through and not cached.
     */
    public function test_search_nuggets_passes_an_unusable_answer_through(): void {
        $naas = $this->answering('upstream error');
        $proxy = $this->proxy_using($naas);

        $this->assertSame('upstream error', $proxy::search_nuggets($this->course->id, []));
        $this->assertSame('upstream error', $proxy::search_nuggets($this->course->id, []));
        $this->assertCount(2, $naas->calls);
        $this->assertNull(\mod_naas\catalogue_cache::get());
    }

    /**
     * The probe records the stamp and producers, and a second probe within a minute is answered from the cache.
     */
    public function test_check_catalogue_records_the_probe_and_reuses_it(): void {
        $naas = $this->answering(self::SEARCH);
        $proxy = $this->proxy_using($naas);

        $live = json_decode($proxy::check_catalogue($this->course->id), true);
        $cached = json_decode($proxy::check_catalogue($this->course->id), true);

        $this->assertFalse($live['from_cache']);
        $this->assertSame(['results_count' => 1, 'newest_modification_date' => '2026-05-01'], $live['stamp']);
        $this->assertSame('isae', $live['producers'][0]['structure_id']);
        $this->assertTrue($cached['from_cache']);
        $this->assertSame($live['producers_digest'], $cached['producers_digest']);
        $this->assertCount(1, $naas->calls);
        $this->assertStringContainsString('sort_by=modification_date', $naas->calls[0]['service']);
    }
}

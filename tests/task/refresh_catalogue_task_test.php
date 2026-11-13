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
 * Tests for the scheduled task {@see \mod_naas\task\refresh_catalogue} against a simulated NaaS.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\task;

use mod_naas\catalogue_cache;
use mod_naas\search_cache;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\task\refresh_catalogue::class)]
/**
 * Catalogue refresh: replays the cached searches, or seeds the landing ones on a cold site.
 *
 * NaaS answers are queued with curl::mock_response(), which serves the last queued first.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_naas\task\refresh_catalogue
 */
final class refresh_catalogue_task_test extends \advanced_testcase {
    /**
     * Configure a NaaS connection.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('naas_endpoint', 'https://naas.example.test/api', 'naas');
        set_config('naas_username', 'refresh-user', 'naas');
        set_config('naas_password', 'refresh-password', 'naas');
        set_config('naas_structure_id', 'struct-ours', 'naas');
        unset_config('naas_refresh_limit', 'naas');
    }

    /**
     * Drop the NaaS answers a failing test did not consume.
     */
    protected function tearDown(): void {
        $mocks = new \ReflectionProperty(\curl::class, 'mockresponses');
        $mocks->setAccessible(true);
        $mocks->setValue(null, []);
        parent::tearDown();
    }

    /**
     * Configuration as the task builds it.
     *
     * @return object
     */
    private function config(): object {
        global $CFG;
        return (object) array_merge((array) get_config('naas'), (array) $CFG);
    }

    /**
     * Queue NaaS answers in request order.
     *
     * @param mixed ...$bodies Body strings, or a broken_answer() object.
     * @return void
     */
    private function queue(...$bodies): void {
        foreach (array_reverse($bodies) as $body) {
            \curl::mock_response($body);
        }
    }

    /**
     * A NaaS search answer.
     *
     * @param array $nuggetids
     * @param array $buckets Producer aggregation buckets.
     * @return string
     */
    private function search_json(array $nuggetids, array $buckets = []): string {
        $items = [];
        foreach ($nuggetids as $id) {
            $items[] = ['nugget_id' => $id, 'name' => 'Nugget ' . $id, 'modification_date' => '2026-01-01'];
        }
        return json_encode(['payload' => [
            'items' => $items,
            'results_count' => count($items),
            'aggregations' => $buckets ? ['producers' => ['buckets' => $buckets]] : [],
        ]]);
    }

    /**
     * Store a search entry as a teacher's browsing would.
     *
     * @param array $options
     * @param object $config
     * @return string Cache key.
     */
    private function cached_search(array $options, object $config): string {
        $key = search_cache::canonical_key($options, $config);
        search_cache::store($key, $options, $this->search_json(['old']), $config);
        return $key;
    }

    /**
     * The task has a readable name.
     */
    public function test_get_name(): void {
        $this->assertSame(get_string('task_refresh_catalogue', 'naas'), (new refresh_catalogue())->get_name());
    }

    /**
     * Without an endpoint the cron run is a logged skip.
     */
    public function test_execute_skips_without_endpoint(): void {
        set_config('naas_endpoint', '', 'naas');

        $this->expectOutputString("mod_naas: no NaaS endpoint configured, skipping catalogue refresh.\n");
        (new refresh_catalogue())->execute();
    }

    /**
     * The cron run rebuilds the snapshot, replays the searches of this connection and forgets the others.
     */
    public function test_execute_replays_cached_searches(): void {
        $config = $this->config();
        $first = $this->cached_search(['is_default_version' => true, 'page' => 0], $config);
        $second = $this->cached_search(['is_public' => ['true'], 'page' => 0], $config);
        $empty = search_cache::canonical_key([], $config);
        search_cache::store($empty, [], $this->search_json(['old']), $config);
        $otherconfig = clone $config;
        $otherconfig->naas_username = 'someone-else';
        $other = $this->cached_search(['page' => 0], $otherconfig);
        $vocabulary = \cache::make('mod_naas', \mod_naas\vocabulary_lookup::CACHE_AREA);
        $vocabulary->set('producer_catalog_v3', 'stale');

        $this->queue(
            $this->search_json(['landing'], [['key' => 'struct-a', 'doc_count' => 3]]),
            json_encode(['payload' => ['items' => [['structure_id' => 'struct-a', 'name' => 'Alpha']], 'pages' => 1]]),
            $this->search_json(['fresh']),
            $this->search_json(['fresh'])
        );

        $this->expectOutputString("mod_naas: refreshed 2 cached searches.\n");
        (new refresh_catalogue())->execute();

        $snapshot = catalogue_cache::get();
        $this->assertSame(catalogue_cache::fingerprint($config), $snapshot['fingerprint']);
        $this->assertSame(['landing'], array_column($snapshot['search']['items'], 'nugget_id'));
        $this->assertSame('Alpha', $snapshot['producers'][0]['name']);
        $this->assertSame(3, $snapshot['producers'][0]['count']);
        foreach ([$first, $second] as $key) {
            $this->assertSame(['fresh'], array_column(search_cache::get($key, $config)['search']['items'], 'nugget_id'));
        }
        $this->assertSame(['old'], array_column(search_cache::get($empty, $config)['search']['items'], 'nugget_id'));
        $this->assertArrayNotHasKey($other, search_cache::index());
        $this->assertFalse($vocabulary->get('producer_catalog_v3'));
    }

    /**
     * A NaaS answer that cannot be cached does not count as refreshed.
     */
    public function test_refresh_does_not_count_unusable_answers(): void {
        $config = $this->config();
        $key = $this->cached_search(['page' => 0], $config);
        $this->queue(
            $this->search_json(['landing']),
            json_encode(['payload' => ['items' => [['structure_id' => 'struct-a']]]]),
            'null'
        );

        $this->assertSame(0, (new refresh_catalogue())->refresh(false));
        $this->assertSame(['old'], array_column(search_cache::get($key, $config)['search']['items'], 'nugget_id'));
    }

    /**
     * An answer that fails with an unexpected (non-Moodle) error as soon as it is read.
     *
     * @return object
     */
    private function broken_answer(): object {
        return new class {
            /**
             * Reading the body fails.
             *
             * @return string
             */
            public function __toString(): string {
                throw new \RuntimeException('NaaS connection reset');
            }
        };
    }

    /**
     * Queue answers where NaaS knows nothing yet, and the search fails.
     *
     * @return void
     */
    private function queue_failing_search(): void {
        $this->queue(
            'not json',
            json_encode(['payload' => ['items' => []]]),
            json_encode(['payload' => ['items' => []]]),
            $this->broken_answer()
        );
    }

    /**
     * In cron, a failed search is traced and the others go on.
     */
    public function test_refresh_traces_failures_in_cron(): void {
        $config = $this->config();
        $key = $this->cached_search(['page' => 0], $config);
        $this->queue_failing_search();

        $this->expectOutputString("mod_naas: refresh failed for one query: NaaS connection reset\n");
        $this->assertSame(0, (new refresh_catalogue())->refresh(true));
        $this->assertSame(['old'], array_column(search_cache::get($key, $config)['search']['items'], 'nugget_id'));
        $this->assertNull(catalogue_cache::get());
    }

    /**
     * From the settings page, a failed search is only a developer debugging message.
     */
    public function test_refresh_reports_failures_as_debugging_outside_cron(): void {
        $config = $this->config();
        $this->cached_search(['page' => 0], $config);
        $this->queue_failing_search();

        $this->assertSame(0, (new refresh_catalogue())->refresh(false));

        $messages = $this->getDebuggingMessages();
        $this->assertCount(1, $messages);
        $this->assertSame('mod_naas: refresh failed for one query: NaaS connection reset', $messages[0]->message);
        $this->resetDebugging();
    }

    /**
     * On a cold site the landing, Open Access and busiest producer searches are seeded, within the limit.
     */
    public function test_refresh_seeds_landing_searches_on_a_cold_site(): void {
        set_config('naas_refresh_limit', 3, 'naas');
        $this->queue(
            $this->search_json(['landing'], [
                ['key' => 'struct-b', 'doc_count' => 2],
                ['key' => 'Struct-A', 'doc_count' => 9],
            ]),
            json_encode(['payload' => ['items' => [['structure_id' => 'STRUCT-A', 'name' => 'Alpha']], 'pages' => 1]]),
            $this->search_json(['seed-1']),
            $this->search_json(['seed-2']),
            $this->search_json(['seed-3'])
        );

        $this->assertSame(3, (new refresh_catalogue())->refresh(false));

        $queries = array_column(search_cache::index(), 'query');
        $this->assertCount(3, $queries);
        $this->assertContains([
            'page' => 0,
            'page_size' => catalogue_cache::SEARCH_PAGE_SIZE,
            'is_default_version' => true,
        ], $queries);
        $this->assertContains([
            'is_public' => ['true'],
            'page' => 0,
            'page_size' => catalogue_cache::SEARCH_PAGE_SIZE,
            'is_default_version' => true,
        ], $queries);
        // The busiest producer is queried with the aggregation key, as its card sends it.
        $this->assertContains([
            'producers' => ['Struct-A'],
            'page' => 0,
            'page_size' => catalogue_cache::SEARCH_PAGE_SIZE,
            'is_default_version' => true,
        ], $queries);
    }

    /**
     * Seeding matches producers to aggregation keys by any id, falls back to the stored id, skips id-less rows.
     */
    public function test_seed_entries_resolve_producer_query_values(): void {
        $config = $this->config();
        catalogue_cache::store([
            'fingerprint' => catalogue_cache::fingerprint((object) get_config('naas')),
            'warmed_at' => time(),
            'search' => [
                'items' => [],
                'results_count' => 0,
                'aggregations' => ['producers' => ['buckets' => [
                    'not-a-bucket',
                    ['doc_count' => 4],
                    ['key' => 'managed_by:structure:UID-1', 'doc_count' => 7],
                ]]],
            ],
            'producers' => [
                ['name' => 'No id at all', 'count' => 50],
                ['uid' => 'uid-1', 'name' => 'Matched by uid', 'count' => 40],
                ['structure_id' => 'kept-id', 'name' => 'Not in the aggregation', 'count' => 30],
                ['uuid' => 'uuid-only', 'name' => 'Uuid fallback', 'count' => 20],
                ['id' => 'id-only', 'name' => 'Nothing to send', 'count' => 10],
            ],
        ]);
        $seed = new \ReflectionMethod(refresh_catalogue::class, 'seed_entries');
        $seed->setAccessible(true);

        $entries = $seed->invoke(new refresh_catalogue(), $config, 10);

        $producers = [];
        foreach ($entries as $key => $entry) {
            $this->assertSame(search_cache::canonical_key($entry['query'], $config), $key);
            if (isset($entry['query']['producers'])) {
                $producers[] = $entry['query']['producers'][0];
            }
        }
        $this->assertCount(5, $entries);
        $this->assertSame(['managed_by:structure:UID-1', 'kept-id', 'uuid-only'], $producers);

        $this->assertCount(2, $seed->invoke(new refresh_catalogue(), $config, 2));
        $this->assertSame([], $seed->invoke(new refresh_catalogue(), $config, 0));
    }
}

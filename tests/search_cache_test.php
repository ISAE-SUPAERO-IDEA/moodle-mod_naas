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
 * Tests for {@see \mod_naas\search_cache}.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

use advanced_testcase;
use mod_naas\catalogue_cache;
use mod_naas\search_cache;

/**
 * Tests for the cached Nugget search index.
 *
 * @covers \mod_naas\search_cache
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
final class search_cache_test extends advanced_testcase {
    /**
     * Isolate MUC and plugin config.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('naas_endpoint', 'https://api.example.test/api', 'naas');
        set_config('naas_username', 'user', 'naas');
        set_config('naas_password', 'secret', 'naas');
        set_config('naas_structure_id', 'struct-1', 'naas');
        set_config('naas_filter', '', 'naas');
        search_cache::purge();
    }

    /**
     * Current plugin config as the webservice builds it.
     *
     * @return object
     */
    private function config(): object {
        return (object) get_config('naas');
    }

    /**
     * A NaaS-shaped search body.
     *
     * @param array $items
     * @param int|null $count
     * @return string
     */
    private function body(array $items, ?int $count = null): string {
        return json_encode([
            'items' => $items,
            'aggregations' => ['producers' => ['buckets' => []]],
            'results_count' => $count ?? count($items),
        ]);
    }

    /**
     * One search hit.
     *
     * @param string $id
     * @param string $modified
     * @return array
     */
    private function hit(string $id, string $modified = '2026-01-01T00:00:00Z'): array {
        return [
            'nugget_id' => $id,
            'name' => 'Nugget ' . $id,
            'resume' => 'Summary',
            'authors' => [],
            'domains' => [],
            'modification_date' => $modified,
        ];
    }

    public function test_canonical_query_is_stable_across_key_and_value_order(): void {
        $first = search_cache::canonical_query(['page_size' => 9, 'producers' => ['b', 'a']]);
        $second = search_cache::canonical_query(['producers' => ['a', 'b'], 'page_size' => 9]);
        $this->assertSame($first, $second);
    }

    public function test_canonical_query_treats_absent_page_as_zero(): void {
        $this->assertSame(
            search_cache::canonical_query(['page_size' => 9]),
            search_cache::canonical_query(['page_size' => 9, 'page' => 0])
        );
        $this->assertNotSame(
            search_cache::canonical_query(['page_size' => 9, 'page' => 0]),
            search_cache::canonical_query(['page_size' => 9, 'page' => 1])
        );
    }

    public function test_canonical_query_folds_producer_aggregation_keys(): void {
        $bare = search_cache::canonical_query([
            'page_size' => 9,
            'producers' => ['06d37c13-6ffe-4c4a-a9e3-ac227652f98c'],
        ]);
        $prefixed = search_cache::canonical_query([
            'page_size' => 9,
            'producers' => ['managed_by:structure:06d37c13-6ffe-4c4a-a9e3-ac227652f98c'],
        ]);
        $this->assertSame($bare, $prefixed);

        $config = $this->config();
        $key = search_cache::canonical_key([
            'page_size' => 9,
            'producers' => ['06d37c13-6ffe-4c4a-a9e3-ac227652f98c'],
            'is_default_version' => true,
        ], $config);
        search_cache::store(
            $key,
            ['producers' => ['06d37c13-6ffe-4c4a-a9e3-ac227652f98c']],
            $this->body([$this->hit('n1')]),
            $config
        );
        $click = search_cache::canonical_key([
            'page_size' => 9,
            'producers' => ['managed_by:structure:06d37c13-6ffe-4c4a-a9e3-ac227652f98c'],
            'is_default_version' => true,
        ], $config);
        $this->assertSame($key, $click);
        $this->assertNotNull(search_cache::get($click, $config));
    }

    /**
     * A rebuild may have stored the list under the structure slug.
     * The card click still sends the aggregation key.
     */
    public function test_click_finds_search_stored_under_structure_slug(): void {
        $config = $this->config();
        catalogue_cache::store([
            'fingerprint' => catalogue_cache::fingerprint($config),
            'producers' => [[
                'structure_id' => 'isae-supaero',
                'uuid' => '06d37c13-6ffe-4c4a-a9e3-ac227652f98c',
            ]],
            'search' => ['items' => [], 'aggregations' => [], 'results_count' => 0],
        ]);
        $stored = [
            'page_size' => 9,
            'producers' => ['isae-supaero'],
            'is_default_version' => true,
        ];
        search_cache::store(
            search_cache::canonical_key($stored, $config),
            $stored,
            $this->body([$this->hit('n1')]),
            $config
        );

        $click = [
            'page_size' => 9,
            'producers' => ['managed_by:structure:06d37c13-6ffe-4c4a-a9e3-ac227652f98c'],
            'is_default_version' => true,
        ];
        $found = null;
        foreach (catalogue_cache::producer_search_aliases($click) as $alias) {
            $found = search_cache::get(search_cache::canonical_key($alias, $config), $config);
            if ($found !== null) {
                break;
            }
        }
        $this->assertNotNull($found);
        $this->assertSame('n1', $found['search']['items'][0]['nugget_id']);
    }

    public function test_canonical_query_drops_blank_and_empty_values(): void {
        $this->assertSame(
            search_cache::canonical_query(['page_size' => 9]),
            search_cache::canonical_query(['page_size' => 9, 'fulltext' => '', 'tags' => []])
        );
    }

    public function test_canonical_key_differs_per_connection(): void {
        $options = ['page_size' => 9];
        $first = search_cache::canonical_key($options, $this->config());
        set_config('naas_endpoint', 'https://Other.example/api', 'naas');
        $second = search_cache::canonical_key($options, $this->config());
        $this->assertNotSame($first, $second);
    }

    public function test_store_then_get_round_trip(): void {
        $options = ['page_size' => 9];
        $key = search_cache::canonical_key($options, $this->config());
        $stored = search_cache::store($key, $options, $this->body([$this->hit('n1')]), $this->config());

        $this->assertNotNull($stored);
        $entry = search_cache::get($key, $this->config());
        $this->assertNotNull($entry);
        $this->assertSame(1, $entry['results_count']);
        $this->assertSame('n1', $entry['search']['items'][0]['nugget_id']);
    }

    public function test_get_ignores_an_entry_from_another_connection(): void {
        $options = ['page_size' => 9];
        $key = search_cache::canonical_key($options, $this->config());
        search_cache::store($key, $options, $this->body([$this->hit('n1')]), $this->config());

        set_config('naas_password', 'rotated', 'naas');
        $this->assertNull(search_cache::get($key, $this->config()));
    }

    public function test_store_rejects_an_undecodable_body(): void {
        $options = ['page_size' => 9];
        $key = search_cache::canonical_key($options, $this->config());
        $this->assertNull(search_cache::store($key, $options, 'not json', $this->config()));
        $this->assertNull(search_cache::get($key, $this->config()));
    }

    public function test_store_unwraps_a_payload_envelope(): void {
        $options = ['page_size' => 9];
        $key = search_cache::canonical_key($options, $this->config());
        $wrapped = json_encode(['payload' => json_decode($this->body([$this->hit('n1')]), true)]);
        $entry = search_cache::store($key, $options, $wrapped, $this->config());
        $this->assertNotNull($entry);
        $this->assertSame('n1', $entry['search']['items'][0]['nugget_id']);
    }

    public function test_digest_detects_an_addition(): void {
        $before = search_cache::digest([
            'results_count' => 1,
            'id_digest' => search_cache::id_digest([$this->hit('n1')]),
            'max_modification_date' => '2026-01-01T00:00:00Z',
        ]);
        $items = [$this->hit('n1'), $this->hit('n2', '2026-05-05T00:00:00Z')];
        $after = [
            'results_count' => 2,
            'id_digest' => search_cache::id_digest($items),
            'max_modification_date' => search_cache::max_modification_date($items),
        ];
        $this->assertNotSame($before, search_cache::digest($after));
    }

    public function test_digest_detects_a_deletion_that_keeps_the_count(): void {
        // A simultaneous add and delete: same count, different page composition.
        $before = search_cache::id_digest([$this->hit('n1'), $this->hit('n2')]);
        $after = search_cache::id_digest([$this->hit('n1'), $this->hit('n3')]);
        $this->assertNotSame($before, $after);
    }

    public function test_digest_detects_an_edit_that_only_moves_the_date(): void {
        $before = search_cache::max_modification_date([$this->hit('n1', '2026-01-01T00:00:00Z')]);
        $after = search_cache::max_modification_date([$this->hit('n1', '2026-06-01T00:00:00Z')]);
        $this->assertNotSame($before, $after);
        // The id list is unchanged, so only the date carries the signal.
        $this->assertSame(
            search_cache::id_digest([$this->hit('n1', '2026-01-01T00:00:00Z')]),
            search_cache::id_digest([$this->hit('n1', '2026-06-01T00:00:00Z')])
        );
    }

    public function test_response_reports_hit_and_carries_the_digest(): void {
        $options = ['page_size' => 9];
        $key = search_cache::canonical_key($options, $this->config());
        $entry = search_cache::store($key, $options, $this->body([$this->hit('n1')]), $this->config());

        $decoded = json_decode(search_cache::response($entry, true), true);
        $this->assertTrue($decoded['_cache']['hit']);
        $this->assertSame(1, $decoded['_cache']['digest']['results_count']);
        $this->assertArrayHasKey('vocabulary', $decoded);
        $this->assertSame('n1', $decoded['items'][0]['nugget_id']);

        $live = json_decode(search_cache::response($entry, false), true);
        $this->assertFalse($live['_cache']['hit']);
    }

    public function test_index_records_the_query_for_replay(): void {
        $options = ['page_size' => 9, 'producers' => ['p1']];
        $key = search_cache::canonical_key($options, $this->config());
        search_cache::store($key, $options, $this->body([$this->hit('n1')]), $this->config());

        $index = search_cache::index();
        $this->assertArrayHasKey($key, $index);
        $this->assertSame(['p1'], $index[$key]['query']['producers']);
    }

    public function test_index_evicts_the_least_recently_used_overflow(): void {
        for ($i = 0; $i < search_cache::MAX_ENTRIES + 5; $i++) {
            $options = ['page_size' => 9, 'fulltext' => 'term' . $i];
            $key = search_cache::canonical_key($options, $this->config());
            search_cache::store($key, $options, $this->body([$this->hit('n' . $i)]), $this->config());
        }
        $this->assertLessThanOrEqual(search_cache::MAX_ENTRIES, count(search_cache::index()));
    }

    public function test_refreshable_skips_entries_nobody_has_read(): void {
        $options = ['page_size' => 9];
        $key = search_cache::canonical_key($options, $this->config());
        search_cache::store($key, $options, $this->body([$this->hit('n1')]), $this->config());

        $this->assertArrayHasKey($key, search_cache::refreshable(40, 2592000));
        // A cutoff shorter than the entry's age excludes it.
        $this->assertSame([], search_cache::refreshable(40, -1));
    }

    public function test_refreshable_returns_nothing_on_an_empty_index(): void {
        $this->assertSame([], search_cache::refreshable(40, 2592000));
    }

    public function test_refreshable_honours_the_limit(): void {
        for ($i = 0; $i < 5; $i++) {
            $options = ['page_size' => 9, 'fulltext' => 'term' . $i];
            $key = search_cache::canonical_key($options, $this->config());
            search_cache::store($key, $options, $this->body([$this->hit('n' . $i)]), $this->config());
        }
        $this->assertCount(3, search_cache::refreshable(3, 2592000));
    }

    public function test_purge_forgets_every_query(): void {
        $options = ['page_size' => 9];
        $key = search_cache::canonical_key($options, $this->config());
        search_cache::store($key, $options, $this->body([$this->hit('n1')]), $this->config());

        search_cache::purge();
        $this->assertNull(search_cache::get($key, $this->config()));
        $this->assertSame([], search_cache::index());
    }
}

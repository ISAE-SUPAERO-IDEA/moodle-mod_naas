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
 * Tests for the edge cases of the catalogue snapshot helpers.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\catalogue_cache::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\catalogue_export::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\catalogue_producers::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\catalogue_stamp::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\naas_payload::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\nugget_cache::class)]
/**
 * Tests for malformed input, stale snapshots and other connections in the catalogue helpers.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\catalogue_cache
 * @covers \mod_naas\catalogue_export
 * @covers \mod_naas\catalogue_producers
 * @covers \mod_naas\catalogue_stamp
 * @covers \mod_naas\naas_payload
 * @covers \mod_naas\nugget_cache
 */
final class catalogue_snapshot_edges_test extends \advanced_testcase {
    /** @var object Plugin configuration of the current connection. */
    private $config;

    /**
     * Isolate config and caches.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('naas_endpoint', 'https://api.naas.example/api', 'naas');
        $this->config = (object) get_config('naas');
        catalogue_cache::purge();
        nugget_cache::purge();
        \cache::make('mod_naas', vocabulary_lookup::CACHE_AREA)->purge();
    }

    /**
     * Store a snapshot of the current connection.
     *
     * @param array $fields
     */
    private function snapshot(array $fields): void {
        catalogue_cache::store(['fingerprint' => catalogue_cache::fingerprint($this->config)] + $fields);
    }

    /**
     * A facet given with an empty value does not make a search non-landing.
     */
    public function test_is_landing_search_ignores_empty_facets(): void {
        $this->assertTrue(catalogue_cache::is_landing_search(['tags' => [], 'language' => '', 'type' => null]));
        $this->assertFalse(catalogue_cache::is_landing_search(['tags' => [], 'level' => ['beginner']]));
    }

    /**
     * Aggregations from another connection replace its snapshot instead of merging into it.
     */
    public function test_remember_aggregations_drops_another_connections_snapshot(): void {
        catalogue_cache::store([
            'fingerprint' => 'other',
            'producers' => [['structure_id' => 'old', 'count' => 9]],
            'search' => ['items' => [['nugget_id' => 'old']]],
        ]);
        $json = '{"payload":{"items":[{"modification_date":"2026-01-02"}],"results_count":4,'
            . '"aggregations":{"producers":{"buckets":[{"key":"isae","doc_count":4}]}}}}';

        $digest = catalogue_cache::remember_aggregations($json, $this->config);

        $snapshot = catalogue_cache::get();
        $this->assertSame(catalogue_cache::fingerprint($this->config), $snapshot['fingerprint']);
        $this->assertSame(['isae'], array_column($snapshot['producers'], 'structure_id'));
        $this->assertSame([], $snapshot['search']['items']);
        $this->assertSame(4, $snapshot['search']['results_count']);
        $this->assertSame(catalogue_producers::producers_digest($snapshot['producers']), $digest);
    }

    /**
     * A stored probe of another connection is not reused.
     */
    public function test_cached_probe_ignores_another_connection(): void {
        $this->assertNull(catalogue_export::cached_probe($this->config));

        catalogue_cache::store(['fingerprint' => 'other', 'stamp_checked_at' => time()]);
        $this->assertNull(catalogue_export::cached_probe($this->config));
    }

    /**
     * A snapshot without a search still exports, with an empty landing page.
     */
    public function test_export_for_widget_without_a_search(): void {
        $this->snapshot(['producers' => []]);

        $export = catalogue_export::export_for_widget();

        $this->assertSame(
            ['items' => [], 'aggregations' => [], 'results_count' => 0, 'vocabulary' => []],
            $export['search']
        );
    }

    /**
     * Rows without a count or an id do not change the producers digest.
     */
    public function test_producers_digest_ignores_incomplete_rows(): void {
        $digest = catalogue_producers::producers_digest([
            ['structure_id' => 'A', 'count' => 2],
            'junk',
            ['structure_id' => 'b'],
            ['name' => 'no id', 'count' => 1],
        ]);

        $this->assertSame(sha1('a:2'), $digest);
    }

    /**
     * Malformed and blank producer buckets are skipped.
     */
    public function test_reconcile_producers_skips_malformed_buckets(): void {
        $rows = catalogue_producers::reconcile_producers([], ['producers' => ['buckets' => [
            'junk',
            ['doc_count' => 3],
            ['key' => '   '],
            ['key' => 'isae', 'docCount' => 2],
        ]]]);

        $this->assertSame(['isae'], array_column($rows, 'structure_id'));
        $this->assertSame(2, $rows[0]['count']);
    }

    /**
     * Producer names come from the structure cache, also when its payload is a JSON string.
     */
    public function test_hydrate_producer_names_reads_the_structure_cache(): void {
        $cache = \cache::make('mod_naas', vocabulary_lookup::CACHE_AREA);
        $cache->set(structure_lookup::cache_key('isae'), '{"payload":"{\"acronym\":\"ISAE\",\"title\":\"ISAE-SUPAERO\"}"}');
        $cache->set(structure_lookup::cache_key('list'), '[1]');
        $this->snapshot(['producers' => []]);

        $rows = catalogue_producers::hydrate_producer_names([
            ['structure_id' => 'isae', 'needs_resolve' => true],
            ['structure_id' => 'list', 'needs_resolve' => true],
        ]);

        $this->assertSame(['structure_id' => 'isae', 'name' => 'ISAE-SUPAERO', 'acronym' => 'ISAE'], $rows[0]);
        $this->assertTrue($rows[1]['needs_resolve'], 'A cache entry that is not a record names nothing.');
        $this->assertSame($rows, catalogue_cache::get()['producers']);
    }

    /**
     * Without a snapshot no producer is found and no structure JSON is built.
     */
    public function test_find_producer_without_a_snapshot(): void {
        $this->assertNull(catalogue_producers::find_producer('isae'));
        $this->assertNull(catalogue_producers::structure_json('isae'));
    }

    /**
     * Search aliases skip blank values and the requested id itself.
     */
    public function test_producer_search_aliases_skip_blank_and_requested_ids(): void {
        $this->snapshot(['producers' => [['structure_id' => 'isae', 'uuid' => 'u-1', 'id' => 'isae']]]);

        $this->assertSame([], catalogue_producers::producer_search_aliases(['producers' => ['']]));
        $this->assertSame(
            [['page' => 0, 'producers' => ['u-1']]],
            catalogue_producers::producer_search_aliases(['page' => 0, 'producers' => ['isae']])
        );
    }

    /**
     * Without a date on the first item, the stamp takes the newest date of the page.
     */
    public function test_stamp_falls_back_to_the_newest_date_on_the_page(): void {
        $stamp = catalogue_stamp::from_search([
            'items' => [['nugget_id' => 'a'], ['modification_date' => '2026-03-01'], ['modification_date' => '2026-04-01']],
            'results_count' => 3,
        ]);

        $this->assertSame(['results_count' => 3, 'newest_modification_date' => '2026-04-01'], $stamp);
    }

    /**
     * A string that only looks like JSON is returned as it is.
     */
    public function test_unwrap_keeps_a_string_that_is_not_json(): void {
        $this->assertSame('{not json', naas_payload::unwrap('{not json'));
        $this->assertSame(['a' => 1], naas_payload::unwrap('{"payload":"{\"a\":1}"}'));
    }

    /**
     * A Nugget document cached for another connection is not served.
     */
    public function test_nugget_cache_ignores_another_connection(): void {
        nugget_cache::store('n-1', '{"payload":{"nugget_id":"n-1"}}', $this->config);
        $other = (object) ['naas_endpoint' => 'https://other.example/api'];
        \cache::make('mod_naas', nugget_cache::CACHE_AREA)->set(
            nugget_cache::key('n-2', $this->config),
            json_encode(['fingerprint' => catalogue_cache::fingerprint($other), 'document' => ['nugget_id' => 'n-2']])
        );

        $this->assertSame('n-1', nugget_cache::get('n-1', $this->config)['document']['nugget_id']);
        $this->assertNull(nugget_cache::get('n-2', $this->config));
    }
}

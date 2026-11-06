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
 * Tests for {@see \mod_naas\catalogue_cache}.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

use advanced_testcase;
use mod_naas\catalogue_filters;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\catalogue_cache::class)]
/**
 * Tests for the catalogue snapshot record, its fingerprint and key helpers.
 *
 * @covers \mod_naas\catalogue_cache
 */
final class catalogue_cache_test extends advanced_testcase {
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
        catalogue_cache::purge();
    }

    public function test_fingerprint_changes_with_endpoint(): void {
        $config = (object) get_config('naas');
        $first = catalogue_cache::fingerprint($config);
        set_config('naas_endpoint', 'https://Other.example/api', 'naas');
        $second = catalogue_cache::fingerprint((object) get_config('naas'));
        $this->assertNotSame($first, $second);
    }

    public function test_fingerprint_changes_with_license_filter(): void {
        $first = catalogue_cache::fingerprint((object) get_config('naas'));
        set_config('naas_license_filter', catalogue_filters::LICENSE_NONCOMMERCIAL, 'naas');
        $second = catalogue_cache::fingerprint((object) get_config('naas'));
        $this->assertNotSame($first, $second);
    }

    public function test_fingerprint_changes_with_access_filter(): void {
        $first = catalogue_cache::fingerprint((object) get_config('naas'));
        set_config('naas_access_filter', catalogue_filters::ACCESS_RESTRICTED, 'naas');
        $second = catalogue_cache::fingerprint((object) get_config('naas'));
        $this->assertNotSame($first, $second);
    }

    public function test_is_landing_search(): void {
        $this->assertTrue(catalogue_cache::is_landing_search(['page_size' => 9]));
        $this->assertTrue(catalogue_cache::is_landing_search([
            'page_size' => 9,
            'fulltext' => '',
            'is_default_version' => true,
        ]));
        $this->assertFalse(catalogue_cache::is_landing_search(['fulltext' => 'math']));
        $this->assertFalse(catalogue_cache::is_landing_search(['producers' => ['abc']]));
        $this->assertFalse(catalogue_cache::is_landing_search(['page' => 1]));
        $this->assertFalse(catalogue_cache::is_landing_search(['is_public' => ['true']]));
    }

    public function test_remember_search_keeps_producers_and_tracks_modification_date(): void {
        $config = (object) get_config('naas');
        catalogue_cache::store([
            'fingerprint' => catalogue_cache::fingerprint($config),
            'producers' => [['structure_id' => 'isae', 'acronym' => 'ISAE', 'name' => 'ISAE']],
        ]);

        $payload = json_encode([
            'payload' => [
                'items' => [
                    [
                        'nugget_id' => 'n1',
                        'name' => 'Alpha',
                        'resume' => '<p>secret</p>',
                        'modification_date' => '2026-01-01T00:00:00Z',
                    ],
                    [
                        'nugget_id' => 'n2',
                        'name' => 'Beta',
                        'modification_date' => '2026-09-01T12:00:00Z',
                    ],
                ],
                'aggregations' => [
                    'producers' => ['buckets' => [['key' => 'isae', 'docCount' => 4]]],
                ],
                'results_count' => 2,
            ],
        ]);
        catalogue_cache::remember_search($payload, $config);

        $stored = catalogue_cache::get();
        $this->assertSame('ISAE', $stored['producers'][0]['acronym']);
        $this->assertSame('2026-09-01T12:00:00Z', $stored['max_modification_date']);
        $this->assertSame(2, $stored['search']['results_count']);

        $exported = catalogue_export::export_for_widget();
        $this->assertNotNull($exported);
        // The card description ships with the snapshot so the card is complete.
        $this->assertSame('<p>secret</p>', $exported['search']['items'][0]['resume']);
        $this->assertSame('Alpha', $exported['search']['items'][0]['name']);
        $this->assertSame('ISAE', $exported['producers'][0]['acronym']);
        $this->assertSame(4, $exported['producers'][0]['count']);
        $this->assertNotSame('', $exported['producers_digest']);
    }

    public function test_remember_aggregations_refreshes_counts_without_touching_the_cards(): void {
        $config = (object) get_config('naas');
        catalogue_cache::store([
            'fingerprint' => catalogue_cache::fingerprint($config),
            'producers' => [['structure_id' => 'isae', 'uuid' => 'isae', 'acronym' => 'ISAE']],
            'search' => [
                'items' => [['nugget_id' => 'n1', 'name' => 'Alpha']],
                'aggregations' => ['producers' => ['buckets' => [['key' => 'isae', 'docCount' => 4]]]],
                'results_count' => 9,
            ],
        ]);

        // The probe runs with page_size=1, so its single item must not win.
        $digest = catalogue_cache::remember_aggregations(json_encode([
            'items' => [[
                'nugget_id' => 'probe',
                'name' => 'Probe',
                'modification_date' => '2026-09-17T08:00:00Z',
            ]],
            'aggregations' => ['producers' => ['buckets' => [['key' => 'isae', 'docCount' => 11]]]],
            'results_count' => 11,
        ]), $config);

        $stored = catalogue_cache::get();
        $this->assertCount(1, $stored['search']['items']);
        $this->assertSame('n1', $stored['search']['items'][0]['nugget_id']);
        $this->assertSame(11, $stored['search']['results_count']);
        $this->assertSame(11, $stored['producers'][0]['count']);
        $this->assertSame('ISAE', $stored['producers'][0]['acronym']);
        $this->assertSame(catalogue_producers::producers_digest($stored['producers']), $digest);
        $this->assertSame(11, $stored['stamp']['results_count']);
        $this->assertSame('2026-09-17T08:00:00Z', $stored['stamp']['newest_modification_date']);
        $this->assertGreaterThan(0, $stored['stamp_checked_at']);
    }

    public function test_get_returns_null_for_an_empty_or_corrupt_record(): void {
        $this->assertNull(catalogue_cache::get());
        \cache::make('mod_naas', catalogue_cache::CACHE_AREA)->set(catalogue_cache::CACHE_KEY, '{not json');
        $this->assertNull(catalogue_cache::get());

        catalogue_cache::store(['fingerprint' => 'abc', 'producers' => []]);
        $this->assertSame(['fingerprint' => 'abc', 'producers' => []], catalogue_cache::get());
        catalogue_cache::purge();
        $this->assertNull(catalogue_cache::get());
    }

    public function test_normalize_structure_key_strips_the_structure_prefixes(): void {
        $this->assertSame('abc', catalogue_cache::normalize_structure_key('managed_by:structure:abc'));
        $this->assertSame('abc', catalogue_cache::normalize_structure_key(' Structure: abc '));
        $this->assertSame('plain', catalogue_cache::normalize_structure_key(' plain '));
        $this->assertSame('person:abc', catalogue_cache::normalize_structure_key('person:abc'));
    }

    public function test_normalize_person_key_strips_the_person_prefixes(): void {
        $this->assertSame('h1', catalogue_cache::normalize_person_key('authored_by:person:h1'));
        $this->assertSame('h2', catalogue_cache::normalize_person_key('designed_by:person:h2'));
        $this->assertSame('h3', catalogue_cache::normalize_person_key('reviewed_by:person:h3'));
        $this->assertSame('h4', catalogue_cache::normalize_person_key(' person: h4'));
        $this->assertSame('structure:abc', catalogue_cache::normalize_person_key('structure:abc'));
    }

    public function test_max_modification_date_reads_arrays_and_objects(): void {
        $this->assertSame('', catalogue_cache::max_modification_date([]));
        $this->assertSame('2026-05-01T00:00:00Z', catalogue_cache::max_modification_date([
            ['modification_date' => '2026-01-01T00:00:00Z'],
            (object) ['modification_date' => '2026-05-01T00:00:00Z'],
            ['name' => 'undated'],
            'junk',
        ]));
    }

    public function test_remember_search_drops_the_snapshot_of_another_tenant(): void {
        catalogue_cache::store([
            'fingerprint' => 'another-tenant',
            'producers' => [['structure_id' => 'theirs', 'acronym' => 'THEM']],
        ]);
        $config = (object) get_config('naas');
        catalogue_cache::remember_search(json_encode([
            'items' => [['nugget_id' => 'n1']],
            'aggregations' => [],
            'results_count' => 1,
        ]), $config);

        $stored = catalogue_cache::get();
        $this->assertSame(catalogue_cache::fingerprint($config), $stored['fingerprint']);
        $this->assertSame([], $stored['producers']);
        $this->assertSame('n1', $stored['search']['items'][0]['nugget_id']);
    }

    public function test_remember_ignores_an_undecodable_body(): void {
        $config = (object) get_config('naas');
        catalogue_cache::remember_search('not json', $config);
        $this->assertNull(catalogue_cache::get());
        $this->assertSame('', catalogue_cache::remember_aggregations('not json', $config));
        $this->assertNull(catalogue_cache::get());
    }
}

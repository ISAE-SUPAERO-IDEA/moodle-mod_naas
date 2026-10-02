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
use mod_naas\catalogue_cache;
use mod_naas\catalogue_filters;

/**
 * Tests for the cached catalogue snapshot.
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

    public function test_store_and_find_producer_by_uuid_or_structure_id(): void {
        $config = (object) get_config('naas');
        catalogue_cache::store([
            'fingerprint' => catalogue_cache::fingerprint($config),
            'warmed_at' => time(),
            'producers' => [[
                'structure_id' => 'isae-supaero',
                'uuid' => '06d37c13-6ffe-4c4a-a9e3-ac227652f98c',
                'name' => 'ISAE-SUPAERO',
                'acronym' => 'ISAE',
                'structure_thumbnail_url' => 'https://api.example.test/api/thumbnails/structure/isae-supaero/thumbnail',
                'structure_banner_url' => 'https://api.example.test/api/thumbnails/structure/isae-supaero/banner',
            ]],
            'search' => ['items' => [], 'aggregations' => [], 'results_count' => 0],
        ]);

        $byuuid = catalogue_cache::find_producer('06d37c13-6ffe-4c4a-a9e3-ac227652f98c');
        $this->assertSame('ISAE', $byuuid['acronym']);
        $prefixed = catalogue_cache::find_producer(
            'managed_by:structure:06d37c13-6ffe-4c4a-a9e3-ac227652f98c'
        );
        $this->assertSame('ISAE-SUPAERO', $prefixed['name']);
        $this->assertSame('ISAE', catalogue_cache::find_producer('isae-supaero')['acronym']);
        $this->assertNull(catalogue_cache::find_producer('missing'));
    }

    public function test_structure_json_requires_a_human_name(): void {
        $config = (object) get_config('naas');
        catalogue_cache::store([
            'fingerprint' => catalogue_cache::fingerprint($config),
            'producers' => [[
                'structure_id' => '06d37c13-6ffe-4c4a-a9e3-ac227652f98c',
                'uuid' => '06d37c13-6ffe-4c4a-a9e3-ac227652f98c',
                'name' => '',
                'acronym' => '',
            ]],
        ]);
        $this->assertNull(catalogue_cache::structure_json('06d37c13-6ffe-4c4a-a9e3-ac227652f98c'));

        catalogue_cache::store([
            'fingerprint' => catalogue_cache::fingerprint($config),
            'producers' => [[
                'structure_id' => 'isae-supaero',
                'uuid' => '06d37c13-6ffe-4c4a-a9e3-ac227652f98c',
                'name' => 'ISAE-SUPAERO',
                'acronym' => 'ISAE',
            ]],
        ]);
        $json = catalogue_cache::structure_json('06d37c13-6ffe-4c4a-a9e3-ac227652f98c');
        $this->assertNotNull($json);
        $this->assertStringContainsString('"acronym":"ISAE"', $json);
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

        $exported = catalogue_cache::export_for_widget();
        $this->assertNotNull($exported);
        // The card description ships with the snapshot so the card is complete.
        $this->assertSame('<p>secret</p>', $exported['search']['items'][0]['resume']);
        $this->assertSame('Alpha', $exported['search']['items'][0]['name']);
        $this->assertSame('ISAE', $exported['producers'][0]['acronym']);
        $this->assertSame(4, $exported['producers'][0]['count']);
        $this->assertNotSame('', $exported['producers_digest']);
    }

    public function test_export_truncates_the_resume_for_the_injected_payload(): void {
        $config = (object) get_config('naas');
        catalogue_cache::store(['fingerprint' => catalogue_cache::fingerprint($config)]);
        catalogue_cache::remember_search(json_encode([
            'items' => [[
                'nugget_id' => 'n1',
                'name' => 'Alpha',
                'resume' => str_repeat('x', catalogue_cache::RESUME_MAX_LENGTH + 400),
            ]],
            'aggregations' => [],
            'results_count' => 1,
        ]), $config);

        $exported = catalogue_cache::export_for_widget();
        $this->assertSame(
            catalogue_cache::RESUME_MAX_LENGTH,
            \core_text::strlen($exported['search']['items'][0]['resume'])
        );
        // The stored record keeps the full text for the webservice to return.
        $stored = catalogue_cache::get();
        $this->assertSame(
            catalogue_cache::RESUME_MAX_LENGTH + 400,
            \core_text::strlen($stored['search']['items'][0]['resume'])
        );
    }

    public function test_reconcile_producers_adds_a_producer_seen_only_in_the_aggregations(): void {
        $rows = catalogue_cache::reconcile_producers([], [
            'producers' => ['buckets' => [['key' => 'newcomer', 'docCount' => 7]]],
        ]);
        $this->assertCount(1, $rows);
        $this->assertSame('newcomer', $rows[0]['structure_id']);
        $this->assertSame(7, $rows[0]['count']);
        $this->assertTrue($rows[0]['needs_resolve']);
    }

    public function test_reconcile_producers_refreshes_the_count_of_a_known_producer(): void {
        $existing = [[
            'structure_id' => 'isae',
            'uuid' => 'isae',
            'acronym' => 'ISAE',
            'count' => 1,
        ]];
        $rows = catalogue_cache::reconcile_producers($existing, [
            'producers' => ['buckets' => [['key' => 'managed_by:structure:isae', 'docCount' => 12]]],
        ]);
        $this->assertCount(1, $rows);
        $this->assertSame('ISAE', $rows[0]['acronym']);
        $this->assertSame(12, $rows[0]['count']);
    }

    public function test_reconcile_producers_drops_a_producer_unseen_for_too_long(): void {
        $existing = [
            [
                'structure_id' => 'gone',
                'uuid' => 'gone',
                'seen_at' => time() - catalogue_cache::PRODUCER_MAX_AGE - 10,
            ],
            ['structure_id' => 'kept', 'uuid' => 'kept', 'seen_at' => time()],
        ];
        $rows = catalogue_cache::reconcile_producers($existing, [
            'producers' => ['buckets' => [['key' => 'kept', 'docCount' => 2]]],
        ]);
        $this->assertCount(1, $rows);
        $this->assertSame('kept', $rows[0]['structure_id']);
    }

    public function test_reconcile_producers_keeps_everything_when_there_is_no_signal(): void {
        $existing = [['structure_id' => 'isae', 'acronym' => 'ISAE']];
        $this->assertSame($existing, catalogue_cache::reconcile_producers($existing, []));
        $this->assertSame(
            $existing,
            catalogue_cache::reconcile_producers($existing, ['producers' => ['buckets' => []]])
        );
    }

    public function test_producers_digest_tracks_membership_and_counts(): void {
        $base = [['structure_id' => 'a', 'count' => 1], ['structure_id' => 'b', 'count' => 2]];
        $reordered = [['structure_id' => 'b', 'count' => 2], ['structure_id' => 'a', 'count' => 1]];
        $this->assertSame(
            catalogue_cache::producers_digest($base),
            catalogue_cache::producers_digest($reordered)
        );

        $recounted = [['structure_id' => 'a', 'count' => 1], ['structure_id' => 'b', 'count' => 3]];
        $this->assertNotSame(
            catalogue_cache::producers_digest($base),
            catalogue_cache::producers_digest($recounted)
        );

        $joined = array_merge($base, [['structure_id' => 'c', 'count' => 1]]);
        $this->assertNotSame(
            catalogue_cache::producers_digest($base),
            catalogue_cache::producers_digest($joined)
        );
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
        $this->assertSame(catalogue_cache::producers_digest($stored['producers']), $digest);
        $this->assertSame(11, $stored['stamp']['results_count']);
        $this->assertSame('2026-09-17T08:00:00Z', $stored['stamp']['newest_modification_date']);
        $this->assertGreaterThan(0, $stored['stamp_checked_at']);
    }

    public function test_cached_probe_is_served_inside_the_ttl(): void {
        $config = (object) get_config('naas');
        catalogue_cache::store([
            'fingerprint' => catalogue_cache::fingerprint($config),
            'stamp' => ['results_count' => 4, 'newest_modification_date' => '2026-09-17T08:00:00Z'],
            'stamp_checked_at' => time(),
            'producers' => [['structure_id' => 'isae', 'uuid' => 'isae', 'acronym' => 'ISAE', 'count' => 4]],
            'search' => [
                'items' => [['nugget_id' => 'n1']],
                'aggregations' => ['producers' => ['buckets' => [['key' => 'isae', 'docCount' => 4]]]],
                'results_count' => 4,
            ],
        ]);
        $probe = catalogue_cache::cached_probe($config);
        $this->assertNotNull($probe);
        $this->assertTrue($probe['from_cache']);
        $this->assertSame(4, $probe['stamp']['results_count']);
        $this->assertSame('ISAE', $probe['labels']['producers']['isae']);
    }

    public function test_cached_probe_expires_after_ttl(): void {
        $config = (object) get_config('naas');
        catalogue_cache::store([
            'fingerprint' => catalogue_cache::fingerprint($config),
            'stamp' => ['results_count' => 4, 'newest_modification_date' => '2026-09-17T08:00:00Z'],
            'stamp_checked_at' => time() - catalogue_cache::STAMP_TTL - 1,
            'search' => ['items' => [], 'aggregations' => [], 'results_count' => 4],
        ]);
        $this->assertNull(catalogue_cache::cached_probe($config));
    }

    public function test_export_for_widget_rejects_other_fingerprint(): void {
        catalogue_cache::store([
            'fingerprint' => 'not-this-tenant',
            'producers' => [['acronym' => 'NOPE']],
            'search' => ['items' => [], 'aggregations' => [], 'results_count' => 0],
        ]);
        $this->assertNull(catalogue_cache::export_for_widget());
    }

    public function test_warm_stores_search_and_producers(): void {
        $stub = new class extends \mod_naas\naas_client {
            /**
             * Create the stub.
             */
            public function __construct() {
                $cfg = new \stdClass();
                $cfg->naas_endpoint = 'https://Stub.example';
                $cfg->naas_username = 'u';
                $cfg->naas_password = 'p';
                $cfg->naas_structure_id = 's';
                parent::__construct($cfg);
            }

            /**
             * Return the stubbed HTTP body.
             */
            public function request_raw($protocol, $service, $data = null, $params = null) {
                if (str_contains((string) $service, '/nuggets/search')) {
                    return json_encode([
                        'payload' => [
                            'items' => [[
                                'nugget_id' => 'n1',
                                'name' => 'Cached',
                                'modification_date' => '2026-09-16T08:00:00Z',
                            ]],
                            'aggregations' => [
                                'producers' => ['buckets' => [[
                                    'key' => '06d37c13-6ffe-4c4a-a9e3-ac227652f98c',
                                    'docCount' => 3,
                                ]]],
                            ],
                            'results_count' => 1,
                        ],
                    ]);
                }
                return json_encode([
                    'payload' => [
                        'items' => [[
                            'uuid' => '06d37c13-6ffe-4c4a-a9e3-ac227652f98c',
                            'structure_id' => 'isae-supaero',
                            'name' => 'ISAE-SUPAERO',
                            'acronym' => 'ISAE',
                        ]],
                        'pages' => 1,
                    ],
                ]);
            }
        };

        catalogue_cache::warm($stub, (object) get_config('naas'));
        $exported = catalogue_cache::export_for_widget();
        $this->assertNotNull($exported);
        $this->assertSame('Cached', $exported['search']['items'][0]['name']);
        $this->assertSame('ISAE', $exported['producers'][0]['acronym']);
        $this->assertSame('2026-09-16T08:00:00Z', $exported['max_modification_date']);
        $this->assertNotNull(catalogue_cache::cached_probe((object) get_config('naas')));
    }

    public function test_export_copies_cached_structure_names_onto_producers(): void {
        $config = (object) get_config('naas');
        $key = '06d37c13-6ffe-4c4a-a9e3-ac227652f98c';
        catalogue_cache::store([
            'fingerprint' => catalogue_cache::fingerprint($config),
            'producers' => [[
                'structure_id' => $key,
                'uuid' => $key,
                'name' => '',
                'acronym' => '',
                'needs_resolve' => true,
            ]],
            'search' => ['items' => [], 'aggregations' => [], 'results_count' => 0],
        ]);
        \cache::make('mod_naas', 'vocabulary_entries')->set(
            'structurelabel_' . sha1(strtolower($key)),
            '{"payload":{"acronym":"ISAE","name":"ISAE-SUPAERO"}}'
        );

        $exported = catalogue_cache::export_for_widget();
        $this->assertSame('ISAE', $exported['producers'][0]['acronym']);
        $this->assertSame('ISAE-SUPAERO', $exported['producers'][0]['name']);
        $this->assertArrayNotHasKey('needs_resolve', $exported['producers'][0]);
        $stored = catalogue_cache::get();
        $this->assertSame('ISAE', $stored['producers'][0]['acronym']);
    }
}

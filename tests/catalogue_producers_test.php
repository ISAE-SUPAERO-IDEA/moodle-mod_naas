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
 * Tests for {@see \mod_naas\catalogue_producers}.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

use advanced_testcase;
use mod_naas\catalogue_cache;

/**
 * Tests for the producer table of the catalogue snapshot.
 *
 * @covers \mod_naas\catalogue_producers
 */
final class catalogue_producers_test extends advanced_testcase {
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

        $byuuid = catalogue_producers::find_producer('06d37c13-6ffe-4c4a-a9e3-ac227652f98c');
        $this->assertSame('ISAE', $byuuid['acronym']);
        $prefixed = catalogue_producers::find_producer(
            'managed_by:structure:06d37c13-6ffe-4c4a-a9e3-ac227652f98c'
        );
        $this->assertSame('ISAE-SUPAERO', $prefixed['name']);
        $this->assertSame('ISAE', catalogue_producers::find_producer('isae-supaero')['acronym']);
        $this->assertNull(catalogue_producers::find_producer('missing'));
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
        $this->assertNull(catalogue_producers::structure_json('06d37c13-6ffe-4c4a-a9e3-ac227652f98c'));

        catalogue_cache::store([
            'fingerprint' => catalogue_cache::fingerprint($config),
            'producers' => [[
                'structure_id' => 'isae-supaero',
                'uuid' => '06d37c13-6ffe-4c4a-a9e3-ac227652f98c',
                'name' => 'ISAE-SUPAERO',
                'acronym' => 'ISAE',
            ]],
        ]);
        $json = catalogue_producers::structure_json('06d37c13-6ffe-4c4a-a9e3-ac227652f98c');
        $this->assertNotNull($json);
        $this->assertStringContainsString('"acronym":"ISAE"', $json);
    }

    public function test_reconcile_producers_adds_a_producer_seen_only_in_the_aggregations(): void {
        $rows = catalogue_producers::reconcile_producers([], [
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
        $rows = catalogue_producers::reconcile_producers($existing, [
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
                'seen_at' => time() - catalogue_producers::PRODUCER_MAX_AGE - 10,
            ],
            ['structure_id' => 'kept', 'uuid' => 'kept', 'seen_at' => time()],
        ];
        $rows = catalogue_producers::reconcile_producers($existing, [
            'producers' => ['buckets' => [['key' => 'kept', 'docCount' => 2]]],
        ]);
        $this->assertCount(1, $rows);
        $this->assertSame('kept', $rows[0]['structure_id']);
    }

    public function test_reconcile_producers_keeps_everything_when_there_is_no_signal(): void {
        $existing = [['structure_id' => 'isae', 'acronym' => 'ISAE']];
        $this->assertSame($existing, catalogue_producers::reconcile_producers($existing, []));
        $this->assertSame(
            $existing,
            catalogue_producers::reconcile_producers($existing, ['producers' => ['buckets' => []]])
        );
    }

    public function test_producers_digest_tracks_membership_and_counts(): void {
        $base = [['structure_id' => 'a', 'count' => 1], ['structure_id' => 'b', 'count' => 2]];
        $reordered = [['structure_id' => 'b', 'count' => 2], ['structure_id' => 'a', 'count' => 1]];
        $this->assertSame(
            catalogue_producers::producers_digest($base),
            catalogue_producers::producers_digest($reordered)
        );

        $recounted = [['structure_id' => 'a', 'count' => 1], ['structure_id' => 'b', 'count' => 3]];
        $this->assertNotSame(
            catalogue_producers::producers_digest($base),
            catalogue_producers::producers_digest($recounted)
        );

        $joined = array_merge($base, [['structure_id' => 'c', 'count' => 1]]);
        $this->assertNotSame(
            catalogue_producers::producers_digest($base),
            catalogue_producers::producers_digest($joined)
        );
    }

    public function test_find_producer_ignores_another_tenant_and_empty_keys(): void {
        $rows = [['structure_id' => 'isae', 'uuid' => 'isae', 'acronym' => 'ISAE']];
        catalogue_cache::store(['fingerprint' => 'another-tenant', 'producers' => $rows]);
        $this->assertNull(catalogue_producers::find_producer('isae'));

        catalogue_cache::store([
            'fingerprint' => catalogue_cache::fingerprint((object) get_config('naas')),
            'producers' => $rows,
        ]);
        $this->assertSame('ISAE', catalogue_producers::find_producer('ISAE')['acronym']);
        $this->assertNull(catalogue_producers::find_producer('   '));
    }

    public function test_hydrate_producer_names_ignores_opaque_cached_labels(): void {
        $key = '06d37c13-6ffe-4c4a-a9e3-ac227652f98c';
        \cache::make('mod_naas', 'vocabulary_entries')->set(
            'structurelabel_' . sha1(strtolower($key)),
            json_encode(['payload' => ['acronym' => str_repeat('ab', 16), 'name' => '']])
        );
        $rows = [['structure_id' => $key, 'uuid' => $key, 'name' => '', 'needs_resolve' => true]];
        $this->assertSame($rows, catalogue_producers::hydrate_producer_names($rows));
    }

    public function test_hydrate_producer_names_reads_the_label_under_any_id_field(): void {
        catalogue_cache::store(['fingerprint' => 'x', 'producers' => []]);
        \cache::make('mod_naas', 'vocabulary_entries')->set(
            'structurelabel_' . sha1('isae-supaero'),
            json_encode(['title' => 'ISAE-SUPAERO'])
        );
        $rows = catalogue_producers::hydrate_producer_names([
            ['structure_id' => 'unknown', 'uid' => 'managed_by:structure:ISAE-SUPAERO', 'needs_resolve' => true],
            'not a row',
        ]);
        $this->assertSame('ISAE-SUPAERO', $rows[0]['name']);
        $this->assertArrayNotHasKey('needs_resolve', $rows[0]);
        $this->assertSame('not a row', $rows[1]);
        // The resolved names are written back to the snapshot.
        $this->assertSame('ISAE-SUPAERO', catalogue_cache::get()['producers'][0]['name']);
    }

    public function test_producer_search_aliases_lists_the_other_ids_of_the_same_row(): void {
        catalogue_cache::store([
            'fingerprint' => catalogue_cache::fingerprint((object) get_config('naas')),
            'producers' => [
                ['structure_id' => 'isae-supaero', 'uuid' => 'uuid-1'],
                ['structure_id' => 'other', 'uuid' => 'uuid-2'],
            ],
        ]);
        $options = ['producers' => ['managed_by:structure:uuid-1'], 'page_size' => 9];
        $this->assertSame([
            ['producers' => ['isae-supaero'], 'page_size' => 9],
            ['producers' => ['uuid-1'], 'page_size' => 9],
        ], catalogue_producers::producer_search_aliases($options));

        $this->assertSame([], catalogue_producers::producer_search_aliases(['producers' => ['uuid-1', 'uuid-2']]));
        $this->assertSame([], catalogue_producers::producer_search_aliases(['producers' => ['nobody']]));
        $this->assertSame([], catalogue_producers::producer_search_aliases(['fulltext' => 'math']));
    }

    public function test_reconcile_producers_derives_media_urls_for_a_placeholder(): void {
        $rows = catalogue_producers::reconcile_producers([], [
            'producers' => ['buckets' => [['key' => 'managed_by:structure:a b', 'doc_count' => 3]]],
        ]);
        $this->assertSame('a b', $rows[0]['structure_id']);
        $this->assertSame(3, $rows[0]['count']);
        $this->assertSame(
            'https://api.example.test/api/thumbnails/structure/a%20b/thumbnail',
            $rows[0]['structure_thumbnail_url']
        );
        $this->assertSame(
            'https://api.example.test/api/thumbnails/structure/a%20b/banner',
            $rows[0]['structure_banner_url']
        );
    }
}

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
 * Tests for {@see \mod_naas\catalogue_export}.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

use advanced_testcase;
use mod_naas\catalogue_cache;
use mod_naas\catalogue_producers;

/**
 * Tests for the widget snapshot and the landing probe payload.
 *
 * @covers \mod_naas\catalogue_export
 */
final class catalogue_export_test extends advanced_testcase {
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

    public function test_export_truncates_the_resume_for_the_injected_payload(): void {
        $config = (object) get_config('naas');
        catalogue_cache::store(['fingerprint' => catalogue_cache::fingerprint($config)]);
        catalogue_cache::remember_search(json_encode([
            'items' => [[
                'nugget_id' => 'n1',
                'name' => 'Alpha',
                'resume' => str_repeat('x', catalogue_export::RESUME_MAX_LENGTH + 400),
            ]],
            'aggregations' => [],
            'results_count' => 1,
        ]), $config);

        $exported = catalogue_export::export_for_widget();
        $this->assertSame(
            catalogue_export::RESUME_MAX_LENGTH,
            \core_text::strlen($exported['search']['items'][0]['resume'])
        );
        // The stored record keeps the full text for the webservice to return.
        $stored = catalogue_cache::get();
        $this->assertSame(
            catalogue_export::RESUME_MAX_LENGTH + 400,
            \core_text::strlen($stored['search']['items'][0]['resume'])
        );
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
        $probe = catalogue_export::cached_probe($config);
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
            'stamp_checked_at' => time() - catalogue_export::STAMP_TTL - 1,
            'search' => ['items' => [], 'aggregations' => [], 'results_count' => 4],
        ]);
        $this->assertNull(catalogue_export::cached_probe($config));
    }

    public function test_export_for_widget_rejects_other_fingerprint(): void {
        catalogue_cache::store([
            'fingerprint' => 'not-this-tenant',
            'producers' => [['acronym' => 'NOPE']],
            'search' => ['items' => [], 'aggregations' => [], 'results_count' => 0],
        ]);
        $this->assertNull(catalogue_export::export_for_widget());
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

        $exported = catalogue_export::export_for_widget();
        $this->assertSame('ISAE', $exported['producers'][0]['acronym']);
        $this->assertSame('ISAE-SUPAERO', $exported['producers'][0]['name']);
        $this->assertArrayNotHasKey('needs_resolve', $exported['producers'][0]);
        $stored = catalogue_cache::get();
        $this->assertSame('ISAE', $stored['producers'][0]['acronym']);
    }

    public function test_cached_probe_needs_a_complete_stamp(): void {
        $config = (object) get_config('naas');
        catalogue_cache::store([
            'fingerprint' => catalogue_cache::fingerprint($config),
            'stamp' => ['results_count' => 4, 'newest_modification_date' => ''],
            'stamp_checked_at' => time(),
            'search' => ['items' => [], 'aggregations' => [], 'results_count' => 4],
        ]);
        $this->assertNull(catalogue_export::cached_probe($config));
    }

    public function test_probe_payload_tolerates_a_partial_snapshot(): void {
        $probe = catalogue_export::probe_payload(['search' => ['aggregations' => 'broken']]);
        $this->assertNull($probe['stamp']);
        $this->assertSame([], $probe['producers']);
        $this->assertSame([], $probe['aggregations']);
        $this->assertSame(0, $probe['results_count']);
        $this->assertFalse($probe['from_cache']);
        $this->assertSame(catalogue_producers::producers_digest([]), $probe['producers_digest']);
    }

    public function test_export_for_widget_slims_the_cards(): void {
        catalogue_cache::store([
            'fingerprint' => catalogue_cache::fingerprint((object) get_config('naas')),
            'search' => [
                'items' => [
                    [
                        'nugget_id' => 'n1',
                        'name' => 'Alpha',
                        'managing_structures' => ['isae'],
                        'body' => '<p>heavy</p>',
                    ],
                    'not a row',
                ],
                'aggregations' => 'broken',
                'results_count' => '3',
            ],
        ]);
        $search = catalogue_export::export_for_widget()['search'];
        $this->assertCount(1, $search['items']);
        $this->assertArrayNotHasKey('body', $search['items'][0]);
        $this->assertSame(['isae'], $search['items'][0]['producers']);
        $this->assertSame('', $search['items'][0]['resume']);
        $this->assertSame([], $search['aggregations']);
        $this->assertSame(3, $search['results_count']);
        $this->assertArrayHasKey('vocabulary', $search);
    }
}

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
 * Tests for {@see \mod_naas\nugget_cache}.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

use advanced_testcase;
use mod_naas\nugget_cache;

/**
 * Tests for the Nugget document cache.
 *
 * @covers \mod_naas\nugget_cache
 */
final class nugget_cache_test extends advanced_testcase {
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
        nugget_cache::purge();
    }

    /**
     * Plugin configuration for this test.
     *
     * @return object
     */
    private function config(): object {
        return (object) get_config('naas');
    }

    /**
     * A NaaS default-version document.
     *
     * @param string $modified
     * @param string $version
     * @return string
     */
    private function document(string $modified = '2026-01-01T00:00:00Z', string $version = 'v1'): string {
        return json_encode([
            'nugget_id' => 'n1',
            'name' => 'Alpha',
            'resume' => 'Summary',
            'authors' => ['a1'],
            'domains' => [],
            'version_id' => $version,
            'modification_date' => $modified,
        ]);
    }

    public function test_store_then_get_round_trip(): void {
        $entry = nugget_cache::store('n1', $this->document(), $this->config());
        $this->assertNotNull($entry);
        $this->assertSame('v1', $entry['version_id']);

        $read = nugget_cache::get('n1', $this->config());
        $this->assertNotNull($read);
        $this->assertSame('Alpha', $read['document']['name']);
    }

    public function test_get_misses_for_an_unknown_nugget(): void {
        $this->assertNull(nugget_cache::get('nope', $this->config()));
    }

    public function test_get_ignores_an_entry_from_another_connection(): void {
        nugget_cache::store('n1', $this->document(), $this->config());
        set_config('naas_endpoint', 'https://Other.example/api', 'naas');
        $this->assertNull(nugget_cache::get('n1', $this->config()));
    }

    public function test_store_unwraps_a_payload_envelope(): void {
        $wrapped = json_encode(['payload' => json_decode($this->document(), true)]);
        $entry = nugget_cache::store('n1', $wrapped, $this->config());
        $this->assertNotNull($entry);
        $this->assertSame('Alpha', $entry['document']['name']);
    }

    public function test_store_rejects_a_body_that_is_not_a_nugget(): void {
        $this->assertNull(nugget_cache::store('n1', 'not json', $this->config()));
        $this->assertNull(nugget_cache::store('n1', json_encode(['error' => 'nope']), $this->config()));
    }

    public function test_response_carries_the_freshness_digest_and_vocabulary(): void {
        \cache::make('mod_naas', \mod_naas\vocabulary_lookup::CACHE_AREA)->set(
            'person_a1',
            json_encode(['firstname' => 'Ada', 'lastname' => 'Lovelace'])
        );
        $entry = nugget_cache::store('n1', $this->document(), $this->config());

        $cached = json_decode(nugget_cache::response($entry, true), true);
        $this->assertTrue($cached['_cache']['hit']);
        $this->assertSame('v1', $cached['_cache']['digest']['version_id']);
        $this->assertSame('2026-01-01T00:00:00Z', $cached['_cache']['digest']['modification_date']);
        $this->assertSame('Ada', $cached['vocabulary']['persons']['a1']['firstname']);
        $this->assertSame('Alpha', $cached['name']);

        $live = json_decode(nugget_cache::response($entry, false), true);
        $this->assertFalse($live['_cache']['hit']);
    }

    public function test_digest_moves_when_the_document_is_edited(): void {
        $before = nugget_cache::store('n1', $this->document(), $this->config());
        $after = nugget_cache::store('n1', $this->document('2026-07-07T00:00:00Z', 'v2'), $this->config());
        $this->assertNotSame($before['modification_date'], $after['modification_date']);
        $this->assertNotSame($before['version_id'], $after['version_id']);
    }

    public function test_purge_forgets_one_nugget_or_all_of_them(): void {
        nugget_cache::store('n1', $this->document(), $this->config());
        nugget_cache::purge('n1', $this->config());
        $this->assertNull(nugget_cache::get('n1', $this->config()));

        nugget_cache::store('n1', $this->document(), $this->config());
        nugget_cache::purge();
        $this->assertNull(nugget_cache::get('n1', $this->config()));
    }
}

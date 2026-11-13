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
 * Tests for the facet labels built from the vocabulary cache.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\vocabulary_lookup::class)]
/**
 * Tests for vocabulary_lookup::labels_for_aggregations() and unusable cache entries.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\vocabulary_lookup
 */
final class vocabulary_lookup_labels_test extends \advanced_testcase {
    /**
     * Isolate MUC.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        \cache::make('mod_naas', vocabulary_lookup::CACHE_AREA)->purge();
    }

    /**
     * Store a raw value in the vocabulary cache.
     *
     * @param string $prefix 'person_' or 'domain_'.
     * @param string $key
     * @param string $raw
     */
    private function seed(string $prefix, string $key, string $raw): void {
        \cache::make('mod_naas', vocabulary_lookup::CACHE_AREA)->set(vocabulary_lookup::cache_key($prefix, $key), $raw);
    }

    /**
     * A person known only by a full name is listed under that name.
     */
    public function test_collect_uses_a_full_name_as_last_name(): void {
        $this->seed('person_', 'p1', '{"payload":{"fullname":"Ada Lovelace","mail":"ada@example.test"}}');

        $table = vocabulary_lookup::collect([['authors' => ['p1']]]);

        $this->assertSame([
            'email' => 'ada@example.test',
            'firstname' => '',
            'lastname' => 'Ada Lovelace',
            'bio' => '',
        ], $table['persons']['p1']);
    }

    /**
     * Cache values that are not a JSON record are skipped.
     */
    public function test_collect_skips_entries_that_are_not_records(): void {
        $this->seed('domain_', 'd1', '"just text"');
        $this->seed('domain_', 'd2', '{"label":"Physics"}');

        $table = vocabulary_lookup::collect([['domains' => ['d1', 'd2']]]);

        $this->assertSame(['d2' => ['id' => 'd2', 'label' => 'Physics']], $table['domains']);
    }

    /**
     * Facet buckets get producer, domain and author labels; malformed buckets are ignored.
     */
    public function test_labels_for_aggregations(): void {
        $this->seed('person_', 'p1', '{"firstname":"Ada","lastname":"Lovelace"}');
        $this->seed('domain_', 'd1', '{"label":"Mathematics"}');
        $aggregations = [
            'authors' => ['buckets' => [['key' => 'authored_by:person:p1'], ['key' => ' '], 'junk', ['doc_count' => 1]]],
            'related_domains' => ['buckets' => [['key' => 'd1']]],
            'producers' => ['buckets' => [
                ['key' => 'managed_by:structure:isae'],
                ['key' => 'lab'],
                ['key' => 'ghost'],
            ]],
        ];
        $producers = [
            'not a row',
            ['structure_id' => '', 'uuid' => 'ISAE', 'acronym' => ' ', 'name' => 'ISAE-SUPAERO'],
            ['structure_id' => 'lab', 'acronym' => 'LAB'],
            ['structure_id' => 'ghost', 'acronym' => '', 'name' => ''],
        ];

        $labels = vocabulary_lookup::labels_for_aggregations($aggregations, $producers);

        $this->assertSame([
            'producers' => ['managed_by:structure:isae' => 'ISAE-SUPAERO', 'lab' => 'LAB'],
            'related_domains' => ['d1' => 'Mathematics'],
            'authors' => ['authored_by:person:p1' => 'ADA LOVELACE'],
        ], $labels);
    }
}

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
 * Tests for {@see \mod_naas\vocabulary_lookup}.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\tests;

defined('MOODLE_INTERNAL') || die();

use advanced_testcase;
use mod_naas\vocabulary_lookup;

/**
 * @covers \mod_naas\vocabulary_lookup
 */
final class vocabulary_lookup_test extends advanced_testcase {

    /**
     * Isolate MUC.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        \cache::make('mod_naas', vocabulary_lookup::CACHE_AREA)->purge();
    }

    /**
     * Seed the MUC the way the proxy endpoints do.
     *
     * @param string $key
     * @param array $value
     */
    private function seed(string $key, array $value): void {
        \cache::make('mod_naas', vocabulary_lookup::CACHE_AREA)->set($key, json_encode($value));
    }

    public function test_collect_resolves_persons_and_domains(): void {
        $this->seed('person_a1', [
            'email' => 'ada@example.com',
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
            'bio' => 'Mathematician',
        ]);
        $this->seed('domain_d1', ['id' => 'd1', 'label' => 'Mathematics']);

        $table = vocabulary_lookup::collect([
            ['authors' => ['a1'], 'domains' => ['d1']],
        ]);

        $this->assertSame('Ada', $table['persons']['a1']['firstname']);
        $this->assertSame('Mathematician', $table['persons']['a1']['bio']);
        $this->assertSame('Mathematics', $table['domains']['d1']['label']);
    }

    public function test_collect_peels_the_payload_envelope(): void {
        $this->seed('domain_d1', ['payload' => ['id' => 'd1', 'label' => 'Physics']]);
        $table = vocabulary_lookup::collect([['domains' => ['d1']]]);
        $this->assertSame('Physics', $table['domains']['d1']['label']);
    }

    public function test_collect_omits_cold_entries_without_calling_out(): void {
        // Nothing seeded: a cold MUC must yield an empty table, never an HTTP call.
        $table = vocabulary_lookup::collect([
            ['authors' => ['missing'], 'domains' => ['gone']],
        ]);
        $this->assertSame([], $table['persons']);
        $this->assertSame([], $table['domains']);
    }

    public function test_collect_deduplicates_keys_shared_across_cards(): void {
        $this->seed('person_a1', ['firstname' => 'Ada', 'lastname' => 'Lovelace']);
        $table = vocabulary_lookup::collect([
            ['authors' => ['a1']],
            ['authors' => ['a1']],
            ['authors' => ['a1']],
        ]);
        $this->assertCount(1, $table['persons']);
    }

    public function test_collect_accepts_alternative_field_spellings(): void {
        $this->seed('person_a1', ['first_name' => 'Grace', 'last_name' => 'Hopper']);
        $this->seed('domain_d1', ['name' => 'Computing']);
        $table = vocabulary_lookup::collect([['authors' => ['a1'], 'domains' => ['d1']]]);
        $this->assertSame('Grace', $table['persons']['a1']['firstname']);
        $this->assertSame('Computing', $table['domains']['d1']['label']);
    }

    public function test_collect_skips_a_record_with_no_usable_name(): void {
        $this->seed('person_a1', ['email' => 'nobody@example.com']);
        $this->seed('domain_d1', ['id' => 'd1']);
        $table = vocabulary_lookup::collect([['authors' => ['a1'], 'domains' => ['d1']]]);
        $this->assertSame([], $table['persons']);
        $this->assertSame([], $table['domains']);
    }

    public function test_collect_caps_long_biographies(): void {
        $this->seed('person_a1', [
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
            'bio' => str_repeat('x', vocabulary_lookup::BIO_MAX_LENGTH + 500),
        ]);
        $table = vocabulary_lookup::collect([['authors' => ['a1']]]);
        $this->assertSame(
            vocabulary_lookup::BIO_MAX_LENGTH,
            \core_text::strlen($table['persons']['a1']['bio'])
        );
    }

    public function test_collect_handles_items_without_key_arrays(): void {
        $table = vocabulary_lookup::collect([['nugget_id' => 'n1'], 'not an array']);
        $this->assertSame([], $table['persons']);
        $this->assertSame([], $table['domains']);
    }

    public function test_labels_for_aggregations_resolve_warm_keys_and_omit_cold_ones(): void {
        $this->seed('person_a1', ['firstname' => 'Ada', 'lastname' => 'Lovelace']);
        $this->seed('domain_d1', ['id' => 'd1', 'label' => 'Mathematics']);

        $labels = vocabulary_lookup::labels_for_aggregations(
            [
                'authors' => ['buckets' => [['key' => 'a1'], ['key' => 'missing']]],
                'related_domains' => ['buckets' => [['key' => 'd1']]],
                'producers' => ['buckets' => [['key' => 'isae'], ['key' => 'unknown']]],
            ],
            [['structure_id' => 'isae', 'acronym' => 'ISAE', 'name' => 'ISAE-SUPAERO']]
        );

        $this->assertSame('ADA LOVELACE', $labels['authors']['a1']);
        $this->assertArrayNotHasKey('missing', $labels['authors']);
        $this->assertSame('Mathematics', $labels['related_domains']['d1']);
        $this->assertSame('ISAE', $labels['producers']['isae']);
        $this->assertArrayNotHasKey('unknown', $labels['producers']);
    }
}

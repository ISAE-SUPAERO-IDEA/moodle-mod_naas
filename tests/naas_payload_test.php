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
 * Tests for {@see \mod_naas\naas_payload}.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\tests;

defined('MOODLE_INTERNAL') || die();

use advanced_testcase;
use mod_naas\naas_payload;

/**
 * @covers \mod_naas\naas_payload
 */
final class naas_payload_test extends advanced_testcase {

    public function test_unwrap_payload_object(): void {
        $this->assertSame(
            ['firstname' => 'Ada'],
            naas_payload::unwrap(['payload' => ['firstname' => 'Ada']])
        );
    }

    public function test_unwrap_json_string_inside_payload(): void {
        $this->assertSame(
            ['acronym' => 'ISAE'],
            naas_payload::unwrap(['payload' => '{"acronym":"ISAE"}'])
        );
    }

    public function test_unwrap_nested_payload_wrappers(): void {
        $this->assertSame(
            ['acronym' => 'ISAE'],
            naas_payload::unwrap(['payload' => ['payload' => ['acronym' => 'ISAE']]])
        );
    }

    public function test_unwrap_object_envelope(): void {
        $wrapped = (object) ['payload' => (object) ['acronym' => 'ISAE']];
        $unwrapped = naas_payload::unwrap($wrapped);
        $this->assertIsObject($unwrapped);
        $this->assertSame('ISAE', $unwrapped->acronym);
    }

    public function test_decode_search_reads_items_and_count(): void {
        $search = naas_payload::decode_search(json_encode([
            'payload' => [
                'items' => [['nugget_id' => 'n1']],
                'aggregations' => ['producers' => ['buckets' => []]],
                'results_count' => 4,
            ],
        ]));
        $this->assertSame('n1', $search['items'][0]['nugget_id']);
        $this->assertSame(4, $search['results_count']);
        $this->assertSame(['producers' => ['buckets' => []]], $search['aggregations']);
    }

    public function test_decode_search_rejects_non_object_bodies(): void {
        $this->assertNull(naas_payload::decode_search('"just a string"'));
        $this->assertNull(naas_payload::decode_search('not json'));
    }
}

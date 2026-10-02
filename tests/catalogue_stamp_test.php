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
 * Tests for {@see \mod_naas\catalogue_stamp}.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

use advanced_testcase;
use mod_naas\catalogue_stamp;

/**
 * Tests for catalogue freshness stamps.
 *
 * @covers \mod_naas\catalogue_stamp
 */
final class catalogue_stamp_test extends advanced_testcase {
    public function test_from_search_uses_the_first_item_as_newest(): void {
        $stamp = catalogue_stamp::from_search([
            'results_count' => 12,
            'items' => [
                ['nugget_id' => 'n-new', 'modification_date' => '2026-09-17T10:00:00Z'],
                ['nugget_id' => 'n-old', 'modification_date' => '2020-01-01T00:00:00Z'],
            ],
        ]);
        $this->assertSame(12, $stamp['results_count']);
        $this->assertSame('2026-09-17T10:00:00Z', $stamp['newest_modification_date']);
    }

    public function test_equals_detects_add_delete_and_edit(): void {
        $base = ['results_count' => 10, 'newest_modification_date' => '2026-01-01T00:00:00Z'];
        $this->assertTrue(catalogue_stamp::equals($base, $base));
        $this->assertFalse(catalogue_stamp::equals(
            $base,
            ['results_count' => 11, 'newest_modification_date' => '2026-01-01T00:00:00Z']
        ));
        $this->assertFalse(catalogue_stamp::equals(
            $base,
            ['results_count' => 10, 'newest_modification_date' => '2026-02-02T00:00:00Z']
        ));
    }

    public function test_incomplete_stamp_never_equals(): void {
        $complete = ['results_count' => 1, 'newest_modification_date' => '2026-01-01T00:00:00Z'];
        $this->assertFalse(catalogue_stamp::is_complete(['results_count' => 1, 'newest_modification_date' => '']));
        $this->assertFalse(catalogue_stamp::equals($complete, ['results_count' => 1, 'newest_modification_date' => '']));
        $this->assertFalse(catalogue_stamp::equals($complete, null));
    }
}

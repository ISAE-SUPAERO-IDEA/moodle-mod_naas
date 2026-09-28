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
 * Tests for {@see \mod_naas\cache_refresh}.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\tests;

defined('MOODLE_INTERNAL') || die();

use advanced_testcase;
use mod_naas\cache_refresh;
use mod_naas\catalogue_cache;

/**
 * @covers \mod_naas\cache_refresh
 */
final class cache_refresh_test extends advanced_testcase {

    /**
     * A click with no saved API URL must not wipe an existing catalogue.
     */
    public function test_full_keeps_cache_when_endpoint_missing(): void {
        global $CFG;

        $this->resetAfterTest(true);
        unset_config('naas_endpoint', 'naas');
        unset($CFG->naas_endpoint);

        catalogue_cache::store([
            'fingerprint' => 'kept',
            'producers' => [],
        ]);

        try {
            cache_refresh::full();
            $this->fail('Expected cache_refresh_failed');
        } catch (\moodle_exception $e) {
            $this->assertSame('cache_refresh_failed', $e->errorcode);
        }

        $snapshot = catalogue_cache::get();
        $this->assertIsArray($snapshot);
        $this->assertSame('kept', $snapshot['fingerprint']);
    }
}

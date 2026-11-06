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
 * Tests for {@see \mod_naas\task\refresh_catalogue}.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

defined('MOODLE_INTERNAL') || die();

use advanced_testcase;
use mod_naas\catalogue_filters;
use mod_naas\search_cache;
use mod_naas\task\refresh_catalogue;

require_once(__DIR__ . '/fixtures/testable_naas_client.php');

/**
 * Tests for the scheduled catalogue refresh task.
 *
 * @covers \mod_naas\task\refresh_catalogue
 */
final class refresh_catalogue_test extends advanced_testcase {
    /**
     * Isolate plugin config.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        unset_config('naas_refresh_limit', 'naas');
    }

    public function test_effective_limit_defaults_to_the_whole_cache(): void {
        $this->assertSame(search_cache::MAX_ENTRIES, refresh_catalogue::DEFAULT_LIMIT);
        $this->assertSame(search_cache::MAX_ENTRIES, refresh_catalogue::effective_limit());
    }

    public function test_effective_limit_treats_zero_as_the_whole_cache(): void {
        set_config('naas_refresh_limit', 0, 'naas');
        $this->assertSame(search_cache::MAX_ENTRIES, refresh_catalogue::effective_limit());
    }

    public function test_effective_limit_honours_an_explicit_cap(): void {
        set_config('naas_refresh_limit', 12, 'naas');
        $this->assertSame(12, refresh_catalogue::effective_limit());
    }

    /**
     * Missing endpoint is a skip, not a failed refresh.
     */
    public function test_refresh_skips_when_endpoint_missing(): void {
        global $CFG;

        unset_config('naas_endpoint', 'naas');
        unset($CFG->naas_endpoint);

        $task = new refresh_catalogue();
        $this->assertNull($task->refresh(false));
    }

    /**
     * The nightly refresh must drop the nuggets the licence settings hide, like the live search.
     */
    public function test_fetch_search_applies_the_licence_filters_before_caching(): void {
        $config = (object) [
            'naas_structure_id' => 'struct-ours',
            'naas_license_filter' => catalogue_filters::LICENSE_COMMERCIAL,
        ];
        $client = new testable_naas_client((object) [
            'naas_endpoint' => 'https://Stub.example',
            'naas_username' => 'u',
            'naas_password' => 'p',
            'naas_structure_id' => 'struct-ours',
        ]);
        $client->stubjson = json_encode([
            'payload' => [
                'items' => [
                    [
                        'nugget_id' => 'keep',
                        'access_licences' => [['structure_id' => 'struct-ours', 'rights' => ['co', 'uu']]],
                    ],
                    [
                        'nugget_id' => 'drop',
                        'access_licences' => [['structure_id' => 'struct-ours', 'rights' => ['nc', 'uu']]],
                    ],
                ],
                'results_count' => 2,
            ],
        ]);
        $options = ['is_default_version' => true, 'page_size' => 10];

        $raw = refresh_catalogue::fetch_search($client, $options, $config);
        $entry = search_cache::store(search_cache::canonical_key($options, $config), $options, $raw, $config);

        $this->assertNotNull($entry);
        $this->assertSame(['keep'], array_column($entry['search']['items'], 'nugget_id'));
        $this->assertSame(1, $entry['results_count']);
    }
}

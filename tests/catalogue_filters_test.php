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
 * Tests for {@see \mod_naas\catalogue_filters}.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\tests;

defined('MOODLE_INTERNAL') || die();

use advanced_testcase;
use mod_naas\catalogue_filters;

/**
 * @covers \mod_naas\catalogue_filters
 */
final class catalogue_filters_test extends advanced_testcase {

    /**
     * Isolate plugin config.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        unset_config('naas_filter', 'naas');
        unset_config('naas_license_filter', 'naas');
        unset_config('naas_access_filter', 'naas');
    }

    public function test_modes_default_to_all(): void {
        $this->assertSame(catalogue_filters::LICENSE_ALL, catalogue_filters::commercial_mode());
        $this->assertSame(catalogue_filters::ACCESS_ALL, catalogue_filters::access_mode());
        $this->assertSame([], catalogue_filters::required_rights());
        $this->assertSame('', catalogue_filters::license_nql());
    }

    public function test_commercial_nql_uses_access_licence_rights(): void {
        $config = (object) ['naas_license_filter' => catalogue_filters::LICENSE_COMMERCIAL];
        $this->assertSame(['co'], catalogue_filters::required_rights($config));
        $this->assertSame(
            "nugget:access_licences/*/rights = 'co'",
            catalogue_filters::license_nql($config)
        );
    }

    public function test_noncommercial_and_restricted_nql_are_anded(): void {
        $config = (object) [
            'naas_license_filter' => catalogue_filters::LICENSE_NONCOMMERCIAL,
            'naas_access_filter' => catalogue_filters::ACCESS_RESTRICTED,
        ];
        $this->assertSame(['nc', 'ru'], catalogue_filters::required_rights($config));
        $this->assertSame(
            "(nugget:access_licences/*/rights = 'nc') AND (nugget:access_licences/*/rights = 'ru')",
            catalogue_filters::license_nql($config)
        );
    }

    public function test_apply_leaves_nql_unencoded_for_http_build_query(): void {
        $config = (object) [
            'naas_filter' => 'nugget_search:type = \'video\'',
            'naas_license_filter' => catalogue_filters::LICENSE_ALL,
        ];
        $applied = catalogue_filters::apply(['page_size' => 6, 'license' => ['1']], $config);
        $this->assertSame("nugget_search:type = 'video'", $applied['nql']);
        $this->assertArrayNotHasKey('license', $applied);
    }

    public function test_nugget_matches_the_institute_access_licence_not_other_structures(): void {
        $nugget = [
            'access_licences' => [
                [
                    'structure_id' => 'struct-ours',
                    'rights' => ['by', 'nc', 'uu'],
                ],
                [
                    'structure_id' => 'struct-other',
                    'rights' => ['by', 'co', 'ru'],
                ],
            ],
        ];
        $ours = (object) [
            'naas_structure_id' => 'struct-ours',
            'naas_license_filter' => catalogue_filters::LICENSE_NONCOMMERCIAL,
            'naas_access_filter' => catalogue_filters::ACCESS_UNRESTRICTED,
        ];
        $this->assertTrue(catalogue_filters::nugget_matches($nugget, $ours));

        $commercial = clone $ours;
        $commercial->naas_license_filter = catalogue_filters::LICENSE_COMMERCIAL;
        $this->assertFalse(catalogue_filters::nugget_matches($nugget, $commercial));

        $restricted = clone $ours;
        $restricted->naas_access_filter = catalogue_filters::ACCESS_RESTRICTED;
        $this->assertFalse(catalogue_filters::nugget_matches($nugget, $restricted));
    }

    public function test_constrain_search_json_drops_disallowed_items(): void {
        $config = (object) [
            'naas_structure_id' => 'struct-ours',
            'naas_license_filter' => catalogue_filters::LICENSE_COMMERCIAL,
        ];
        $json = json_encode([
            'payload' => [
                'items' => [
                    [
                        'name' => 'keep',
                        'access_licences' => [[
                            'structure_id' => 'struct-ours',
                            'rights' => ['co', 'uu'],
                        ]],
                    ],
                    [
                        'name' => 'drop',
                        'access_licences' => [[
                            'structure_id' => 'struct-ours',
                            'rights' => ['nc', 'uu'],
                        ]],
                    ],
                ],
                'results_count' => 2,
            ],
        ]);
        $out = json_decode(catalogue_filters::constrain_search_json($json, $config), true);
        $this->assertCount(1, $out['payload']['items']);
        $this->assertSame('keep', $out['payload']['items'][0]['name']);
        $this->assertSame(1, $out['payload']['results_count']);
    }

    public function test_widget_config_exposes_both_modes(): void {
        $config = catalogue_filters::widget_license_config((object) [
            'naas_license_filter' => catalogue_filters::LICENSE_COMMERCIAL,
            'naas_access_filter' => catalogue_filters::ACCESS_UNRESTRICTED,
        ]);
        $this->assertSame('commercial', $config['commercial']);
        $this->assertSame('unrestricted', $config['access']);
    }
}

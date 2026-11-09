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
 * Tests for the access-licence rights filter of the catalogue.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\catalogue_filters::class)]
/**
 * Tests for catalogue_filters: NQL composition, search post-filter and per-institute licence rights.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\catalogue_filters
 */
final class catalogue_filters_rights_test extends \advanced_testcase {
    /**
     * Configuration requiring the commercial right for the institute 'isae'.
     *
     * @return object
     */
    private static function commercial(): object {
        return (object) [
            'naas_structure_id' => 'isae',
            'naas_license_filter' => catalogue_filters::LICENSE_COMMERCIAL,
        ];
    }

    /**
     * A Nugget whose licences are $licences.
     *
     * @param mixed $licences
     * @return array
     */
    private static function nugget($licences): array {
        return ['nugget_id' => 'n', 'access_licences' => $licences];
    }

    /**
     * The site NQL and each required right become AND-ed clauses; a single clause has no brackets.
     */
    public function test_apply_combines_the_site_nql_and_the_rights(): void {
        $both = catalogue_filters::apply(['license' => ['x'], 'page' => 1], (object) [
            'naas_filter' => ' type:video ',
            'naas_license_filter' => catalogue_filters::LICENSE_NONCOMMERCIAL,
            'naas_access_filter' => catalogue_filters::ACCESS_RESTRICTED,
        ]);
        $single = catalogue_filters::apply([], (object) ['naas_access_filter' => catalogue_filters::ACCESS_UNRESTRICTED]);
        $none = catalogue_filters::apply(['license' => ['x']], (object) []);

        $this->assertSame([
            'page' => 1,
            'nql' => "(type:video) AND (nugget:access_licences/*/rights = 'nc') AND (nugget:access_licences/*/rights = 'ru')",
        ], $both);
        $this->assertSame(['nql' => "nugget:access_licences/*/rights = 'uu'"], $single);
        $this->assertSame([], $none);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('untouched_body_provider')]
    /**
     * Bodies the post-filter cannot or need not change are returned as they are.
     *
     * @dataProvider untouched_body_provider
     * @param string $json
     * @param bool $filtered Whether a rights filter is configured.
     */
    public function test_constrain_search_json_leaves_some_bodies_untouched(string $json, bool $filtered): void {
        $config = $filtered ? self::commercial() : (object) [];

        $this->assertSame($json, catalogue_filters::constrain_search_json($json, $config));
    }

    /**
     * Search bodies that are left as they are.
     *
     * @return array
     */
    public static function untouched_body_provider(): array {
        return [
            'no filter configured' => ['{"items":[{"nugget_id":"n"}]}', false],
            'not json' => ['upstream error', true],
            'no item list' => ['{"payload":{"results_count":3}}', true],
            'items is not a list' => ['{"items":"none"}', true],
        ];
    }

    /**
     * An unwrapped body is filtered and its count lowered by the Nuggets dropped.
     */
    public function test_constrain_search_json_filters_an_unwrapped_body(): void {
        $json = json_encode([
            'items' => [
                self::nugget([['structure_id' => 'isae', 'rights' => ['co', 'uu']]]),
                self::nugget([['structure_id' => 'isae', 'rights' => ['nc']]]),
                'not a nugget',
            ],
            'results_count' => 10,
        ]);

        $result = json_decode(catalogue_filters::constrain_search_json($json, self::commercial()), true);

        $this->assertCount(1, $result['items']);
        $this->assertSame(['co', 'uu'], $result['items'][0]['access_licences'][0]['rights']);
        $this->assertSame(8, $result['results_count']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nugget_provider')]
    /**
     * Whether a Nugget's licence for the institute grants the required rights.
     *
     * @dataProvider nugget_provider
     * @param mixed $licences
     * @param object $config
     * @param bool $expected
     */
    public function test_nugget_matches($licences, object $config, bool $expected): void {
        $this->assertSame($expected, catalogue_filters::nugget_matches(self::nugget($licences), $config));
    }

    /**
     * Licences, configuration and the expected verdict.
     *
     * @return array
     */
    public static function nugget_provider(): array {
        $both = (object) [
            'naas_structure_id' => 'isae',
            'naas_license_filter' => catalogue_filters::LICENSE_COMMERCIAL,
            'naas_access_filter' => catalogue_filters::ACCESS_UNRESTRICTED,
        ];
        return [
            'no filter' => [[], (object) [], true],
            'licences not a list' => ['co', self::commercial(), false],
            'only other institutes' => [[['structure_id' => 'other', 'rights' => ['co']]], self::commercial(), false],
            'malformed licence skipped' => [['junk', ['structure_id' => 'isae', 'rights' => ['co']]], self::commercial(), true],
            'rights not a list' => [[['structure_id' => 'isae', 'rights' => 'co']], self::commercial(), false],
            'blank rights ignored' => [[['structure_id' => 'isae', 'rights' => ['', 5, 'co']]], self::commercial(), true],
            'one of two rights missing' => [[['structure_id' => 'isae', 'rights' => ['co']]], $both, false],
            'no institute: first licence' => [
                [['structure_id' => 'other', 'rights' => ['co']]],
                (object) ['naas_license_filter' => catalogue_filters::LICENSE_COMMERCIAL],
                true,
            ],
        ];
    }

    /**
     * Without a configuration object the modes are read from the plugin settings.
     */
    public function test_modes_fall_back_to_the_plugin_settings(): void {
        $this->resetAfterTest(true);
        set_config('naas_license_filter', catalogue_filters::LICENSE_NONCOMMERCIAL, 'naas');
        set_config('naas_access_filter', 'bogus', 'naas');

        $this->assertSame(['commercial' => 'noncommercial', 'access' => 'all'], catalogue_filters::widget_license_config());
        $this->assertSame(['nc'], catalogue_filters::required_rights());
    }
}

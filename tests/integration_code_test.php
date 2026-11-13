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
 * Tests for {@see \mod_naas\integration_code}.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

defined('MOODLE_INTERNAL') || die();

use advanced_testcase;

require_once(__DIR__ . '/fixtures/testable_naas_client.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\integration_code::class)]
/**
 * Tests for the integration code of a Nugget.
 *
 * @covers \mod_naas\integration_code
 */
final class integration_code_test extends advanced_testcase {
    /**
     * Plugin settings shared by the tests.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('naas_endpoint', 'https://api.example.test/api', 'naas');
        set_config('naas_username', 'user', 'naas');
        set_config('naas_password', 'secret', 'naas');
        set_config('naas_structure_id', 'struct-1', 'naas');
        nugget_cache::purge();
    }

    /**
     * The codes the NaaS author app shows for these titles (docs/NUGGET_INTEGRATION_CODE_DRUPAL.md of 2026-10-22).
     *
     * @return array
     */
    public static function title_provider(): array {
        return [
            ['Introduction to flight mechanics', '878569'],
            ['introduction to flight mechanics', '637587'],
            ['Introduction to flight mechanics ', '005099'],
            ['Hello World', '786068'],
            ["École d'été", '956553'],
            ['Aérodynamique : les bases', '678476'],
            ['Mécanique du vol — partie 1', '316475'],
            ['Mécanique du vol - partie 1', '256180'],
            ['日本語のタイトル', '292531'],
            ['a', '926610'],
        ];
    }

    /**
     * The code is computed from the exact title, as in the author app.
     *
     * @dataProvider title_provider
     * @param string $title
     * @param string $code
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('title_provider')]
    public function test_from_title_matches_the_author_app(string $title, string $code): void {
        $this->assertSame($code, integration_code::from_title($title));
        $this->assertTrue(integration_code::matches($title, $code));
    }

    public function test_matches_refuses_other_codes_and_empty_titles(): void {
        $this->assertFalse(integration_code::matches('Hello World', '786069'));
        $this->assertFalse(integration_code::matches('Hello World', '78606'));
        $this->assertFalse(integration_code::matches('Hello World', '786068 '));
        $this->assertFalse(integration_code::matches('Hello World', 'abcdef'));
        $this->assertFalse(integration_code::matches('', integration_code::from_title('')));
    }

    public function test_is_public_reads_every_form_of_true(): void {
        foreach ([true, 1, '1', 'true'] as $value) {
            $this->assertTrue(integration_code::is_public(['is_public' => $value]));
        }
        $this->assertTrue(integration_code::is_public((object) ['is_public' => true]));
        foreach ([false, 0, '0', 'false', null, ''] as $value) {
            $this->assertFalse(integration_code::is_public(['is_public' => $value]));
        }
        $this->assertFalse(integration_code::is_public([]));
    }

    public function test_is_required_follows_the_setting(): void {
        $this->assertFalse(integration_code::is_required());
        set_config('naas_require_integration_code', 1, 'naas');
        $this->assertTrue(integration_code::is_required());
    }

    public function test_nothing_is_checked_without_the_setting_or_a_new_nugget(): void {
        $this->assertTrue(integration_code::is_valid_selection('', 'n-1', ''));
        set_config('naas_require_integration_code', 1, 'naas');
        $this->assertTrue(integration_code::is_valid_selection('', '', ''));
        $this->assertTrue(integration_code::is_valid_selection('n-1', 'n-1', ''));
    }

    public function test_a_cached_nugget_needs_its_code_unless_public(): void {
        set_config('naas_require_integration_code', 1, 'naas');
        $config = (object) get_config('naas');
        nugget_cache::store('n-1', json_encode(['payload' => ['nugget_id' => 'n-1', 'name' => 'Hello World']]), $config);
        $public = ['nugget_id' => 'n-2', 'name' => 'Open', 'is_public' => true];
        nugget_cache::store('n-2', json_encode(['payload' => $public]), $config);

        $this->assertTrue(integration_code::is_valid_selection('', 'n-1', '786068'));
        $this->assertTrue(integration_code::is_valid_selection('n-0', 'n-1', '786068'));
        $this->assertFalse(integration_code::is_valid_selection('', 'n-1', '000000'));
        $this->assertFalse(integration_code::is_valid_selection('', 'n-1', ''));
        $this->assertTrue(integration_code::is_valid_selection('', 'n-2', ''));
    }

    public function test_an_uncached_nugget_is_asked_from_naas(): void {
        set_config('naas_require_integration_code', 1, 'naas');
        $client = new testable_naas_client((object) get_config('naas'));
        $client->stubjson = json_encode(['payload' => ['nugget_id' => 'n-3', 'name' => 'a']]);

        $this->assertTrue(integration_code::is_valid_selection('', 'n-3', '926610', $client));
        $this->assertSame('/nuggets/n-3/default_version', $client->captured['service']);
        $this->assertFalse(integration_code::is_valid_selection('', 'n-3', '926611', $client));
    }

    public function test_a_nugget_that_cannot_be_loaded_is_refused(): void {
        set_config('naas_require_integration_code', 1, 'naas');
        $client = new testable_naas_client((object) get_config('naas'));
        $client->stuberror = true;
        $this->assertFalse(integration_code::is_valid_selection('', 'n-4', '926610', $client));

        $client->stuberror = false;
        $client->stubjson = json_encode(['error' => ['code' => 404]]);
        $this->assertFalse(integration_code::is_valid_selection('', 'n-4', '926610', $client));
    }
}

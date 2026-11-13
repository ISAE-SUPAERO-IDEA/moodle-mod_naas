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
 * Tests for the producer listings the catalogue warm-up reads.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/routing_naas_client.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\catalogue_warmer::class)]
/**
 * Tests for catalogue_warmer with failing, bare-list and unusable producer listings.
 *
 * NaaS is never reached: every request goes to a routing_naas_client.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\catalogue_warmer
 */
final class catalogue_warmer_listing_test extends \advanced_testcase {
    /** A landing search answer with no Nugget. */
    private const SEARCH = '{"payload":{"items":[],"aggregations":{},"results_count":0}}';

    /**
     * Isolate config and the snapshot.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('naas_endpoint', 'https://api.example.test/api/', 'naas');
        catalogue_cache::purge();
    }

    /**
     * A NaaS client answering searches with $search and structure listings with $listing.
     *
     * @param string $search Body for /nuggets/search.
     * @param string|\Throwable $listing Body (or error) for /structures.
     * @param string $fallback Body for /structures/search.
     * @return routing_naas_client
     */
    private function naas(string $search, $listing, string $fallback = '{}'): routing_naas_client {
        return new routing_naas_client(function (string $protocol, string $service) use ($search, $listing, $fallback): string {
            if (strpos($service, '/nuggets/search') === 0) {
                return $search;
            }
            if ($service === '/structures/search') {
                return $fallback;
            }
            if ($listing instanceof \Throwable) {
                throw $listing;
            }
            return $listing;
        });
    }

    /**
     * A failing /structures listing is logged and /structures/search is read instead.
     */
    public function test_warm_reads_the_search_listing_when_the_listing_fails(): void {
        $naas = $this->naas(
            self::SEARCH,
            routing_naas_client::naas_error('error:naas_api:unknown'),
            '{"payload":{"results":[{"uid":"u-1","properties":{"structure:acronym":"LAB","structure:name":"Lab"}}]}}'
        );

        catalogue_warmer::warm($naas, (object) get_config('naas'));

        $this->assertDebuggingCalled('NAAS catalogue cache structures: error:naas_api:unknown', DEBUG_DEVELOPER);
        $this->assertSame(['/nuggets/search', '/structures', '/structures/search'], array_map(function ($service) {
            return strtok($service, '?');
        }, $naas->services()));
        $this->assertSame([
            'structure_id' => 'u-1',
            'uuid' => 'u-1',
            'name' => 'Lab',
            'acronym' => 'LAB',
            'structure_thumbnail_url' => 'https://api.example.test/api/thumbnails/structure/u-1/thumbnail',
            'structure_banner_url' => 'https://api.example.test/api/thumbnails/structure/u-1/banner',
        ], catalogue_cache::get()['producers'][0]);
    }

    /**
     * A listing that is a bare JSON list is read row by row; rows without an id are dropped.
     */
    public function test_warm_reads_a_bare_list_of_structures(): void {
        $listing = json_encode([
            ['properties' => [
                'structure:structure_id' => 'isae',
                'dc:title' => 'ISAE-SUPAERO',
                'structure:acronym' => 'ISAE',
            ], 'structure_thumbnail_url' => 'https://cdn.example/isae.png'],
            5,
            ['name' => 'No id'],
        ]);

        catalogue_warmer::warm($this->naas('not json', $listing), (object) get_config('naas'));

        $snapshot = catalogue_cache::get();
        $this->assertCount(1, $snapshot['producers']);
        $this->assertSame('isae', $snapshot['producers'][0]['structure_id']);
        $this->assertSame('ISAE-SUPAERO', $snapshot['producers'][0]['name']);
        $this->assertSame('https://cdn.example/isae.png', $snapshot['producers'][0]['structure_thumbnail_url']);
        $this->assertSame([], $snapshot['search']['items'], 'An unreadable search still stores the producers.');
    }

    /**
     * A listing that is neither an object nor a list gives no producer, and nothing is stored.
     */
    public function test_warm_stores_nothing_from_an_unusable_listing(): void {
        $naas = $this->naas('not json', '"maintenance"', '"maintenance"');

        catalogue_warmer::warm($naas, (object) get_config('naas'));

        $this->assertNull(catalogue_cache::get());
        $this->assertCount(3, $naas->calls);
    }
}

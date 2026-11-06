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
 * Tests for {@see \mod_naas\catalogue_warmer}.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

use advanced_testcase;
use mod_naas\catalogue_cache;
use mod_naas\catalogue_export;

/**
 * Tests for the catalogue rebuild from NaaS.
 *
 * @covers \mod_naas\catalogue_warmer
 */
final class catalogue_warmer_test extends advanced_testcase {
    /**
     * Isolate MUC and plugin config.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('naas_endpoint', 'https://api.example.test/api', 'naas');
        set_config('naas_username', 'user', 'naas');
        set_config('naas_password', 'secret', 'naas');
        set_config('naas_structure_id', 'struct-1', 'naas');
        set_config('naas_filter', '', 'naas');
        catalogue_cache::purge();
    }

    public function test_warm_stores_search_and_producers(): void {
        $stub = new class extends \mod_naas\naas_client {
            /**
             * Create the stub.
             */
            public function __construct() {
                $cfg = new \stdClass();
                $cfg->naas_endpoint = 'https://Stub.example';
                $cfg->naas_username = 'u';
                $cfg->naas_password = 'p';
                $cfg->naas_structure_id = 's';
                parent::__construct($cfg);
            }

            /**
             * Return the stubbed HTTP body.
             *
             * @param string $protocol
             * @param string $service
             * @param object|null $data
             * @param array|null $params
             */
            public function request_raw($protocol, $service, $data = null, $params = null) {
                if (str_contains((string) $service, '/nuggets/search')) {
                    return json_encode([
                        'payload' => [
                            'items' => [[
                                'nugget_id' => 'n1',
                                'name' => 'Cached',
                                'modification_date' => '2026-09-16T08:00:00Z',
                            ]],
                            'aggregations' => [
                                'producers' => ['buckets' => [[
                                    'key' => '06d37c13-6ffe-4c4a-a9e3-ac227652f98c',
                                    'docCount' => 3,
                                ]]],
                            ],
                            'results_count' => 1,
                        ],
                    ]);
                }
                return json_encode([
                    'payload' => [
                        'items' => [[
                            'uuid' => '06d37c13-6ffe-4c4a-a9e3-ac227652f98c',
                            'structure_id' => 'isae-supaero',
                            'name' => 'ISAE-SUPAERO',
                            'acronym' => 'ISAE',
                        ]],
                        'pages' => 1,
                    ],
                ]);
            }
        };

        catalogue_warmer::warm($stub, (object) get_config('naas'));
        $exported = catalogue_export::export_for_widget();
        $this->assertNotNull($exported);
        $this->assertSame('Cached', $exported['search']['items'][0]['name']);
        $this->assertSame('ISAE', $exported['producers'][0]['acronym']);
        $this->assertSame('2026-09-16T08:00:00Z', $exported['max_modification_date']);
        $this->assertNotNull(catalogue_export::cached_probe((object) get_config('naas')));
    }

    public function test_warm_falls_back_to_structure_search_and_derives_media_urls(): void {
        $paths = [];
        $stub = $this->stub_client(function (string $service, array $params) use (&$paths): string {
            $paths[] = $service;
            if (str_contains($service, '/nuggets/search')) {
                return json_encode(['items' => [], 'aggregations' => [], 'results_count' => 0]);
            }
            if ($service === '/structures') {
                return json_encode(['payload' => ['items' => [], 'pages' => 1]]);
            }
            return json_encode(['payload' => ['entries' => [
                ['properties' => ['structure:structure_id' => 'isae-supaero', 'dc:title' => 'ISAE-SUPAERO']],
                ['name' => 'No identifier'],
            ]]]);
        });

        catalogue_warmer::warm($stub, (object) get_config('naas'));
        $this->assertSame(['/structures', '/structures/search'], array_slice($paths, 1));
        $producers = catalogue_cache::get()['producers'];
        $this->assertCount(1, $producers);
        $this->assertSame('isae-supaero', $producers[0]['structure_id']);
        $this->assertSame('isae-supaero', $producers[0]['uuid']);
        $this->assertSame('ISAE-SUPAERO', $producers[0]['name']);
        $this->assertSame(
            'https://api.example.test/api/thumbnails/structure/isae-supaero/thumbnail',
            $producers[0]['structure_thumbnail_url']
        );
        $this->assertSame(
            'https://api.example.test/api/thumbnails/structure/isae-supaero/banner',
            $producers[0]['structure_banner_url']
        );
    }

    public function test_warm_pages_through_the_structure_listing(): void {
        $pages = [];
        $sizes = [];
        $stub = $this->stub_client(function (string $service, array $params) use (&$pages, &$sizes): string {
            if (str_contains($service, '/nuggets/search')) {
                return 'not json';
            }
            $pages[] = $params['page'];
            $sizes[] = $params['page_size'];
            return json_encode(['items' => [['uuid' => 'p' . $params['page']]], 'pages' => 3]);
        });

        catalogue_warmer::warm($stub, (object) get_config('naas'));
        $this->assertSame([0, 1, 2], $pages);
        $this->assertSame(array_fill(0, 3, catalogue_warmer::PRODUCER_PAGE_SIZE), $sizes);
        $stored = catalogue_cache::get();
        $this->assertSame(['p0', 'p1', 'p2'], array_column($stored['producers'], 'uuid'));
        // No usable search: an empty one is stored and the probe is not trusted.
        $this->assertSame(0, $stored['search']['results_count']);
        $this->assertSame(0, $stored['stamp_checked_at']);
    }

    public function test_warm_swallows_naas_failures(): void {
        $stub = $this->stub_client(function (string $service, array $params): string {
            throw new \moodle_exception('naas_unreachable', 'naas');
        });
        catalogue_warmer::warm($stub, (object) get_config('naas'));
        $this->assertDebuggingCalled();
        $this->assertNull(catalogue_cache::get());
    }

    public function test_warm_stores_nothing_when_naas_returns_nothing(): void {
        $stub = $this->stub_client(function (string $service, array $params): string {
            return str_contains($service, '/nuggets/search') ? '' : json_encode(['items' => []]);
        });
        catalogue_warmer::warm($stub, (object) get_config('naas'));
        $this->assertNull(catalogue_cache::get());
    }

    /**
     * A NaaS client whose HTTP bodies come from a callback.
     *
     * @param callable $responder Receives the service path and query, returns the body.
     * @return naas_client
     */
    private function stub_client(callable $responder): naas_client {
        return new class ($responder) extends naas_client {
            /** @var callable Returns the HTTP body for a request. */
            private $responder;

            /**
             * Create the stub.
             *
             * @param callable $responder
             */
            public function __construct(callable $responder) {
                $cfg = new \stdClass();
                $cfg->naas_endpoint = 'https://Stub.example';
                $cfg->naas_username = 'u';
                $cfg->naas_password = 'p';
                $cfg->naas_structure_id = 's';
                parent::__construct($cfg);
                $this->responder = $responder;
            }

            /**
             * Return the stubbed HTTP body.
             *
             * @param string $protocol
             * @param string $service
             * @param object|null $data
             * @param array|null $params
             */
            public function request_raw($protocol, $service, $data = null, $params = null) {
                return ($this->responder)((string) $service, (array) ($params ?? []));
            }
        };
    }
}

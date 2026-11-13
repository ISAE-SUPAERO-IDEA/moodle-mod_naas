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
 * Tests for the NaaS structure (producer) lookup.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/routing_naas_client.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\structure_lookup::class)]
/**
 * Tests for structure_lookup: keys, name detection, cache, snapshot, NaaS and producer catalogue.
 *
 * NaaS is never reached: every request goes to a routing_naas_client.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\structure_lookup
 */
final class structure_lookup_test extends \advanced_testcase {
    /** Cache key of the producer catalogue in the vocabulary cache. */
    private const CATALOG_KEY = 'producer_catalog_v3';

    /** Configured NaaS API URL. */
    private const ENDPOINT = 'https://api.naas.example/api';

    /** @var object Configuration passed to the lookup. */
    private $config;

    /**
     * Isolate config and caches.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('naas_endpoint', self::ENDPOINT, 'naas');
        $this->config = (object) get_config('naas');
        $this->cache()->purge();
        catalogue_cache::purge();
    }

    /**
     * The vocabulary cache the lookup uses.
     *
     * @return \cache
     */
    private function cache(): \cache {
        return \cache::make('mod_naas', vocabulary_lookup::CACHE_AREA);
    }

    /**
     * A NaaS client answering from a callback; it gets the service path and the query parameters.
     *
     * Returning null answers "not found"; returning a \Throwable throws it.
     *
     * @param callable $route fn(string $service, array $params): string|\Throwable|null.
     * @return routing_naas_client
     */
    private function naas(callable $route): routing_naas_client {
        return new routing_naas_client(function (string $protocol, string $service, $params) use ($route): string {
            $answer = $route($service, (array) $params);
            if ($answer === null) {
                throw routing_naas_client::naas_error('error:naas_api:not_found');
            }
            if ($answer instanceof \Throwable) {
                throw $answer;
            }
            return $answer;
        });
    }

    /**
     * A NaaS client that knows no structure at all.
     *
     * @return routing_naas_client
     */
    private function empty_naas(): routing_naas_client {
        return $this->naas(function (): ?string {
            return null;
        });
    }

    /**
     * Cache keys ignore case and surrounding spaces, and only use MUC-safe characters.
     */
    public function test_cache_keys_are_case_insensitive_and_safe(): void {
        $this->assertSame(structure_lookup::cache_key('ABC-1'), structure_lookup::cache_key(' abc-1 '));
        $this->assertSame('structurelabel_' . sha1('abc-1'), structure_lookup::cache_key('ABC-1'));
        $this->assertSame('structuremiss_' . sha1('abc-1'), structure_lookup::miss_key(' Abc-1'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('normalize_key_provider')]
    /**
     * Producer aggregation keys lose their relationship prefix.
     *
     * @dataProvider normalize_key_provider
     * @param string $raw
     * @param string $expected
     */
    public function test_normalize_key(string $raw, string $expected): void {
        $this->assertSame($expected, structure_lookup::normalize_key($raw));
    }

    /**
     * Aggregation keys and their structure id.
     *
     * @return array
     */
    public static function normalize_key_provider(): array {
        return [
            'managed_by prefix' => ['managed_by:structure:isae', 'isae'],
            'structure prefix, any case' => [' Structure: isae ', 'isae'],
            'bare id' => [' isae ', 'isae'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('json_has_name_provider')]
    /**
     * Whether a JSON body carries a human-readable structure name.
     *
     * @dataProvider json_has_name_provider
     * @param string $json
     * @param bool $expected
     */
    public function test_json_has_name(string $json, bool $expected): void {
        $this->assertSame($expected, structure_lookup::json_has_name($json));
    }

    /**
     * Bodies with and without a structure name.
     *
     * @return array
     */
    public static function json_has_name_provider(): array {
        return [
            'not json' => ['oops', false],
            'json list' => ['[{"acronym":"ISAE"}]', false],
            'payload as a JSON string' => ['{"payload":"{\"acronym\":\"ISAE\"}"}', true],
            'uuid as name' => ['{"payload":{"name":"0f8fad5b-d9cb-469f-a165-70867728950e"}}', false],
            'hex hash as title, blank label' => ['{"title":"' . str_repeat('ab', 16) . '","label":" "}', false],
            'numeric acronym, then a label' => ['{"acronym":5,"label":"Lab"}', true],
            'Nuxeo title' => ['{"properties":{"dc:title":"ISAE-SUPAERO"}}', true],
            'payload string that is not JSON' => ['{"payload":"plain"}', false],
        ];
    }

    /**
     * Nuxeo structure:* and dc:title properties are flattened, from an array or an object.
     */
    public function test_normalise_record_flattens_nuxeo_properties(): void {
        $fromarray = structure_lookup::normalise_record([
            'uid' => 'uuid-1',
            'properties' => [
                'structure:acronym' => 'ISAE',
                'dc:title' => 'ISAE-SUPAERO',
                'structure:name' => 'ignored',
                'structure:structure_id' => 'isae',
            ],
        ]);
        $fromname = structure_lookup::normalise_record((object) [
            'properties' => (object) ['structure:name' => 'Institut'],
        ]);

        $this->assertSame('ISAE', $fromarray->acronym);
        $this->assertSame('ISAE-SUPAERO', $fromarray->name);
        $this->assertSame('isae', $fromarray->structure_id);
        $this->assertSame('uuid-1', $fromarray->uuid);
        $this->assertSame('Institut', $fromname->name);
    }

    /**
     * Values already on the record win; a title fills a missing name; scalars give an empty record.
     */
    public function test_normalise_record_keeps_existing_values(): void {
        $record = structure_lookup::normalise_record((object) [
            'acronym' => 'KEEP',
            'name' => 'Kept',
            'structure_id' => 'kept',
            'uuid' => 'kept-uuid',
            'uid' => 'other',
            'properties' => (object) [
                'structure:acronym' => 'X',
                'dc:title' => 'X',
                'structure:structure_id' => 'x',
            ],
        ]);
        $titled = structure_lookup::normalise_record((object) ['title' => 'From title']);

        $this->assertSame(['KEEP', 'Kept', 'kept', 'kept-uuid'], [$record->acronym, $record->name, $record->structure_id,
            $record->uuid]);
        $this->assertSame('From title', $titled->name);
        $this->assertEquals(new \stdClass(), structure_lookup::normalise_record(42));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('page_count_provider')]
    /**
     * Page count of a list body.
     *
     * @dataProvider page_count_provider
     * @param mixed $decoded
     * @param int $expected
     */
    public function test_list_page_count($decoded, int $expected): void {
        $this->assertSame($expected, structure_lookup::list_page_count($decoded));
    }

    /**
     * Decoded list bodies and their page count.
     *
     * @return array
     */
    public static function page_count_provider(): array {
        return [
            'not an object' => [[1, 2], 1],
            'pages in payload' => [(object) ['payload' => (object) ['pages' => '3']], 3],
            'zero pages' => [(object) ['pages' => 0], 1],
            'non-numeric pages' => [(object) ['pages' => 'many'], 1],
            'no pages' => [(object) [], 1],
        ];
    }

    /**
     * Rows match by structure_id, structureId, uuid, uid or id, ignoring case.
     */
    public function test_record_matches(): void {
        $this->assertTrue(structure_lookup::record_matches((object) ['structureId' => 'ISAE'], 'isae'));
        $this->assertTrue(structure_lookup::record_matches((object) ['structure_id' => 5, 'id' => 'Isae'], 'isae'));
        $this->assertFalse(structure_lookup::record_matches((object) ['uuid' => 'other'], 'isae'));
    }

    /**
     * A named cache entry is answered without NaaS; a nameless one is dropped.
     */
    public function test_resolve_answers_a_named_cache_entry_and_drops_a_nameless_one(): void {
        $named = '{"payload":{"acronym":"ISAE"}}';
        $this->cache()->set(structure_lookup::cache_key('isae'), $named);
        $this->cache()->set(structure_lookup::cache_key('lab'), '{"payload":{"structure_id":"lab"}}');
        $naas = $this->naas(function (string $service): ?string {
            return $service === '/structures/lab' ? '{"payload":{"acronym":"LAB"}}' : null;
        });

        $this->assertSame($named, structure_lookup::resolve($naas, $this->config, 'isae'));
        $this->assertSame([], $naas->calls);

        $this->assertSame('{"payload":{"acronym":"LAB"}}', structure_lookup::resolve($naas, $this->config, 'lab'));
        $this->assertSame(['/structures/lab'], $naas->services());
        $this->assertSame('{"payload":{"acronym":"LAB"}}', $this->cache()->get(structure_lookup::cache_key('lab')));
    }

    /**
     * A named producer in the catalogue snapshot answers before NaaS.
     */
    public function test_resolve_answers_from_the_catalogue_snapshot(): void {
        catalogue_cache::store([
            'fingerprint' => catalogue_cache::fingerprint($this->config),
            'producers' => [['structure_id' => 'isae', 'acronym' => 'ISAE', 'name' => 'ISAE-SUPAERO']],
        ]);
        $naas = $this->empty_naas();

        $result = json_decode(structure_lookup::resolve($naas, $this->config, 'isae'));

        $this->assertSame('ISAE', $result->payload->acronym);
        $this->assertSame([], $naas->calls);
        $this->assertNotFalse($this->cache()->get(structure_lookup::cache_key('isae')));
    }

    /**
     * A recent miss answers with the payload it remembered, without NaaS.
     */
    public function test_resolve_answers_a_recent_miss(): void {
        $this->cache()->set(structure_lookup::miss_key('ghost'), json_encode(['at' => time(), 'payload' => '{"x":1}']));
        $naas = $this->empty_naas();

        $this->assertSame('{"x":1}', structure_lookup::resolve($naas, $this->config, 'ghost'));
        $this->assertSame([], $naas->calls);
    }

    /**
     * A mixed-case key is asked as given, then in lower case; the named answer wins.
     */
    public function test_resolve_retries_a_mixed_case_key_in_lower_case(): void {
        $naas = $this->naas(function (string $service): ?string {
            if ($service === '/structures/ISAE') {
                return '{"payload":{"structure_id":"ISAE"}}';
            }
            return $service === '/structures/isae' ? '{"payload":{"acronym":"ISAE"}}' : null;
        });
        $this->cache()->set(structure_lookup::miss_key('ISAE'), json_encode(['at' => 0, 'payload' => 'old']));

        $result = structure_lookup::resolve($naas, $this->config, 'ISAE');

        $this->assertSame('{"payload":{"acronym":"ISAE"}}', $result);
        $this->assertSame(['/structures/ISAE', '/structures/isae'], $naas->services());
        $this->assertFalse($this->cache()->get(structure_lookup::miss_key('ISAE')), 'A named answer clears the old miss.');
    }

    /**
     * Errors other than "NaaS does not know" reach the caller.
     */
    public function test_resolve_rethrows_other_naas_errors(): void {
        $naas = $this->naas(function (): \moodle_exception {
            return routing_naas_client::naas_error('error:naas_api:invalid_credentials');
        });

        try {
            structure_lookup::resolve($naas, $this->config, 'isae');
            $this->fail('A credential error must reach the caller');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:naas_api:invalid_credentials', $e->errorcode);
        }
        $this->assertSame(['/structures/isae'], $naas->services());
    }

    /**
     * A nameless NaaS record is completed from the producer listing, which is then cached.
     */
    public function test_resolve_completes_a_nameless_record_from_the_producer_catalogue(): void {
        $naas = $this->naas(function (string $service, array $params): ?string {
            if ($service === '/structures/uuid-1') {
                return '{"payload":{"uuid":"uuid-1"}}';
            }
            if ($service === '/structures' && isset($params['is_producer'])) {
                $rows = $params['page'] === 0
                    ? [['uid' => 'other', 'acronym' => 'OTHER']]
                    : [['uid' => 'UUID-1', 'properties' => ['structure:acronym' => 'LAB']]];
                return json_encode(['payload' => ['pages' => 2, 'items' => $rows]]);
            }
            return null;
        });

        $result = json_decode(structure_lookup::resolve($naas, $this->config, 'uuid-1'));

        $this->assertSame('LAB', $result->payload->acronym);
        $this->assertSame(['/structures/uuid-1', '/structures', '/structures'], $naas->services());
        $this->assertSame(['is_producer' => 'true', 'page_size' => 100, 'page' => 1], $naas->calls[2]['params']);
        $this->assertCount(2, json_decode($this->cache()->get(self::CATALOG_KEY)));
    }

    /**
     * A listing whose payload is the bare list of structures is read too.
     */
    public function test_resolve_reads_a_bare_list_payload(): void {
        $naas = $this->naas(function (string $service): ?string {
            return $service === '/structures' ? '{"payload":[{"structure_id":"lab","acronym":"LAB"}]}' : null;
        });

        $result = json_decode(structure_lookup::resolve($naas, $this->config, 'lab'));

        $this->assertSame('LAB', $result->payload->acronym);
        $this->assertSame(['/structures/lab', '/structures'], $naas->services());
    }

    /**
     * Listing and search fail: the structure_id query on /structures, then /structures/search, is tried.
     */
    public function test_resolve_queries_by_structure_id_when_the_catalogue_is_unavailable(): void {
        $naas = $this->naas(function (string $service, array $params) {
            if (isset($params['is_producer'])) {
                return routing_naas_client::naas_error('error:naas_api:unknown');
            }
            if ($service === '/structures' && isset($params['structure_id'])) {
                return '{"payload":{"entries":[{"structure_id":"lab"}]}}';
            }
            if ($service === '/structures/search' && isset($params['structure_id'])) {
                return '{"payload":"{\"results\":[{\"structure_id\":\"lab\",\"name\":\"Lab\"}]}"}';
            }
            return null;
        });

        $result = json_decode(structure_lookup::resolve($naas, $this->config, 'lab'));

        $this->assertDebuggingCalledCount(2, [
            'NAAS producer catalog: error:naas_api:unknown',
            'NAAS producer catalog search: error:naas_api:unknown',
        ], [DEBUG_DEVELOPER, DEBUG_DEVELOPER]);
        $this->assertSame('Lab', $result->payload->name);
        $this->assertSame(['structure_id' => 'lab', 'page_size' => 5], end($naas->calls)['params']);
        $this->assertFalse($this->cache()->get(self::CATALOG_KEY), 'An empty catalogue is not cached.');
    }

    /**
     * Nothing anywhere: the media-only payload is returned and remembered as a miss.
     */
    public function test_resolve_falls_back_to_the_media_payload(): void {
        $naas = $this->naas(function (string $service, array $params) {
            return isset($params['structure_id']) ? routing_naas_client::naas_error('error:naas_api:unknown') : null;
        });

        $result = structure_lookup::resolve($naas, $this->config, 'a b');

        $this->assertDebuggingCalledCount(4);
        $this->assertSame([
            'payload' => [
                'structure_id' => 'a b',
                'structure_thumbnail_url' => self::ENDPOINT . '/thumbnails/structure/a%20b/thumbnail',
                'structure_banner_url' => self::ENDPOINT . '/thumbnails/structure/a%20b/banner',
            ],
        ], json_decode($result, true));
        $miss = json_decode($this->cache()->get(structure_lookup::miss_key('a b')), true);
        $this->assertSame($result, $miss['payload']);
        $this->assertFalse($this->cache()->get(structure_lookup::cache_key('a b')));
    }

    /**
     * A nameless NaaS record with no better source is returned as is and remembered as a miss.
     */
    public function test_resolve_returns_a_nameless_record_when_nothing_names_it(): void {
        $this->cache()->set(self::CATALOG_KEY, json_encode([['structure_id' => 'lab']]));
        $naas = $this->naas(function (string $service, array $params): ?string {
            if ($service === '/structures/lab') {
                return '{"payload":{"structure_id":"lab"}}';
            }
            return '{"payload":[{"structure_id":"lab"}]}';
        });

        $result = structure_lookup::resolve($naas, $this->config, 'lab');

        // The matching catalogue row (served from the cache) has no name but is still wrapped and used.
        $this->assertSame('{"payload":{"structure_id":"lab"}}', $result);
        $this->assertSame(['/structures/lab'], $naas->services());
        $this->assertNotFalse($this->cache()->get(structure_lookup::miss_key('lab')));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unusable_listing_provider')]
    /**
     * A corrupt cached catalogue is fetched again; unusable listing bodies give no row.
     *
     * @dataProvider unusable_listing_provider
     * @param string $body
     */
    public function test_resolve_with_an_unusable_listing(string $body): void {
        $this->cache()->set(self::CATALOG_KEY, '"corrupt"');
        $naas = $this->naas(function (string $service) use ($body): ?string {
            return $service === '/structures/lab' ? null : $body;
        });

        $result = json_decode(structure_lookup::resolve($naas, $this->config, 'lab'), true);

        $this->assertArrayHasKey('structure_thumbnail_url', $result['payload']);
        $this->assertSame(
            ['/structures/lab', '/structures', '/structures/search', '/structures', '/structures/search'],
            $naas->services()
        );
    }

    /**
     * Listing bodies that hold no structure.
     *
     * @return array
     */
    public static function unusable_listing_provider(): array {
        return [
            'not an object' => ['[]'],
            'object without a list' => ['{"payload":{"count":0}}'],
            'payload is a number' => ['{"payload":3}'],
        ];
    }
}

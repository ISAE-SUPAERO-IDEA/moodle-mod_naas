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
 * Tests for the NaaS person (author) lookup.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/routing_naas_client.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\person_lookup::class)]
/**
 * Tests for person_lookup: name detection, Nuxeo normalisation, cache, NaaS and person catalogue.
 *
 * NaaS is never reached: every request goes to a routing_naas_client.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\person_lookup
 */
final class person_lookup_test extends \advanced_testcase {
    /** Cache key of the person catalogue in the vocabulary cache. */
    private const CATALOG_KEY = 'person_catalog_v1';

    /**
     * Isolate caches.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->cache()->purge();
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
     * A NaaS client answering from a route table; unknown routes answer "not found".
     *
     * Each route is a body, an exception to throw, or a callable taking the query parameters.
     *
     * @param array $routes Service path => string|\Throwable|callable.
     * @return routing_naas_client
     */
    private function naas(array $routes): routing_naas_client {
        return new routing_naas_client(function (string $protocol, string $service, $params) use ($routes): string {
            $answer = $routes[$service] ?? routing_naas_client::naas_error('error:naas_api:not_found');
            if ($answer instanceof \Throwable) {
                throw $answer;
            }
            if (is_callable($answer)) {
                return $answer((array) $params);
            }
            return $answer;
        });
    }

    /**
     * Whether a JSON body carries a human-readable person name.
     *
     * @dataProvider json_has_name_provider
     * @param string $json
     * @param bool $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('json_has_name_provider')]
    public function test_json_has_name(string $json, bool $expected): void {
        $this->assertSame($expected, person_lookup::json_has_name($json));
    }

    /**
     * Bodies with and without a person name.
     *
     * @return array
     */
    public static function json_has_name_provider(): array {
        return [
            'not json' => ['not json', false],
            'json list' => ['[{"firstname":"Ada"}]', false],
            'payload as a JSON string' => ['{"payload":"{\"firstname\":\"Ada\"}"}', true],
            'camel-case last name in payload' => ['{"payload":{"lastName":"Lovelace"}}', true],
            'blank first name only' => ['{"firstname":"   "}', false],
            'opaque hash as name' => ['{"name":"0123456789abcdef0123"}', false],
            'blank name, numeric fullname, then a title' => ['{"name":" ","fullname":5,"title":"Ada L."}', true],
            'email only' => ['{"email":"ada@example.test"}', false],
            'Nuxeo first name' => ['{"properties":{"person:firstname":"Ada"}}', true],
            'payload string that is not JSON' => ['{"payload":"plain text"}', false],
        ];
    }

    /**
     * Nuxeo person:* properties are lifted onto the record, from an array or an object.
     */
    public function test_normalise_record_lifts_nuxeo_properties_from_an_array(): void {
        $record = person_lookup::normalise_record([
            'properties' => [
                'person:firstname' => 'Ada',
                'person:lastname' => 'Lovelace',
                'person:email' => 'ada@example.test',
            ],
        ]);

        $this->assertSame('Ada', $record->firstname);
        $this->assertSame('Lovelace', $record->lastname);
        $this->assertSame('ada@example.test', $record->email);
    }

    /**
     * Values already on the record win over the Nuxeo properties.
     */
    public function test_normalise_record_keeps_existing_values(): void {
        $record = person_lookup::normalise_record((object) [
            'firstname' => 'Augusta',
            'email' => 'keep@example.test',
            'properties' => (object) [
                'person:firstname' => 'Ada',
                'person:lastname' => 'Lovelace',
                'person:email' => 'other@example.test',
            ],
        ]);

        $this->assertSame('Augusta', $record->firstname);
        $this->assertSame('Lovelace', $record->lastname);
        $this->assertSame('keep@example.test', $record->email);
    }

    /**
     * snake_case and camelCase name fields are copied onto firstname / lastname.
     */
    public function test_normalise_record_maps_alternative_name_fields(): void {
        $snake = person_lookup::normalise_record((object) ['first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $camel = person_lookup::normalise_record((object) ['firstName' => 'Grace', 'lastName' => 'Hopper', 'last_name' => 7]);

        $this->assertSame(['Ada', 'Lovelace'], [$snake->firstname, $snake->lastname]);
        $this->assertSame(['Grace', 'Hopper'], [$camel->firstname, $camel->lastname]);
    }

    /**
     * Without first or last name, a readable title becomes the name; an opaque one does not.
     */
    public function test_normalise_record_uses_a_readable_title_as_name(): void {
        $fromprops = person_lookup::normalise_record((object) ['properties' => (object) ['dc:title' => ' Ada Lovelace ']]);
        $fromitem = person_lookup::normalise_record((object) ['title' => 'Grace Hopper']);
        $opaque = person_lookup::normalise_record((object) ['title' => 'abcdef0123456789abcdef']);
        $named = person_lookup::normalise_record((object) ['title' => 'Grace Hopper', 'name' => 'Keep me']);
        $nontext = person_lookup::normalise_record((object) ['title' => ['x']]);

        $this->assertSame('Ada Lovelace', $fromprops->name);
        $this->assertSame('Grace Hopper', $fromitem->name);
        $this->assert_no_property('name', $opaque);
        $this->assertSame('Keep me', $named->name);
        $this->assert_no_property('name', $nontext);
    }

    /**
     * Anything but an object or an array becomes an empty record.
     */
    public function test_normalise_record_of_a_scalar_is_an_empty_record(): void {
        $this->assertEquals(new \stdClass(), person_lookup::normalise_record('ada'));
    }

    /**
     * Bodies that already carry a name, or that cannot be read, are returned unchanged.
     *
     * @dataProvider unchanged_json_provider
     * @param string $json
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('unchanged_json_provider')]
    public function test_enrich_json_leaves_some_bodies_untouched(string $json): void {
        $this->assertSame($json, person_lookup::enrich_json($json));
    }

    /**
     * Bodies enrich_json() must not rewrite.
     *
     * @return array
     */
    public static function unchanged_json_provider(): array {
        return [
            'not json' => ['<html>'],
            'payload is a list' => ['{"payload":[1,2]}'],
            'payload string that is not JSON' => ['{"payload":"oops"}'],
            'already named, odd spacing kept' => ['{ "firstname" : "Ada" }'],
            'readable full name' => ['{"payload":{"full_name":"Ada Lovelace","properties":{"person:firstname":"X"}}}'],
        ];
    }

    /**
     * Nuxeo properties become firstname / lastname, wrapped or not.
     */
    public function test_enrich_json_lifts_properties(): void {
        $bare = json_decode(person_lookup::enrich_json(
            '{"name":"abcdef0123456789abcd","fullname":5,"properties":{"person:firstname":"Ada"}}'
        ));
        $wrapped = json_decode(person_lookup::enrich_json(
            '{"payload":"{\"properties\":{\"person:lastname\":\"Lovelace\"}}","status":200}'
        ));

        $this->assertSame('Ada', $bare->firstname);
        $this->assertSame('Lovelace', $wrapped->payload->lastname);
        $this->assertSame(200, $wrapped->status);
    }

    /**
     * A named person in the cache is answered without NaaS.
     */
    public function test_resolve_answers_a_named_cache_entry_without_naas(): void {
        $key = 'ada@example.test';
        $json = '{"payload":{"firstname":"Ada"}}';
        $this->cache()->set(vocabulary_lookup::cache_key('person_', $key), $json);
        $naas = $this->naas([]);

        $this->assertSame($json, person_lookup::resolve($naas, $key));
        $this->assertSame([], $naas->calls);
    }

    /**
     * A cached Nuxeo record is enriched once and the cache rewritten with the result.
     */
    public function test_resolve_rewrites_a_cache_entry_it_had_to_enrich(): void {
        $cachekey = vocabulary_lookup::cache_key('person_', 'ada');
        $this->cache()->set($cachekey, '{"payload":{"properties":{"person:firstname":"Ada"}}}');
        $naas = $this->naas([]);

        $result = person_lookup::resolve($naas, 'ada');

        $this->assertSame('Ada', json_decode($result)->payload->firstname);
        $this->assertSame($result, $this->cache()->get($cachekey));
        $this->assertSame([], $naas->calls);
    }

    /**
     * A nameless cache entry is dropped and NaaS asked; the named answer replaces it.
     */
    public function test_resolve_replaces_a_nameless_cache_entry_with_the_naas_answer(): void {
        $cachekey = vocabulary_lookup::cache_key('person_', 'ada@example.test');
        $this->cache()->set($cachekey, '{"payload":{"email":"ada@example.test"}}');
        $naas = $this->naas([
            '/persons/ada%40example.test' => '{"payload":{"firstname":"Ada","lastname":"Lovelace"}}',
        ]);

        $result = person_lookup::resolve($naas, 'ada@example.test');

        $this->assertSame('{"payload":{"firstname":"Ada","lastname":"Lovelace"}}', $result);
        $this->assertSame(['/persons/ada%40example.test'], $naas->services());
        $this->assertSame($result, $this->cache()->get($cachekey));
        $this->assertFalse($this->cache()->get(vocabulary_lookup::cache_key('personmiss_', 'ada@example.test')));
    }

    /**
     * A recent miss that had a (nameless) answer returns that answer without NaaS.
     */
    public function test_resolve_answers_a_recent_miss_with_its_payload(): void {
        $this->cache()->set(
            vocabulary_lookup::cache_key('personmiss_', 'ghost'),
            json_encode(['at' => time(), 'payload' => '{"payload":{"email":"ghost"}}'])
        );
        $naas = $this->naas([]);

        $this->assertSame('{"payload":{"email":"ghost"}}', person_lookup::resolve($naas, 'ghost'));
        $this->assertSame([], $naas->calls);
    }

    /**
     * A recent miss without any answer is reported as "not found" without NaaS.
     */
    public function test_resolve_reports_a_recent_empty_miss_as_not_found(): void {
        $this->cache()->set(
            vocabulary_lookup::cache_key('personmiss_', 'ghost'),
            json_encode(['at' => time(), 'payload' => null])
        );
        $naas = $this->naas([]);

        try {
            person_lookup::resolve($naas, 'ghost');
            $this->fail('A remembered empty miss is reported as not found');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:naas_api:not_found', $e->errorcode);
        }
        $this->assertSame([], $naas->calls);
    }

    /**
     * Errors other than "NaaS does not know" reach the caller and nothing is remembered.
     */
    public function test_resolve_rethrows_other_naas_errors(): void {
        $naas = $this->naas([
            '/persons/ada' => routing_naas_client::naas_error('error:naas_api:invalid_credentials'),
        ]);

        try {
            person_lookup::resolve($naas, 'ada');
            $this->fail('A credential error must reach the caller');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:naas_api:invalid_credentials', $e->errorcode);
        }
        $this->assertSame(['/persons/ada'], $naas->services());
        $this->assertFalse($this->cache()->get(vocabulary_lookup::cache_key('personmiss_', 'ada')));
    }

    /**
     * Unknown to /persons/{id}: the person catalogue names the author, and every named row is cached.
     */
    public function test_resolve_falls_back_to_the_person_catalogue(): void {
        $hash = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';
        $naas = $this->naas([
            '/persons/' . $hash => routing_naas_client::naas_error('error:naas_api:unknown'),
            '/persons' => json_encode(['payload' => ['pages' => 1, 'items' => [
                ['email' => 'nameless@example.test'],
                ['email' => 'person:' . strtoupper($hash), 'properties' => ['person:firstname' => 'Ada']],
                ['uuid' => 'uuid-grace', 'id' => 42, 'email' => '', 'firstname' => 'Grace'],
            ]]]),
        ]);

        $result = json_decode(person_lookup::resolve($naas, $hash));

        $this->assertSame('Ada', $result->payload->firstname);
        $this->assertSame(['/persons/' . $hash, '/persons'], $naas->services());
        $this->assertSame(['page_size' => 100, 'page' => 0], $naas->calls[1]['params']);
        $catalog = json_decode($this->cache()->get(self::CATALOG_KEY));
        $this->assertCount(3, $catalog);
        // Named rows are cached per key; the nameless row, the blank email and the numeric id are not.
        $this->assertNotFalse($this->cache()->get(vocabulary_lookup::cache_key('person_', 'uuid-grace')));
        $this->assertFalse($this->cache()->get(vocabulary_lookup::cache_key('person_', 'nameless@example.test')));
        $this->assertFalse($this->cache()->get(vocabulary_lookup::cache_key('person_', '42')));
        $this->assertNotFalse($this->cache()->get(vocabulary_lookup::cache_key('person_', $hash)));
    }

    /**
     * /persons fails: /persons/search is used instead, and the failure is logged for developers.
     */
    public function test_resolve_uses_the_person_search_when_the_listing_fails(): void {
        $naas = $this->naas([
            '/persons/ada' => routing_naas_client::naas_error('error:naas_api:bad_request'),
            '/persons' => routing_naas_client::naas_error('error:naas_api:unknown'),
            '/persons/search' => '{"payload":"{\"entries\":[{\"id\":\"ada\",\"lastname\":\"Lovelace\"}]}"}',
        ]);

        $result = json_decode(person_lookup::resolve($naas, 'ada'));

        $this->assertDebuggingCalled('NAAS person catalog: error:naas_api:unknown', DEBUG_DEVELOPER);
        $this->assertSame('Lovelace', $result->payload->lastname);
        $this->assertSame(['/persons/ada', '/persons', '/persons/search'], $naas->services());
    }

    /**
     * Nobody knows the person: "not found", and the miss is remembered without a payload.
     */
    public function test_resolve_reports_not_found_when_every_source_fails(): void {
        $naas = $this->naas([
            '/persons' => routing_naas_client::naas_error('error:naas_api:unknown'),
            '/persons/search' => routing_naas_client::naas_error('error:naas_api:unknown'),
        ]);

        try {
            person_lookup::resolve($naas, 'nobody');
            $this->fail('An unknown person is reported as not found');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:naas_api:not_found', $e->errorcode);
        }
        $this->assertDebuggingCalledCount(2, [
            'NAAS person catalog: error:naas_api:unknown',
            'NAAS person catalog search: error:naas_api:unknown',
        ], [DEBUG_DEVELOPER, DEBUG_DEVELOPER]);
        $miss = json_decode($this->cache()->get(vocabulary_lookup::cache_key('personmiss_', 'nobody')), true);
        $this->assertNull($miss['payload']);
        $this->assertFalse($this->cache()->get(self::CATALOG_KEY));
    }

    /**
     * A nameless NaaS answer is returned, remembered as a miss, and served from it the next time.
     */
    public function test_resolve_remembers_a_nameless_answer(): void {
        $nameless = '{"payload":{"email":"ghost@example.test"}}';
        $naas = $this->naas([
            '/persons/ghost%40example.test' => $nameless,
            '/persons' => '{"payload":{"items":[]}}',
            '/persons/search' => '[]',
        ]);

        $this->assertSame($nameless, person_lookup::resolve($naas, 'ghost@example.test'));
        $asked = count($naas->calls);
        $this->assertSame($nameless, person_lookup::resolve($naas, 'ghost@example.test'));

        $this->assertSame(3, $asked);
        $this->assertCount($asked, $naas->calls, 'The remembered miss must not reach NaaS.');
        $this->assertFalse($this->cache()->get(vocabulary_lookup::cache_key('person_', 'ghost@example.test')));
    }

    /**
     * A catalogue row that matches but has no name does not count as an answer.
     */
    public function test_resolve_ignores_a_matching_catalogue_row_without_name(): void {
        $this->cache()->set(self::CATALOG_KEY, json_encode([['email' => 'ada', 'title' => 'abcdef0123456789abcdef']]));
        $naas = $this->naas([]);

        try {
            person_lookup::resolve($naas, 'ada');
            $this->fail('A nameless catalogue row is not an answer');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:naas_api:not_found', $e->errorcode);
        }
        $this->assertSame(['/persons/ada'], $naas->services(), 'The cached catalogue is reused.');
    }

    /**
     * An empty key never matches a catalogue row.
     */
    public function test_resolve_does_not_match_an_empty_key(): void {
        $this->cache()->set(self::CATALOG_KEY, json_encode([['email' => '', 'firstname' => 'Ada']]));

        $this->expectException(\moodle_exception::class);
        person_lookup::resolve($this->naas([]), '');
    }

    /**
     * warm_catalog() pages through /persons and stores the normalised rows.
     */
    public function test_warm_catalog_pages_through_the_listing(): void {
        $naas = $this->naas([
            '/persons' => function (array $params): string {
                $row = ['uid' => 'p' . $params['page'], 'properties' => ['person:lastname' => 'L' . $params['page']]];
                return json_encode(['pages' => 2, 'results' => [$row]]);
            },
        ]);

        $this->assertSame(2, person_lookup::warm_catalog($naas));

        $this->assertSame([0, 1], array_column(array_column($naas->calls, 'params'), 'page'));
        $catalog = json_decode($this->cache()->get(self::CATALOG_KEY));
        $this->assertSame(['L0', 'L1'], array_column($catalog, 'lastname'));
        $this->assertNotFalse($this->cache()->get(vocabulary_lookup::cache_key('person_', 'p1')));
    }

    /**
     * A usable cached catalogue is reused; a corrupt one is fetched again.
     */
    public function test_warm_catalog_reuses_only_a_readable_cached_catalogue(): void {
        $this->cache()->set(self::CATALOG_KEY, json_encode([['email' => 'a', 'firstname' => 'A']]));
        $naas = $this->naas(['/persons' => '{"payload":[{"email":"b","firstname":"B"},{"email":"c","firstname":"C"}]}']);

        $this->assertSame(1, person_lookup::warm_catalog($naas));
        $this->assertSame([], $naas->calls);

        $this->cache()->set(self::CATALOG_KEY, '"corrupt"');
        $this->assertSame(2, person_lookup::warm_catalog($naas));
        $this->assertSame(['/persons'], $naas->services());
    }

    /**
     * Listing bodies without a list of persons give an empty catalogue.
     *
     * @dataProvider empty_listing_provider
     * @param string $body
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('empty_listing_provider')]
    public function test_warm_catalog_of_an_unusable_listing_is_empty(string $body): void {
        $naas = $this->naas(['/persons' => $body, '/persons/search' => $body]);

        $this->assertSame(0, person_lookup::warm_catalog($naas));
        $this->assertSame(['/persons', '/persons/search'], $naas->services());
        $this->assertFalse($this->cache()->get(self::CATALOG_KEY));
    }

    /**
     * Listing bodies that hold no person.
     *
     * @return array
     */
    public static function empty_listing_provider(): array {
        return [
            'not an object' => ['[]'],
            'object without a list' => ['{"payload":{"count":0}}'],
            'payload is a number' => ['{"payload":3}'],
        ];
    }

    /**
     * Assert that an object has no such property (works on PHPUnit 9 and 11).
     *
     * @param string $name
     * @param object $object
     */
    private function assert_no_property(string $name, object $object): void {
        $this->assertFalse(property_exists($object, $name), "Unexpected property {$name}");
    }
}

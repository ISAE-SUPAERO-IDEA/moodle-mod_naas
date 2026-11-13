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
 * Tests for the one-hour memory of structures and persons NaaS cannot name.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

use advanced_testcase;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\structure_lookup::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\person_lookup::class)]
/**
 * Tests for the negative cache of structure_lookup and person_lookup.
 *
 * @covers \mod_naas\structure_lookup
 * @covers \mod_naas\person_lookup
 */
final class lookup_miss_cache_test extends advanced_testcase {
    /** @var object Configuration passed to the lookups. */
    private $config;

    /**
     * Isolate config and caches.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('naas_endpoint', 'https://api.naas.example/api', 'naas');
        $this->config = (object) get_config('naas');
        \cache::make('mod_naas', vocabulary_lookup::CACHE_AREA)->purge();
        catalogue_cache::purge();
    }

    /**
     * A NaaS client that knows nobody and counts the requests it receives.
     *
     * @param string|null $answer Body returned for every request, or null to answer "not found".
     * @return naas_client
     */
    private function client(?string $answer = null): naas_client {
        return new class ($answer) extends naas_client {
            /** @var int Requests received. */
            public $requests = 0;
            /** @var string|null */
            private $answer;

            /**
             * Constructor.
             *
             * @param string|null $answer
             */
            public function __construct(?string $answer) {
                parent::__construct((object) [
                    'naas_endpoint' => 'https://api.naas.example/api',
                    'naas_username' => 'u',
                    'naas_password' => 'p',
                    'naas_structure_id' => 's',
                ]);
                $this->answer = $answer;
            }

            /**
             * Count the request, then answer or report "not found".
             *
             * @param string $protocol
             * @param string $service
             * @param mixed $data
             * @param mixed $params
             * @return string
             */
            public function request_raw($protocol, $service, $data = null, $params = null): string {
                $this->requests++;
                if ($this->answer === null) {
                    throw new \moodle_exception('error:naas_api:not_found', 'naas');
                }
                return $this->answer;
            }
        };
    }

    /**
     * Pretend the miss was recorded more than an hour ago.
     *
     * @param string $key Cache key of the miss.
     */
    private function age_miss(string $key): void {
        $cache = \cache::make('mod_naas', vocabulary_lookup::CACHE_AREA);
        $miss = json_decode($cache->get($key), true);
        $miss['at'] = time() - structure_lookup::MISS_TTL - 1;
        $cache->set($key, json_encode($miss));
    }

    public function test_unknown_structure_is_not_asked_again_within_the_hour(): void {
        $naas = $this->client();

        $first = structure_lookup::resolve($naas, $this->config, 'unknown-structure');
        $asked = $naas->requests;
        $this->assertGreaterThan(0, $asked);

        $second = structure_lookup::resolve($naas, $this->config, 'unknown-structure');
        $this->assertSame($asked, $naas->requests, 'The second lookup must not reach NaaS.');
        $this->assertSame($first, $second);

        $this->age_miss(structure_lookup::miss_key('unknown-structure'));
        structure_lookup::resolve($naas, $this->config, 'unknown-structure');
        $this->assertGreaterThan($asked, $naas->requests, 'After an hour NaaS is asked again.');
        // The producer catalogue logs each NaaS failure with debugging().
        $this->resetDebugging();
    }

    public function test_a_named_structure_replaces_the_miss(): void {
        structure_lookup::resolve($this->client(), $this->config, 'isae-supaero');
        $cache = \cache::make('mod_naas', vocabulary_lookup::CACHE_AREA);
        $this->assertNotFalse($cache->get(structure_lookup::miss_key('isae-supaero')));

        $this->age_miss(structure_lookup::miss_key('isae-supaero'));
        $named = $this->client('{"payload":{"structure_id":"isae-supaero","acronym":"ISAE","name":"ISAE-SUPAERO"}}');
        $result = structure_lookup::resolve($named, $this->config, 'isae-supaero');

        $this->assertStringContainsString('ISAE-SUPAERO', $result);
        $this->assertFalse($cache->get(structure_lookup::miss_key('isae-supaero')));
        $this->resetDebugging();
    }

    public function test_unknown_person_is_not_asked_again_within_the_hour(): void {
        $naas = $this->client();
        $key = 'nobody@example.test';

        try {
            person_lookup::resolve($naas, $key);
            $this->fail('An unknown person is reported as not found');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:naas_api:not_found', $e->errorcode);
        }
        $asked = $naas->requests;
        $this->assertGreaterThan(0, $asked);

        try {
            person_lookup::resolve($naas, $key);
            $this->fail('The remembered miss is still reported as not found');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:naas_api:not_found', $e->errorcode);
        }
        $this->assertSame($asked, $naas->requests, 'The second lookup must not reach NaaS.');

        $this->age_miss(vocabulary_lookup::cache_key('personmiss_', $key));
        try {
            person_lookup::resolve($naas, $key);
        } catch (\moodle_exception $e) {
            $this->assertSame('error:naas_api:not_found', $e->errorcode);
        }
        $this->assertGreaterThan($asked, $naas->requests, 'After an hour NaaS is asked again.');
        $this->resetDebugging();
    }
}

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
 * Tests for the mod_naas_get_person web service.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\external;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/naas_client_stubs.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\get_person::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\naas_external_base::class)]
/**
 * Tests for get_person: capability, person key check and how an author name is resolved.
 *
 * NaaS is never reached: the NaaS client is injected through proxy_using().
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\external\get_person
 * @covers \mod_naas\external\naas_external_base
 */
final class get_person_test extends \advanced_testcase {
    use naas_client_stubs;

    /**
     * A student whose role prohibits mod/naas:view cannot read a person.
     */
    public function test_get_person_requires_capability(): void {
        global $DB;
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability('mod/naas:view', CAP_PROHIBIT, $roleid, \context_course::instance($course->id)->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        get_person::execute($course->id, 'uuid123');
    }

    /**
     * A teacher reaches NaaS, whose failure is reported.
     */
    public function test_get_person_executes(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);

        $proxy = $this->proxy_using($this->failing_client());
        $this->expectException(\moodle_exception::class);
        $proxy::execute($course->id, 'uuid123');
    }

    /**
     * A personKey with special characters must be rejected.
     */
    public function test_get_person_special_chars_rejected(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(\invalid_parameter_exception::class);
        get_person::execute($course->id, 'person/with/slashes');
    }

    /**
     * An aggregation key with its relationship prefix asks NaaS for the bare hash.
     */
    public function test_get_person_accepts_relationship_prefixed_hash(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $client = $this->failing_client();
        $proxy = $this->proxy_using($client);
        try {
            $proxy::execute($course->id, 'authored_by:person:abcdef0123456789abcdef0123456789');
            $this->fail('Expected the stubbed transport failure');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:proxy_naas_api:curl', $e->errorcode);
        }
        $this->assertSame('/persons/abcdef0123456789abcdef0123456789', $client->captured['service']);
    }

    /**
     * An e-mail address is a valid person key.
     */
    public function test_get_person_accepts_an_email_key(): void {
        $this->resetAfterTest(true);
        set_config('naas_endpoint', 'https://api.naas.example/api', 'naas');
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);
        $naas = $this->routing_client(function (): string {
            return '{"payload":{"firstname":"Ada","email":"ada@example.test"}}';
        });

        $result = json_decode($this->proxy_using($naas)::execute($course->id, 'ada@example.test'));

        $this->assertSame('Ada', $result->payload->firstname);
        $this->assertSame(['/persons/ada%40example.test'], $naas->services());
    }

    /**
     * A person in the vocabulary cache is returned without any request.
     */
    public function test_get_person_returns_cached_entry_without_http(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cache->set(
            \mod_naas\vocabulary_lookup::cache_key('person_', 'person99'),
            '{"firstname":"Ada","lastname":"Lovelace"}'
        );

        $this->assertSame(
            '{"firstname":"Ada","lastname":"Lovelace"}',
            get_person::execute($course->id, 'person99')
        );
    }

    /**
     * Cache miss: must call the API client, store the JSON in MUC, then return it.
     */
    public function test_get_person_cache_miss_fetches_and_stores(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $personkey = 'personcover1';
        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cache->delete(\mod_naas\vocabulary_lookup::cache_key('person_', $personkey));

        $proxy = $this->proxy_using($this->answering_client('{"firstname":"Ada","lastname":"Lovelace"}'));
        $json = $proxy::execute($course->id, $personkey);

        $this->assertSame('{"firstname":"Ada","lastname":"Lovelace"}', $json);
        // The real service, without a stubbed client, now answers from the cache.
        $this->assertSame(
            '{"firstname":"Ada","lastname":"Lovelace"}',
            get_person::execute($course->id, $personkey)
        );
    }

    /**
     * A Nuxeo person document keeps the name under properties, not firstname.
     */
    public function test_get_person_lifts_nuxeo_properties(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $personkey = 'abcdef0123456789abcdef0123456789';
        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cache->delete(\mod_naas\vocabulary_lookup::cache_key('person_', $personkey));

        $proxy = $this->proxy_using($this->answering_client(json_encode([
            'uid' => 'person-doc',
            'properties' => [
                'person:firstname' => 'Ada',
                'person:lastname' => 'Lovelace',
                'person:email' => 'abcdef0123456789abcdef0123456789',
            ],
        ])));
        $json = $proxy::execute($course->id, $personkey);

        $this->assertStringContainsString('"firstname":"Ada"', $json);
        $this->assertStringContainsString('"lastname":"Lovelace"', $json);
    }

    /**
     * GET /persons/{id} 404s still resolve names from the person listing.
     * Author aggregation keys are the encrypted email stored on each row.
     */
    public function test_get_person_uses_person_catalog_on_not_found(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $personkey = 'abcdef0123456789abcdef0123456789';
        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cache->delete(\mod_naas\vocabulary_lookup::cache_key('person_', $personkey));
        $cache->delete('person_catalog_v1');

        $naas = $this->routing_client(function (string $service): string {
            if ($service === '/persons/search') {
                return json_encode([
                    'items' => [
                        [
                            'email' => 'abcdef0123456789abcdef0123456789',
                            'firstname' => 'Ada',
                            'lastname' => 'Lovelace',
                        ],
                    ],
                    'pages' => 1,
                ]);
            }
            throw new \moodle_exception('error:naas_api:not_found', 'naas');
        });
        $json = $this->proxy_using($naas)::execute(
            $course->id,
            'authored_by:person:' . $personkey
        );

        // The failed GET /persons listing is logged before the search fallback succeeds.
        $this->assertDebuggingCalled('NAAS person catalog: error:naas_api:not_found', DEBUG_DEVELOPER);
        $this->assertStringContainsString('"firstname":"Ada"', $json);
        $this->assertStringContainsString('"lastname":"Lovelace"', $json);
    }

    /**
     * Name of a get_person subclass whose NaaS client is $client.
     *
     * @param \mod_naas\naas_client $client
     * @return string
     */
    private function proxy_using(\mod_naas\naas_client $client): string {
        $proxy = new class extends get_person {
            use injected_naas_client;
        };
        return $proxy::using($client);
    }
}

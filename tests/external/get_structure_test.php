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
 * Tests for the mod_naas_get_structure web service.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\external;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/naas_client_stubs.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\get_structure::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\naas_external_base::class)]
/**
 * Tests for get_structure: capability, structure key check and how a producer name is resolved.
 *
 * NaaS is never reached: the NaaS client is injected through proxy_using().
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\external\get_structure
 * @covers \mod_naas\external\naas_external_base
 */
final class get_structure_test extends \advanced_testcase {
    use naas_client_stubs;

    /**
     * A structureKey with path-traversal characters must be rejected.
     */
    public function test_get_structure_path_traversal_rejected(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(\invalid_parameter_exception::class);
        get_structure::execute($course->id, '../etc/passwd');
    }

    /**
     * A student whose role prohibits mod/naas:view cannot read a structure.
     */
    public function test_get_structure_requires_capability(): void {
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
        get_structure::execute($course->id, 'uuid123');
    }

    /**
     * A teacher reaches NaaS, whose failure is reported.
     */
    public function test_get_structure_executes(): void {
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
     * A structureKey that is too long must be rejected.
     */
    public function test_get_structure_too_long_key_rejected(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(\invalid_parameter_exception::class);
        get_structure::execute($course->id, str_repeat('a', 129));
    }

    /**
     * A named structure in the vocabulary cache is returned without any request.
     */
    public function test_get_structure_returns_cached_entry_without_http(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cache->set(
            \mod_naas\structure_lookup::cache_key('structA'),
            '{"name":"ISAE-SUPAERO","acronym":"ISAE"}'
        );

        $this->assertSame(
            '{"name":"ISAE-SUPAERO","acronym":"ISAE"}',
            get_structure::execute($course->id, 'structA')
        );
    }

    /**
     * Cache miss: must call the API client, store the JSON in MUC, then return it.
     */
    public function test_get_structure_cache_miss_fetches_and_stores(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $structurekey = 'structcover1';
        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cache->delete(\mod_naas\structure_lookup::cache_key($structurekey));

        $proxy = $this->proxy_using($this->answering_client('{"name":"Cover Structure","acronym":"COV"}'));
        $json = $proxy::execute($course->id, $structurekey);

        $this->assertSame('{"name":"Cover Structure","acronym":"COV"}', $json);
        // The real service, without a stubbed client, now answers from the cache.
        $this->assertSame(
            '{"name":"Cover Structure","acronym":"COV"}',
            get_structure::execute($course->id, $structurekey)
        );
    }

    /**
     * Nameless MUC entries must be deleted and refetched, not returned as-is.
     */
    public function test_get_structure_ignores_nameless_cache(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $structurekey = 'struct-nameless';
        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cache->set(
            \mod_naas\structure_lookup::cache_key($structurekey),
            '{"payload":{"structure_id":"struct-nameless"}}'
        );

        $proxy = $this->proxy_using($this->answering_client('{"name":"ISAE-SUPAERO","acronym":"ISAE"}'));
        $json = $proxy::execute($course->id, $structurekey);

        $this->assertStringContainsString('ISAE-SUPAERO', $json);
    }

    /**
     * GET /structures/{id} 404s still resolve names from the producer listing.
     */
    public function test_get_structure_uses_producer_catalog_on_not_found(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $structurekey = '06d37c13-6ffe-4c4a-a9e3-ac227652f98c';
        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cache->delete(\mod_naas\structure_lookup::cache_key($structurekey));
        $cache->delete('producer_catalog_v3');

        $naas = $this->routing_client(function (string $service): string {
            if (strpos($service, '/structures/') === 0) {
                throw new \moodle_exception('error:naas_api:not_found', 'naas');
            }
            return json_encode([
                'payload' => [
                    'items' => [
                        [
                            'uuid' => '06d37c13-6ffe-4c4a-a9e3-ac227652f98c',
                            'structure_id' => 'isae-supaero',
                            'name' => 'ISAE-SUPAERO',
                            'acronym' => 'ISAE',
                        ],
                    ],
                    'pages' => 1,
                ],
            ]);
        });
        $json = $this->proxy_using($naas)::execute($course->id, $structurekey);

        $this->assertStringContainsString('ISAE-SUPAERO', $json);
        $this->assertStringContainsString('"acronym":"ISAE"', $json);
    }

    /**
     * A producer of the catalogue snapshot wins over a nameless cache entry, without any request.
     */
    public function test_get_structure_prefers_catalogue_snapshot_over_nameless_cache(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        set_config('naas_endpoint', 'https://api.example.test/api', 'naas');
        set_config('naas_username', 'user', 'naas');
        set_config('naas_password', 'secret', 'naas');
        set_config('naas_structure_id', 'struct-1', 'naas');

        $structurekey = '06d37c13-6ffe-4c4a-a9e3-ac227652f98c';
        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cache->set(
            \mod_naas\structure_lookup::cache_key($structurekey),
            '{"payload":{"structure_id":"' . $structurekey . '"}}'
        );

        \mod_naas\catalogue_cache::store([
            'fingerprint' => \mod_naas\catalogue_cache::fingerprint((object) get_config('naas')),
            'producers' => [[
                'structure_id' => 'isae-supaero',
                'uuid' => $structurekey,
                'name' => 'ISAE-SUPAERO',
                'acronym' => 'ISAE',
            ]],
        ]);

        $json = get_structure::execute($course->id, $structurekey);
        $this->assertStringContainsString('"acronym":"ISAE"', $json);
    }

    /**
     * Only a payload with a name counts as a resolved structure.
     */
    public function test_structure_json_has_name_ignores_uuid_only_payloads(): void {
        $m = new \ReflectionMethod(\mod_naas\structure_lookup::class, 'json_has_name');
        $m->setAccessible(true);
        $this->assertTrue($m->invoke(null, '{"payload":{"acronym":"ISAE","name":"ISAE-SUPAERO"}}'));
        $this->assertFalse($m->invoke(
            null,
            '{"payload":{"structure_id":"06d37c13-6ffe-4c4a-a9e3-ac227652f98c"}}'
        ));
    }

    /**
     * A structure record matches its uuid (any case) or its structure_id, flat or Nuxeo-shaped.
     */
    public function test_structure_record_matches_uuid_or_structure_id(): void {
        $m = new \ReflectionMethod(\mod_naas\structure_lookup::class, 'record_matches');
        $m->setAccessible(true);
        $item = (object) [
            'uuid' => '06d37c13-6ffe-4c4a-a9e3-ac227652f98c',
            'structure_id' => 'isae-supaero',
            'acronym' => 'ISAE',
        ];
        $this->assertTrue($m->invoke(null, $item, '06D37C13-6FFE-4C4A-A9E3-AC227652F98C'));
        $this->assertTrue($m->invoke(null, $item, 'isae-supaero'));
        $this->assertFalse($m->invoke(null, $item, 'other-id'));

        $nuxeo = (object) [
            'uid' => '06d37c13-6ffe-4c4a-a9e3-ac227652f98c',
            'title' => 'ISAE-SUPAERO',
            'properties' => (object) [
                'structure:acronym' => 'ISAE',
                'structure:structure_id' => 'isae-supaero',
            ],
        ];
        $normalise = new \ReflectionMethod(\mod_naas\structure_lookup::class, 'normalise_record');
        $normalise->setAccessible(true);
        $flat = $normalise->invoke(null, $nuxeo);
        $this->assertTrue($m->invoke(null, $flat, '06d37c13-6ffe-4c4a-a9e3-ac227652f98c'));
        $this->assertSame('ISAE', $flat->acronym);
        $this->assertSame('ISAE-SUPAERO', $flat->name);
    }

    /**
     * Name of a get_structure subclass whose NaaS client is $client.
     *
     * @param \mod_naas\naas_client $client
     * @return string
     */
    private function proxy_using(\mod_naas\naas_client $client): string {
        $proxy = new class extends get_structure {
            use injected_naas_client;
        };
        return $proxy::using($client);
    }
}

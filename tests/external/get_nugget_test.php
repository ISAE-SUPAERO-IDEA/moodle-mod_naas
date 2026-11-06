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
 * Tests for the mod_naas_get_nugget web service.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\external;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/naas_client_stubs.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\get_nugget::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\naas_external_base::class)]
/**
 * Tests for get_nugget: access control, input validation, the request sent and the Nugget cache.
 *
 * NaaS is never reached: the NaaS client is injected through proxy_using().
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\external\get_nugget
 * @covers \mod_naas\external\naas_external_base
 */
final class get_nugget_test extends \advanced_testcase {
    use naas_client_stubs;

    // Authentication gate.

    /**
     * get_nugget called by a guest (not logged in) must throw an exception.
     */
    public function test_get_nugget_requires_login(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $this->setUser(null);

        $this->expectException(\Exception::class);
        get_nugget::execute($course->id, 'valid-uuid-123');
    }

    /**
     * get_nugget called by a user without addinstance capability must throw.
     */
    public function test_get_nugget_requires_addinstance_capability(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        // Enrol as a plain student (no addinstance).
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $this->expectException(\Exception::class);
        get_nugget::execute($course->id, 'valid-uuid-123');
    }

    // Input validation.

    /**
     * A nuggetId containing path-traversal sequences must be rejected.
     */
    public function test_get_nugget_path_traversal_rejected(): void {
        $this->resetAfterTest(true);

        $course   = $this->getDataGenerator()->create_course();
        $teacher  = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\invalid_parameter_exception::class);
        get_nugget::execute($course->id, '../../etc/passwd');
    }

    /**
     * A nuggetId containing SQL injection characters must be rejected.
     */
    public function test_get_nugget_sql_injection_rejected(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\invalid_parameter_exception::class);
        get_nugget::execute($course->id, "'; DROP TABLE naas; --");
    }

    /**
     * A student lacks the addinstance capability.
     */
    public function test_get_nugget_requires_capability(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        get_nugget::execute($course->id, 'valid-uuid-123');
    }

    /**
     * A teacher reaches NaaS, whose failure is reported.
     */
    public function test_get_nugget_executes(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);

        // The stubbed transport fails like an unreachable NaaS endpoint.
        $proxy = $this->proxy_using($this->failing_client());
        $this->expectException(\moodle_exception::class);
        $proxy::execute($course->id, 'valid-uuid-123');
    }

    /**
     * A nuggetId containing spaces must be rejected.
     */
    public function test_get_nugget_spaces_rejected(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\invalid_parameter_exception::class);
        get_nugget::execute($course->id, 'valid uuid with spaces');
    }

    /**
     * A nuggetId that exceeds 128 characters must be rejected.
     */
    public function test_get_nugget_too_long_id_rejected(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\invalid_parameter_exception::class);
        get_nugget::execute($course->id, str_repeat('a', 129));
    }

    /**
     * Test that invalid IDs (containing path traversal or special chars) are rejected.
     *
     * @dataProvider invalid_ids_provider
     * @param string $badid Nugget id that must be rejected.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalid_ids_provider')]
    public function test_invalid_ids_throw_exception(string $badid): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);

        $this->expectException(\invalid_parameter_exception::class);
        get_nugget::execute($course->id, $badid);
    }

    /**
     * Data provider for invalid IDs.
     *
     * @return array
     */
    public static function invalid_ids_provider(): array {
        return [
            'path traversal' => ['../etc/passwd'],
            'null byte' => ["nugget\0id"],
            'space' => ['nugget id'],
            'slash' => ['nugget/id'],
            'too long' => [str_repeat('a', 129)],
        ];
    }

    // Nugget cache.

    /**
     * get_nugget stores the live document, answers the next call from the cache, and revalidates on request.
     */
    public function test_get_nugget_caches_the_document(): void {
        $course = $this->log_in_as_teacher_with_clean_caches();
        $naas = $this->routing_client(function (): string {
            return '{"payload":{"nugget_id":"n-1","name":"First","modification_date":"2026-05-01","version_id":"v-1"}}';
        });
        $proxy = $this->proxy_using($naas);

        $live = json_decode($proxy::execute($course->id, 'n-1'), true);
        $cached = json_decode($proxy::execute($course->id, 'n-1'), true);
        $revalidated = json_decode($proxy::execute($course->id, 'n-1', 'revalidate'), true);

        $this->assertSame(['First', false], [$live['name'], $live['_cache']['hit']]);
        $this->assertSame(['First', true], [$cached['name'], $cached['_cache']['hit']]);
        $this->assertSame(['modification_date' => '2026-05-01', 'version_id' => 'v-1'], $cached['_cache']['digest']);
        $this->assertFalse($revalidated['_cache']['hit']);
        $this->assertSame(['/nuggets/n-1/default_version', '/nuggets/n-1/default_version'], $naas->services());
    }

    /**
     * A NaaS answer that is not a Nugget document is passed through and not cached.
     */
    public function test_get_nugget_passes_an_unusable_answer_through(): void {
        $course = $this->log_in_as_teacher_with_clean_caches();
        $naas = $this->routing_client(function (): string {
            return '{"error":"gone"}';
        });
        $proxy = $this->proxy_using($naas);

        $this->assertSame('{"error":"gone"}', $proxy::execute($course->id, 'n-1'));
        $this->assertSame('{"error":"gone"}', $proxy::execute($course->id, 'n-1'));
        $this->assertCount(2, $naas->calls);
    }

    /**
     * Log in as an editing teacher of a fresh course, with an empty Nugget cache.
     *
     * @return \stdClass The course.
     */
    private function log_in_as_teacher_with_clean_caches(): \stdClass {
        $this->resetAfterTest(true);
        set_config('naas_endpoint', 'https://api.naas.example/api', 'naas');
        \mod_naas\nugget_cache::purge();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);
        return $course;
    }

    /**
     * Name of a get_nugget subclass whose NaaS client is $client.
     *
     * @param \mod_naas\naas_client $client
     * @return string
     */
    private function proxy_using(\mod_naas\naas_client $client): string {
        $proxy = new class extends get_nugget {
            use injected_naas_client;
        };
        return $proxy::using($client);
    }
}

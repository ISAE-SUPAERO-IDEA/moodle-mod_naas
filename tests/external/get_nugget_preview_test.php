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
 * Tests for the mod_naas_get_nugget_preview web service.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\external;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/naas_client_stubs.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\get_nugget_preview::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\external\naas_external_base::class)]
/**
 * Tests for get_nugget_preview: capability, version id check and the URL allowed in the preview iframe.
 *
 * NaaS is never reached: the NaaS client is injected through proxy_using().
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\external\get_nugget_preview
 * @covers \mod_naas\external\naas_external_base
 */
final class get_nugget_preview_test extends \advanced_testcase {
    use naas_client_stubs;

    /**
     * A student lacks the addinstance capability.
     */
    public function test_get_nugget_preview_requires_capability(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        get_nugget_preview::execute($course->id, 'valid-uuid-123');
    }

    /**
     * A teacher reaches NaaS, whose failure is reported.
     */
    public function test_get_nugget_preview_executes(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);
        $proxy = $this->proxy_using($this->failing_client());

        $this->expectException(\moodle_exception::class);
        $proxy::execute($course->id, 'valid-uuid-123');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('preview_url_provider')]
    /**
     * The preview URL ends up in an iframe: only a web page URL may come back.
     *
     * @dataProvider preview_url_provider
     * @param string $previewurl URL NaaS returns.
     * @param bool $allowed
     */
    public function test_get_nugget_preview_only_returns_a_web_page_url(string $previewurl, bool $allowed): void {
        $this->resetAfterTest(true);
        set_config('naas_endpoint', 'http://naas.local/api', 'naas');
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);
        $proxy = $this->proxy_using($this->answering_client(json_encode(['payload' => $previewurl])));

        if (!$allowed) {
            $this->expectException(\moodle_exception::class);
        }
        $result = json_decode($proxy::execute($course->id, 'valid-uuid-123'), true);
        $this->assertSame($previewurl, $result['payload']);
    }

    /**
     * Preview URLs NaaS may return.
     *
     * @return array
     */
    public static function preview_url_provider(): array {
        return [
            'https player' => ['https://player.naas.example/preview/abc', true],
            'local NaaS over http' => ['http://naas.local/preview/abc', true],
            'http elsewhere' => ['http://evil.example/preview', false],
            'javascript' => ['javascript:alert(document.cookie)', false],
            'data' => ['data:text/html,<script>alert(1)</script>', false],
        ];
    }

    /**
     * A versionId with path-traversal characters must be rejected.
     */
    public function test_get_nugget_preview_path_traversal_rejected(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\invalid_parameter_exception::class);
        get_nugget_preview::execute($course->id, '../../etc/passwd');
    }

    /**
     * Name of a get_nugget_preview subclass whose NaaS client is $client.
     *
     * @param \mod_naas\naas_client $client
     * @return string
     */
    private function proxy_using(\mod_naas\naas_client $client): string {
        $proxy = new class extends get_nugget_preview {
            use injected_naas_client;
        };
        return $proxy::using($client);
    }
}

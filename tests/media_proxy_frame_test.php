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
 * Tests for the iframe URL check, the default HTTP client and the access check of the media proxy.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\media_proxy::class)]
/**
 * Tests for media_proxy::is_frame_url_allowed(), make_curl(), SVG sniffing and the capability check.
 *
 * Nothing leaves the test site: curl::mock_response() answers Moodle's curl client.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_naas\media_proxy
 */
final class media_proxy_frame_test extends \advanced_testcase {
    /** Configured NaaS API URL in these tests. */
    private const ENDPOINT = 'https://api.naas.example/api';

    /** A 1x1 transparent PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    /**
     * Isolate config and caches.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('naas_endpoint', self::ENDPOINT, 'naas');
        \cache::make('mod_naas', media_proxy::CACHE_AREA)->purge();
    }

    /**
     * Which preview URLs may be loaded in an iframe.
     *
     * @dataProvider frame_url_provider
     * @param string $url
     * @param string $endpoint
     * @param bool $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('frame_url_provider')]
    public function test_is_frame_url_allowed(string $url, string $endpoint, bool $expected): void {
        $this->assertSame($expected, media_proxy::is_frame_url_allowed($url, $endpoint));
    }

    /**
     * Preview URLs, the configured NaaS URL, and whether the URL may be framed.
     *
     * @return array
     */
    public static function frame_url_provider(): array {
        return [
            'https anywhere' => [' https://player.example/v/1 ', self::ENDPOINT, true],
            'no host' => ['/relative/path', self::ENDPOINT, false],
            'javascript' => ['javascript:alert(1)', self::ENDPOINT, false],
            'user info' => ['https://user@player.example/', self::ENDPOINT, false],
            'password' => ['https://:secret@player.example/', self::ENDPOINT, false],
            'http on the NaaS host' => ['http://naas.local:8080/preview', 'http://NAAS.local/api', true],
            'http on another host' => ['http://other.local/preview', 'http://naas.local/api', false],
            'http over an https NaaS' => ['http://api.naas.example/preview', self::ENDPOINT, false],
            'http with no NaaS URL' => ['http://naas.local/preview', '', false],
        ];
    }

    /**
     * A NaaS URL that is not http(s) is refused.
     */
    public function test_structure_source_refuses_an_unusable_naas_url(): void {
        $this->expectException(\invalid_parameter_exception::class);
        media_proxy::structure_source('ftp://api.naas.example', 'isae', 'thumbnail');
    }

    /**
     * Without an override, images are fetched with Moodle's curl client and then cached.
     */
    public function test_fetch_uses_moodle_curl_and_caches_the_image(): void {
        \curl::mock_response(base64_decode(self::PNG));
        $url = self::ENDPOINT . '/thumbnails/structure/isae/thumbnail';

        $image = media_proxy::fetch($url);
        $again = media_proxy::fetch($url);

        $this->assertSame(['image/png', 'png'], [$image['mime'], $image['extension']]);
        $this->assertSame(base64_decode(self::PNG), $image['bytes']);
        $this->assertSame($image, $again, 'The second read is served from the cache, with no response left to mock.');
    }

    /**
     * An SVG that file sniffing reads as plain text is still recognised as SVG.
     */
    public function test_fetch_recognises_an_svg_that_looks_like_text(): void {
        $svg = '<!-- logo --><svg xmlns="http://www.w3.org/2000/svg" width="1"></svg>';
        \curl::mock_response($svg);

        $image = media_proxy::fetch(self::ENDPOINT . '/thumbnails/structure/isae/banner');

        $this->assertSame(['image/svg+xml', 'svg'], [$image['mime'], $image['extension']]);
    }

    /**
     * A user in the course without the view or add capability gets no image.
     */
    public function test_resolve_request_refuses_a_user_without_the_capabilities(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $coursecontext = \context_course::instance($course->id);
        assign_capability('mod/naas:view', CAP_PROHIBIT, $studentrole, $coursecontext->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($user);

        try {
            media_proxy::resolve_request((int) $course->id, 'isae', 'thumbnail', '', 0, 0);
            $this->fail('A user without the capabilities must be refused');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
    }
}

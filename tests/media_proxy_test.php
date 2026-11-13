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
 * Tests for {@see \mod_naas\media_proxy}.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

use advanced_testcase;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\media_proxy::class)]
/**
 * Tests for the NaaS image proxy behind media.php.
 *
 * @covers \mod_naas\media_proxy
 */
final class media_proxy_test extends advanced_testcase {
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
    }

    /**
     * A media_proxy whose HTTP client returns a fixed answer and counts the calls.
     *
     * @param string $body
     * @param int $code
     * @return string Class name to call statically.
     */
    private function proxy_answering(string $body, int $code = 200): string {
        $proxy = new class extends media_proxy {
            /** @var object|null */
            public static $curl = null;

            /**
             * Return the stub client.
             *
             * @return object
             */
            protected static function make_curl() {
                return self::$curl;
            }
        };
        $class = get_class($proxy);
        $class::$curl = new class ($body, $code) {
            /** @var string */
            private $body;
            /** @var int */
            private $code;
            /** @var string[] URLs requested. */
            public $requested = [];

            /**
             * Constructor.
             *
             * @param string $body
             * @param int $code
             */
            public function __construct(string $body, int $code) {
                $this->body = $body;
                $this->code = $code;
            }

            /**
             * Ignore options.
             *
             * @param array $options
             */
            public function setopt($options) {
            }

            /**
             * Record the URL and return the body.
             *
             * @param string $url
             * @return string
             */
            public function get($url) {
                $this->requested[] = $url;
                return $this->body;
            }

            /**
             * HTTP status.
             *
             * @return array
             */
            public function get_info() {
                return ['http_code' => $this->code];
            }

            /**
             * No transport error.
             *
             * @return int
             */
            public function get_errno() {
                return 0;
            }
        };
        return $class;
    }

    public function test_structure_source_builds_the_naas_url(): void {
        $this->assertSame(
            'https://api.naas.example/api/thumbnails/structure/isae-supaero/banner',
            media_proxy::structure_source(self::ENDPOINT, 'isae-supaero', 'banner')
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalid_structure_provider')]
    /**
     * Structure ids and variants outside the allowed set never reach NaaS.
     *
     * @dataProvider invalid_structure_provider
     * @param string $id
     * @param string $variant
     */
    public function test_structure_source_rejects_bad_ids_and_variants(string $id, string $variant): void {
        $this->expectException(\invalid_parameter_exception::class);
        media_proxy::structure_source(self::ENDPOINT, $id, $variant);
    }

    /**
     * Structure ids and variants that must not reach NaaS.
     *
     * @return array
     */
    public static function invalid_structure_provider(): array {
        return [
            'path traversal' => ['../auth', 'thumbnail'],
            'empty id' => ['', 'thumbnail'],
            'unknown variant' => ['isae', 'original'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('accepted_source_provider')]
    /**
     * Image URLs on the NaaS host are accepted and normalised.
     *
     * @dataProvider accepted_source_provider
     * @param string $src
     * @param string $expected
     */
    public function test_check_source_accepts_images_on_the_naas_host(string $src, string $expected): void {
        $this->assertSame($expected, media_proxy::check_source($src, self::ENDPOINT));
    }

    /**
     * Image URLs that stay on the NaaS host.
     *
     * @return array
     */
    public static function accepted_source_provider(): array {
        return [
            'absolute' => [
                'https://api.naas.example/api/thumbnails/nugget/n1/thumbnail',
                'https://api.naas.example/api/thumbnails/nugget/n1/thumbnail',
            ],
            'host case and default port' => [
                'https://API.naas.example:443/files/a.png',
                'https://api.naas.example/files/a.png',
            ],
            'root-relative' => ['/files/logo.png', 'https://api.naas.example/files/logo.png'],
            'relative to the API URL' => ['thumbnails/x.jpg', 'https://api.naas.example/api/thumbnails/x.jpg'],
            'size kept' => [
                'https://api.naas.example/api/t/n1?width=700&height=394',
                'https://api.naas.example/api/t/n1?width=700&height=394',
            ],
            'thumbnail hash kept' => [
                'https://api.naas.example/api/thumbnails/nugget/n1/thumbnail?hash=e8391083bd0e91df303f55bb2c294194',
                'https://api.naas.example/api/thumbnails/nugget/n1/thumbnail?hash=e8391083bd0e91df303f55bb2c294194',
            ],
            'empty thumbnail hash dropped' => [
                'https://api.naas.example/api/thumbnails/nugget/n1/thumbnail?hash=',
                'https://api.naas.example/api/thumbnails/nugget/n1/thumbnail',
            ],
            'hash and size kept' => [
                'https://api.naas.example/api/t/n1?hash=abc&width=700',
                'https://api.naas.example/api/t/n1?width=700&hash=abc',
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('refused_source_provider')]
    /**
     * Any other URL is refused before a request is made.
     *
     * @dataProvider refused_source_provider
     * @param string $src
     */
    public function test_check_source_refuses_anything_else(string $src): void {
        $this->expectException(\invalid_parameter_exception::class);
        media_proxy::check_source($src, self::ENDPOINT);
    }

    /**
     * URLs media.php must never fetch (SSRF, traversal, smuggled parameters).
     *
     * @return array
     */
    public static function refused_source_provider(): array {
        return [
            'other host' => ['https://evil.example/x.png'],
            'look-alike host' => ['https://api.naas.example.evil.example/x.png'],
            'other scheme' => ['http://api.naas.example/api/x.png'],
            'other port' => ['https://api.naas.example:8443/x.png'],
            'internal address' => ['http://169.254.169.254/latest/meta-data'],
            'user info' => ['https://user:pass@api.naas.example/x.png'],
            'traversal' => ['https://api.naas.example/api/../admin'],
            'encoded traversal' => ['https://api.naas.example/api/%2e%2e/admin'],
            'other query' => ['https://api.naas.example/api/x.png?token=abc'],
            'non-numeric size' => ['https://api.naas.example/api/x.png?width=big'],
            'hash with other characters' => ['https://api.naas.example/api/x.png?hash=a%26token%3Dabc'],
            'hash as an array' => ['https://api.naas.example/api/x.png?hash[]=abc'],
            'over-long hash' => ['https://api.naas.example/api/x.png?hash=' . str_repeat('a', 129)],
            'file scheme' => ['file:///etc/passwd'],
            'javascript' => ['javascript:alert(1)'],
            'empty' => [''],
        ];
    }

    public function test_with_size_caps_the_dimensions(): void {
        $this->assertSame(
            'https://api.naas.example/a.png?width=2000&height=394',
            media_proxy::with_size('https://api.naas.example/a.png?width=1', 99999, 394)
        );
        $this->assertSame('https://api.naas.example/a.png', media_proxy::with_size('https://api.naas.example/a.png', 0, 0));
    }

    public function test_with_size_keeps_the_thumbnail_hash(): void {
        $this->assertSame(
            'https://api.naas.example/t?width=700&height=394&hash=abc',
            media_proxy::with_size('https://api.naas.example/t?hash=abc&width=1', 700, 394)
        );
        $this->assertSame(
            'https://api.naas.example/t?hash=abc',
            media_proxy::with_size('https://api.naas.example/t?hash=abc', 0, 0)
        );
    }

    public function test_fetch_returns_and_caches_an_image(): void {
        $proxy = $this->proxy_answering(base64_decode(self::PNG));
        $url = 'https://api.naas.example/api/a.png';

        $image = $proxy::fetch($url);
        $this->assertSame('image/png', $image['mime']);
        $this->assertSame('png', $image['extension']);

        $again = $proxy::fetch($url);
        $this->assertSame($image['bytes'], $again['bytes']);
        $this->assertCount(1, $proxy::$curl->requested, 'The second request must come from the cache.');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unusable_answer_provider')]
    /**
     * Only an image answer from NaaS is accepted.
     *
     * @dataProvider unusable_answer_provider
     * @param string $body
     * @param int $code
     */
    public function test_fetch_refuses_anything_but_an_image(string $body, int $code): void {
        $proxy = $this->proxy_answering($body, $code);
        $this->expectException(\moodle_exception::class);
        $proxy::fetch('https://api.naas.example/api/a.png');
    }

    /**
     * NaaS answers that must not be sent to the browser.
     *
     * @return array
     */
    public static function unusable_answer_provider(): array {
        return [
            'html page' => ['<html><script>alert(1)</script></html>', 200],
            'json error' => ['{"error":"not found"}', 200],
            'not found' => [base64_decode(self::PNG), 404],
            'empty' => ['', 200],
        ];
    }

    public function test_svg_is_served_with_a_sandbox(): void {
        $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>';
        $proxy = $this->proxy_answering($svg);

        $image = $proxy::fetch('https://api.naas.example/api/logo.svg');

        $this->assertSame('image/svg+xml', $image['mime']);
        $headers = implode("\n", media_proxy::security_headers($image['mime']));
        $this->assertStringContainsString('sandbox', $headers);
        $this->assertStringContainsString('nosniff', $headers);
        $this->assertStringNotContainsString('sandbox', implode("\n", media_proxy::security_headers('image/png')));
    }

    public function test_resolve_request_serves_an_enrolled_user(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        $proxy = $this->proxy_answering(base64_decode(self::PNG));

        $image = $proxy::resolve_request((int) $course->id, 'isae-supaero', 'thumbnail', '', 0, 0);

        $this->assertSame('image/png', $image['mime']);
        $this->assertSame(
            ['https://api.naas.example/api/thumbnails/structure/isae-supaero/thumbnail'],
            $proxy::$curl->requested
        );
    }

    public function test_resolve_request_refuses_a_user_outside_the_course(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->setUser($this->getDataGenerator()->create_user());
        $proxy = $this->proxy_answering(base64_decode(self::PNG));

        try {
            $proxy::resolve_request((int) $course->id, 'isae-supaero', 'thumbnail', '', 0, 0);
            $this->fail('A user who cannot enter the course must not get NaaS images');
        } catch (\moodle_exception $e) {
            $this->assertSame([], $proxy::$curl->requested);
        }
    }

    public function test_resolve_request_refuses_a_foreign_url_for_an_enrolled_user(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        $proxy = $this->proxy_answering(base64_decode(self::PNG));

        try {
            $proxy::resolve_request((int) $course->id, '', '', 'https://evil.example/x.png', 0, 0);
            $this->fail('Expected invalid_parameter_exception');
        } catch (\invalid_parameter_exception $e) {
            $this->assertSame([], $proxy::$curl->requested);
        }
    }
}

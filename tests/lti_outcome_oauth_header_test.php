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
 * Tests the Authorization header checks of {@see \mod_naas\lti_outcome_oauth::verify()}.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\lti_outcome_oauth::class)]
/**
 * Headers with missing parameters, another consumer key or a stale timestamp are refused.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_naas\lti_outcome_oauth
 */
final class lti_outcome_oauth_header_test extends \advanced_testcase {
    /** @var string */
    private const KEY = 'header-key';
    /** @var string */
    private const SECRET = 'header-secret';
    /** @var string */
    private const URL = 'https://moodle.example.test/mod/naas/outcome.php';
    /** @var string */
    private const BODY = '<imsx_POXEnvelopeRequest/>';

    /**
     * Build an OAuth header from parameters, as sign() would format it.
     *
     * @param array $params
     * @return string
     */
    private function header(array $params): string {
        $parts = [];
        foreach ($params as $name => $value) {
            $parts[] = $name . '="' . rawurlencode((string) $value) . '"';
        }
        return 'OAuth realm="",' . implode(',', $parts);
    }

    /**
     * Valid OAuth parameters for BODY.
     *
     * @return array
     */
    private function params(): array {
        return [
            'oauth_consumer_key' => self::KEY,
            'oauth_body_hash' => base64_encode(sha1(self::BODY, true)),
            'oauth_signature' => 'c2lnbmF0dXJl',
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => time(),
            'oauth_nonce' => 'nonce-' . random_string(8),
            'oauth_version' => '1.0',
        ];
    }

    /**
     * Assert that verify() refuses a header.
     *
     * @param string $header
     * @return void
     */
    private function assert_refused(string $header): void {
        try {
            lti_outcome_oauth::verify(self::KEY, self::SECRET, self::BODY, $header, self::URL);
            $this->fail('The header must be refused');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:oauth_invalid', $e->errorcode);
        }
    }

    /**
     * Every required OAuth parameter must be present.
     */
    public function test_verify_refuses_missing_oauth_parameters(): void {
        $this->resetAfterTest(true);
        foreach (['oauth_consumer_key', 'oauth_body_hash', 'oauth_signature', 'oauth_timestamp', 'oauth_nonce'] as $name) {
            $params = $this->params();
            unset($params[$name]);
            $this->assert_refused($this->header($params));
        }
    }

    /**
     * A header signed for another consumer key is refused.
     */
    public function test_verify_refuses_another_consumer_key(): void {
        $this->resetAfterTest(true);
        $params = $this->params();
        $params['oauth_consumer_key'] = 'someone-else';
        $this->assert_refused($this->header($params));

        // The same refusal for a genuine signature made with another key.
        $this->assert_refused(lti_outcome_oauth::sign('someone-else', self::SECRET, self::BODY, self::URL));
    }

    /**
     * Timestamps outside the five-minute window are refused, before and after now.
     */
    public function test_verify_refuses_timestamps_outside_the_window(): void {
        $this->resetAfterTest(true);
        foreach ([-1, 1] as $direction) {
            $params = $this->params();
            $params['oauth_timestamp'] = time() + $direction * (lti_outcome_oauth::TIMESTAMP_THRESHOLD + 60);
            $this->assert_refused($this->header($params));
        }
    }

    /**
     * A body hash that does not match the body is refused before the signature is checked.
     */
    public function test_verify_refuses_body_hash_mismatch(): void {
        $this->resetAfterTest(true);
        $params = $this->params();
        $params['oauth_body_hash'] = base64_encode(sha1('another body', true));
        $this->assert_refused($this->header($params));
    }
}

<?php
// This file is part of Moodle - http://moodle.org
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
 * OAuth 1.0 body-hash helpers for LTI Basic Outcomes (replaceResult).
 *
 * @package   mod_naas
 * @copyright 2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/lti/OAuth.php');
require_once($CFG->dirroot . '/mod/lti/TrivialStore.php');

use moodle\mod\lti;

/**
 * Sign and verify LTI Basic Outcomes POX posts (OAuth body hash).
 *
 * @package   mod_naas
 * @copyright 2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lti_outcome_oauth {
    /** @var int Reject timestamps older than five minutes (OAuth / LTI default). */
    public const TIMESTAMP_THRESHOLD = 300;

    /**
     * Build an Authorization header for a POX body.
     *
     * @param string $key Consumer key
     * @param string $secret Consumer secret
     * @param string $body Raw XML body
     * @param string $url Absolute outcome service URL (may include query string)
     * @param string $method HTTP method
     * @return string Full "Authorization: OAuth …" header line
     */
    public static function sign(string $key, string $secret, string $body, string $url, string $method = 'POST'): string {
        $consumer = new lti\OAuthConsumer($key, $secret);
        $params = [
            'oauth_body_hash' => base64_encode(sha1($body, true)),
        ];
        $request = lti\OAuthRequest::from_consumer_and_token($consumer, null, $method, $url, $params);
        $request->sign_request(new lti\OAuthSignatureMethod_HMAC_SHA1(), $consumer, null);
        return $request->to_header();
    }

    /**
     * Verify an Authorization header against the body and shared secret.
     *
     * @param string $key Expected consumer key
     * @param string $secret Shared secret
     * @param string $body Raw request body
     * @param string $authorizationheader Value of the Authorization header (with or without "Authorization: ")
     * @param string $url Absolute request URL as seen by Moodle
     * @param string $method HTTP method
     * @return void
     * @throws \moodle_exception
     */
    public static function verify(
        string $key,
        string $secret,
        string $body,
        string $authorizationheader,
        string $url,
        string $method = 'POST'
    ): void {
        if ($authorizationheader === '' || stripos($authorizationheader, 'OAuth ') === false) {
            throw new \moodle_exception('error:oauth_invalid', 'naas');
        }

        // Accept either the raw header value or the "Authorization: …" form produced by sign().
        if (stripos($authorizationheader, 'Authorization:') === 0) {
            $authorizationheader = trim(substr($authorizationheader, strlen('Authorization:')));
        }

        $oauthparams = lti\OAuthUtil::split_header($authorizationheader);
        if (
            empty($oauthparams['oauth_consumer_key']) || empty($oauthparams['oauth_body_hash'])
            || empty($oauthparams['oauth_signature']) || empty($oauthparams['oauth_timestamp'])
            || empty($oauthparams['oauth_nonce'])
        ) {
            throw new \moodle_exception('error:oauth_invalid', 'naas');
        }

        if ((string) $oauthparams['oauth_consumer_key'] !== (string) $key) {
            throw new \moodle_exception('error:oauth_invalid', 'naas');
        }

        $expectedhash = base64_encode(sha1($body, true));
        if (!hash_equals($expectedhash, $oauthparams['oauth_body_hash'])) {
            throw new \moodle_exception('error:oauth_invalid', 'naas');
        }

        $timestamp = (int) $oauthparams['oauth_timestamp'];
        if (abs(time() - $timestamp) > self::TIMESTAMP_THRESHOLD) {
            throw new \moodle_exception('error:oauth_invalid', 'naas');
        }

        $store = new lti\TrivialOAuthDataStore();
        $store->add_consumer($key, $secret);
        $server = new lti\OAuthServer($store);
        $server->add_signature_method(new lti\OAuthSignatureMethod_HMAC_SHA1());

        // Merge query-string params into the OAuth parameter set (same as from_consumer_and_token).
        $params = $oauthparams;
        $parts = parse_url($url);
        if (!empty($parts['query'])) {
            $params = array_merge(lti\OAuthUtil::parse_parameters($parts['query']), $params);
        }

        $request = new lti\OAuthRequest($method, $url, $params);
        try {
            $server->verify_request($request);
        } catch (\Exception $e) {
            throw new \moodle_exception('error:oauth_invalid', 'naas');
        }

        // Only a correctly signed request may consume a nonce, so unsigned posts cannot fill the cache.
        self::assert_nonce_unused($key, $oauthparams['oauth_nonce']);
    }

    /**
     * Reject a replayed oauth_nonce within the timestamp window, then remember it.
     *
     * The nonce is chosen by the sender and may contain any character, while the
     * cache uses simple keys (a-z, 0-9, _), so it is stored under a hash.
     *
     * @param string $key Consumer key the nonce belongs to
     * @param string $nonce
     * @return void
     * @throws \moodle_exception
     */
    private static function assert_nonce_unused(string $key, string $nonce): void {
        $cache = \cache::make('mod_naas', 'oauth_nonces');
        $cachekey = 'n_' . sha1($key . "\n" . $nonce);
        if ($cache->get($cachekey) !== false) {
            throw new \moodle_exception('error:oauth_invalid', 'naas');
        }
        $cache->set($cachekey, 1);
    }
}

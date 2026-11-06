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
 * Serve NaaS images (Nugget thumbnails, producer logos and banners) through Moodle.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

/**
 * Validates, fetches and caches NaaS images for media.php.
 *
 * The browser never talks to NaaS: it asks media.php, which only fetches URLs
 * on the configured NaaS host and only returns images.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class media_proxy {
    /** MUC area name from db/caches.php. */
    public const CACHE_AREA = 'media';
    /** Structure image variants NaaS serves under /thumbnails/structure/{id}/. */
    public const VARIANTS = ['thumbnail', 'banner'];
    /** Largest image accepted from NaaS, in bytes. */
    public const MAX_BYTES = 5 * 1024 * 1024;
    /** Largest width or height a caller may ask NaaS to resize to. */
    public const MAX_DIMENSION = 2000;
    /** Seconds browsers may keep an image. */
    public const BROWSER_TTL = 86400;
    /** Accepted image types and the file extension sent to the browser. */
    public const MIME_TYPES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];
    /** Structure ids, same rule as the proxy web services. */
    private const ID_PATTERN = '/^[a-zA-Z0-9_\-]{1,128}$/';

    /**
     * Check access, then return the image a media.php request asks for.
     *
     * @param int $courseid Course the widget is shown in.
     * @param string $structure Structure id, for a structure thumbnail or banner.
     * @param string $variant 'thumbnail' or 'banner' (structure images only).
     * @param string $src NaaS image URL, for any other image.
     * @param int $width Requested width (0 = original).
     * @param int $height Requested height (0 = original).
     * @return array ['bytes' => string, 'mime' => string, 'extension' => string]
     * @throws \require_login_exception|\required_capability_exception When the user may not see NaaS images here.
     * @throws \invalid_parameter_exception When the request does not name an allowed image.
     * @throws \moodle_exception When NaaS does not return a usable image.
     */
    public static function resolve_request(
        int $courseid,
        string $structure,
        string $variant,
        string $src,
        int $width,
        int $height
    ): array {
        $course = get_course($courseid);
        require_login($course, false, null, false, true);
        $context = \context_course::instance($course->id);
        if (!has_any_capability(['mod/naas:view', 'mod/naas:addinstance'], $context)) {
            throw new \required_capability_exception($context, 'mod/naas:view', 'nopermissions', '');
        }
        // Pages load many images at once: do not serialise them on the session lock.
        \core\session\manager::write_close();

        $endpoint = (string) get_config('naas', 'naas_endpoint');
        $url = $structure !== ''
            ? self::structure_source($endpoint, $structure, $variant)
            : self::check_source($src, $endpoint);
        return self::fetch(self::with_size($url, $width, $height));
    }

    /**
     * NaaS URL of a structure's default thumbnail or banner.
     *
     * @param string $endpoint Configured NaaS API URL.
     * @param string $structureid
     * @param string $variant
     * @return string
     * @throws \invalid_parameter_exception
     */
    public static function structure_source(string $endpoint, string $structureid, string $variant): string {
        if (!preg_match(self::ID_PATTERN, $structureid)) {
            throw new \invalid_parameter_exception(get_string('error:invalid_param', 'naas', 'structure'));
        }
        if (!in_array($variant, self::VARIANTS, true)) {
            throw new \invalid_parameter_exception(get_string('error:invalid_param', 'naas', 'variant'));
        }
        $base = self::endpoint_parts($endpoint);
        return $base['origin'] . $base['path'] . '/thumbnails/structure/' . $structureid . '/' . $variant;
    }

    /**
     * Accept an image URL only when it is on the configured NaaS host.
     *
     * Relative URLs are resolved against the NaaS API URL. User info, other hosts,
     * other ports and path traversal are refused. A query string may only carry
     * width and height.
     *
     * @param string $src
     * @param string $endpoint Configured NaaS API URL.
     * @return string Normalised absolute URL.
     * @throws \invalid_parameter_exception
     */
    public static function check_source(string $src, string $endpoint): string {
        $invalid = new \invalid_parameter_exception(get_string('error:invalid_param', 'naas', 'src'));
        $base = self::endpoint_parts($endpoint);
        $src = trim($src);
        if ($src === '' || strlen($src) > 2048) {
            throw $invalid;
        }
        if (!preg_match('#^[a-z][a-z0-9+.\-]*://#i', $src)) {
            // Relative to the NaaS API URL, as NaaS sometimes returns them.
            $src = $base['origin'] . (strpos($src, '/') === 0 ? '' : $base['path'] . '/') . $src;
        }
        $parts = parse_url($src);
        if ($parts === false || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw $invalid;
        }
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if ($scheme !== $base['scheme'] || $host !== $base['host'] || $port !== $base['port']) {
            throw $invalid;
        }
        $path = $parts['path'] ?? '';
        if (!preg_match('#^/[A-Za-z0-9/_\-.~%]*$#', $path) || preg_match('#(^|/)\.\.?(/|$)#', rawurldecode($path))) {
            throw $invalid;
        }
        $url = $base['origin'] . $path;
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
            $size = [];
            foreach ($query as $name => $value) {
                if (!in_array($name, ['width', 'height'], true) || !is_string($value) || !ctype_digit($value)) {
                    throw $invalid;
                }
                $size[$name] = (int) $value;
            }
            $url = self::with_size($url, $size['width'] ?? 0, $size['height'] ?? 0);
        }
        return $url;
    }

    /**
     * Add (or replace) the width and height NaaS should resize the image to.
     *
     * @param string $url URL with no query string, or only width and height.
     * @param int $width 0 to leave unset.
     * @param int $height 0 to leave unset.
     * @return string
     */
    public static function with_size(string $url, int $width, int $height): string {
        $size = [];
        if ($width > 0) {
            $size['width'] = min($width, self::MAX_DIMENSION);
        }
        if ($height > 0) {
            $size['height'] = min($height, self::MAX_DIMENSION);
        }
        if (!$size) {
            return $url;
        }
        $url = strtok($url, '?');
        // Moodle sets arg_separator.output to '&amp;': give the separator explicitly.
        return $url . '?' . http_build_query($size, '', '&');
    }

    /**
     * Fetch an image from NaaS, or from the cache.
     *
     * @param string $url Absolute URL already checked by structure_source() or check_source().
     * @return array ['bytes' => string, 'mime' => string, 'extension' => string]
     * @throws \moodle_exception When NaaS does not answer with an accepted image.
     */
    public static function fetch(string $url): array {
        $cache = \cache::make('mod_naas', self::CACHE_AREA);
        $key = sha1($url);
        $cached = $cache->get($key);
        if (is_array($cached) && isset($cached['bytes'], $cached['mime'])) {
            return $cached;
        }

        $curl = static::make_curl();
        $curl->setopt([
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_TIMEOUT' => 20,
            'CURLOPT_MAXFILESIZE' => self::MAX_BYTES,
            'CURLOPT_FOLLOWLOCATION' => false,
        ]);
        // The curl client returns false on a transport error: an empty string is refused below.
        $bytes = (string) $curl->get($url);
        $code = (int) ($curl->get_info()['http_code'] ?? 0);
        $usable = !$curl->get_errno() && $code === 200 && $bytes !== ''
            && strlen($bytes) <= self::MAX_BYTES;
        if (!$usable) {
            throw new \moodle_exception('error:naas_media_unavailable', 'naas');
        }
        $mime = self::sniff_mime($bytes);
        if (!isset(self::MIME_TYPES[$mime])) {
            throw new \moodle_exception('error:naas_media_unavailable', 'naas');
        }
        $image = ['bytes' => $bytes, 'mime' => $mime, 'extension' => self::MIME_TYPES[$mime]];
        $cache->set($key, $image);
        return $image;
    }

    /**
     * Whether a NaaS-supplied URL may be loaded in an iframe (Nugget preview).
     *
     * HTTPS anywhere, or the scheme and host of the configured NaaS API (local
     * development over HTTP). Never javascript:, data: or other schemes.
     *
     * @param string $url
     * @param string $endpoint Configured NaaS API URL.
     * @return bool
     */
    public static function is_frame_url_allowed(string $url, string $endpoint): bool {
        $parts = parse_url(trim($url));
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        if ($host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        if ($scheme === 'https') {
            return true;
        }
        try {
            $base = self::endpoint_parts($endpoint);
        } catch (\invalid_parameter_exception $e) {
            return false;
        }
        return $scheme === $base['scheme'] && $host === $base['host'];
    }

    /**
     * Headers for an image response. SVG gets a sandbox so a script in it can
     * never run, even when the URL is opened directly.
     *
     * @param string $mime
     * @return string[]
     */
    public static function security_headers(string $mime): array {
        $headers = ['X-Content-Type-Options: nosniff'];
        if ($mime === 'image/svg+xml') {
            $headers[] = "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox";
        }
        return $headers;
    }

    /**
     * Image type from the bytes themselves, not from what NaaS claims.
     *
     * @param string $bytes
     * @return string
     */
    private static function sniff_mime(string $bytes): string {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->buffer($bytes);
        $xmllike = in_array($mime, ['text/xml', 'application/xml', 'text/plain'], true);
        if ($xmllike && preg_match('/^\s*(<\?xml[^>]*>\s*)?(<!--.*?-->\s*)*<svg[\s>]/is', $bytes)) {
            return 'image/svg+xml';
        }
        return $mime;
    }

    /**
     * Scheme, host, port, origin and path of the configured NaaS API URL.
     *
     * @param string $endpoint
     * @return array
     * @throws \invalid_parameter_exception When no usable NaaS URL is configured.
     */
    private static function endpoint_parts(string $endpoint): array {
        $parts = parse_url(trim($endpoint));
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new \invalid_parameter_exception(get_string('error:invalid_param', 'naas', 'naas_endpoint'));
        }
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $default = ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);
        return [
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
            'origin' => $scheme . '://' . $host . ($default ? '' : ':' . $port),
            'path' => rtrim($parts['path'] ?? '', '/'),
        ];
    }

    /**
     * HTTP client, overridden in tests.
     *
     * @return \curl
     */
    protected static function make_curl() {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        return new \curl(['proxy' => true]);
    }
}

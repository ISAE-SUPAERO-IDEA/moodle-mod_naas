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
 * Site-level restrictions applied to teacher Nugget search.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

defined('MOODLE_INTERNAL') || die();

/**
 * Compose catalogue NQL and post-filter by the institute's access-licence rights.
 *
 * NaaS stores commercial (`co`/`nc`) and distribution (`uu`/`ru`) flags on each
 * Nugget's access licences, per institute. The plugin therefore filters on the
 * licence granted to `naas_structure_id`, not on Creative Commons card badges.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalogue_filters {
    /** No extra commercial-use restriction. */
    public const LICENSE_ALL = 'all';
    /** Require the commercial-use right (`co`). */
    public const LICENSE_COMMERCIAL = 'commercial';
    /** Require the non-commercial right (`nc`). */
    public const LICENSE_NONCOMMERCIAL = 'noncommercial';

    /** No extra distribution restriction. */
    public const ACCESS_ALL = 'all';
    /** Require unrestricted use (`uu`). */
    public const ACCESS_UNRESTRICTED = 'unrestricted';
    /** Require restricted use (`ru`). */
    public const ACCESS_RESTRICTED = 'restricted';

    /**
     * Merge the site NQL textarea and access-licence rights into search options.
     *
     * The `nql` value is left unencoded; {@see proxy_naas_api::search_url()}
     * encodes query parameters once.
     *
     * @param array $options
     * @param object $config
     * @return array
     */
    public static function apply(array $options, object $config): array {
        unset($options['license']);
        $clauses = [];
        $site = trim((string) ($config->naas_filter ?? ''));
        if ($site !== '') {
            $clauses[] = $site;
        }
        foreach (self::required_rights($config) as $right) {
            $clauses[] = "nugget:access_licences/*/rights = '" . $right . "'";
        }
        if ($clauses) {
            $options['nql'] = count($clauses) === 1
                ? $clauses[0]
                : '(' . implode(') AND (', $clauses) . ')';
        }
        return $options;
    }

    /**
     * Drop Nuggets whose access licence for this institute is not allowed.
     *
     * @param string $json Sanitised NaaS search body.
     * @param object $config
     * @return string
     */
    public static function constrain_search_json(string $json, object $config): string {
        if (!self::required_rights($config)) {
            return $json;
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return $json;
        }
        $hadpayload = isset($decoded['payload']) && is_array($decoded['payload']);
        $body = $hadpayload ? $decoded['payload'] : $decoded;
        if (!is_array($body) || !isset($body['items']) || !is_array($body['items'])) {
            return $json;
        }
        $filtered = [];
        foreach ($body['items'] as $item) {
            if (is_array($item) && self::nugget_matches($item, $config)) {
                $filtered[] = $item;
            }
        }
        $dropped = count($body['items']) - count($filtered);
        $body['items'] = $filtered;
        if (isset($body['results_count'])) {
            $body['results_count'] = max(count($filtered), (int) $body['results_count'] - $dropped);
        }
        if ($hadpayload) {
            $decoded['payload'] = $body;
            return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Whether this Nugget's licence for the configured institute matches the filter.
     *
     * @param array $nugget
     * @param object $config
     * @return bool
     */
    public static function nugget_matches(array $nugget, object $config): bool {
        $needed = self::required_rights($config);
        if (!$needed) {
            return true;
        }
        $rights = self::structure_rights($nugget, (string) ($config->naas_structure_id ?? ''));
        if ($rights === null) {
            return false;
        }
        foreach ($needed as $right) {
            if (!in_array($right, $rights, true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Access-licence rights required by the admin settings, in stable order.
     *
     * @param object|null $config
     * @return string[]
     */
    public static function required_rights(?object $config = null): array {
        $rights = [];
        $commercial = self::commercial_mode($config);
        if ($commercial === self::LICENSE_COMMERCIAL) {
            $rights[] = 'co';
        } else if ($commercial === self::LICENSE_NONCOMMERCIAL) {
            $rights[] = 'nc';
        }
        $access = self::access_mode($config);
        if ($access === self::ACCESS_UNRESTRICTED) {
            $rights[] = 'uu';
        } else if ($access === self::ACCESS_RESTRICTED) {
            $rights[] = 'ru';
        }
        return $rights;
    }

    /**
     * @param object|null $config
     * @return string
     */
    public static function commercial_mode(?object $config = null): string {
        return self::mode_from_config($config, 'naas_license_filter', [
            self::LICENSE_COMMERCIAL,
            self::LICENSE_NONCOMMERCIAL,
        ]);
    }

    /**
     * @param object|null $config
     * @return string
     */
    public static function access_mode(?object $config = null): string {
        return self::mode_from_config($config, 'naas_access_filter', [
            self::ACCESS_UNRESTRICTED,
            self::ACCESS_RESTRICTED,
        ]);
    }

    /**
     * Kept for callers that still use the old name.
     *
     * @param object|null $config
     * @return string
     */
    public static function license_mode(?object $config = null): string {
        return self::commercial_mode($config);
    }

    /**
     * @param object|null $config
     * @return string
     */
    public static function license_nql(?object $config = null): string {
        $clauses = [];
        foreach (self::required_rights($config) as $right) {
            $clauses[] = "nugget:access_licences/*/rights = '" . $right . "'";
        }
        if (!$clauses) {
            return '';
        }
        return count($clauses) === 1
            ? $clauses[0]
            : '(' . implode(') AND (', $clauses) . ')';
    }

    /**
     * Config payload for the Vue picker.
     *
     * @param object|null $config
     * @return array{commercial: string, access: string}
     */
    public static function widget_license_config(?object $config = null): array {
        return [
            'commercial' => self::commercial_mode($config),
            'access' => self::access_mode($config),
        ];
    }

    /**
     * @param object|null $config
     * @param string $name
     * @param string[] $allowed
     * @return string
     */
    private static function mode_from_config(?object $config, string $name, array $allowed): string {
        if ($config === null) {
            $raw = (string) get_config('naas', $name);
        } else {
            $raw = (string) ($config->{$name} ?? '');
        }
        if (in_array($raw, $allowed, true)) {
            return $raw;
        }
        return 'all';
    }

    /**
     * Rights on the access licence issued to this Moodle institute.
     *
     * @param array $nugget
     * @param string $structureid
     * @return string[]|null
     */
    private static function structure_rights(array $nugget, string $structureid): ?array {
        $licences = $nugget['access_licences'] ?? [];
        if (!is_array($licences)) {
            return null;
        }
        foreach ($licences as $licence) {
            if (!is_array($licence)) {
                continue;
            }
            if ($structureid !== '' && (string) ($licence['structure_id'] ?? '') !== $structureid) {
                continue;
            }
            $rights = $licence['rights'] ?? [];
            if (!is_array($rights)) {
                return [];
            }
            $clean = [];
            foreach ($rights as $right) {
                if (is_string($right) && $right !== '') {
                    $clean[] = $right;
                }
            }
            return $clean;
        }
        return null;
    }
}

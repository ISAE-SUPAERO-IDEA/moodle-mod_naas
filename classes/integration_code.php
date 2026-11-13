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

namespace mod_naas;

/**
 * Integration code a teacher types to select a Nugget, derived from its exact title.
 *
 * The NaaS author app computes the same code, so nothing is stored or exchanged:
 * first 4 bytes of SHA-256(title) as an unsigned big-endian integer, modulo 1000000,
 * zero-padded to 6 digits. Do not change it on one side only: every code already
 * sent would stop working.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class integration_code {
    /** Number of digits in a code. */
    const LENGTH = 6;

    /**
     * Whether the site requires a code to select a Nugget (admin setting).
     *
     * @return bool
     */
    public static function is_required(): bool {
        return !empty(get_config('naas', 'naas_require_integration_code'));
    }

    /**
     * Code of a Nugget title.
     *
     * @param string $title Exact name of the default version, not normalised
     * @return string Six digits
     */
    public static function from_title(string $title): string {
        $first32 = unpack('N', substr(hash('sha256', $title, true), 0, 4))[1];
        return str_pad((string) ($first32 % 1000000), self::LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Whether a typed code is the code of a title.
     *
     * @param string $title
     * @param string $code
     * @return bool
     */
    public static function matches(string $title, string $code): bool {
        if ($title === '' || !preg_match('/^\d{' . self::LENGTH . '}$/', $code)) {
            return false;
        }
        return hash_equals(self::from_title($title), $code);
    }

    /**
     * Public Nuggets (open educational resources) need no code.
     *
     * @param object|array $nugget Default-version payload
     * @return bool
     */
    public static function is_public($nugget): bool {
        $value = ((array) $nugget)['is_public'] ?? false;
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    /**
     * Check the code posted with the activity form.
     *
     * Nothing is checked when the setting is off, when no Nugget is posted, or when the
     * activity keeps the Nugget it already had.
     *
     * @param string $previousnuggetid Nugget saved before this save ("" for a new activity)
     * @param string $nuggetid Posted Nugget
     * @param string $code Posted code
     * @param naas_client|null $client NaaS client used when the Nugget is not cached (tests)
     * @return bool
     */
    public static function is_valid_selection(
        string $previousnuggetid,
        string $nuggetid,
        string $code,
        ?naas_client $client = null
    ): bool {
        if (!self::is_required() || $nuggetid === '' || $nuggetid === $previousnuggetid) {
            return true;
        }
        $nugget = self::load_nugget($nuggetid, $client);
        if ($nugget === null) {
            return false;
        }
        if (self::is_public($nugget)) {
            return true;
        }
        return self::matches((string) ($nugget['name'] ?? ''), $code);
    }

    /**
     * Default version of a Nugget: from the plugin cache, else from NaaS.
     *
     * @param string $nuggetid
     * @param naas_client|null $client
     * @return array|null Null when NaaS cannot be reached or does not know the Nugget
     */
    private static function load_nugget(string $nuggetid, ?naas_client $client): ?array {
        global $CFG;
        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        $cached = nugget_cache::get($nuggetid, $config);
        if ($cached !== null) {
            return $cached['document'];
        }
        try {
            $payload = ($client ?? naas_client::create($config))->get_nugget_data($nuggetid);
        } catch (\moodle_exception $e) {
            return null;
        }
        $nugget = json_decode(json_encode($payload), true);
        return is_array($nugget) && isset($nugget['name']) ? $nugget : null;
    }
}

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
 * Admin purge and rebuild of every NaaS cache the plugin keeps.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

/**
 * Reloads the NaaS caches, and only then drops the entries that are fetched again on demand.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cache_refresh {
    /**
     * Rebuild the catalogue and the search lists, then refresh names and Nugget documents.
     *
     * The rebuild overwrites the catalogue snapshot and the cached searches in place.
     * Names and Nugget documents are purged only once the catalogue was rebuilt, so
     * a slow or unreachable NaaS leaves every existing cache untouched. Nothing is
     * touched when the API URL is not saved, so a click on an unfinished settings
     * form cannot wipe a working catalogue.
     *
     * @return string User-facing success message.
     * @throws \moodle_exception cache_refresh_failed when NaaS could not rebuild the catalogue.
     */
    public static function full(): string {
        global $CFG;

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        if (empty($config->naas_endpoint)) {
            throw new \moodle_exception('cache_refresh_failed', 'naas');
        }

        \core_php_time_limit::raise(180);
        $started = time();

        static::make_refresh_task()->refresh(false);

        // The catalogue warm-up swallows NaaS errors: a snapshot written by this
        // run is the proof that NaaS answered.
        $snapshot = catalogue_cache::get();
        if ($snapshot === null || (int) ($snapshot['warmed_at'] ?? 0) < $started) {
            throw new \moodle_exception('cache_refresh_failed', 'naas');
        }

        // Fetched again on demand: drop them only now that the catalogue is fresh.
        \cache::make('mod_naas', vocabulary_lookup::CACHE_AREA)->purge();
        \cache::make('mod_naas', nugget_cache::CACHE_AREA)->purge();

        try {
            $naas = naas_client::create($config);
            person_lookup::warm_catalog($naas);
        } catch (\Throwable $e) {
            debugging('NAAS person catalog refresh failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return get_string('cache_refresh_success', 'naas');
    }

    /**
     * The task that rebuilds the catalogue and the search lists. Overridden in tests.
     *
     * @return \mod_naas\task\refresh_catalogue
     */
    protected static function make_refresh_task(): \mod_naas\task\refresh_catalogue {
        return new \mod_naas\task\refresh_catalogue();
    }
}

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
 * Clears the four MUC areas, then reloads them from NaaS.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cache_refresh {
    /**
     * Drop every cached catalogue, search, name, and Nugget document.
     */
    public static function purge_all(): void {
        \cache::make('mod_naas', vocabulary_lookup::CACHE_AREA)->purge();
        \cache::make('mod_naas', catalogue_cache::CACHE_AREA)->purge();
        \cache::make('mod_naas', search_cache::CACHE_AREA)->purge();
        \cache::make('mod_naas', nugget_cache::CACHE_AREA)->purge();
    }

    /**
     * Purge, then rebuild the catalogue, the search lists, and author names.
     *
     * Does nothing to the caches when the API URL is not saved, so a click
     * on an unfinished settings form cannot wipe a working catalogue.
     *
     * @return string User-facing success message.
     */
    public static function full(): string {
        global $CFG;

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        if (empty($config->naas_endpoint)) {
            throw new \moodle_exception('cache_refresh_failed', 'naas');
        }

        \core_php_time_limit::raise(180);
        self::purge_all();

        $task = new \mod_naas\task\refresh_catalogue();
        $task->refresh(false);

        if (catalogue_cache::get() === null) {
            throw new \moodle_exception('cache_refresh_failed', 'naas');
        }

        try {
            $naas = new naas_client($config);
            \mod_naas\external\proxy_naas_api::warm_person_catalog($naas);
        } catch (\Throwable $e) {
            debugging('NAAS person catalog refresh failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return get_string('cache_refresh_success', 'naas');
    }
}

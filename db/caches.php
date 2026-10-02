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
 * MUC cache definitions for the NaaS plugin.
 *
 * vocabulary_entries caches responses for get_domain, get_structure, and get_person.
 * These are stable vocabulary lookups that change rarely; a 24-hour TTL avoids
 * repeated round-trips to the NaaS API on every page load.
 *
 * catalogue_snapshot is the unfiltered search + producer list warmed when
 * Test connection succeeds. The search widget paints from it immediately.
 *
 * search_results caches one entry per canonical search query so a producer
 * section or a filtered list paints without a NaaS round-trip. Entries are
 * revalidated by the widget rather than expired by the clock, so the TTL is
 * only a backstop; \mod_naas\search_cache bounds the keyspace itself.
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    'vocabulary_entries' => [
        'mode'       => cache_store::MODE_APPLICATION,
        'ttl'        => 86400, // 24 hours.
        'simplekeys' => true,
        'simpledata' => true,
    ],
    'catalogue_snapshot' => [
        'mode'       => cache_store::MODE_APPLICATION,
        'ttl'        => 2592000, // 30 days; replaced on warm / landing search.
        'simplekeys' => true,
        'simpledata' => true,
    ],
    'search_results' => [
        'mode'       => cache_store::MODE_APPLICATION,
        'ttl'        => 604800, // 7 days; freshness comes from revalidation, not the clock.
        'simplekeys' => true,
        'simpledata' => true,
    ],
    'nugget_documents' => [
        'mode'       => cache_store::MODE_APPLICATION,
        'ttl'        => 604800, // 7 days; revalidated by the widget on every form open.
        'simplekeys' => true,
        'simpledata' => true,
    ],
];

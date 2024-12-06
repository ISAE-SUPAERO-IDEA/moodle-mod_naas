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
 * Folder module version information
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version        = 2024073004;                  // The current module version (Date: YYYYMMDDXX).
$plugin->requires       = 2022041900;                  // Requires this Moodle version.
$plugin->component      = 'mod_naas';                  // Full name of the plugin (used for diagnostics).
$plugin->cron           = 0;                           // Frequency of the plugin's cron task.
$plugin->maturity       = MATURITY_STABLE;             // Plugin thoroughly tested and considered stable for production.
$plugin->dependencies   = [
    'mod_url' => 20191030,
    'mod_lti' => 2022041900,
];

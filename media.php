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
 * Serve a NaaS image (Nugget thumbnail, producer logo or banner) through Moodle.
 *
 * Either media.php?course=ID&structure=STRUCTUREID&variant=thumbnail|banner
 * or media.php?course=ID&src=NAAS_IMAGE_URL, with optional w and h.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- media_proxy::resolve_request() runs require_login() for the course.
require(__DIR__ . '/../../config.php');

$courseid = required_param('course', PARAM_INT);
$structure = optional_param('structure', '', PARAM_ALPHANUMEXT);
$variant = optional_param('variant', 'thumbnail', PARAM_ALPHA);
$src = optional_param('src', '', PARAM_RAW_TRIMMED);
$width = optional_param('w', 0, PARAM_INT);
$height = optional_param('h', 0, PARAM_INT);

try {
    $image = \mod_naas\media_proxy::resolve_request($courseid, $structure, $variant, $src, $width, $height);
} catch (\invalid_parameter_exception | \moodle_exception $e) {
    // Access refused, unknown image or NaaS failure: an image tag only needs to know it failed.
    send_header_404();
    die();
}

foreach (\mod_naas\media_proxy::security_headers($image['mime']) as $header) {
    header($header);
}
send_file(
    $image['bytes'],
    'naas-image.' . $image['extension'],
    \mod_naas\media_proxy::BROWSER_TTL,
    0,
    true,
    false,
    $image['mime']
);

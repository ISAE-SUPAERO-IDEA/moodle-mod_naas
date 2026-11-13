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
 * Coverage information for the mod_naas component.
 *
 * Read by admin/tool/phpunit/cli/init.php when it generates mod/naas/phpunit.xml.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Coverage scope: the plugin classes and the root entry scripts.
 *
 * A named class rather than an anonymous one keeps local_moodlecheck happy
 * (https://github.com/moodlehq/moodle-local_moodlecheck/issues/50).
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_naas_coverage extends phpunit_coverage_info {
    /** @var array Folders relative to the plugin root to include in coverage generation. */
    protected $includelistfolders = [
        'classes',
    ];

    /** @var array Files relative to the plugin root to include in coverage generation. */
    protected $includelistfiles = [
        'lib.php',
        'index.php',
        'view.php',
        'launch.php',
        'mod_form.php',
        'outcome.php',
        'settings.php',
        'version.php',
    ];
}

return new mod_naas_coverage();

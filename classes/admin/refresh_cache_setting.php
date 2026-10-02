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
 * Display-only admin setting for the NaaS cache refresh control.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\admin;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * Renders the Refresh cache button and result region on the plugin settings page.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refresh_cache_setting extends \admin_setting {
    /**
     * Prepare the display-only refresh-cache control.
     *
     * @param string $name unique ascii name, plugin/setting
     * @param string $visiblename localised name
     * @param string $description localised long description
     */
    public function __construct($name, $visiblename, $description) {
        $this->nosave = true;
        parent::__construct($name, $visiblename, $description, '');
    }

    /**
     * Display-only control; always treated as set.
     *
     * @return bool
     */
    public function get_setting() {
        return true;
    }

    /**
     * Display-only control; always treated as set.
     *
     * @return bool
     */
    public function get_defaultsetting() {
        return true;
    }

    /**
     * Never persist a value.
     *
     * @param mixed $data unused
     * @return string empty string on success
     */
    public function write_setting($data) {
        return '';
    }

    /**
     * Render the button and result region, and load the AMD module.
     *
     * @param mixed $data unused
     * @param string $query search query to highlight
     * @return string HTML
     */
    public function output_html($data, $query = '') {
        global $OUTPUT, $PAGE;

        $PAGE->requires->js_call_amd('mod_naas/refresh_cache', 'init');

        $html = $OUTPUT->render_from_template('mod_naas/admin_test_connection', [
            'buttonid' => 'refreshcache',
            'resultid' => 'cache-refresh-result',
            'buttonlabel' => get_string('cache_refresh', 'naas'),
            'testinglabel' => get_string('cache_refresh_testing', 'naas'),
        ]);

        return format_admin_setting($this, $this->visiblename, $html, $this->description, false, '', null, $query);
    }
}

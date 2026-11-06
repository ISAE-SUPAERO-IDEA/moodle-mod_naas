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
 * Display-only admin setting: a button that calls a web service and shows the result.
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
 * Renders an action button and its result region on the plugin settings page.
 *
 * Used for "Test connection" and "Refresh cache": the button calls a web service
 * through the mod_naas/action_button AMD module, which reads everything it needs
 * from the button's data attributes.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class action_button_setting extends \admin_setting {
    /** @var array Button settings, see the constructor. */
    protected $action;

    /**
     * Prepare the display-only control.
     *
     * @param string $name unique ascii name, plugin/setting
     * @param string $visiblename localised name
     * @param string $description localised long description
     * @param array $action buttonid, resultid (HTML ids), methodname (web service),
     *     label, pendinglabel (localised button texts), successstring, failurestring
     *     (mod_naas string keys shown after the call)
     */
    public function __construct($name, $visiblename, $description, array $action) {
        foreach (['buttonid', 'resultid', 'methodname', 'label', 'pendinglabel', 'successstring', 'failurestring'] as $key) {
            if (!isset($action[$key]) || $action[$key] === '') {
                throw new \coding_exception("action_button_setting: missing '{$key}'");
            }
        }
        $this->action = $action;
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

        $PAGE->requires->js_call_amd('mod_naas/action_button', 'init', [$this->action['buttonid']]);

        $html = $OUTPUT->render_from_template('mod_naas/admin_action_button', $this->action);

        return format_admin_setting($this, $this->visiblename, $html, $this->description, false, '', null, $query);
    }
}

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
 * mod_naas view page output component.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\output;

defined('MOODLE_INTERNAL') || die();

use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Renderable and templatable component for the naas view.php page.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      mod_naas 1.0.0
 */
class view_page implements renderable, templatable {

    /** @var moodle_url The main course URL */
    public moodle_url $courseurl;

    /** @var string The localized "back to course" string */
    public string $backtocourse;

    /** @var stdClass|null The next activity details */
    public ?stdClass $nextactivity;

    /** @var string Pre-rendered widget HTML */
    public string $widgethtml;

    /**
     * Constructor.
     *
     * @param moodle_url $courseurl
     * @param string $backtocourse
     * @param stdClass|null $nextactivity
     * @param string $widgethtml
     */
    public function __construct(
        moodle_url $courseurl,
        string $backtocourse,
        ?stdClass $nextactivity,
        string $widgethtml
    ) {
        $this->courseurl = $courseurl;
        $this->backtocourse = $backtocourse;
        $this->nextactivity = $nextactivity;
        $this->widgethtml = $widgethtml;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output The renderer handling the export
     * @return stdClass Context available in the template
     */
    public function export_for_template(renderer_base $output): stdClass {
        $context = new stdClass();
        $context->courseurl = $this->courseurl->out(false);
        $context->backtocourse = $this->backtocourse;
        $context->widgethtml = $this->widgethtml;
        $context->hasnextactivity = false;

        if (!empty($this->nextactivity)) {
            $context->hasnextactivity = true;
            $context->nextactivityname = $this->nextactivity->name;

            // Re-parse the link to a proper moodle_url object to safely append parameters.
            $nexturl = new moodle_url($this->nextactivity->link);
            $nexturl->param('forceview', 1);
            $context->nextactivityurl = $nexturl->out(false);
        }

        return $context;
    }
}

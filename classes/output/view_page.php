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
 * Output for the NaaS view page.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\output;

use renderable;
use templatable;
use renderer_base;
use stdClass;
use moodle_url;

/**
 * Output for the NaaS view page.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view_page implements renderable, templatable {
    /** @var \mod_naas\output\widget The NaaS widget. */
    protected $widget;

    /** @var int The course ID. */
    protected $courseid;

    /**
     * Constructor.
     *
     * @param \mod_naas\output\widget $widget
     * @param int $courseid
     */
    public function __construct(widget $widget, $courseid) {
        $this->widget = $widget;
        $this->courseid = $courseid;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        $data = new stdClass();

        // Widget.
        $data->widget = $this->widget->export_for_template($output);

        // Back to course button.
        $courseurl = new moodle_url('/course/view.php', ['id' => $this->courseid]);
        $data->backcourseurl = $courseurl->out(false);
        $data->backcoursetext = get_string('back_to_course', 'naas');

        // Next activity.
        $nextactivity = \mod_naas\mod_util::get_next_activity_url();
        if ($nextactivity) {
            $data->nextactivity = new stdClass();
            $data->nextactivity->url = $nextactivity->link->out(false) . '&forceview=1';
            $data->nextactivity->name = $nextactivity->name;
        }

        return $data;
    }
}

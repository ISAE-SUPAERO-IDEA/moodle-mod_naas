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
 * mod_naas index page output component.
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
 * Renderable and templatable component for the naas index.php page.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      mod_naas 1.0.0
 */
class index_page implements renderable, templatable {

    /** @var moodle_url The main course URL */
    public moodle_url $courseurl;

    /** @var string The localized "back to course" string */
    public string $backtocourse;

    /** @var string The heading to display */
    public string $heading;

    /** @var bool Whether the course format uses sections */
    public bool $usesections;

    /** @var array List of row objects mapping to naas modules */
    public array $rows;

    /**
     * Constructor.
     *
     * @param moodle_url $courseurl
     * @param string $backtocourse
     * @param string $heading
     * @param bool $usesections
     * @param array $rows
     */
    public function __construct(
        moodle_url $courseurl,
        string $backtocourse,
        string $heading,
        bool $usesections,
        array $rows
    ) {
        $this->courseurl = $courseurl;
        $this->backtocourse = $backtocourse;
        $this->heading = $heading;
        $this->usesections = $usesections;
        $this->rows = $rows;
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
        $context->heading = $this->heading;
        $context->usesections = $this->usesections;
        $context->rows = $this->rows;
        return $context;
    }
}

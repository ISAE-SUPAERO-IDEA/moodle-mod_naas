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
 * mod_naas lti launch form output component.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use stdClass;
use templatable;

/**
 * Renderable and templatable component for the LTI launch form.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      mod_naas 1.0.0
 */
class lti_launch_form implements renderable, templatable {

    /** @var string The validated LTI launch URL */
    public string $launchurl;

    /** @var array Array of form fields (name, value) for the launch */
    public array $fields;

    /**
     * Constructor.
     *
     * @param string $launchurl
     * @param array $fields Array of objects/arrays containing 'name' and 'value'
     */
    public function __construct(string $launchurl, array $fields) {
        $this->launchurl = $launchurl;
        $this->fields = $fields;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param object $output The renderer handling the export
     * @return stdClass Context available in the template
     */
    public function export_for_template($output): stdClass {
        $context = new stdClass();
        // The launchurl is expected to be validated via clean_param before
        // instantiating this class, so it's safe to pass to the template here.
        $context->launchurl = $this->launchurl;
        $context->fields = $this->fields;
        return $context;
    }
}

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
 * Render the NaaS widget which allows to display a Nugget in Moodle.
 * @author Bruno Ilponse
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */
namespace mod_naas;

/**
 * Render the NaaS widget which allows to display a Nugget in Moodle.
 * @author Bruno Ilponse
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */
class naas_widget {
    /**
     * Render this NaaS widget as HTML
     * @param int $nuggetid
     * @param int $courseid
     * @param int $cmid
     * @param string $component
     * @return string
     */
    public static function naas_widget_html($nuggetid, $courseid, $cmid, $component): string {
        global $PAGE;

        $widget = new \mod_naas\output\widget($nuggetid, $courseid, $cmid, $component);
        $renderer = $PAGE->get_renderer('mod_naas');

        return $renderer->render($widget);
    }

    /**
     * Returns the grading options.
     * @return array int => lang string the options for calculating the NaaS grade
     *      from the individual attempt grades.
     */
    public static function naas_get_grading_options() {
        return [
            NAAS_GRADEHIGHEST => get_string('gradehighest', 'naas'),
            NAAS_ATTEMPTFIRST => get_string('attemptfirst', 'naas'),
            NAAS_ATTEMPTLAST  => get_string('attemptlast', 'naas'),
        ];
    }
}

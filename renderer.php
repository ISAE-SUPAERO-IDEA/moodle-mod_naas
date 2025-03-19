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
 * Moodle Nugget Plugin: Renderer
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2024  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Renderer for mod_naas
 *
 * @package mod_naas
 * @copyright (C) 2024  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_naas_renderer extends \plugin_renderer_base {

    /**
     * Renders an error message
     *
     * @param string $errormessage The error message to display
     * @return string HTML content for the error message
     */
    public function render_error_message(string $errormessage): string {
        $data = [
            'errormessage' => $errormessage
        ];
        return $this->render_from_template('mod_naas/error_message', $data);
    }

    /**
     * Renders the LTI launch form
     *
     * @param string $launchurl The URL to launch the LTI content
     * @param array $launchdata The data to include in the form
     * @param string $signature The OAuth signature
     * @return string HTML content for the LTI form
     */
    public function render_lti_form(string $launchurl, array $launchdata, string $signature): string {
        $data = [
            'launchurl' => $launchurl,
            'launchdata' => array_map(function($key, $value) {
                return [
                    'key' => $key,
                    'value' => $value
                ];
            }, array_keys($launchdata), array_values($launchdata)),
            'signature' => $signature
        ];
        return $this->render_from_template('mod_naas/lti_form', $data);
    }

    /**
     * Renders the NAAS widget
     *
     * @param string $widgetconfig The widget configuration JSON
     * @param string $widgetjsurl The URL to the widget JavaScript
     * @return string HTML content for the widget
     */
    public function render_naas_widget(string $widgetconfig, string $widgetjsurl): string {
        $data = [
            'widgetconfig' => $widgetconfig,
            'widgetjsurl' => $widgetjsurl
        ];
        return $this->render_from_template('mod_naas/naas_widget', $data);
    }

    /**
     * Renders a back to course button
     *
     * @param moodle_url $courseurl The URL to the course
     * @param string $buttontext The text for the button
     * @return string HTML content for the back button
     */
    public function render_back_to_course_button(moodle_url $courseurl, string $buttontext): string {
        $data = [
            'courseurl' => $courseurl->out(false),
            'buttontext' => $buttontext
        ];
        return $this->render_from_template('mod_naas/back_to_course_button', $data);
    }

    /**
     * Renders a next activity button
     *
     * @param object $nextactivityurl Object containing link and name for the next activity
     * @return string HTML content for the next activity button
     */
    public function render_next_activity_button(object $nextactivityurl): string {
        $data = [
            'link' => $nextactivityurl->link,
            'name' => $nextactivityurl->name
        ];
        return $this->render_from_template('mod_naas/next_activity_button', $data);
    }

    /**
     * Renders the JavaScript to toggle the About Modal
     *
     * @return string HTML content with JavaScript
     */
    public function render_about_modal_toggle(): string {
        return $this->render_from_template('mod_naas/about_modal_toggle', []);
    }

    /**
     * Renders a table of NAAS modules in a course
     *
     * @param bool $usesections Whether the course format uses sections
     * @param string $strsectionname Section name string (if usesections is true)
     * @param string $strname Name string
     * @param string $strintro Intro string
     * @param string $strlastmodified Last modified string (if usesections is false)
     * @param array $rows Array of table row data
     * @return string HTML content for the course modules table
     */
    public function render_course_modules_table(bool $usesections, string $strsectionname, string $strname, 
                                               string $strintro, string $strlastmodified, array $rows): string {
        $data = [
            'usesections' => $usesections,
            'strsectionname' => $strsectionname,
            'strname' => $strname,
            'strintro' => $strintro,
            'strlastmodified' => $strlastmodified,
            'rows' => $rows
        ];
        return $this->render_from_template('mod_naas/course_modules_table', $data);
    }
} 
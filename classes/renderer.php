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
 * mod_naas renderer class.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

defined('MOODLE_INTERNAL') || die();

use plugin_renderer_base;

/**
 * The mod_naas plugin renderer.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      mod_naas 1.0.0
 */
class renderer extends plugin_renderer_base {

    /**
     * Render the naas view page.
     *
     * @param view_page $page The view page renderable
     * @return string HTML for the view page
     * @throws \moodle_exception If the template cannot be rendered
     */
    public function render_view_page(view_page $page): string {
        $data = $page->export_for_template($this);
        return $this->render_from_template('mod_naas/view_page', $data);
    }

    /**
     * Render the naas index page.
     *
     * @param index_page $page The index page renderable
     * @return string HTML for the index page
     * @throws \moodle_exception If the template cannot be rendered
     */
    public function render_index_page(index_page $page): string {
        $data = $page->export_for_template($this);
        return $this->render_from_template('mod_naas/index_page', $data);
    }

    /**
     * Render the LTI launch form.
     *
     * @param lti_launch_form $form The LTI launch form renderable
     * @return string HTML for the LTI launch form
     * @throws \moodle_exception If the template cannot be rendered
     */
    public function render_lti_launch_form(lti_launch_form $form): string {
        $data = $form->export_for_template($this);
        return $this->render_from_template('mod_naas/lti_launch_form', $data);
    }
}

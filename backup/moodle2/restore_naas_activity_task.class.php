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
 * NaaS restore task that provides all the settings and steps to perform one
 *  complete restore of the activity
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/naas/backup/moodle2/restore_naas_stepslib.php'); // Because it exists (must).

/**
 * NaaS restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 */
class restore_naas_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // Naas only has one structure step.
        $this->add_step(new restore_naas_activity_structure_step('naas_structure', 'naas.xml'));
    }

    /**
     * Define the contents in the activity that must be processed by the link decoder
     */
    public static function define_decode_contents() {
        $contents = [];

        $contents[] = new restore_decode_content('naas', ['intro'], 'naas');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging to the activity to be executed by the link decoder
     */
    public static function define_decode_rules() {
        $rules = [];

        $rules[] = new restore_decode_rule('NAASVIEWBYID', '/mod/naas/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('NAASINDEX', '/mod/naas/index.php?id=$1', 'course');

        return $rules;
    }

    /**
     * Define the restore log rules
     */
    public static function define_restore_log_rules() {
        $rules = [];

        $rules[] = new restore_log_rule('naas', 'add', 'view.php?id={course_module}', '{naas}');
        $rules[] = new restore_log_rule('naas', 'update', 'view.php?id={course_module}', '{naas}');
        $rules[] = new restore_log_rule('naas', 'view', 'view.php?id={course_module}', '{naas}');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied when restoring course logs.
     *
     * Note: these rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    public static function define_restore_log_rules_for_course() {
        $rules = [];

        $rules[] = new restore_log_rule('naas', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}

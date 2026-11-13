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
 * Define all the backup steps that will be used by the backup_naas_activity_task
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */
class backup_naas_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define NaaS activity structure
     * @return mixed
     */
    protected function define_structure() {
        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Every column of the naas table except id and course, so a restored or
        // duplicated activity keeps its Nugget, attempts, grading and completion rules.
        $naas = new backup_nested_element('naas', ['id'], [
            'name', 'intro', 'introformat', 'timecreated', 'timemodified',
            'nugget_id', 'cgu_agreement', 'attempts', 'grade_method',
            'completionattemptsexhausted', 'completionpass', 'completionminattempts',
            'allowofflineattempts']);

        // Learner attempts: they count towards the attempts limit and completion.
        $outcomes = new backup_nested_element('outcomes');
        $outcome = new backup_nested_element('outcome', ['id'], [
            'user_id', 'date_added', 'score', 'timecompleted']);

        // Build the tree.
        $naas->add_child($outcomes);
        $outcomes->add_child($outcome);

        // Define sources.
        $naas->set_source_table('naas', ['id' => backup::VAR_ACTIVITYID]);
        if ($userinfo) {
            // Attempts are keyed on the course module id, not the instance id.
            $outcome->set_source_table('naas_activity_outcome', ['activity_id' => backup::VAR_MODID], 'id ASC');
        }

        // Define id annotations.
        $outcome->annotate_ids('user', 'user_id');

        // Define file annotations.
        $naas->annotate_files('mod_naas', 'intro', null);

        // Return the root element (naas), wrapped into standard activity structure.
        return $this->prepare_activity_structure($naas);
    }
}

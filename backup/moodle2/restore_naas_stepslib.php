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
 * Define all the restore steps that will be used by the restore_naas_activity_task
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */
class restore_naas_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define the NaaS activity structure.
     * @return mixed
     */
    protected function define_structure() {
        $paths = [];
        $paths[] = new restore_path_element('naas', '/activity/naas');
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('naas_outcome', '/activity/naas/outcomes/outcome');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * NaaS restore process.
     *
     * Backups made before 3.0.0 carry only name, nugget_id and intro (plus unused
     * fields copied from mod_choice): the missing settings take the table defaults.
     *
     * @param array $data The process data
     * @return void
     */
    protected function process_naas($data) {
        global $DB;

        $data = (object)$data;
        $data->course = $this->get_courseid();
        $data->timemodified = time();
        if (empty($data->timecreated)) {
            $data->timecreated = $data->timemodified;
        }

        // Insert the naas record. Columns the table does not have are ignored.
        $newitemid = $DB->insert_record('naas', $data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restore one learner attempt.
     *
     * The attempt gets a fresh lis_result_sourcedid: the original one stays bound to
     * the source activity, so a late grade from NaaS cannot land on both copies.
     *
     * @param array $data The process data
     * @return void
     */
    protected function process_naas_outcome($data) {
        global $DB;

        $data = (object)$data;
        $userid = $this->get_mappingid('user', $data->user_id);
        if (!$userid) {
            return;
        }

        $record = (object) [
            'user_id' => $userid,
            'activity_id' => $this->task->get_moduleid(),
            'sourced_id' => bin2hex(random_bytes(16)),
            'date_added' => $this->apply_date_offset($data->date_added),
            'score' => $data->score ?? null,
            'timecompleted' => empty($data->timecompleted) ? null : $this->apply_date_offset($data->timecompleted),
        ];
        $DB->insert_record('naas_activity_outcome', $record);
    }

    /**
     * Add NaaS related files at the end.
     * @return void
     */
    protected function after_execute() {
        // Add naas related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_naas', 'intro', null);
    }
}

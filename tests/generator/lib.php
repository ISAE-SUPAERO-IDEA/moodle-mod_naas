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
 * mod_naas data generator.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Data generator for mod_naas.
 *
 * Provides default values for naas-specific fields so tests can call
 * $generator->create_module('naas', ['course' => $id]) without having to
 * repeat boilerplate for every required column.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_naas_generator extends testing_module_generator {
    /**
     * Create a naas module instance with plugin-specific defaults.
     *
     * Moodle 4.1/4.3 do not call get_defaults(); set required columns here
     * so tests can use create_module('naas', ['course' => $id]).
     *
     * @param array|stdClass|null $record
     * @param array|null $options
     * @return stdClass
     */
    public function create_instance($record = null, array $options = null) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/naas/lib.php');

        $record = (array) $record;

        if (empty($record['nugget_id'])) {
            $record['nugget_id'] = 'test-nugget-' . uniqid('', false);
        }
        if (!isset($record['grade_method'])) {
            $record['grade_method'] = NAAS_GRADEHIGHEST;
        }
        if (!isset($record['attempts'])) {
            $record['attempts'] = 0;
        }
        if (!isset($record['completionattemptsexhausted'])) {
            $record['completionattemptsexhausted'] = 0;
        }
        if (!isset($record['completionpass'])) {
            $record['completionpass'] = 0;
        }
        if (!isset($record['completionminattempts'])) {
            $record['completionminattempts'] = 0;
        }
        if (!isset($record['allowofflineattempts'])) {
            $record['allowofflineattempts'] = 0;
        }

        return parent::create_instance($record, $options);
    }

    /**
     * Create a naas_activity_outcome row linking a user to an activity session.
     *
     * @param int $userid
     * @param int $activityid  The course-module id (cm->id)
     * @param string|null $sourcedid  Random token; generated when null.
     * @return \stdClass  The inserted record.
     */
    public function create_activity_outcome(int $userid, int $activityid, ?string $sourcedid = null): \stdClass {
        global $DB;

        $record = new \stdClass();
        $record->user_id     = $userid;
        $record->activity_id = $activityid;
        $record->sourced_id  = $sourcedid ?? bin2hex(random_bytes(16));
        $record->date_added  = time();

        $record->id = $DB->insert_record('naas_activity_outcome', $record);
        return $record;
    }
}

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

namespace mod_naas;

/**
 * Attempt accounting for a Nugget activity.
 *
 * Every LTI launch inserts a row in naas_activity_outcome carrying the lis_result_sourcedid.
 * The row only becomes a completed attempt once NaaS posts an outcome for that sourced id
 * (outcome.php sets score and timecompleted). Reloading the launch page therefore never
 * consumes an attempt; only completed attempts are counted here.
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */
class attempts {
    /**
     * Number of completed attempts of a user on a Nugget course module.
     *
     * @param int $cmid Course module id (naas_activity_outcome.activity_id)
     * @param int $userid
     * @return int
     */
    public static function count_completed(int $cmid, int $userid): int {
        global $DB;

        return $DB->count_records_select(
            'naas_activity_outcome',
            'activity_id = :activityid AND user_id = :userid AND timecompleted IS NOT NULL',
            ['activityid' => $cmid, 'userid' => $userid]
        );
    }

    /**
     * Whether the user has used every allowed attempt.
     *
     * @param int $maxattempts naas.attempts; 0 means unlimited and never exhausts
     * @param int $cmid Course module id
     * @param int $userid
     * @return bool
     */
    public static function is_exhausted(int $maxattempts, int $cmid, int $userid): bool {
        if ($maxattempts < 1) {
            return false;
        }

        return self::count_completed($cmid, $userid) >= $maxattempts;
    }

    /**
     * Close the attempt behind an LTI sourced id.
     *
     * Stores the posted score and, on the first outcome only, the completion time. A later
     * outcome for the same session refreshes the score but does not count as a new attempt.
     *
     * @param string $sourcedid lis_result_sourcedid sent at launch
     * @param float $score Normalised LTI score (0..1)
     * @param int|null $now Completion timestamp; defaults to time()
     * @return void
     */
    public static function mark_completed(string $sourcedid, float $score, ?int $now = null): void {
        global $DB;

        $now = $now ?? time();
        $records = $DB->get_records('naas_activity_outcome', ['sourced_id' => $sourcedid]);
        foreach ($records as $record) {
            $record->score = $score;
            if (empty($record->timecompleted)) {
                $record->timecompleted = $now;
            }
            $DB->update_record('naas_activity_outcome', $record);
        }
    }
}

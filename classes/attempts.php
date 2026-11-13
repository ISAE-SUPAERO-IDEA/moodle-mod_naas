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

    /**
     * Score the grading method keeps among a learner's attempts.
     *
     * Attempts are ordered by completion time (then id), the order outcome.php sees them in.
     *
     * @param array $attempts naas_activity_outcome rows of one learner, in any order
     * @param string $grademethod NAAS_GRADEHIGHEST, NAAS_ATTEMPTFIRST or NAAS_ATTEMPTLAST
     * @return float|null Normalised score (0..1); null when no attempt has a score
     */
    public static function kept_score(array $attempts, string $grademethod): ?float {
        $completed = array_values(array_filter($attempts, function ($attempt) {
            return !empty($attempt->timecompleted) && $attempt->score !== null;
        }));
        if (!$completed) {
            return null;
        }
        usort($completed, function ($a, $b) {
            return [(int) $a->timecompleted, (int) $a->id] <=> [(int) $b->timecompleted, (int) $b->id];
        });
        if ($grademethod === NAAS_ATTEMPTFIRST) {
            return (float) $completed[0]->score;
        }
        if ($grademethod === NAAS_ATTEMPTLAST) {
            return (float) $completed[count($completed) - 1]->score;
        }
        return max(array_map(function ($attempt) {
            return (float) $attempt->score;
        }, $completed));
    }

    /**
     * Grade every learner again from the attempts already completed.
     *
     * Used when the grading method changes: outcome.php only applies the method to a new
     * result, so existing grades would otherwise keep the old method until the next attempt.
     *
     * @param \stdClass $naas naas record (id, course, grade_method)
     * @param int $cmid Course module id (naas_activity_outcome.activity_id)
     * @return int Number of learners regraded
     */
    public static function regrade(\stdClass $naas, int $cmid): int {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');

        $item = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'naas',
            'iteminstance' => $naas->id,
            'courseid' => $naas->course,
            'itemnumber' => 0,
        ]);
        if (!$item) {
            return 0;
        }

        $byuser = [];
        $rows = $DB->get_records_select(
            'naas_activity_outcome',
            'activity_id = ? AND timecompleted IS NOT NULL',
            [$cmid],
            'id ASC'
        );
        foreach ($rows as $row) {
            $byuser[(int) $row->user_id][] = $row;
        }

        $grades = [];
        foreach ($byuser as $userid => $attempts) {
            $score = self::kept_score($attempts, (string) $naas->grade_method);
            if ($score !== null) {
                $grades[$userid] = (object) [
                    'userid' => $userid,
                    // Same rounding as outcome.php, so binary fractions land on the scale.
                    'rawgrade' => round($score * (float) $item->grademax, 5),
                ];
            }
        }
        if ($grades) {
            grade_update('mod/naas', $naas->course, 'mod', 'naas', $naas->id, 0, $grades);
        }
        return count($grades);
    }
}

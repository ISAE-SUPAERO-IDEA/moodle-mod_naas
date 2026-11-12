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
 * Tests for {@see \mod_naas\attempts}.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_naas\attempts::class)]
/**
 * Attempt accounting: only launches that received an outcome are attempts.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_naas\attempts
 */
final class attempts_test extends \advanced_testcase {
    /**
     * Create a course, a Nugget activity and an enrolled learner.
     *
     * @return array [naas module, learner, generator]
     */
    private function setup_activity(): array {
        $course = $this->getDataGenerator()->create_course();
        $naas = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        /** @var \mod_naas_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_naas');
        return [$naas, $user, $generator];
    }

    /**
     * Launches without an outcome are not counted, completed ones are, per user and activity.
     */
    public function test_count_completed_counts_only_completed_rows_of_the_user_and_activity(): void {
        $this->resetAfterTest(true);
        [$naas, $user, $generator] = $this->setup_activity();
        $other = $this->getDataGenerator()->create_user();

        $this->assertSame(0, attempts::count_completed((int) $naas->cmid, (int) $user->id));

        $generator->create_activity_outcome($user->id, $naas->cmid);
        $this->assertSame(0, attempts::count_completed((int) $naas->cmid, (int) $user->id));

        $generator->create_completed_attempt($user->id, $naas->cmid, 0.5);
        $generator->create_completed_attempt($user->id, $naas->cmid, 0.7);
        $generator->create_completed_attempt($other->id, $naas->cmid, 0.9);
        $generator->create_completed_attempt($user->id, $naas->cmid + 1000, 0.9);

        $this->assertSame(2, attempts::count_completed((int) $naas->cmid, (int) $user->id));
        $this->assertSame(1, attempts::count_completed((int) $naas->cmid, (int) $other->id));
    }

    /**
     * Zero or a negative limit means unlimited attempts.
     */
    public function test_is_exhausted_never_true_when_unlimited(): void {
        $this->resetAfterTest(true);
        [$naas, $user, $generator] = $this->setup_activity();
        $generator->create_completed_attempt($user->id, $naas->cmid);

        $this->assertFalse(attempts::is_exhausted(0, (int) $naas->cmid, (int) $user->id));
        $this->assertFalse(attempts::is_exhausted(-1, (int) $naas->cmid, (int) $user->id));
    }

    /**
     * The limit is reached once as many attempts as allowed are completed.
     */
    public function test_is_exhausted_compares_completed_attempts_with_the_limit(): void {
        $this->resetAfterTest(true);
        [$naas, $user, $generator] = $this->setup_activity();

        $this->assertFalse(attempts::is_exhausted(2, (int) $naas->cmid, (int) $user->id));
        $generator->create_completed_attempt($user->id, $naas->cmid);
        $this->assertFalse(attempts::is_exhausted(2, (int) $naas->cmid, (int) $user->id));
        $generator->create_completed_attempt($user->id, $naas->cmid);
        $this->assertTrue(attempts::is_exhausted(2, (int) $naas->cmid, (int) $user->id));
        $this->assertTrue(attempts::is_exhausted(1, (int) $naas->cmid, (int) $user->id));
    }

    /**
     * The first outcome stores score and completion time; a later one only refreshes the score.
     */
    public function test_mark_completed_sets_completion_time_once_and_updates_score(): void {
        global $DB;
        $this->resetAfterTest(true);
        [$naas, $user, $generator] = $this->setup_activity();
        $launch = $generator->create_activity_outcome($user->id, $naas->cmid, 'sourced-mark');
        $untouched = $generator->create_activity_outcome($user->id, $naas->cmid, 'sourced-other');

        attempts::mark_completed('sourced-mark', 0.25, 1000);
        $row = $DB->get_record('naas_activity_outcome', ['id' => $launch->id], '*', MUST_EXIST);
        $this->assertEquals(0.25, (float) $row->score);
        $this->assertSame(1000, (int) $row->timecompleted);

        attempts::mark_completed('sourced-mark', 0.75, 2000);
        $row = $DB->get_record('naas_activity_outcome', ['id' => $launch->id], '*', MUST_EXIST);
        $this->assertEquals(0.75, (float) $row->score);
        $this->assertSame(1000, (int) $row->timecompleted);

        $other = $DB->get_record('naas_activity_outcome', ['id' => $untouched->id], '*', MUST_EXIST);
        $this->assertNull($other->timecompleted);
        $this->assertSame(1, attempts::count_completed((int) $naas->cmid, (int) $user->id));
    }

    /**
     * Without an explicit time the current time is stored; an unknown sourced id changes nothing.
     */
    public function test_mark_completed_defaults_to_now_and_ignores_unknown_sourced_id(): void {
        global $DB;
        $this->resetAfterTest(true);
        [$naas, $user, $generator] = $this->setup_activity();
        $launch = $generator->create_activity_outcome($user->id, $naas->cmid, 'sourced-now');

        attempts::mark_completed('sourced-unknown', 1.0);
        $this->assertSame(0, attempts::count_completed((int) $naas->cmid, (int) $user->id));

        $before = time();
        attempts::mark_completed('sourced-now', 1.0);
        $row = $DB->get_record('naas_activity_outcome', ['id' => $launch->id], '*', MUST_EXIST);
        $this->assertGreaterThanOrEqual($before, (int) $row->timecompleted);
        $this->assertLessThanOrEqual(time(), (int) $row->timecompleted);
    }
    /**
     * Attempt row as kept_score() reads it.
     *
     * @param int $id
     * @param float|null $score
     * @param int|null $timecompleted
     * @return \stdClass
     */
    private function attempt(int $id, ?float $score, ?int $timecompleted): \stdClass {
        return (object) ['id' => $id, 'score' => $score, 'timecompleted' => $timecompleted];
    }

    /**
     * Each grading method keeps its attempt, in completion order whatever the row order.
     */
    public function test_kept_score_follows_the_grading_method(): void {
        $this->resetAfterTest(true);
        $attempts = [
            $this->attempt(3, 0.6, 3000),
            $this->attempt(1, 0.4, 1000),
            $this->attempt(2, 0.9, 2000),
            $this->attempt(4, null, null),
        ];

        $this->assertEquals(0.9, attempts::kept_score($attempts, NAAS_GRADEHIGHEST));
        $this->assertEquals(0.4, attempts::kept_score($attempts, NAAS_ATTEMPTFIRST));
        $this->assertEquals(0.6, attempts::kept_score($attempts, NAAS_ATTEMPTLAST));
        // Same completion time: the earlier row comes first.
        $tied = [$this->attempt(8, 0.2, 5000), $this->attempt(7, 0.7, 5000)];
        $this->assertEquals(0.7, attempts::kept_score($tied, NAAS_ATTEMPTFIRST));
        $this->assertNull(attempts::kept_score([$this->attempt(5, null, null)], NAAS_GRADEHIGHEST));
        $this->assertNull(attempts::kept_score([], NAAS_ATTEMPTLAST));
    }

    /**
     * Changing the grading method regrades learners from their completed attempts.
     */
    public function test_changing_the_grading_method_regrades_existing_attempts(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        $this->resetAfterTest(true);
        $this->setAdminUser();
        [$naas, $user, $generator] = $this->setup_activity();
        $DB->set_field('naas', 'grade_method', NAAS_GRADEHIGHEST, ['id' => $naas->id]);
        $generator->create_completed_attempt($user->id, $naas->cmid, 0.4, 1000);
        $generator->create_completed_attempt($user->id, $naas->cmid, 0.9, 2000);
        $generator->create_completed_attempt($user->id, $naas->cmid, 0.6, 3000);
        $grade = function () use ($naas, $user): ?float {
            $grades = grade_get_grades($naas->course, 'mod', 'naas', $naas->id, $user->id);
            $value = $grades->items[0]->grades[$user->id]->grade;
            return $value === null ? null : (float) $value;
        };
        $update = function (string $method) use ($naas) {
            $data = (object) [
                'instance' => $naas->id,
                'coursemodule' => $naas->cmid,
                'course' => $naas->course,
                'name' => $naas->name,
                'grade_method' => $method,
            ];
            naas_update_instance($data);
        };
        $grademax = (float) grade_get_grades($naas->course, 'mod', 'naas', $naas->id)->items[0]->grademax;

        $update(NAAS_ATTEMPTFIRST);
        $this->assertEquals(round(0.4 * $grademax, 5), $grade());
        $update(NAAS_ATTEMPTLAST);
        $this->assertEquals(round(0.6 * $grademax, 5), $grade());
        $update(NAAS_GRADEHIGHEST);
        $this->assertEquals(round(0.9 * $grademax, 5), $grade());
    }

    /**
     * Saving the activity with the same method leaves the gradebook alone.
     */
    public function test_same_grading_method_does_not_regrade(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest(true);
        [$naas, $user, $generator] = $this->setup_activity();
        $DB->set_field('naas', 'grade_method', NAAS_ATTEMPTLAST, ['id' => $naas->id]);
        $generator->create_completed_attempt($user->id, $naas->cmid, 0.5, 1000);

        naas_update_instance((object) [
            'instance' => $naas->id,
            'coursemodule' => $naas->cmid,
            'course' => $naas->course,
            'name' => $naas->name,
            'grade_method' => NAAS_ATTEMPTLAST,
        ]);

        $grades = grade_get_grades($naas->course, 'mod', 'naas', $naas->id, $user->id);
        $this->assertNull($grades->items[0]->grades[$user->id]->grade);
    }

    /**
     * Without a grade item there is nothing to regrade.
     */
    public function test_regrade_without_grade_item_does_nothing(): void {
        global $DB;
        $this->resetAfterTest(true);
        [$naas, $user, $generator] = $this->setup_activity();
        $generator->create_completed_attempt($user->id, $naas->cmid, 0.5, 1000);
        $DB->delete_records('grade_items', ['itemmodule' => 'naas', 'iteminstance' => $naas->id]);

        $record = $DB->get_record('naas', ['id' => $naas->id]);
        $this->assertSame(0, attempts::regrade($record, (int) $naas->cmid));
    }
}

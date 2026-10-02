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
 * Unit tests for mod_naas\completion\custom_completion.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\completion;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/mod/naas/lib.php');

use advanced_testcase;
use mod_naas\completion\custom_completion;

/**
 * Tests for mod_naas\completion\custom_completion.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/).
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 * @covers \mod_naas\completion\custom_completion
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class custom_completion_test extends advanced_testcase {
    // Defined custom rules.

    /**
     * get_defined_custom_rules() must return the expected rule names.
     */
    public function test_get_defined_custom_rules_returns_array(): void {
        $rules = custom_completion::get_defined_custom_rules();
        $this->assertIsArray($rules);
        $this->assertContains('completionpassorattemptsexhausted', $rules);
    }

    /**
     * The rule list must not be empty.
     */
    public function test_get_defined_custom_rules_not_empty(): void {
        $this->assertNotEmpty(custom_completion::get_defined_custom_rules());
    }

    // Custom rule descriptions.

    public function test_get_custom_rule_descriptions(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas   = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpass' => 1,
        ]);
        $user   = $this->getDataGenerator()->create_user();
        $cm     = get_fast_modinfo($course)->get_cm($naas->cmid);

        $completion = new custom_completion($cm, $user->id);
        $descriptions = $completion->get_custom_rule_descriptions();

        $this->assertIsArray($descriptions);
        $this->assertArrayHasKey('completionpassorattemptsexhausted', $descriptions);
        $this->assertSame(
            get_string('completiondetail:passgrade', 'naas'),
            $descriptions['completionpassorattemptsexhausted']
        );
    }

    /**
     * When "attempts exhausted" is enabled, the pass/exhaust description string must be used.
     */
    public function test_get_custom_rule_descriptions_pass_or_exhaust_label(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas   = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpass' => 0,
            'completionattemptsexhausted' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $cm   = get_fast_modinfo($course)->get_cm($naas->cmid);

        $completion   = new custom_completion($cm, $user->id);
        $descriptions = $completion->get_custom_rule_descriptions();

        $this->assertSame(
            get_string('completiondetail:passorexhaust', 'naas'),
            $descriptions['completionpassorattemptsexhausted']
        );
    }

    /**
     * When a minimum attempt count is set, descriptions must include that rule text.
     */
    public function test_get_custom_rule_descriptions_includes_min_attempts(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas   = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionminattempts' => 4,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $cm   = get_fast_modinfo($course)->get_cm($naas->cmid);

        $completion   = new custom_completion($cm, $user->id);
        $descriptions = $completion->get_custom_rule_descriptions();

        $this->assertSame(
            get_string('completiondetail:minattempts', 'naas', 4),
            $descriptions['completionminattempts']
        );
    }

    /**
     * Protected helper: when pass and exhaustion are both off, the check is a no-op (true).
     */
    public function test_check_passing_grade_or_all_attempts_true_when_both_disabled(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas   = $this->getDataGenerator()->create_module('naas', [
            'course'         => $course->id, 'completion'     => COMPLETION_TRACKING_AUTOMATIC, 'completionpass' => 0, ]);
        $user = $this->getDataGenerator()->create_user();
        $cm   = get_fast_modinfo($course)->get_cm($naas->cmid);

        $completion = new custom_completion($cm, $user->id);
        $this->assertTrue($this->invoke_check_passing($completion));
    }

    /**
     * Protected helper: zero minimum attempts means the rule is trivially satisfied.
     */
    public function test_check_min_attempts_true_when_min_is_zero(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas   = $this->getDataGenerator()->create_module('naas', [
            'course'         => $course->id, 'completion'     => COMPLETION_TRACKING_AUTOMATIC, 'completionpass' => 1, ]);
        $user = $this->getDataGenerator()->create_user();
        $cm   = get_fast_modinfo($course)->get_cm($naas->cmid);

        $completion = new custom_completion($cm, $user->id);
        $this->assertTrue($this->invoke_check_min_attempts($completion));
    }

    /**
     * With a passing grade required, a recorded grade below the pass threshold must not satisfy the rule.
     */
    public function test_incomplete_when_pass_required_but_grade_below_pass_threshold(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas   = $this->getDataGenerator()->create_module('naas', [
            'course'         => $course->id, 'completion'     => COMPLETION_TRACKING_AUTOMATIC, 'completionpass' => 1, ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $gradeitem = \grade_item::fetch([
            'courseid'     => $course->id, 'itemtype'     => 'mod', 'itemmodule'   => 'naas', 'iteminstance' => $naas->id, ]);
        $gradeitem->gradepass = 70;
        $gradeitem->update();

        $grade           = new \stdClass();
        $grade->userid   = $user->id;
        $grade->rawgrade = 40;
        grade_update('mod/naas', $course->id, 'mod', 'naas', $naas->id, 0, $grade);

        $cm         = get_fast_modinfo($course)->get_cm($naas->cmid);
        $completion = new custom_completion($cm, $user->id);

        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_state('completionpassorattemptsexhausted'));
    }

    /**
     * Exhaustion rule enabled but attempts remaining must yield incomplete.
     */
    public function test_incomplete_when_exhaust_enabled_but_attempts_remain(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas   = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpass' => 0,
            'completionattemptsexhausted' => 1,
            'attempts' => 3,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        /** @var \mod_naas_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_naas');
        $gen->create_activity_outcome($user->id, $naas->cmid);

        $cm         = get_fast_modinfo($course)->get_cm($naas->cmid);
        $completion = new custom_completion($cm, $user->id);

        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_state('completionpassorattemptsexhausted'));
    }

    /**
     * Only exhaustion enabled: the pass/exhaust rule must stay available for get_state().
     */
    public function test_get_available_custom_rules_includes_rule_when_only_exhaust_enabled(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas   = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpass' => 0,
            'completionattemptsexhausted' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $cm   = get_fast_modinfo($course)->get_cm($naas->cmid);

        $completion = new custom_completion($cm, $user->id);
        $this->assertContains('completionpassorattemptsexhausted', $completion->get_available_custom_rules());
    }

    // Sort order.

    /**
     * get_sort_order() must include the custom rule in the ordered list.
     */
    public function test_get_sort_order_includes_custom_rule(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas   = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpass' => 0,
        ]);
        $user   = $this->getDataGenerator()->create_user();
        $cm     = get_fast_modinfo($course)->get_cm($naas->cmid);

        $completion = new custom_completion($cm, $user->id);
        $order = $completion->get_sort_order();

        $this->assertIsArray($order);
        $this->assertContains('completionpassorattemptsexhausted', $order);
    }

    // Get_state() – completionpass disabled.

    /**
     * When completionpass=0 the rule is not registered as available for the
     * activity, so the framework excludes it from custom completion checks
     * and it cannot block the activity.
     */
    public function test_rule_unavailable_when_completionpass_disabled(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas   = $this->getDataGenerator()->create_module('naas', [
            'course'         => $course->id, 'completion'     => COMPLETION_TRACKING_AUTOMATIC, 'completionpass' => 0, ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $cm = get_fast_modinfo($course)->get_cm($naas->cmid);
        $completion = new custom_completion($cm, $user->id);

        $this->assertFalse($completion->is_available('completionpassorattemptsexhausted'));
        $this->assertNotContains(
            'completionpassorattemptsexhausted',
            $completion->get_available_custom_rules()
        );
    }

    /**
     * Core may still call get_state when customcompletionrules holds a truthy
     * array with both sub-flags off; the implementation must return complete without validate_rule.
     */
    public function test_get_state_pass_rule_with_zero_flags_returns_complete(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpass' => 0,
            'completionattemptsexhausted' => 0,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $cm = get_fast_modinfo($course)->get_cm($naas->cmid);
        $cm->override_customdata('customcompletionrules', [
            'completionpassorattemptsexhausted' => [
                'completionpass' => 0, 'completionattemptsexhausted' => 0, ], 'completionminattempts' => 0, ]);
        $completion = new custom_completion($cm, $user->id);

        $this->assertSame(COMPLETION_COMPLETE, $completion->get_state('completionpassorattemptsexhausted'));
    }

    // Get_state() – completionpass enabled, no grade.

    /**
     * When completionpass=1 and the user has no grade, get_state() must return
     * COMPLETION_INCOMPLETE.
     */
    public function test_incomplete_when_pass_required_and_no_grade(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas   = $this->getDataGenerator()->create_module('naas', [
            'course'         => $course->id, 'completion'     => COMPLETION_TRACKING_AUTOMATIC, 'completionpass' => 1, ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $cm = get_fast_modinfo($course)->get_cm($naas->cmid);
        $completion = new custom_completion($cm, $user->id);

        $state = $completion->get_state('completionpassorattemptsexhausted');
        $this->assertEquals(COMPLETION_INCOMPLETE, $state);
    }

    // Get_state() – completionpass enabled, passing grade.

    /**
     * When completionpass=1 and the user has a passing grade, get_state() must
     * return COMPLETION_COMPLETE.
     */
    public function test_complete_after_passing_grade(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas   = $this->getDataGenerator()->create_module('naas', [
            'course'         => $course->id, 'completion'     => COMPLETION_TRACKING_AUTOMATIC, 'completionpass' => 1, ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Set a passing grade (gradepass defaults to 0, which means any grade passes).
        // To make the test meaningful we set gradepass to 50 and award 80).
        $gradeitem = \grade_item::fetch([
            'courseid'     => $course->id, 'itemtype'     => 'mod', 'itemmodule'   => 'naas', 'iteminstance' => $naas->id, ]);
        $gradeitem->gradepass = 50;
        $gradeitem->update();

        $grade          = new \stdClass();
        $grade->userid  = $user->id;
        $grade->rawgrade = 80;
        grade_update('mod/naas', $course->id, 'mod', 'naas', $naas->id, 0, $grade);

        $cm = get_fast_modinfo($course)->get_cm($naas->cmid);
        $completion = new custom_completion($cm, $user->id);

        $state = $completion->get_state('completionpassorattemptsexhausted');
        $this->assertEquals(COMPLETION_COMPLETE, $state);
    }

    // Isolation between users.

    /**
     * Completion state is isolated per user: User A completing must not affect
     * User B's state.
     */
    public function test_completion_isolated_per_user(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas   = $this->getDataGenerator()->create_module('naas', [
            'course'         => $course->id, 'completion'     => COMPLETION_TRACKING_AUTOMATIC, 'completionpass' => 1, ]);
        $usera = $this->getDataGenerator()->create_user();
        $userb = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($usera->id, $course->id);
        $this->getDataGenerator()->enrol_user($userb->id, $course->id);

        // Award a passing grade only to User A.
        $gradeitem = \grade_item::fetch([
            'courseid'     => $course->id, 'itemtype'     => 'mod', 'itemmodule'   => 'naas', 'iteminstance' => $naas->id, ]);
        $gradeitem->gradepass = 50;
        $gradeitem->update();

        $grade          = new \stdClass();
        $grade->userid  = $usera->id;
        $grade->rawgrade = 80;
        grade_update('mod/naas', $course->id, 'mod', 'naas', $naas->id, 0, $grade);

        $cm          = get_fast_modinfo($course)->get_cm($naas->cmid);
        $completiona = new custom_completion($cm, $usera->id);
        $completionb = new custom_completion($cm, $userb->id);

        $this->assertEquals(COMPLETION_COMPLETE, $completiona->get_state('completionpassorattemptsexhausted'));
        $this->assertEquals(COMPLETION_INCOMPLETE, $completionb->get_state('completionpassorattemptsexhausted'));
    }

    // Isolation between activities.

    /**
     * Completion state for one activity must not bleed into a second activity
     * in the same course.
     */
    public function test_completion_isolated_per_activity(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas1   = $this->getDataGenerator()->create_module('naas', [
            'course'         => $course->id, 'completion'     => COMPLETION_TRACKING_AUTOMATIC, 'completionpass' => 1, ]);
        $naas2   = $this->getDataGenerator()->create_module('naas', [
            'course'         => $course->id, 'completion'     => COMPLETION_TRACKING_AUTOMATIC, 'completionpass' => 1, ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Award a passing grade on naas1 only.
        foreach ([$naas1->id] as $instanceid) {
            $gradeitem = \grade_item::fetch([
                'courseid'     => $course->id, 'itemtype'     => 'mod', 'itemmodule'   => 'naas', 'iteminstance' => $instanceid, ]);
            $gradeitem->gradepass = 50;
            $gradeitem->update();

            $grade           = new \stdClass();
            $grade->userid   = $user->id;
            $grade->rawgrade = 80;
            grade_update('mod/naas', $course->id, 'mod', 'naas', $instanceid, 0, $grade);
        }

        $modinfo = get_fast_modinfo($course);
        $cm1 = $modinfo->get_cm($naas1->cmid);
        $cm2 = $modinfo->get_cm($naas2->cmid);

        $completion1 = new custom_completion($cm1, $user->id);
        $completion2 = new custom_completion($cm2, $user->id);

        $this->assertEquals(COMPLETION_COMPLETE, $completion1->get_state('completionpassorattemptsexhausted'));
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion2->get_state('completionpassorattemptsexhausted'));
    }

    // Rule validation.

    /**
     * get_state() must throw a coding_exception for an unknown rule.
     */
    public function test_get_state_throws_for_unknown_rule(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas   = $this->getDataGenerator()->create_module('naas', [
            'course'     => $course->id, 'completion' => COMPLETION_TRACKING_AUTOMATIC, ]);
        $user = $this->getDataGenerator()->create_user();
        $cm   = get_fast_modinfo($course)->get_cm($naas->cmid);

        $completion = new custom_completion($cm, $user->id);

        $this->expectException(\coding_exception::class);
        $completion->get_state('unknown_rule_xyz');
    }

    // Get_state() – completionattemptsexhausted.

    /**
     * When completionattemptsexhausted=1 and the user has used all attempts,
     * get_state() must return COMPLETION_COMPLETE even without a passing grade.
     */
    public function test_complete_after_attempts_exhausted(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas   = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpass' => 1,
            'completionattemptsexhausted' => 1,
            'attempts' => 2,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Record 2 attempts.
        /** @var \mod_naas_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_naas');
        $gen->create_activity_outcome($user->id, $naas->cmid);
        $gen->create_activity_outcome($user->id, $naas->cmid);

        $cm = get_fast_modinfo($course)->get_cm($naas->cmid);
        $completion = new custom_completion($cm, $user->id);

        $state = $completion->get_state('completionpassorattemptsexhausted');
        $this->assertEquals(COMPLETION_COMPLETE, $state);
    }

    // Get_state() – completionminattempts.

    /**
     * When completionminattempts > 0, get_state() must return
     * COMPLETION_INCOMPLETE until the minimum number of attempts is reached.
     */
    public function test_incomplete_until_min_attempts_reached(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $naas   = $this->getDataGenerator()->create_module('naas', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionminattempts' => 2,
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $cm = get_fast_modinfo($course)->get_cm($naas->cmid);
        $completion = new custom_completion($cm, $user->id);

        // 0 attempts.
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_state('completionminattempts'));

        // 1 attempt.
        /** @var \mod_naas_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_naas');
        $gen->create_activity_outcome($user->id, $naas->cmid);

        // Refetch modinfo is NOT needed for get_state as it queries DB directly for attempts.
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_state('completionminattempts'));

        // 2 attempts.
        $gen->create_activity_outcome($user->id, $naas->cmid);
        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_state('completionminattempts'));
    }

    /**
     * Invoke {@see custom_completion::check_passing_grade_or_all_attempts()} for branch coverage.
     *
     * @param custom_completion $completion
     * @return bool
     */
    private function invoke_check_passing(custom_completion $completion): bool {
        $m = new \ReflectionMethod(custom_completion::class, 'check_passing_grade_or_all_attempts');
        $m->setAccessible(true);
        return (bool) $m->invoke($completion);
    }

    /**
     * Invoke {@see custom_completion::check_min_attempts()} for branch coverage.
     *
     * @param custom_completion $completion
     * @return bool
     */
    private function invoke_check_min_attempts(custom_completion $completion): bool {
        $m = new \ReflectionMethod(custom_completion::class, 'check_min_attempts');
        $m->setAccessible(true);
        return (bool) $m->invoke($completion);
    }
}

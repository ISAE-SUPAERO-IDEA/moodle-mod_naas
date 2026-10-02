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
 * Privacy provider tests for mod_naas.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use mod_naas\privacy\provider;

/**
 * Tests for mod_naas\privacy\provider.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/).
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 * @covers \mod_naas\privacy\provider
 */
class provider_test extends provider_testcase {
    // Get_metadata.

    /**
     * get_metadata() must return a populated collection (not empty).
     */
    public function test_get_metadata_returns_collection(): void {
        $collection = new \core_privacy\local\metadata\collection('mod_naas');
        $result     = provider::get_metadata($collection);
        $this->assertNotEmpty($result->get_collection());
    }

    /**
     * get_metadata() must declare the naas_activity_outcome table.
     */
    public function test_get_metadata_includes_activity_outcome_table(): void {
        $collection = new \core_privacy\local\metadata\collection('mod_naas');
        provider::get_metadata($collection);

        $tables = array_map(
            fn($item) => $item->get_name(),
            $collection->get_collection()
        );
        $this->assertContains('naas_activity_outcome', $tables);
    }

    // Get_contexts_for_userid.

    /**
     * A user with no activity data must produce an empty context list.
     */
    public function test_no_data_returns_empty_context_list(): void {
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();

        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(0, $contextlist);
    }

    /**
     * A user with one outcome session must appear in exactly one context.
     */
    public function test_get_contexts_for_userid_returns_correct_context(): void {
        $this->resetAfterTest(true);

        [$course, $naas, $user] = $this->setup_activity_with_user();

        /** @var \mod_naas_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_naas');
        $gen->create_activity_outcome($user->id, $naas->cmid);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(1, $contextlist);

        $context = \context_module::instance($naas->cmid);
        $this->assertContains((int)$context->id, array_map('intval', $contextlist->get_contextids()));
    }

    /**
     * A user with sessions in two different activities must appear in two contexts.
     */
    public function test_get_contexts_for_userid_with_multiple_activities(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $naas1 = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        $naas2 = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);

        /** @var \mod_naas_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_naas');
        $gen->create_activity_outcome($user->id, $naas1->cmid);
        $gen->create_activity_outcome($user->id, $naas2->cmid);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(2, $contextlist);
    }

    // Export_user_data.

    /**
     * export_user_data() must write data for the user's activity sessions.
     */
    public function test_export_user_data_includes_session(): void {
        $this->resetAfterTest(true);

        [$course, $naas, $user] = $this->setup_activity_with_user();

        /** @var \mod_naas_generator $gen */
        $gen      = $this->getDataGenerator()->get_plugin_generator('mod_naas');
        $outcome  = $gen->create_activity_outcome($user->id, $naas->cmid);

        $context  = \context_module::instance($naas->cmid);
        $approved = new approved_contextlist($user, 'mod_naas', [$context->id]);
        provider::export_user_data($approved);

        $data = writer::with_context($context)->get_data(['Nugget info pertaining to user']);
        $this->assertNotEmpty($data);

        // The writer stores a stdClass; the user information property must be an array.
        $this->assertTrue(property_exists($data, 'User information'));
    }

    // Delete_data_for_user.

    /**
     * delete_data_for_user() must remove all outcome rows for the target user.
     */
    public function test_delete_data_for_user_removes_sessions(): void {
        global $DB;
        $this->resetAfterTest(true);

        [$course, $naas, $user] = $this->setup_activity_with_user();

        /** @var \mod_naas_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_naas');
        $gen->create_activity_outcome($user->id, $naas->cmid);
        $gen->create_activity_outcome($user->id, $naas->cmid);

        $this->assertEquals(2, $DB->count_records('naas_activity_outcome', ['user_id' => $user->id]));

        $context  = \context_module::instance($naas->cmid);
        $approved = new approved_contextlist($user, 'mod_naas', [$context->id]);
        provider::delete_data_for_user($approved);

        $this->assertEquals(0, $DB->count_records('naas_activity_outcome', ['user_id' => $user->id]));
    }

    /**
     * delete_data_for_user() must not remove sessions belonging to other users.
     */
    public function test_delete_data_for_user_preserves_other_users(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $usera   = $this->getDataGenerator()->create_user();
        $userb   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($usera->id, $course->id);
        $this->getDataGenerator()->enrol_user($userb->id, $course->id);
        $naas    = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);

        /** @var \mod_naas_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_naas');
        $gen->create_activity_outcome($usera->id, $naas->cmid);
        $gen->create_activity_outcome($userb->id, $naas->cmid);

        $context  = \context_module::instance($naas->cmid);
        $approved = new approved_contextlist($usera, 'mod_naas', [$context->id]);
        provider::delete_data_for_user($approved);

        // User B's row must survive.
        $this->assertEquals(1, $DB->count_records('naas_activity_outcome', ['user_id' => $userb->id]));
    }

    // Delete_data_for_all_users_in_context.

    /**
     * delete_data_for_all_users_in_context() must remove every outcome row in
     * the given module context regardless of which user owns it.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $usera   = $this->getDataGenerator()->create_user();
        $userb   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($usera->id, $course->id);
        $this->getDataGenerator()->enrol_user($userb->id, $course->id);
        $naas    = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);

        /** @var \mod_naas_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_naas');
        $gen->create_activity_outcome($usera->id, $naas->cmid);
        $gen->create_activity_outcome($userb->id, $naas->cmid);

        $context = \context_module::instance($naas->cmid);
        provider::delete_data_for_all_users_in_context($context);

        $this->assertEquals(0, $DB->count_records('naas_activity_outcome', ['activity_id' => $naas->cmid]));
    }

    /**
     * delete_data_for_all_users_in_context() on a non-module context must be
     * a no-op.
     */
    public function test_delete_all_ignores_non_module_context(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $user    = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $naas    = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);

        /** @var \mod_naas_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_naas');
        $gen->create_activity_outcome($user->id, $naas->cmid);

        // Pass a course context, not a module context.
        $coursecontext = \context_course::instance($course->id);
        provider::delete_data_for_all_users_in_context($coursecontext);

        // The row must still exist.
        $this->assertEquals(1, $DB->count_records('naas_activity_outcome', ['activity_id' => $naas->cmid]));
    }

    // Get_users_in_context / delete_data_for_users.

    /**
     * get_users_in_context() must list users who have sessions in the context.
     */
    public function test_get_users_in_context_returns_users_with_data(): void {
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $usera   = $this->getDataGenerator()->create_user();
        $userb   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($usera->id, $course->id);
        $this->getDataGenerator()->enrol_user($userb->id, $course->id);
        $naas    = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);

        /** @var \mod_naas_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_naas');
        $gen->create_activity_outcome($usera->id, $naas->cmid);

        $context  = \context_module::instance($naas->cmid);
        $userlist = new \core_privacy\local\request\userlist($context, 'mod_naas');
        provider::get_users_in_context($userlist);

        $this->assertCount(1, $userlist->get_userids());
        $this->assertContains((int)$usera->id, array_map('intval', $userlist->get_userids()));
    }

    /**
     * delete_data_for_users() must only remove rows for the listed users.
     */
    public function test_delete_data_for_users_removes_only_listed(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course  = $this->getDataGenerator()->create_course();
        $usera   = $this->getDataGenerator()->create_user();
        $userb   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($usera->id, $course->id);
        $this->getDataGenerator()->enrol_user($userb->id, $course->id);
        $naas    = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);

        /** @var \mod_naas_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_naas');
        $gen->create_activity_outcome($usera->id, $naas->cmid);
        $gen->create_activity_outcome($userb->id, $naas->cmid);

        $context      = \context_module::instance($naas->cmid);
        $approveduserlist = new approved_userlist($context, 'mod_naas', [$usera->id]);
        provider::delete_data_for_users($approveduserlist);

        $this->assertEquals(0, $DB->count_records('naas_activity_outcome', ['user_id' => $usera->id]));
        $this->assertEquals(1, $DB->count_records('naas_activity_outcome', ['user_id' => $userb->id]));
    }

    /**
     * get_users_in_context must no-op when the context is not a module context.
     */
    public function test_get_users_in_context_non_module_returns_early(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $ctx = \context_course::instance($course->id);
        $userlist = new \core_privacy\local\request\userlist($ctx, 'mod_naas');
        provider::get_users_in_context($userlist);
        $this->assertCount(0, $userlist->get_userids());
    }

    /**
     * delete_data_for_user with an empty approved list must return immediately.
     */
    public function test_delete_data_for_user_empty_context_list(): void {
        global $DB;
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        $approved = new approved_contextlist($user, 'mod_naas', []);
        provider::delete_data_for_user($approved);
        $this->assertSame(0, (int) $DB->count_records('naas_activity_outcome'));
    }

    /**
     * delete_data_for_all_users_in_context must return when the CM is not a naas instance.
     */
    public function test_delete_all_users_non_naas_module_returns(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $label = $this->getDataGenerator()->create_module('label', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        /** @var \mod_naas_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_naas');
        $gen->create_activity_outcome($user->id, $label->cmid);

        $ctx = \context_module::instance($label->cmid);
        provider::delete_data_for_all_users_in_context($ctx);

        $this->assertEquals(1, $DB->count_records('naas_activity_outcome', ['activity_id' => $label->cmid]));
    }

    // Helpers.

    /**
     * Create a course, one naas module, and one enrolled user.
     *
     * @return array  [$course, $naas, $user]
     */
    private function setup_activity_with_user(): array {
        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $naas   = $this->getDataGenerator()->create_module('naas', ['course' => $course->id]);
        return [$course, $naas, $user];
    }
}

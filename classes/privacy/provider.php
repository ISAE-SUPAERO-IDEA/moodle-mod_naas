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

namespace mod_naas\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Moodle Nugget Plugin : Privacy API
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 * @author John Tranier
 */
class provider implements \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Description of the stored personal data.
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'naas_activity_outcome',
            [
                'id' => 'privacy:metadata:naas_activity_outcome:id',
                'user_id' => 'privacy:metadata:naas_activity_outcome:user_id',
                'activity_id' => 'privacy:metadata:naas_activity_outcome:activity_id',
                'sourced_id' => 'privacy:metadata:naas_activity_outcome:sourced_id',
                'date_added' => 'privacy:metadata:naas_activity_outcome:date_added',
            ],
            'privacy:metadata:naas_activity_outcome'
        );

        $collection->add_subsystem_link(
            'core_completion',
            [],
            'privacy:metadata:core_completion'
        );

        $collection->add_subsystem_link(
            'core_grades',
            [],
            'privacy:metadata:core_grades'
        );

        // External systems.
        $collection->add_external_location_link(
            'naas',
            [
                'oauth_consumer_key' => 'privacy:metadata:naas:oauth_consumer_key',
                'oauth_timestamp' => 'privacy:metadata:naas:oauth_timestamp',

                'context_id' => 'privacy:metadata:naas:context_id',
                'lis_result_sourcedid' => 'privacy:metadata:naas:lis_result_sourcedid',
                'lis_outcome_service_url' => 'privacy:metadata:naas:lis_outcome_service_url',
                'lis_person_name_full' => 'privacy:metadata:naas:lis_person_name_full',
                'lis_person_contact_email_primary' => 'privacy:metadata:naas:lis_person_contact_email_primary',
            ],
            'privacy:metadata:naas'
        );

        return $collection;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {

        global $DB;

        if (empty($contextlist)) {
            echo"empty contexts";
            return;
        }

        // Get the user.
        $user = $contextlist->get_user();
        $userid = $user->id;

        // Get the list of contexts that contain user information for the specified user.
        // (The context->instanceid = cm->id and the cm.instance equals moodlecourseid).
        foreach ($contextlist as $context) {
            $data = helper::get_context_data($context, $user);
            // Retrieve the coursemodule.
            $cm = get_coursemodule_from_id('naas', $context->instanceid);
            $activityid = $cm->id;
            $params = ['user_id' => $userid,   'activity_id' => $activityid];
            $recordset = $DB->get_recordset('naas_activity_outcome', $params);

            $nuggetsessiondata = [];
            foreach ($recordset as $record) {
                $nuggetsessiondata[] = [
                    'ID of instance' => $record->id,
                    'User ID' => $record->user_id,
                    'Activity ID' => $record->activity_id,
                    'Session ID' => $record->sourced_id,
                    'Started ad' => $record->date_added,
                ];
            }

            // Combine the course data with the usercourse data.
            $contextdata = (object)array_merge((array)$data, ['User information' => $nuggetsessiondata]);

            // Write data out.
            writer::with_context($context)->export_data(
                ['Nugget info pertaining to user'],
                (object)  $contextdata
            );
        }
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {

        $sql = "SELECT ctx.id
        FROM {context} ctx
        JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :context
        JOIN {modules} m ON m.id = cm.module AND m.name = 'naas'
        JOIN {naas_activity_outcome} naas_session ON naas_session.activity_id = cm.id
        WHERE naas_session.user_id = :userid";

        $params = ['context' => CONTEXT_MODULE, 'userid' => $userid];

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        $sql = "SELECT naas_session.user_id
            FROM {naas_activity_outcome} naas_session
            JOIN {modules} m ON m.name = 'naas'
            JOIN {course_modules} cm ON cm.id = naas_session.activity_id AND cm.module = m.id
            JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modlevel
            WHERE ctx.id = :contextid";

        $params = ['modlevel' => CONTEXT_MODULE, 'contextid' => $context->id];

        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Delete all user data which matches the specified context.
     *
     * @param context $context A user context.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        // This should not happen, but just in case.
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('naas', $context->instanceid);
        if (!$cm) {
            return;
        }

        $DB->delete_records('naas_activity_outcome', ['activity_id' => $cm->id]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {

            // Retrieve the instance id from the context.
            $cmid = $DB->get_field('course_modules', 'id', ['id' => $context->instanceid], MUST_EXIST);

            $sql = ["activity_id" => $cmid, "user_id" => $userid];
            $DB->delete_records('naas_activity_outcome', $sql);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        $cm = $DB->get_record('course_modules', ['id' => $context->instanceid]);

        list($userinsql, $userinparams) = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params = array_merge(['activity_id' => $cm->id], $userinparams);
        $sql = "activity_id = :activity_id AND user_id {$userinsql}";

        $DB->delete_records_select('naas_activity_outcome', $sql, $params);
    }
}

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
 * Privacy provider for AI Agent Blocker plugin (GDPR compliance)
 *
 * @package    local_aiagentblock
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiagentblock\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for AI Agent Blocker plugin
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns metadata about this plugin's data storage.
     *
     * @param collection $collection The collection to add metadata to
     * @return collection The updated collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_aiagentblock_log',
            [
                'userid' => 'privacy:metadata:local_aiagentblock_log:userid',
                'courseid' => 'privacy:metadata:local_aiagentblock_log:courseid',
                'pageurl' => 'privacy:metadata:local_aiagentblock_log:pageurl',
                'user_agent' => 'privacy:metadata:local_aiagentblock_log:user_agent',
                'browser' => 'privacy:metadata:local_aiagentblock_log:browser',
                'ip_address' => 'privacy:metadata:local_aiagentblock_log:ip_address',
                'timecreated' => 'privacy:metadata:local_aiagentblock_log:timecreated',
            ],
            'privacy:metadata:local_aiagentblock_log'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search
     * @return contextlist The contextlist containing the list of contexts
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT DISTINCT ctx.id
                FROM {local_aiagentblock_log} l
                JOIN {context} ctx ON ctx.id = l.contextid
                WHERE l.userid = :userid";

        $contextlist->add_from_sql($sql, ['userid' => $userid]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        $sql = "SELECT userid
                FROM {local_aiagentblock_log}
                WHERE contextid = :contextid";

        $userlist->add_from_sql('userid', $sql, ['contextid' => $context->id]);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT l.*
                FROM {local_aiagentblock_log} l
                WHERE l.userid = :userid
                AND l.contextid {$contextsql}
                ORDER BY l.timecreated ASC";

        $params = ['userid' => $user->id] + $contextparams;
        $records = $DB->get_records_sql($sql, $params);

        foreach ($records as $record) {
            $context = \context::instance_by_id($record->contextid);
            
            // Prepare data for export
            $data = (object) [
                'courseid' => $record->courseid,
                'pageurl' => $record->pageurl,
                'protection_level' => $record->protection_level,
                'detection_method' => $record->detection_method,
                'user_agent' => $record->user_agent,
                'browser' => $record->browser,
                'ip_address' => $record->ip_address,
                'timecreated' => \core_privacy\local\request\transform::datetime($record->timecreated),
            ];

            // Export the data
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_aiagentblock')],
                $data
            );
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The context to delete data for
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        $DB->delete_records('local_aiagentblock_log', ['contextid' => $context->id]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            $DB->delete_records('local_aiagentblock_log', [
                'userid' => $user->id,
                'contextid' => $context->id
            ]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        $userids = $userlist->get_userids();

        if (empty($userids)) {
            return;
        }

        list($usersql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $select = "contextid = :contextid AND userid {$usersql}";
        $params = ['contextid' => $context->id] + $userparams;

        $DB->delete_records_select('local_aiagentblock_log', $select, $params);
    }
}

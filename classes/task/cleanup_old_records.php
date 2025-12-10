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
 * Scheduled task to clean up old detection records
 *
 * @package    local_aiagentblock
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiagentblock\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task class for cleaning up old records
 */
class cleanup_old_records extends \core\task\scheduled_task {
    
    /**
     * Get a descriptive name for this task
     *
     * @return string
     */
    public function get_name() {
        return get_string('cleanup_old_records', 'local_aiagentblock');
    }
    
    /**
     * Execute the task
     */
    public function execute() {
        global $DB;
        
        // Check if auto-delete is enabled
        $auto_delete = get_config('local_aiagentblock', 'auto_delete_records');
        
        if (!$auto_delete) {
            mtrace('Auto-delete is disabled. Skipping cleanup.');
            return;
        }
        
        // Get retention period in days
        $retention_days = get_config('local_aiagentblock', 'retention_days');
        
        if (empty($retention_days) || $retention_days <= 0) {
            mtrace('Invalid retention period. Skipping cleanup.');
            return;
        }
        
        // Calculate cutoff timestamp
        $cutoff_time = time() - ($retention_days * 86400); // 86400 seconds in a day
        
        // Count records to be deleted
        $count = $DB->count_records_select(
            'local_aiagentblock_log',
            'timecreated < :cutoff',
            ['cutoff' => $cutoff_time]
        );
        
        if ($count > 0) {
            // Delete old records
            $DB->delete_records_select(
                'local_aiagentblock_log',
                'timecreated < :cutoff',
                ['cutoff' => $cutoff_time]
            );
            
            mtrace("Deleted {$count} detection records older than {$retention_days} days.");
        } else {
            mtrace('No old records to delete.');
        }
    }
}

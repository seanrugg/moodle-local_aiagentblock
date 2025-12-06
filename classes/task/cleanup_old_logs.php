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
 * Scheduled task to clean up old AI agent detection logs
 *
 * @package    local_aiagentblock
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiagentblock\task;

/**
 * Cleanup old detection logs
 */
class cleanup_old_logs extends \core\task\scheduled_task {
    
    /**
     * Get a descriptive name for this task
     *
     * @return string
     */
    public function get_name() {
        return get_string('cleanup_old_logs', 'local_aiagentblock');
    }
    
    /**
     * Execute the task
     */
    public function execute() {
        global $DB;
        
        // Default to 90 days retention
        $days = get_config('local_aiagentblock', 'log_retention_days') ?: 90;
        $cutoff = time() - ($days * 24 * 60 * 60);
        
        // Count records to be deleted
        $count = $DB->count_records_select('local_aiagentblock_log', 
            'timecreated < ?', [$cutoff]);
        
        if ($count > 0) {
            // Delete old records
            $DB->delete_records_select('local_aiagentblock_log', 
                'timecreated < ?', [$cutoff]);
            
            mtrace("Deleted {$count} old AI agent detection records (older than {$days} days)");
        } else {
            mtrace("No old AI agent detection records to delete");
        }
    }
}

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
 * Upgrade script for AI Agent Blocker plugin
 *
 * @package    local_aiagentblock
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute AI Agent Blocker upgrade from the given old version
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_aiagentblock_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Add suspicion_score column if it doesn't exist
    if ($oldversion < 2025120501) {
        $table = new xmldb_table('local_aiagentblock_log');
        
        $field = new xmldb_field('suspicion_score', XMLDB_TYPE_INTEGER, '3', null, 
            XMLDB_NOTNULL, null, '0', 'ip_address');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2025120501, 'local', 'aiagentblock');
    }
    
    // Clean up any records with invalid data and set defaults
    if ($oldversion < 2025120502) {
        // Delete records where courseid doesn't exist anymore
        $DB->execute("
            DELETE FROM {local_aiagentblock_log}
            WHERE courseid NOT IN (SELECT id FROM {course})
        ");
        
        // Delete records where userid doesn't exist anymore
        $DB->execute("
            DELETE FROM {local_aiagentblock_log}
            WHERE userid NOT IN (SELECT id FROM {user})
        ");
        
        // Delete records where contextid doesn't exist anymore
        $DB->execute("
            DELETE FROM {local_aiagentblock_log}
            WHERE contextid NOT IN (SELECT id FROM {context})
        ");
        
        // Set default suspicion_score for old records that don't have it
        $DB->execute("
            UPDATE {local_aiagentblock_log}
            SET suspicion_score = 0
            WHERE suspicion_score IS NULL
        ");
        
        // Ensure browser field is not null
        $DB->execute("
            UPDATE {local_aiagentblock_log}
            SET browser = 'Unknown'
            WHERE browser IS NULL OR browser = ''
        ");
        
        upgrade_plugin_savepoint(true, 2025120502, 'local', 'aiagentblock');
    }
    
    // Add comprehensive behavioral analysis columns
    if ($oldversion < 2025120503) {
        $table = new xmldb_table('local_aiagentblock_log');
        
        // Browser details
        $field = new xmldb_field('browser_version', XMLDB_TYPE_CHAR, '50', null, 
            null, null, null, 'browser');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('os', XMLDB_TYPE_CHAR, '100', null, 
            null, null, null, 'browser_version');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('device_type', XMLDB_TYPE_CHAR, '50', null, 
            null, null, null, 'os');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Quiz metrics
        $field = new xmldb_field('duration_seconds', XMLDB_TYPE_INTEGER, '10', null, 
            null, null, null, 'protection_level');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('duration_minutes', XMLDB_TYPE_NUMBER, '10, 2', null, 
            null, null, null, 'duration_seconds');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('question_count', XMLDB_TYPE_INTEGER, '5', null, 
            null, null, null, 'duration_minutes');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('grade_percent', XMLDB_TYPE_NUMBER, '5, 2', null, 
            null, null, null, 'question_count');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('answer_changes', XMLDB_TYPE_INTEGER, '5', null, 
            null, null, null, 'grade_percent');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Timing analysis metrics
        $field = new xmldb_field('timing_variance', XMLDB_TYPE_NUMBER, '10, 2', null, 
            null, null, null, 'answer_changes');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('timing_std_dev', XMLDB_TYPE_NUMBER, '10, 2', null, 
            null, null, null, 'timing_variance');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('timing_mean', XMLDB_TYPE_NUMBER, '10, 2', null, 
            null, null, null, 'timing_std_dev');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('sequential_order', XMLDB_TYPE_INTEGER, '1', null, 
            null, null, '0', 'timing_mean');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Detection details
        $field = new xmldb_field('detection_reasons', XMLDB_TYPE_TEXT, null, null, 
            null, null, null, 'sequential_order');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('behavior_flags', XMLDB_TYPE_TEXT, null, null, 
            null, null, null, 'detection_reasons');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        upgrade_plugin_savepoint(true, 2025120503, 'local', 'aiagentblock');
    }

    return true;
}

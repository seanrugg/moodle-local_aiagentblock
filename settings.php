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
 * Site administration settings for AI Agent Blocker plugin
 *
 * @package    local_aiagentblock
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    
    // Create settings page
    $settings = new admin_settingpage(
        'local_aiagentblock',
        get_string('pluginname', 'local_aiagentblock')
    );
    
    // Add settings page to local plugins category
    $ADMIN->add('localplugins', $settings);
    
    // Header
    $settings->add(new admin_setting_heading(
        'local_aiagentblock/settings_header',
        get_string('settings_header', 'local_aiagentblock'),
        ''
    ));
    
    // Enable/Disable detection
    $settings->add(new admin_setting_configcheckbox(
        'local_aiagentblock/enabled',
        get_string('enabled', 'local_aiagentblock'),
        get_string('enabled_desc', 'local_aiagentblock'),
        1
    ));
    
    // Log detections
    $settings->add(new admin_setting_configcheckbox(
        'local_aiagentblock/log_detections',
        get_string('log_detections', 'local_aiagentblock'),
        get_string('log_detections_desc', 'local_aiagentblock'),
        1
    ));
    
    // Notify administrators
    $settings->add(new admin_setting_configcheckbox(
        'local_aiagentblock/notify_admin',
        get_string('notify_admin', 'local_aiagentblock'),
        get_string('notify_admin_desc', 'local_aiagentblock'),
        0
    ));
    
    // Check for missing headers
    $settings->add(new admin_setting_configcheckbox(
        'local_aiagentblock/check_missing_headers',
        get_string('check_missing_headers', 'local_aiagentblock'),
        get_string('check_missing_headers_desc', 'local_aiagentblock'),
        1
    ));
    
    // Block access immediately
    $settings->add(new admin_setting_configcheckbox(
        'local_aiagentblock/block_access',
        get_string('block_access', 'local_aiagentblock'),
        get_string('block_access_desc', 'local_aiagentblock'),
        1
    ));
    
    // Custom blocking message
    $settings->add(new admin_setting_configtextarea(
        'local_aiagentblock/custom_message',
        get_string('custom_message', 'local_aiagentblock'),
        get_string('custom_message_desc', 'local_aiagentblock'),
        '',
        PARAM_TEXT,
        60,
        4
    ));
    
    // === DATA MANAGEMENT SECTION ===
    
    $settings->add(new admin_setting_heading(
        'local_aiagentblock/datamanagement_header',
        get_string('datamanagement_header', 'local_aiagentblock'),
        ''
    ));
    
    // Automatic record deletion
    $settings->add(new admin_setting_configcheckbox(
        'local_aiagentblock/auto_delete_records',
        get_string('auto_delete_records', 'local_aiagentblock'),
        get_string('auto_delete_records_desc', 'local_aiagentblock'),
        0
    ));
    
    // Retention period in days
    $settings->add(new admin_setting_configtext(
        'local_aiagentblock/retention_days',
        get_string('retention_days', 'local_aiagentblock'),
        get_string('retention_days_desc', 'local_aiagentblock'),
        '90',
        PARAM_INT
    ));
    
    // Manual delete all records button
    $settings->add(new admin_setting_description(
        'local_aiagentblock/delete_records',
        get_string('delete_records', 'local_aiagentblock'),
        get_string('delete_records_desc', 'local_aiagentblock') . '<br><br>' .
        html_writer::link(
            new moodle_url('/local/aiagentblock/delete_records.php'),
            get_string('delete_all_records', 'local_aiagentblock'),
            ['class' => 'btn btn-danger']
        )
    ));
    
}

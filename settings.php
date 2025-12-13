<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    
    $settings = new admin_settingpage(
        'local_aiagentblock',
        get_string('pluginname', 'local_aiagentblock')
    );
    
    $ADMIN->add('localplugins', $settings);
    
    // ========== GENERAL SETTINGS ==========
    $settings->add(new admin_setting_heading(
        'local_aiagentblock/settings_header',
        get_string('settings_header', 'local_aiagentblock'),
        get_string('settings_header_desc', 'local_aiagentblock')
    ));
    
    // Enable/Disable detection
    $settings->add(new admin_setting_configcheckbox(
        'local_aiagentblock/enabled',
        get_string('enabled', 'local_aiagentblock'),
        get_string('enabled_desc', 'local_aiagentblock'),
        1
    ));
    
    // ========== DATA COLLECTION MODE ========== NEW!
    $settings->add(new admin_setting_configcheckbox(
        'local_aiagentblock/analysis_mode',
        get_string('analysis_mode', 'local_aiagentblock'),
        get_string('analysis_mode_desc', 'local_aiagentblock'),
        0
    ));
    
    // Block access immediately
    $settings->add(new admin_setting_configcheckbox(
        'local_aiagentblock/block_access',
        get_string('block_access', 'local_aiagentblock'),
        get_string('block_access_desc', 'local_aiagentblock'),
        1
    ));
    
    // ========== DETECTION METHODS ==========
    $settings->add(new admin_setting_heading(
        'local_aiagentblock/detection_methods_header',
        get_string('detection_methods_header', 'local_aiagentblock'),
        get_string('detection_methods_header_desc', 'local_aiagentblock')
    ));
    
    // User Agent Detection
    $settings->add(new admin_setting_configcheckbox(
        'local_aiagentblock/detect_user_agent',
        get_string('detect_user_agent', 'local_aiagentblock'),
        get_string('detect_user_agent_desc', 'local_aiagentblock'),
        1
    ));
    
    // HTTP Headers Detection
    $settings->add(new admin_setting_configcheckbox(
        'local_aiagentblock/check_missing_headers',
        get_string('check_missing_headers', 'local_aiagentblock'),
        get_string('check_missing_headers_desc', 'local_aiagentblock'),
        1
    ));
    
    // Canvas Fingerprinting
    $settings->add(new admin_setting_configcheckbox(
        'local_aiagentblock/detect_canvas',
        get_string('detect_canvas', 'local_aiagentblock'),
        get_string('detect_canvas_desc', 'local_aiagentblock'),
        1
    ));
    
    // Screenshot Detection
    $settings->add(new admin_setting_configcheckbox(
        'local_aiagentblock/detect_screenshots',
        get_string('detect_screenshots', 'local_aiagentblock'),
        get_string('detect_screenshots_desc', 'local_aiagentblock'),
        1
    ));
    
    // Mouse Movement Analysis
    $settings->add(new admin_setting_configcheckbox(
        'local_aiagentblock/detect_mouse_movement',
        get_string('detect_mouse_movement', 'local_aiagentblock'),
        get_string('detect_mouse_movement_desc', 'local_aiagentblock'),
        1
    ));
    
    // Timing Analysis
    $settings->add(new admin_setting_configcheckbox(
        'local_aiagentblock/detect_timing',
        get_string('detect_timing', 'local_aiagentblock'),
        get_string('detect_timing_desc', 'local_aiagentblock'),
        1
    ));
    
    // ========== THRESHOLDS ==========
    $settings->add(new admin_setting_heading(
        'local_aiagentblock/thresholds_header',
        get_string('thresholds_header', 'local_aiagentblock'),
        get_string('thresholds_header_desc', 'local_aiagentblock')
    ));
    
    // Suspicion Score Threshold
    $settings->add(new admin_setting_configselect(
        'local_aiagentblock/suspicion_threshold',
        get_string('suspicion_threshold', 'local_aiagentblock'),
        get_string('suspicion_threshold_desc', 'local_aiagentblock'),
        60,
        [
            40 => '40 - ' . get_string('threshold_very_sensitive', 'local_aiagentblock'),
            50 => '50 - ' . get_string('threshold_sensitive', 'local_aiagentblock'),
            60 => '60 - ' . get_string('threshold_moderate', 'local_aiagentblock') . ' (Recommended)',
            70 => '70 - ' . get_string('threshold_strict', 'local_aiagentblock'),
            80 => '80 - ' . get_string('threshold_very_strict', 'local_aiagentblock'),
        ]
    ));
    
    // Quiz Completion Speed Threshold (minutes)
    $settings->add(new admin_setting_configtext(
        'local_aiagentblock/quiz_speed_threshold',
        get_string('quiz_speed_threshold', 'local_aiagentblock'),
        get_string('quiz_speed_threshold_desc', 'local_aiagentblock'),
        3,
        PARAM_INT
    ));
    
    // ========== LOGGING & NOTIFICATIONS ==========
    $settings->add(new admin_setting_heading(
        'local_aiagentblock/logging_header',
        get_string('logging_header', 'local_aiagentblock'),
        get_string('logging_header_desc', 'local_aiagentblock')
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
    
    // ========== CUSTOMIZATION ==========
    $settings->add(new admin_setting_heading(
        'local_aiagentblock/customization_header',
        get_string('customization_header', 'local_aiagentblock'),
        ''
    ));
    
    // Custom blocking message
    $settings->add(new admin_setting_configtextarea(
        'local_aiagentblock/custom_message',
        get_string('custom_message', 'local_aiagentblock'),
        get_string('custom_message_desc', 'local_aiagentblock'),
        get_string('default_block_message', 'local_aiagentblock'),
        PARAM_TEXT,
        60,
        4
    ));
    
    // ========== DATA MANAGEMENT ==========
    $settings->add(new admin_setting_heading(
        'local_aiagentblock/data_management_header',
        get_string('data_management_header', 'local_aiagentblock'),
        get_string('data_management_header_desc', 'local_aiagentblock')
    ));
    
    // Automatically delete old records
    $settings->add(new admin_setting_configcheckbox(
        'local_aiagentblock/auto_delete_records',
        get_string('auto_delete_records', 'local_aiagentblock'),
        get_string('auto_delete_records_desc', 'local_aiagentblock'),
        0
    ));
    
    // Record retention days
    $settings->add(new admin_setting_configtext(
        'local_aiagentblock/retention_days',
        get_string('retention_days', 'local_aiagentblock'),
        get_string('retention_days_desc', 'local_aiagentblock'),
        90,
        PARAM_INT
    ));
    
    // Delete detection records button
    $settings->add(new admin_setting_description(
        'local_aiagentblock/delete_records',
        get_string('delete_detection_records', 'local_aiagentblock'),
        get_string('delete_records_warning', 'local_aiagentblock') . '<br><br>' .
        html_writer::div(
            html_writer::link(
                new moodle_url('/local/aiagentblock/delete_records.php'),
                get_string('delete_all_records_button', 'local_aiagentblock'),
                ['class' => 'btn btn-secondary']
            ),
            '',
            ['style' => 'margin-bottom: 2rem;']
        )
    ));
}

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
 * Library functions for AI Agent Blocker plugin
 *
 * @package    local_aiagentblock
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/aiagentblock/classes/detector.php');

/**
 * Hook called before page output
 * This is where we perform AI agent detection
 */
function local_aiagentblock_before_standard_html_head() {
    global $PAGE, $COURSE;
    
    // Don't check on login page or if user is not logged in
    if (!isloggedin() || isguestuser()) {
        return '';
    }
    
    // Check if detection is enabled site-wide
    if (!get_config('local_aiagentblock', 'enabled')) {
        return '';
    }
    
    // Check if protection should be applied in this context
    if (!local_aiagentblock_should_protect()) {
        return '';
    }
    
    // Perform detection
    if (\local_aiagentblock\detector::is_ai_agent()) {
        if (get_config('local_aiagentblock', 'block_access')) {
            \local_aiagentblock\detector::block_access();
        }
    }
    
    // Inject client-side detection JavaScript
    return \local_aiagentblock\detector::get_detection_js();
}

/**
 * Determine if AI agent protection should be active in the current context
 *
 * @return bool True if protection should be applied
 */
function local_aiagentblock_should_protect() {
    global $PAGE, $COURSE, $DB;
    
    $context = $PAGE->context;
    
    // Site-level pages (homepage, dashboard) - no protection by default
    if ($context->contextlevel == CONTEXT_SYSTEM) {
        return false;
    }
    
    // Course context
    if ($context->contextlevel == CONTEXT_COURSE) {
        $courseid = $COURSE->id;
        
        // Check course-level setting
        $coursesetting = $DB->get_record('local_aiagentblock_course', ['courseid' => $courseid]);
        
        if ($coursesetting) {
            return (bool)$coursesetting->enabled;
        }
        
        // Default to enabled if no setting exists
        return true;
    }
    
    // Module (activity) context
    if ($context->contextlevel == CONTEXT_MODULE) {
        $cm = $PAGE->cm;
        
        if (!$cm) {
            return false;
        }
        
        // Check activity-level setting first
        $activitysetting = $DB->get_record('local_aiagentblock_activity', ['cmid' => $cm->id]);
        
        if ($activitysetting) {
            // If activity has explicit setting
            if ($activitysetting->setting === 'enabled') {
                return true;
            } else if ($activitysetting->setting === 'disabled') {
                return false;
            }
            // If 'default', fall through to check course setting
        }
        
        // Check course-level setting
        $courseid = $cm->course;
        $coursesetting = $DB->get_record('local_aiagentblock_course', ['courseid' => $courseid]);
        
        if ($coursesetting) {
            return (bool)$coursesetting->enabled;
        }
        
        // Default to enabled if no settings exist
        return true;
    }
    
    // Other contexts - no protection
    return false;
}

/**
 * Get course-level protection setting
 *
 * @param int $courseid Course ID
 * @return bool True if protection is enabled for this course
 */
function local_aiagentblock_get_course_setting($courseid) {
    global $DB;
    
    $setting = $DB->get_record('local_aiagentblock_course', ['courseid' => $courseid]);
    
    if ($setting) {
        return (bool)$setting->enabled;
    }
    
    // Default to enabled
    return true;
}

/**
 * Set course-level protection setting
 *
 * @param int $courseid Course ID
 * @param bool $enabled Enable or disable protection
 * @return bool Success
 */
function local_aiagentblock_set_course_setting($courseid, $enabled) {
    global $DB, $USER;
    
    $existing = $DB->get_record('local_aiagentblock_course', ['courseid' => $courseid]);
    
    if ($existing) {
        $existing->enabled = $enabled ? 1 : 0;
        $existing->timemodified = time();
        $existing->usermodified = $USER->id;
        return $DB->update_record('local_aiagentblock_course', $existing);
    } else {
        $record = new stdClass();
        $record->courseid = $courseid;
        $record->enabled = $enabled ? 1 : 0;
        $record->timemodified = time();
        $record->usermodified = $USER->id;
        return $DB->insert_record('local_aiagentblock_course', $record);
    }
}

/**
 * Get activity-level protection setting
 *
 * @param int $cmid Course module ID
 * @return string 'default', 'enabled', or 'disabled'
 */
function local_aiagentblock_get_activity_setting($cmid) {
    global $DB;
    
    $setting = $DB->get_record('local_aiagentblock_activity', ['cmid' => $cmid]);
    
    if ($setting) {
        return $setting->setting;
    }
    
    return 'default';
}

/**
 * Set activity-level protection setting
 *
 * @param int $cmid Course module ID
 * @param string $setting 'default', 'enabled', or 'disabled'
 * @return bool Success
 */
function local_aiagentblock_set_activity_setting($cmid, $setting) {
    global $DB, $USER;
    
    // Validate setting value
    if (!in_array($setting, ['default', 'enabled', 'disabled'])) {
        return false;
    }
    
    $existing = $DB->get_record('local_aiagentblock_activity', ['cmid' => $cmid]);
    
    if ($existing) {
        $existing->setting = $setting;
        $existing->timemodified = time();
        $existing->usermodified = $USER->id;
        return $DB->update_record('local_aiagentblock_activity', $existing);
    } else {
        $record = new stdClass();
        $record->cmid = $cmid;
        $record->setting = $setting;
        $record->timemodified = time();
        $record->usermodified = $USER->id;
        return $DB->insert_record('local_aiagentblock_activity', $record);
    }
}

/**
 * Extends the course navigation to add AI Agent Protection link
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course object
 * @param context_course $context The course context
 */
function local_aiagentblock_extend_navigation_course($navigation, $course, $context) {
    global $PAGE;
    
    // Check if user has capability to manage course settings
    if (has_capability('local/aiagentblock:managecourse', $context)) {
        $url = new moodle_url('/local/aiagentblock/course_settings.php', ['id' => $course->id]);
        $node = navigation_node::create(
            get_string('nav_settings', 'local_aiagentblock'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'aiagentblock_settings',
            new pix_icon('i/settings', '')
        );
        $navigation->add_node($node);
    }
    
    // Check if user has capability to view reports
    if (has_capability('local/aiagentblock:viewreports', $context)) {
        $url = new moodle_url('/local/aiagentblock/report.php', ['id' => $course->id]);
        $node = navigation_node::create(
            get_string('nav_reports', 'local_aiagentblock'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'aiagentblock_reports',
            new pix_icon('i/report', '')
        );
        $navigation->add_node($node);
    }
}

/**
 * Add AI Agent Protection settings to activity settings form
 *
 * @param moodleform_mod $formwrapper The moodle quickforms wrapper object
 * @param MoodleQuickForm $mform The actual form object
 */
function local_aiagentblock_coursemodule_standard_elements($formwrapper, $mform) {
    global $COURSE;
    
    $context = context_course::instance($COURSE->id);
    
    // Only add if user has capability
    if (!has_capability('local/aiagentblock:manageactivity', $context)) {
        return;
    }
    
    // Only add for supported activity types
    $modname = $formwrapper->get_current()->modulename;
    if (!in_array($modname, ['assign', 'forum', 'quiz'])) {
        return;
    }
    
    // Add header
    $mform->addElement('header', 'aiagentblock_header', 
        get_string('activity_settings_header', 'local_aiagentblock'));
    
    // Add select dropdown
    $options = [
        'default' => get_string('activity_protection_default', 'local_aiagentblock'),
        'enabled' => get_string('activity_protection_enabled', 'local_aiagentblock'),
        'disabled' => get_string('activity_protection_disabled', 'local_aiagentblock'),
    ];
    
    $mform->addElement('select', 'aiagentblock_protection', 
        get_string('activity_protection', 'local_aiagentblock'), $options);
    $mform->addHelpButton('aiagentblock_protection', 'activity_protection', 'local_aiagentblock');
    $mform->setDefault('aiagentblock_protection', 'default');
}

/**
 * Save AI Agent Protection settings when activity is saved
 *
 * @param stdClass $data Data from the form
 * @param stdClass $course The course
 */
function local_aiagentblock_coursemodule_edit_post_actions($data, $course) {
    if (isset($data->aiagentblock_protection) && isset($data->coursemodule)) {
        local_aiagentblock_set_activity_setting(
            $data->coursemodule, 
            $data->aiagentblock_protection
        );
    }
    return $data;
}

/**
 * Load existing AI Agent Protection settings when editing an activity
 *
 * @param cm_info|stdClass $cm Course module info
 * @param stdClass $data Form data
 */
function local_aiagentblock_coursemodule_definition_after_data($formwrapper, $mform) {
    global $DB;
    
    $cm = $formwrapper->get_coursemodule();
    
    if ($cm) {
        $setting = local_aiagentblock_get_activity_setting($cm->id);
        $mform->setDefault('aiagentblock_protection', $setting);
    }
}

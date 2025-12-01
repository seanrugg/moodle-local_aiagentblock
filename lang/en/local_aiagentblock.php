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
 * English language strings for AI Agent Blocker plugin
 *
 * @package    local_aiagentblock
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin name
$string['pluginname'] = 'AI Agent Blocker';

// Capabilities
$string['aiagentblock:viewreports'] = 'View AI agent detection reports';
$string['aiagentblock:configure'] = 'Configure AI agent protection settings';
$string['aiagentblock:managecourse'] = 'Manage course-level AI agent protection';
$string['aiagentblock:manageactivity'] = 'Manage activity-level AI agent protection';

// Settings page
$string['settings_header'] = 'AI Agent Detection Settings';
$string['enabled'] = 'Enable AI Agent Detection';
$string['enabled_desc'] = 'Master switch to enable or disable AI agent detection site-wide. When disabled, no detection will occur.';
$string['log_detections'] = 'Log Detection Events';
$string['log_detections_desc'] = 'Record all AI agent detection events to the database for reporting.';
$string['notify_admin'] = 'Notify Administrators';
$string['notify_admin_desc'] = 'Send email notifications to site administrators when AI agents are detected.';
$string['check_missing_headers'] = 'Check for Missing Headers';
$string['check_missing_headers_desc'] = 'Flag requests that are missing standard browser headers as potential automation.';
$string['block_access'] = 'Block Access';
$string['block_access_desc'] = 'Immediately block detected AI agents from accessing content. If disabled, detections will only be logged.';
$string['custom_message'] = 'Custom Blocking Message';
$string['custom_message_desc'] = 'Custom message to display to blocked users. Leave empty for default message.';

// Blocking page
$string['access_denied'] = 'Access Denied';
$string['ai_agent_detected'] = 'AI agent activity has been detected. This course or activity does not permit AI agents to complete work on behalf of students.';
$string['contact_instructor'] = 'If you believe this is an error, please contact your instructor or course administrator.';
$string['detection_details'] = 'Detection Details';
$string['blocked_timestamp'] = 'Time: {$a}';
$string['blocked_reason'] = 'Reason: {$a}';

// Course settings
$string['course_settings_header'] = 'AI Agent Protection';
$string['course_protection_enabled'] = 'Enable AI Agent Protection for this Course';
$string['course_protection_enabled_help'] = 'When enabled, AI agents will be detected and blocked from completing coursework on behalf of students in this course. Disable this setting if your course requires students to use AI agents as part of the learning objectives.';
$string['course_protection_disabled'] = 'AI agent protection is currently disabled for this course';
$string['course_protection_enabled_notice'] = 'AI agent protection is currently enabled for this course';

// Activity settings
$string['activity_settings_header'] = 'AI Agent Protection';
$string['activity_protection'] = 'AI Agent Protection Setting';
$string['activity_protection_help'] = 'Control whether AI agents are blocked for this specific activity.';
$string['activity_protection_default'] = 'Use course default';
$string['activity_protection_enabled'] = 'Enable protection (block AI agents)';
$string['activity_protection_disabled'] = 'Disable protection (allow AI agents)';

// Reports
$string['report_title'] = 'AI Agent Detection Report';
$string['report_course_title'] = 'AI Agent Detections in {$a}';
$string['no_detections'] = 'No AI agent detections have been logged for this course.';
$string['detections_found'] = '{$a} detection(s) found';
$string['view_report'] = 'View AI Agent Detection Report';

// Report table headers
$string['col_username'] = 'Username';
$string['col_timestamp'] = 'Date/Time';
$string['col_ipaddress'] = 'IP Address';
$string['col_useragent'] = 'AI Agent';
$string['col_browser'] = 'Browser';
$string['col_location'] = 'Location';
$string['col_protectionlevel'] = 'Protection Level';
$string['col_detectionmethod'] = 'Detection Method';
$string['col_actions'] = 'Actions';

// Report values
$string['protection_level_course'] = 'Course-level';
$string['protection_level_activity'] = 'Activity-level';
$string['detection_method_user_agent'] = 'User Agent';
$string['detection_method_headers'] = 'HTTP Headers';
$string['detection_method_client_side'] = 'Client-side JavaScript';
$string['view_details'] = 'View Details';
$string['export_csv'] = 'Export to CSV';

// Detection methods
$string['detected_chatgpt'] = 'ChatGPT Browser Automation';
$string['detected_manus'] = 'Manus AI';
$string['detected_perplexity'] = 'Perplexity Comet';
$string['detected_generic'] = 'Generic AI Agent';
$string['detected_automation'] = 'Browser Automation Tool';

// Email notifications
$string['email_subject'] = 'AI Agent Detection Alert - {$a}';
$string['email_body'] = 'An AI agent has been detected attempting to access course content on behalf of a student.

Student: {$a->studentname} ({$a->username})
Email: {$a->email}
Course: {$a->coursename}
Location: {$a->location}
Detection Method: {$a->method}
User Agent: {$a->useragent}
IP Address: {$a->ipaddress}
Browser: {$a->browser}
Time: {$a->timestamp}

View full report: {$a->reporturl}';

// Privacy
$string['privacy:metadata:local_aiagentblock_log'] = 'Logs AI agent detection events';
$string['privacy:metadata:local_aiagentblock_log:userid'] = 'The ID of the user whose account was accessed by an AI agent';
$string['privacy:metadata:local_aiagentblock_log:courseid'] = 'The course where the detection occurred';
$string['privacy:metadata:local_aiagentblock_log:pageurl'] = 'The URL where the AI agent was detected';
$string['privacy:metadata:local_aiagentblock_log:user_agent'] = 'The user agent string of the AI agent';
$string['privacy:metadata:local_aiagentblock_log:browser'] = 'Browser information';
$string['privacy:metadata:local_aiagentblock_log:ip_address'] = 'The IP address from which the AI agent accessed the system';
$string['privacy:metadata:local_aiagentblock_log:timecreated'] = 'When the AI agent was detected';

// Errors
$string['error_no_permission'] = 'You do not have permission to view this report.';
$string['error_invalid_course'] = 'Invalid course ID.';
$string['error_no_course_context'] = 'Could not determine course context.';
$string['error_save_failed'] = 'Failed to save settings. Please try again.';

// Success messages
$string['settings_saved'] = 'Settings saved successfully.';
$string['course_protection_updated'] = 'Course protection settings updated.';
$string['activity_protection_updated'] = 'Activity protection settings updated.';

// Navigation
$string['nav_reports'] = 'AI Agent Detections';
$string['nav_settings'] = 'AI Agent Protection Settings';

// Help text
$string['help_course_disable'] = 'Disable protection if this course requires students to use AI agents as learning tools.';
$string['help_activity_disable'] = 'You can disable protection for specific activities while keeping it enabled for the rest of the course.';
$string['help_legitimate_use'] = 'Some courses may require AI agent usage. Examples include: AI literacy courses, research projects involving AI tools, or collaborative AI assignments.';

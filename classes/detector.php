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
 * AI Agent Detection Class
 *
 * @package    local_aiagentblock
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiagentblock;

defined('MOODLE_INTERNAL') || die();

/**
 * Main detector class for AI agent detection
 */
class detector {
    
    /**
     * Known AI agent user agent patterns
     */
    private static $agent_patterns = [
        // ChatGPT/OpenAI agents
        '/ChatGPT-User/i',
        '/OpenAI/i',
        '/GPTBot/i',
        '/gpt-crawler/i',
        
        // Manus AI
        '/Manus/i',
        '/ManusAI/i',
        '/Manus-Agent/i',
        
        // Perplexity Comet
        '/Comet/i',
        '/PerplexityBot/i',
        '/Perplexity/i',
        '/PerplexityAI/i',
        
        // Anthropic Claude
        '/anthropic/i',
        '/claude-web/i',
        '/Claude-Agent/i',
        
        // Generic AI agent patterns
        '/AI-Agent/i',
        '/AIBot/i',
        '/automation-bot/i',
        
        // Common automation tools
        '/HeadlessChrome/i',
        '/PhantomJS/i',
    ];
    
    /**
     * Suspicious browser features that indicate automation
     */
    private static $automation_indicators = [
        'webdriver',
        'selenium',
        'puppeteer',
        'playwright',
        'headless',
        'phantom',
        'automated',
    ];

    /**
     * Check if the current request is from an AI agent
     *
     * @return bool True if AI agent detected
     */
    public static function is_ai_agent() {
        global $CFG;
        
        // Check if blocking is enabled
        if (!get_config('local_aiagentblock', 'enabled')) {
            return false;
        }
        
        // Check user agent
        if (self::check_user_agent()) {
            self::log_detection('user_agent');
            return true;
        }
        
        // Check for automation indicators in headers
        if (self::check_headers()) {
            self::log_detection('headers');
            return true;
        }
        
        // Check JavaScript-based detection results
        if (self::check_client_detection()) {
            self::log_detection('client_side');
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if user agent string indicates an AI agent (public method for observer)
     *
     * @param string $user_agent User agent string to check
     * @return bool True if AI agent detected
     */
    public static function is_ai_user_agent($user_agent = null) {
        if ($user_agent === null) {
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }
        
        if (empty($user_agent)) {
            return false;
        }
        
        foreach (self::$agent_patterns as $pattern) {
            if (preg_match($pattern, $user_agent)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check user agent against known patterns
     *
     * @return bool True if AI agent detected
     */
    private static function check_user_agent() {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }
        
        return self::is_ai_user_agent($_SERVER['HTTP_USER_AGENT']);
    }
    
    /**
     * Check HTTP headers for automation indicators
     *
     * @return bool True if automation detected
     */
    private static function check_headers() {
        // Check for common automation headers
        $suspicious_headers = [
            'HTTP_WEBDRIVER',
            'HTTP_X_AUTOMATION',
            'HTTP_X_AUTOMATED_TOOL',
            'HTTP_SELENIUM',
            'HTTP_PUPPETEER',
        ];
        
        foreach ($suspicious_headers as $header) {
            if (isset($_SERVER[$header])) {
                return true;
            }
        }
        
        // Check for missing expected browser headers
        if (get_config('local_aiagentblock', 'check_missing_headers')) {
            $expected_headers = ['HTTP_ACCEPT_LANGUAGE', 'HTTP_ACCEPT_ENCODING'];
            foreach ($expected_headers as $header) {
                if (!isset($_SERVER[$header])) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check client-side detection results
     *
     * @return bool True if automation detected
     */
    private static function check_client_detection() {
        global $SESSION;
        
        if (isset($SESSION->ai_agent_detected) && $SESSION->ai_agent_detected) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Log AI agent detection
     *
     * @param string $method Detection method used
     */
    private static function log_detection($method) {
        global $USER, $DB, $PAGE, $COURSE;
        
        if (!get_config('local_aiagentblock', 'log_detections')) {
            return;
        }
        
        // Determine protection level
        $protection_level = 'course';
        $cmid = null;
        
        if ($PAGE->context->contextlevel == CONTEXT_MODULE && $PAGE->cm) {
            $activitysetting = $DB->get_record('local_aiagentblock_activity', ['cmid' => $PAGE->cm->id]);
            if ($activitysetting && $activitysetting->setting !== 'default') {
                $protection_level = 'activity';
            }
            $cmid = $PAGE->cm->id;
        }
        
        // Parse browser information from user agent
        $browser = self::parse_browser($_SERVER['HTTP_USER_AGENT'] ?? '');
        
        $record = new \stdClass();
        $record->userid = $USER->id;
        $record->courseid = $COURSE->id;
        $record->contextid = $PAGE->context->id;
        $record->cmid = $cmid;
        $record->pageurl = qualified_me();
        $record->protection_level = $protection_level;
        $record->detection_method = $method;
        $record->user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $record->browser = $browser;
        $record->ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $record->suspicion_score = 50; // Default score for basic detection
        $record->timecreated = time();
        
        $DB->insert_record('local_aiagentblock_log', $record);
        
        // Send notification to admin if configured
        if (get_config('local_aiagentblock', 'notify_admin')) {
            self::notify_admin($record);
        }
    }
    
    /**
     * Parse browser information from user agent string
     *
     * @param string $useragent User agent string
     * @return string Browser name and version
     */
    private static function parse_browser($useragent) {
        if (empty($useragent)) {
            return 'Unknown';
        }
        
        // Detect AI agents first
        if (preg_match('/ChatGPT|OpenAI|GPTBot/i', $useragent)) {
            return 'ChatGPT Agent';
        }
        if (preg_match('/Manus/i', $useragent)) {
            return 'Manus AI';
        }
        if (preg_match('/Comet|Perplexity/i', $useragent)) {
            return 'Perplexity Comet';
        }
        if (preg_match('/anthropic|claude/i', $useragent)) {
            return 'Claude Agent';
        }
        
        // Detect automation tools
        if (preg_match('/HeadlessChrome/i', $useragent)) {
            return 'Headless Chrome';
        }
        if (preg_match('/PhantomJS/i', $useragent)) {
            return 'PhantomJS';
        }
        if (preg_match('/Selenium/i', $useragent)) {
            return 'Selenium';
        }
        if (preg_match('/Puppeteer/i', $useragent)) {
            return 'Puppeteer';
        }
        
        // Detect regular browsers
        if (preg_match('/Firefox\/([0-9.]+)/i', $useragent, $matches)) {
            return 'Firefox ' . $matches[1];
        }
        if (preg_match('/Chrome\/([0-9.]+)/i', $useragent, $matches)) {
            return 'Chrome ' . $matches[1];
        }
        if (preg_match('/Safari\/([0-9.]+)/i', $useragent, $matches)) {
            if (!preg_match('/Chrome/i', $useragent)) {
                return 'Safari ' . $matches[1];
            }
        }
        if (preg_match('/Edge\/([0-9.]+)/i', $useragent, $matches)) {
            return 'Edge ' . $matches[1];
        }
        
        return 'Unknown Browser';
    }
    
    /**
     * Notify administrators of AI agent detection
     *
     * @param stdClass $record Detection record
     */
    private static function notify_admin($record) {
        global $DB;
        
        $user = $DB->get_record('user', ['id' => $record->userid]);
        $course = $DB->get_record('course', ['id' => $record->courseid]);
        $admins = get_admins();
        
        // Determine location description
        $location = $course->fullname;
        if ($record->cmid) {
            $cm = get_coursemodule_from_id('', $record->cmid);
            if ($cm) {
                $location .= ' - ' . $cm->name;
            }
        }
        
        // Prepare email data
        $data = new \stdClass();
        $data->studentname = fullname($user);
        $data->username = $user->username;
        $data->email = $user->email;
        $data->coursename = $course->fullname;
        $data->location = $location;
        $data->method = $record->detection_method;
        $data->useragent = $record->user_agent;
        $data->ipaddress = $record->ip_address;
        $data->browser = $record->browser;
        $data->timestamp = userdate($record->timecreated);
        $data->reporturl = new \moodle_url('/local/aiagentblock/report.php', ['id' => $course->id]);
        
        $subject = get_string('email_subject', 'local_aiagentblock', $course->shortname);
        $message = get_string('email_body', 'local_aiagentblock', $data);
        
        foreach ($admins as $admin) {
            email_to_user($admin, $admin, $subject, $message);
        }
    }
    
    /**
     * Block access and show error page
     */
    public static function block_access() {
        global $OUTPUT, $PAGE, $CFG;
        
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url('/');
        $PAGE->set_title(get_string('access_denied', 'local_aiagentblock'));
        $PAGE->set_heading(get_string('access_denied', 'local_aiagentblock'));
        
        echo $OUTPUT->header();
        
        // Use custom message if configured
        $custom_message = get_config('local_aiagentblock', 'custom_message');
        if (!empty($custom_message)) {
            echo $OUTPUT->box($custom_message, 'generalbox');
        } else {
            echo $OUTPUT->heading(get_string('access_denied', 'local_aiagentblock'));
            echo $OUTPUT->box(get_string('ai_agent_detected', 'local_aiagentblock'), 'generalbox');
            echo $OUTPUT->box(get_string('contact_instructor', 'local_aiagentblock'), 'generalbox');
        }
        
        echo $OUTPUT->footer();
        
        die();
    }
    
    /**
     * Identify specific AI agent from user agent string
     *
     * @param string $useragent User agent string
     * @return string Human-readable agent name
     */
    public static function identify_agent($useragent) {
        if (preg_match('/ChatGPT|OpenAI|GPTBot/i', $useragent)) {
            return get_string('detected_chatgpt', 'local_aiagentblock');
        }
        if (preg_match('/Manus/i', $useragent)) {
            return get_string('detected_manus', 'local_aiagentblock');
        }
        if (preg_match('/Comet|Perplexity/i', $useragent)) {
            return get_string('detected_perplexity', 'local_aiagentblock');
        }
        if (preg_match('/HeadlessChrome|PhantomJS|Selenium|Puppeteer/i', $useragent)) {
            return get_string('detected_automation', 'local_aiagentblock');
        }
        
        return get_string('detected_generic', 'local_aiagentblock');
    }
}

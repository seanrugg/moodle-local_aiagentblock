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
     * Check user agent against known patterns
     *
     * @return bool True if AI agent detected
     */
    private static function check_user_agent() {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }
        
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        
        foreach (self::$agent_patterns as $pattern) {
            if (preg_match($pattern, $user_agent)) {
                return true;
            }
        }
        
        return false;
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
     * Get JavaScript for client-side detection with weighted scoring
     *
     * @return string JavaScript code
     */
    public static function get_detection_js() {
        global $CFG;
        
        $detecturl = new \moodle_url('/local/aiagentblock/detect.php');
        
        return <<<'EOD'
<script>
(function() {
    'use strict';
    
    let suspicionScore = 0;
    let detectionReasons = [];
    
    // PHASE 1: IMMEDIATE DETECTION (No Delay)
    // Run as soon as script loads
    
    // === CANVAS FINGERPRINTING (40 points) ===
    function checkCanvasFingerprint() {
        try {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            if (!ctx) {
                return { detected: true, score: 40, reason: 'no_canvas_context' };
            }
            
            // Draw something and check if it renders properly
            ctx.textBaseline = 'top';
            ctx.font = '14px Arial';
            ctx.textBaseline = 'alphabetic';
            ctx.fillStyle = '#f60';
            ctx.fillRect(125, 1, 62, 20);
            ctx.fillStyle = '#069';
            ctx.fillText('Moodle Test', 2, 15);
            ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
            ctx.fillText('Moodle Test', 4, 17);
            
            const dataURL = canvas.toDataURL();
            
            // Some automation tools return empty or generic canvas
            if (dataURL === 'data:,' || dataURL.length < 100) {
                return { detected: true, score: 40, reason: 'canvas_empty' };
            }
            
            return { detected: false, score: 0 };
        } catch (e) {
            return { detected: true, score: 40, reason: 'canvas_error' };
        }
    }
    
    // === SCREENSHOT DETECTION: Screen Capture API (50 points) ===
    function interceptScreenCaptureAPI() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) {
            return;
        }
        
        const originalGetDisplayMedia = navigator.mediaDevices.getDisplayMedia.bind(navigator.mediaDevices);
        
        navigator.mediaDevices.getDisplayMedia = function(...args) {
            suspicionScore += 50;
            detectionReasons.push('screen_capture_api_called');
            reportImmediately();
            return originalGetDisplayMedia(...args);
        };
    }
    
    // === SCREENSHOT DETECTION: MediaRecorder (40 points) ===
    function interceptMediaRecorder() {
        if (!window.MediaRecorder) {
            return;
        }
        
        const OriginalMediaRecorder = window.MediaRecorder;
        
        window.MediaRecorder = function(...args) {
            suspicionScore += 40;
            detectionReasons.push('media_recorder_instantiated');
            reportImmediately();
            return new OriginalMediaRecorder(...args);
        };
        
        window.MediaRecorder.prototype = OriginalMediaRecorder.prototype;
    }
    
    // === SCREENSHOT DETECTION: Display Capture Permission (45 points) ===
    async function checkDisplayCapturePermission() {
        if (!navigator.permissions || !navigator.permissions.query) {
            return;
        }
        
        try {
            const permissionStatus = await navigator.permissions.query({ 
                name: 'display-capture' 
            });
            
            if (permissionStatus.state === 'granted') {
                suspicionScore += 45;
                detectionReasons.push('display_capture_permission_granted');
                reportImmediately();
            }
            
            permissionStatus.addEventListener('change', () => {
                if (permissionStatus.state === 'granted') {
                    suspicionScore += 45;
                    detectionReasons.push('display_capture_permission_changed');
                    reportImmediately();
                }
            });
        } catch (e) {
            // Permission query not supported
        }
    }
    
    // === SCREENSHOT DETECTION: Screenshot Libraries (35 points) ===
    function detectScreenshotLibraries() {
        const knownLibraries = [
            'html2canvas',
            'dom-to-image',
            'domtoimage',
            'rasterizeHTML',
            'html2image'
        ];
        
        for (const lib of knownLibraries) {
            if (window[lib]) {
                suspicionScore += 35;
                detectionReasons.push('screenshot_library_' + lib);
            }
        }
    }
    
    // === WEIGHTED DETECTION CHECKS ===
    const weightedChecks = {
        // High confidence indicators (40-50 points)
        webdriver: { 
            check: () => navigator.webdriver === true, 
            weight: 50 
        },
        automationProperty: {
            check: () => {
                const props = [
                    'webdriver', '__webdriver_evaluate', '__selenium_evaluate',
                    '__webdriver_script_function', '__driver_evaluate',
                    '__webdriver_unwrapped', '__driver_unwrapped',
                    '_Selenium_IDE_Recorder', '_selenium', 'callSelenium',
                    '$cdc_', '$chrome_asyncScriptInfo', '__$webdriverAsyncExecutor',
                    '__perplexity__', '__comet__', 'perplexityAgent', 'cometAgent'
                ];
                for (let prop of props) {
                    if (window[prop] || document[prop]) return true;
                }
                return false;
            },
            weight: 50
        },
        perplexityElements: {
            check: () => {
                const elements = document.querySelectorAll(
                    '[class*="perplexity"], [class*="comet"], ' +
                    '[data-perplexity], [data-comet], ' +
                    '[id*="perplexity"], [id*="comet"]'
                );
                return elements.length > 0;
            },
            weight: 45
        },
        
        // Medium confidence indicators (20-35 points)
        agentOverlay: {
            check: () => {
                const overlays = document.querySelectorAll(
                    '[class*="agent"], [class*="assistant"], ' +
                    '[id*="agent"], [id*="assistant"]'
                );
                return Array.from(overlays).some(el => {
                    const style = window.getComputedStyle(el);
                    return style.position === 'fixed' || style.position === 'absolute';
                });
            },
            weight: 35
        },
        noPlugins: {
            check: () => navigator.plugins && navigator.plugins.length === 0,
            weight: 25
        },
        headlessUA: {
            check: () => /HeadlessChrome|PhantomJS|Selenium|Puppeteer/i.test(navigator.userAgent),
            weight: 30
        },
        
        // Lower confidence indicators (15-20 points)
        noLanguages: {
            check: () => !navigator.languages || navigator.languages.length === 0,
            weight: 15
        },
        chromeWithoutChrome: {
            check: () => !window.chrome && navigator.userAgent.includes('Chrome'),
            weight: 15
        },
        noPermissions: {
            check: () => navigator.permissions === undefined,
            weight: 15
        }
    };
    
    // Run all weighted checks immediately
    for (let checkName in weightedChecks) {
        try {
            if (weightedChecks[checkName].check()) {
                suspicionScore += weightedChecks[checkName].weight;
                detectionReasons.push(checkName + '_' + weightedChecks[checkName].weight);
            }
        } catch (e) {
            // Ignore errors in individual checks
        }
    }
    
    // Run canvas fingerprint check
    const canvasResult = checkCanvasFingerprint();
    if (canvasResult.detected) {
        suspicionScore += canvasResult.score;
        detectionReasons.push('canvas_' + canvasResult.reason);
    }
    
    // Run screenshot library detection
    detectScreenshotLibraries();
    
    // Setup screenshot API interception
    interceptScreenCaptureAPI();
    interceptMediaRecorder();
    checkDisplayCapturePermission();
    
    // PHASE 2: EVENT-TRIGGERED DETECTION
    
    // === Rapid Form Filling (30 points) ===
    let formInteractionTimes = [];
    document.addEventListener('input', function(e) {
        formInteractionTimes.push(Date.now());
        
        if (formInteractionTimes.length >= 3) {
            const timeDiff = formInteractionTimes[formInteractionTimes.length - 1] - formInteractionTimes[0];
            if (timeDiff < 500) {
                suspicionScore += 30;
                detectionReasons.push('rapid_form_filling_30');
                formInteractionTimes = [];
                reportImmediately();
            }
        }
    }, true);
    
    // === Canvas Monitoring (25 points) ===
    let canvasCreationCount = 0;
    let hiddenCanvasCount = 0;
    const originalCreateElement = document.createElement.bind(document);
    
    document.createElement = function(tagName) {
        const element = originalCreateElement(tagName);
        
        if (tagName && tagName.toLowerCase() === 'canvas') {
            canvasCreationCount++;
            
            setTimeout(() => {
                const computed = window.getComputedStyle(element);
                if (computed.display === 'none' || computed.visibility === 'hidden') {
                    hiddenCanvasCount++;
                    
                    if (hiddenCanvasCount >= 2) {
                        suspicionScore += 25;
                        detectionReasons.push('multiple_hidden_canvases_25');
                        reportImmediately();
                    }
                }
            }, 100);
            
            if (canvasCreationCount > 5) {
                suspicionScore += 20;
                detectionReasons.push('excessive_canvas_count_20');
                reportImmediately();
            }
        }
        
        return element;
    };
    
    // PHASE 3: CONTINUOUS MONITORING
    
    // === Mouse Movement Analysis (35 points for no movement) ===
    let mouseMoveCount = 0;
    let mouseStartTime = Date.now();
    
    document.addEventListener('mousemove', function() {
        mouseMoveCount++;
    });
    
    setTimeout(function() {
        if (mouseMoveCount === 0 && (Date.now() - mouseStartTime) > 5000) {
            suspicionScore += 35;
            detectionReasons.push('no_mouse_movement_35');
            reportImmediately();
        }
    }, 5000);
    
    // === Report Functions ===
    function reportImmediately() {
        if (suspicionScore >= 60) {
            sendReport();
        }
    }
    
    function sendReport() {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'EOD' . $detecturl->out(false) . <<<'EOD', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send('detection=1&score=' + suspicionScore + '&reasons=' + encodeURIComponent(detectionReasons.join(',')));
    }
    
    // IMMEDIATE REPORT if score already high from Phase 1
    if (suspicionScore >= 60) {
        sendReport();
    }
    
    // Also report after brief delay (catch slower indicators)
    setTimeout(function() {
        if (suspicionScore >= 60) {
            sendReport();
        }
    }, 1000);
    
})();
</script>
EOD;
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

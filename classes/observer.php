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
 * Event observers for AI Agent Blocker plugin
 * 
 * COMPREHENSIVE DATA COLLECTION MODE: Logs ALL quiz attempts for behavioral analysis
 *
 * @package    local_aiagentblock
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiagentblock;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer class for AI agent detection
 */
class observer {
    
    /**
     * Observe quiz attempt submitted event for timing analysis
     * 
     * DATA COLLECTION MODE: Logs ALL attempts regardless of suspicion score
     *
     * @param \mod_quiz\event\attempt_submitted $event
     */
    public static function quiz_attempt_submitted(\mod_quiz\event\attempt_submitted $event) {
        global $DB, $USER;
        
        // Skip if logging is disabled
        if (!get_config('local_aiagentblock', 'log_detections')) {
            return;
        }
        
        $attemptid = $event->objectid;
        $courseid = $event->courseid;
        $userid = $event->userid;
        $cmid = $event->contextinstanceid;
        
        // Get quiz attempt details
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid]);
        if (!$attempt) {
            return;
        }
        
        // Get quiz details
        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz]);
        if (!$quiz) {
            return;
        }
        
        // Calculate duration in seconds
        $duration = $attempt->timefinish - $attempt->timestart;
        $minutes = round($duration / 60, 1);
        
        // Get question count
        $questioncount = $DB->count_records('quiz_slots', ['quizid' => $quiz->id]);
        
        // Calculate grade percentage
        $grade_percent = 0;
        if ($quiz->sumgrades > 0) {
            $grade_percent = round(($attempt->sumgrades / $quiz->sumgrades) * 100, 2);
        }
        
        // Get question usage
        $quba = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);
        
        // Initialize metrics
        $answer_changes = 0;
        $question_times = [];
        $sequential_order = true;
        $last_answered_slot = 0;
        
        // Analyze each question
        foreach ($quba->get_slots() as $slot) {
            $qa = $quba->get_question_attempt($slot);
            
            // Count answer changes (steps > 1 means changes were made)
            $steps = $qa->get_step_iterator();
            $step_count = 0;
            $first_answer_time = null;
            $last_answer_time = null;
            
            foreach ($steps as $step) {
                if ($step->has_behaviour_var('answer')) {
                    $step_count++;
                    if ($first_answer_time === null) {
                        $first_answer_time = $step->get_timecreated();
                    }
                    $last_answer_time = $step->get_timecreated();
                }
            }
            
            if ($step_count > 1) {
                $answer_changes++;
            }
            
            // Calculate time spent on this question
            if ($first_answer_time !== null) {
                $question_time = $last_answer_time - $first_answer_time;
                $question_times[] = max(1, $question_time); // Minimum 1 second
                
                // Check if answered sequentially
                if ($slot < $last_answered_slot) {
                    $sequential_order = false;
                }
                $last_answered_slot = $slot;
            }
        }
        
        // Calculate timing statistics
        $timing_variance = null;
        $timing_std_dev = null;
        $timing_mean = null;
        
        if (count($question_times) > 1) {
            $timing_mean = array_sum($question_times) / count($question_times);
            
            $variance_sum = 0;
            foreach ($question_times as $time) {
                $variance_sum += pow($time - $timing_mean, 2);
            }
            $variance = $variance_sum / count($question_times);
            $timing_std_dev = sqrt($variance);
            
            // Coefficient of variation (CV) - more reliable than raw variance
            if ($timing_mean > 0) {
                $timing_variance = ($timing_std_dev / $timing_mean) * 100;
            }
        }
        
        // Initialize suspicion scoring
        $suspicion_score = 0;
        $reasons = [];
        $flags = [];
        
        // =================================================================
        // DETECTION LOGIC - More conservative thresholds for data collection
        // =================================================================
        
        // 1. IMPOSSIBLE COMPLETION TIME (truly impossible)
        $minimum_possible = $questioncount * 3; // 3 seconds per question minimum
        if ($duration < $minimum_possible) {
            $suspicion_score += 100;
            $reasons[] = 'impossible_completion_time';
            $flags[] = 'IMPOSSIBLE_TIME';
        }
        
        // 2. VERY FAST COMPLETION (suspicious but possible)
        $very_fast_threshold = $questioncount * 10; // 10 seconds per question
        $fast_threshold = $questioncount * 20; // 20 seconds per question
        
        if ($duration >= $minimum_possible && $duration < $very_fast_threshold) {
            $suspicion_score += 50;
            $reasons[] = 'very_fast_completion';
            $flags[] = 'VERY_FAST';
        } else if ($duration >= $very_fast_threshold && $duration < $fast_threshold) {
            $suspicion_score += 30;
            $reasons[] = 'fast_completion';
            $flags[] = 'FAST';
        } else {
            // Mark as normal speed for analysis
            $flags[] = 'NORMAL_SPEED';
        }
        
        // 3. NO ANSWER CORRECTIONS (only flag if ALSO fast)
        if ($answer_changes === 0 && $questioncount >= 3) {
            if ($duration < $fast_threshold) {
                $suspicion_score += 20;
                $reasons[] = 'no_corrections_and_fast';
                $flags[] = 'NO_CORRECTIONS';
            } else {
                // Just note it, don't add to score
                $flags[] = 'NO_CORRECTIONS_SLOW';
            }
        } else if ($answer_changes > 0) {
            $flags[] = 'HAS_CORRECTIONS';
        }
        
        // 4. EXTREMELY CONSISTENT TIMING (only flag if ALSO fast)
        // CV < 15% is very consistent, CV < 10% is extremely consistent
        if ($timing_variance !== null) {
            if ($timing_variance < 10 && $duration < $fast_threshold) {
                $suspicion_score += 30;
                $reasons[] = 'extremely_consistent_and_fast';
                $flags[] = 'VERY_LOW_VARIANCE';
            } else if ($timing_variance < 15 && $duration < $fast_threshold) {
                $suspicion_score += 15;
                $reasons[] = 'consistent_and_fast';
                $flags[] = 'LOW_VARIANCE';
            } else if ($timing_variance < 20) {
                // Just note it
                $flags[] = 'MODERATE_VARIANCE';
            } else if ($timing_variance < 40) {
                $flags[] = 'NORMAL_VARIANCE';
            } else {
                $flags[] = 'HIGH_VARIANCE';
            }
        }
        
        // 5. PERFECT SCORE + VERY FAST (high confidence answers)
        if ($grade_percent >= 95 && $duration < $very_fast_threshold) {
            $suspicion_score += 25;
            $reasons[] = 'perfect_score_very_fast';
            $flags[] = 'PERFECT_AND_FAST';
        } else if ($grade_percent >= 90 && $duration < $fast_threshold) {
            $suspicion_score += 10;
            $reasons[] = 'high_score_fast';
            $flags[] = 'HIGH_SCORE_FAST';
        } else if ($grade_percent >= 90) {
            $flags[] = 'HIGH_SCORE_NORMAL';
        }
        
        // 6. LOW SCORE + VERY FAST (likely guessing or giving up - less suspicious)
        if ($grade_percent < 50 && $duration < $very_fast_threshold) {
            $suspicion_score -= 20; // REDUCE score - probably not AI
            $flags[] = 'LOW_SCORE_FAST';
        } else if ($grade_percent < 50) {
            $flags[] = 'LOW_SCORE_NORMAL';
        }
        
        // 7. SEQUENTIAL ANSWERING - Track for analysis only
        if ($sequential_order) {
            $flags[] = 'SEQUENTIAL';
        } else {
            $flags[] = 'NON_SEQUENTIAL';
        }
        
        // 8. EXTREMELY HIGH VARIANCE (jumping around wildly - may indicate human)
        if ($timing_variance !== null && $timing_variance > 80) {
            $suspicion_score -= 10; // Reduce score - likely human
            $flags[] = 'EXTREME_VARIANCE';
        }
        
        // =================================================================
        // COLLECT USER AGENT AND BROWSER DATA
        // =================================================================
        
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // Parse browser information more thoroughly
        $browser_data = self::parse_browser_detailed($user_agent);
        
        // Get IP address
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        // Determine detection method
        $detection_method = 'timing_analysis';
        
        // Check if user agent indicates AI
        if (\local_aiagentblock\detector::is_ai_user_agent($user_agent)) {
            $detection_method = 'user_agent';
            $suspicion_score += 40; // Boost score significantly
            $flags[] = 'AI_USER_AGENT';
        }
        
        // =================================================================
        // LOG ALL ATTEMPTS FOR COMPREHENSIVE ANALYSIS
        // =================================================================
        
        // Build page URL to quiz review
        $pageurl = new \moodle_url('/mod/quiz/review.php', ['attempt' => $attemptid]);
        
        $log = new \stdClass();
        $log->userid = $userid;
        $log->courseid = $courseid;
        $log->contextid = $event->contextid;
        $log->cmid = $cmid;
        $log->pageurl = $pageurl->out(false);
        $log->user_agent = $user_agent;
        $log->browser = $browser_data['browser_name'];
        $log->browser_version = $browser_data['browser_version'];
        $log->os = $browser_data['os'];
        $log->device_type = $browser_data['device_type'];
        $log->ip_address = $ip_address;
        $log->suspicion_score = min(max($suspicion_score, 0), 100); // Clamp between 0-100
        $log->detection_method = $detection_method;
        $log->protection_level = 'course';
        
        // Store comprehensive metrics for analysis
        $log->duration_seconds = $duration;
        $log->duration_minutes = $minutes;
        $log->question_count = $questioncount;
        $log->grade_percent = $grade_percent;
        $log->answer_changes = $answer_changes;
        $log->timing_variance = $timing_variance;
        $log->timing_std_dev = $timing_std_dev;
        $log->timing_mean = $timing_mean;
        $log->sequential_order = $sequential_order ? 1 : 0;
        
        // Store reasons and flags as JSON for detailed analysis
        $log->detection_reasons = json_encode($reasons);
        $log->behavior_flags = json_encode($flags);
        
        $log->timecreated = time();
        
        // LOG EVERY ATTEMPT - No threshold check
        $DB->insert_record('local_aiagentblock_log', $log);
        
        // Only notify on very high-confidence detections (80+)
        if ($suspicion_score >= 80 && get_config('local_aiagentblock', 'notify_admin')) {
            self::notify_admin($log, $USER, $quiz);
        }
    }
    
    /**
     * Parse browser information with more detail
     *
     * @param string $user_agent
     * @return array Browser details
     */
    private static function parse_browser_detailed($user_agent) {
        $result = [
            'browser_name' => 'Unknown',
            'browser_version' => '',
            'os' => 'Unknown',
            'device_type' => 'Unknown'
        ];
        
        // Detect browser
        if (preg_match('/Edg\/([0-9.]+)/', $user_agent, $matches)) {
            $result['browser_name'] = 'Edge';
            $result['browser_version'] = $matches[1];
        } else if (preg_match('/Chrome\/([0-9.]+)/', $user_agent, $matches)) {
            $result['browser_name'] = 'Chrome';
            $result['browser_version'] = $matches[1];
        } else if (preg_match('/Firefox\/([0-9.]+)/', $user_agent, $matches)) {
            $result['browser_name'] = 'Firefox';
            $result['browser_version'] = $matches[1];
        } else if (preg_match('/Safari\/([0-9.]+)/', $user_agent, $matches)) {
            if (!preg_match('/Chrome/', $user_agent)) {
                $result['browser_name'] = 'Safari';
                $result['browser_version'] = $matches[1];
            }
        }
        
        // Detect OS
        if (preg_match('/Windows NT ([0-9.]+)/', $user_agent, $matches)) {
            $result['os'] = 'Windows ' . self::get_windows_version($matches[1]);
        } else if (preg_match('/Mac OS X ([0-9_]+)/', $user_agent, $matches)) {
            $result['os'] = 'macOS ' . str_replace('_', '.', $matches[1]);
        } else if (preg_match('/Linux/', $user_agent)) {
            $result['os'] = 'Linux';
        } else if (preg_match('/Android ([0-9.]+)/', $user_agent, $matches)) {
            $result['os'] = 'Android ' . $matches[1];
        } else if (preg_match('/iPhone OS ([0-9_]+)/', $user_agent, $matches)) {
            $result['os'] = 'iOS ' . str_replace('_', '.', $matches[1]);
        }
        
        // Detect device type
        if (preg_match('/Mobile|Android|iPhone|iPad/', $user_agent)) {
            $result['device_type'] = 'Mobile';
        } else if (preg_match('/Tablet|iPad/', $user_agent)) {
            $result['device_type'] = 'Tablet';
        } else {
            $result['device_type'] = 'Desktop';
        }
        
        return $result;
    }
    
    /**
     * Convert Windows NT version to readable name
     *
     * @param string $nt_version
     * @return string
     */
    private static function get_windows_version($nt_version) {
        $versions = [
            '10.0' => '10/11',
            '6.3' => '8.1',
            '6.2' => '8',
            '6.1' => '7',
            '6.0' => 'Vista',
            '5.1' => 'XP'
        ];
        
        return $versions[$nt_version] ?? $nt_version;
    }
    
    /**
     * Send notification to administrators
     *
     * @param stdClass $log
     * @param stdClass $user
     * @param stdClass $quiz
     */
    private static function notify_admin($log, $user, $quiz) {
        global $CFG, $DB;
        
        $course = $DB->get_record('course', ['id' => $log->courseid]);
        
        $admins = get_admins();
        foreach ($admins as $admin) {
            $message = new \core\message\message();
            $message->component = 'local_aiagentblock';
            $message->name = 'aidetection';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $admin;
            $message->subject = get_string('email_subject', 'local_aiagentblock', $course->fullname);
            $message->fullmessage = get_string('email_body', 'local_aiagentblock', [
                'studentname' => fullname($user),
                'username' => $user->username,
                'email' => $user->email,
                'coursename' => $course->fullname,
                'location' => $quiz->name,
                'method' => $log->detection_method,
                'useragent' => $log->user_agent,
                'ipaddress' => $log->ip_address,
                'browser' => $log->browser,
                'timestamp' => userdate($log->timecreated),
                'reporturl' => $CFG->wwwroot . '/local/aiagentblock/report.php?id=' . $log->courseid
            ]);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = '';
            $message->smallmessage = 'AI agent detected in ' . $course->fullname;
            $message->notification = 1;
            
            message_send($message);
        }
    }
}

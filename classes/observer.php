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
 * @package    local_aiagentblock
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiagentblock;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer class for detecting AI agent activity
 */
class observer {
    
    /**
     * Observer for quiz attempt submitted event
     * Analyzes completion time to detect AI agents
     *
     * @param \mod_quiz\event\attempt_submitted $event
     */
    public static function quiz_attempt_submitted(\mod_quiz\event\attempt_submitted $event) {
        global $DB;
        
        // Get the attempt data
        $attempt = $event->get_record_snapshot('quiz_attempts', $event->objectid);
        
        // Calculate completion time in seconds
        $duration = $attempt->timefinish - $attempt->timestart;
        $minutes = round($duration / 60, 1);
        
        // Get quiz info
        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', MUST_EXIST);
        
        // Get course module ID for the quiz
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $event->courseid);
        if (!$cm) {
            return; // Can't proceed without course module
        }
        
        // Count questions in quiz
        $questioncount = $DB->count_records('quiz_slots', ['quizid' => $quiz->id]);
        
        // Calculate suspicion score based on timing
        $suspicion_score = 0;
        $reasons = [];
        $threshold_triggered = '';
        
        // Impossible speed thresholds
        if ($minutes < 1) {
            $suspicion_score = 100;
            $reasons[] = 'completed_under_1min';
            $threshold_triggered = 'Critical: < 1 minute';
        }
        else if ($minutes < 2) {
            $suspicion_score = 90;
            $reasons[] = 'completed_under_2min';
            $threshold_triggered = 'Very High: < 2 minutes';
        }
        else if ($minutes < 3) {
            $suspicion_score = 70;
            $reasons[] = 'completed_under_3min';
            $threshold_triggered = 'High: < 3 minutes';
        }
        else if ($minutes < 5) {
            $suspicion_score = 50;
            $reasons[] = 'completed_under_5min';
            $threshold_triggered = 'Moderate: < 5 minutes';
        }
        
        // Per-question speed check
        if ($questioncount > 0) {
            $seconds_per_question = $duration / $questioncount;
            
            if ($seconds_per_question < 10) {
                $suspicion_score += 40;
                $reasons[] = 'less_than_10sec_per_question';
            }
            else if ($seconds_per_question < 20) {
                $suspicion_score += 30;
                $reasons[] = 'less_than_20sec_per_question';
            }
            else if ($seconds_per_question < 30) {
                $suspicion_score += 20;
                $reasons[] = 'less_than_30sec_per_question';
            }
        }
        
        // Perfect score on fast completion is extra suspicious
        $grade_percent = ($attempt->sumgrades / $quiz->sumgrades) * 100;
        if ($grade_percent >= 100 && $minutes < 3) {
            $suspicion_score += 20;
            $reasons[] = 'perfect_score_fast_completion';
        }
        
        // Log if suspicious (threshold: 50+)
        if ($suspicion_score >= 50) {
            self::log_timing_detection(
                $event->userid,
                $event->courseid,
                $event->contextid,
                $cm->id,
                $quiz->name,
                $suspicion_score,
                $reasons,
                $duration,
                $minutes,
                $questioncount,
                $grade_percent,
                $threshold_triggered
            );
        }
    }
    
    /**
     * Log timing-based detection to database
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @param int $contextid Context ID
     * @param int $cmid Course module ID
     * @param string $quizname Quiz name
     * @param int $score Suspicion score
     * @param array $reasons Detection reasons
     * @param int $duration Duration in seconds
     * @param float $minutes Duration in minutes
     * @param int $questioncount Number of questions
     * @param float $grade_percent Grade percentage
     * @param string $threshold Threshold description
     */
    private static function log_timing_detection($userid, $courseid, $contextid, $cmid, $quizname, 
                                                  $score, $reasons, $duration, $minutes, 
                                                  $questioncount, $grade_percent, $threshold) {
        global $DB;
        
        // Build page URL to the quiz review
        $pageurl = new \moodle_url('/mod/quiz/view.php', ['id' => $cmid]);
        
        // Create detailed user agent string with timing info
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // Create browser field with timing details
        $browser_info = sprintf(
            'Timing Analysis: %s min (%.0f sec) | %d questions | %.1f%% grade | %s',
            $minutes,
            $duration,
            $questioncount,
            $grade_percent,
            $threshold
        );
        
        $record = new \stdClass();
        $record->userid = $userid;
        $record->courseid = $courseid;
        $record->contextid = $contextid;
        $record->cmid = $cmid;
        $record->pageurl = $pageurl->out(false);
        $record->protection_level = 'course';
        $record->detection_method = 'timing_analysis';
        $record->user_agent = $user_agent;
        $record->browser = $browser_info;
        $record->ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $record->timecreated = time();
        
        $DB->insert_record('local_aiagentblock_log', $record);
        
        // Send admin notification if configured
        if (get_config('local_aiagentblock', 'notify_admin')) {
            self::notify_admin_timing_detection($userid, $courseid, $quizname, $minutes, 
                                                $grade_percent, $threshold, $pageurl);
        }
    }
    
    /**
     * Send admin notification for timing-based detection
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @param string $quizname Quiz name
     * @param float $minutes Completion time in minutes
     * @param float $grade Grade percentage
     * @param string $threshold Threshold triggered
     * @param \moodle_url $pageurl URL to quiz
     */
    private static function notify_admin_timing_detection($userid, $courseid, $quizname, 
                                                          $minutes, $grade, $threshold, $pageurl) {
        global $DB;
        
        $user = $DB->get_record('user', ['id' => $userid]);
        $course = $DB->get_record('course', ['id' => $courseid]);
        $admins = get_admins();
        
        $subject = get_string('email_subject', 'local_aiagentblock', $course->shortname);
        
        $message = "AI Agent detected via timing analysis\n\n";
        $message .= "Student: " . fullname($user) . " (" . $user->email . ")\n";
        $message .= "Course: " . $course->fullname . "\n";
        $message .= "Quiz: " . $quizname . "\n";
        $message .= "Completion Time: " . $minutes . " minutes\n";
        $message .= "Grade: " . round($grade, 1) . "%\n";
        $message .= "Threshold: " . $threshold . "\n";
        $message .= "Quiz URL: " . $pageurl->out(false) . "\n\n";
        $message .= "View report: " . (new \moodle_url('/local/aiagentblock/report.php', 
                                                       ['id' => $courseid]))->out(false);
        
        foreach ($admins as $admin) {
            email_to_user($admin, $admin, $subject, $message);
        }
    }
}

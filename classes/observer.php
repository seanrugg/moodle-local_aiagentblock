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
        
        // Get the attempt data (AFTER submission, so grades are calculated)
        $attempt = $DB->get_record('quiz_attempts', ['id' => $event->objectid], '*', MUST_EXIST);
        
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
        
        // Get the FINAL grade for this attempt (from quiz_grades table)
        $final_grade = $DB->get_record('quiz_grades', [
            'quiz' => $quiz->id,
            'userid' => $attempt->userid
        ]);
        
        // Calculate grade percentage
        $grade_percent = 0;
        if ($final_grade && $quiz->grade > 0) {
            $grade_percent = ($final_grade->grade / $quiz->grade) * 100;
        } else if ($quiz->sumgrades > 0 && $attempt->sumgrades > 0) {
            // Fallback: calculate from attempt grades
            $grade_percent = ($attempt->sumgrades / $quiz->sumgrades) * 100;
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
        if ($grade_percent >= 100 && $minutes < 3) {
            $suspicion_score += 20;
            $reasons[] = 'perfect_score_fast_completion';
        }
        
        // === QUICK WIN ENHANCEMENTS ===
        
        // 1. ANSWER CHANGE DETECTION (No corrections = suspicious)
        $answer_changes = self::count_answer_changes($attempt->uniqueid);
        if ($answer_changes === 0 && $questioncount >= 3) {
            $suspicion_score += 30;
            $reasons[] = 'no_answer_corrections';
        } else if ($answer_changes <= 1 && $questioncount >= 5) {
            $suspicion_score += 20;
            $reasons[] = 'minimal_corrections';
        }
        
        // 2. TIMING CONSISTENCY CHECK (Too consistent = robotic)
        $timing_variance = self::analyze_timing_consistency($attempt->uniqueid);
        if ($timing_variance !== null && $timing_variance < 5) {
            $suspicion_score += 35;
            $reasons[] = 'suspiciously_consistent_timing';
        } else if ($timing_variance !== null && $timing_variance < 10) {
            $suspicion_score += 20;
            $reasons[] = 'low_timing_variance';
        }
        
        // 3. SEQUENTIAL PATTERN (Perfect order = unusual)
        $answered_sequentially = self::check_sequential_pattern($attempt->uniqueid, $questioncount);
        if ($answered_sequentially && $questioncount >= 4) {
            $suspicion_score += 20;
            $reasons[] = 'perfect_sequential_answering';
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
                $threshold_triggered,
                $attempt->id  // Pass attempt ID for review link
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
     * @param int $attemptid Quiz attempt ID
     */
    private static function log_timing_detection($userid, $courseid, $contextid, $cmid, $quizname, 
                                                  $score, $reasons, $duration, $minutes, 
                                                  $questioncount, $grade_percent, $threshold, $attemptid) {
        global $DB;
        
        // Build page URL to the quiz attempt review
        $pageurl = new \moodle_url('/mod/quiz/review.php', [
            'attempt' => $attemptid
        ]);
        
        // Create detailed user agent string with timing info
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // Store suspicion score separately and create detailed browser info
        $browser_info = sprintf(
            'Time: %.1f min (%d sec) | %d questions | Grade: %.1f%% | %s | Reasons: %s',
            $minutes,
            $duration,
            $questioncount,
            $grade_percent,
            $threshold,
            implode(', ', $reasons)
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
        $record->suspicion_score = $score; // NEW: Store score in separate field
        $record->timecreated = time();
        
        $DB->insert_record('local_aiagentblock_log', $record);
        
        // Send admin notification if configured
        if (get_config('local_aiagentblock', 'notify_admin')) {
            self::notify_admin_timing_detection($userid, $courseid, $quizname, $minutes, 
                                                $grade_percent, $threshold, $pageurl);
        }
    }
    
    /**
     * Count how many times student changed their answers
     *
     * @param int $uniqueid Quiz attempt unique ID
     * @return int Number of answer changes
     */
    private static function count_answer_changes($uniqueid) {
        global $DB;
        
        // Get all question attempts for this quiz attempt
        $sql = "SELECT qa.id, COUNT(qas.id) as step_count
                FROM {question_attempts} qa
                JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                WHERE qa.questionusageid = :uniqueid
                AND qas.state != 'todo'
                AND qas.state != 'complete'
                GROUP BY qa.id
                HAVING COUNT(qas.id) > 1";
        
        $results = $DB->get_records_sql($sql, ['uniqueid' => $uniqueid]);
        
        // Each question with more than 1 step (excluding initial) = changed answer
        return count($results);
    }
    
    /**
     * Analyze timing consistency across questions
     * Returns variance in seconds - low variance = robotic
     *
     * @param int $uniqueid Quiz attempt unique ID
     * @return float|null Variance in timing, or null if can't calculate
     */
    private static function analyze_timing_consistency($uniqueid) {
        global $DB;
        
        // Get timestamps for each question's completion
        $sql = "SELECT qa.slot, MIN(qas.timecreated) as start_time, MAX(qas.timecreated) as end_time
                FROM {question_attempts} qa
                JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                WHERE qa.questionusageid = :uniqueid
                GROUP BY qa.slot
                ORDER BY qa.slot";
        
        $questions = $DB->get_records_sql($sql, ['uniqueid' => $uniqueid]);
        
        if (count($questions) < 3) {
            return null; // Need at least 3 questions to calculate variance
        }
        
        // Calculate time spent on each question
        $times = [];
        foreach ($questions as $q) {
            $time_spent = $q->end_time - $q->start_time;
            if ($time_spent > 0) { // Ignore questions answered instantly
                $times[] = $time_spent;
            }
        }
        
        if (count($times) < 3) {
            return null;
        }
        
        // Calculate variance
        $mean = array_sum($times) / count($times);
        $squared_diffs = array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $times);
        $variance = sqrt(array_sum($squared_diffs) / count($times));
        
        return $variance;
    }
    
    /**
     * Check if questions were answered in perfect sequential order
     *
     * @param int $uniqueid Quiz attempt unique ID
     * @param int $total_questions Total number of questions
     * @return bool True if answered in perfect order 1,2,3,4,5...
     */
    private static function check_sequential_pattern($uniqueid, $total_questions) {
        global $DB;
        
        if ($total_questions < 4) {
            return false; // Too few questions to judge
        }
        
        // Get the order in which questions were first answered
        $sql = "SELECT qa.slot, MIN(qas.timecreated) as first_answer
                FROM {question_attempts} qa
                JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                WHERE qa.questionusageid = :uniqueid
                AND qas.state != 'todo'
                GROUP BY qa.slot
                ORDER BY first_answer";
        
        $order = $DB->get_records_sql($sql, ['uniqueid' => $uniqueid]);
        
        // Check if slots are in perfect sequential order
        $expected_slot = 1;
        foreach ($order as $q) {
            if ($q->slot != $expected_slot) {
                return false; // Not sequential
            }
            $expected_slot++;
        }
        
        return true; // Perfect sequential order
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

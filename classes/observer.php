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
        
        // === ENHANCED DETECTION CHECKS ===
        
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
        $timing_result = self::analyze_timing_consistency($attempt->uniqueid);
        if ($timing_result !== null) {
            $timing_variance = $timing_result['cv_percent'];
            $question_pages = $timing_result['question_pages'];
            $total_pages = $timing_result['total_pages'];
            
            // Low variance = robotic (AI signature)
            if ($timing_variance < 5) {
                $suspicion_score += 35;
                $reasons[] = 'robotic_timing_cv_' . round($timing_variance, 1) . '%';
            } else if ($timing_variance < 10) {
                $suspicion_score += 20;
                $reasons[] = 'very_consistent_timing_cv_' . round($timing_variance, 1) . '%';
            }
        } else {
            $timing_variance = null;
            $question_pages = 0;
            $total_pages = 0;
        }
        
        // 3. SEQUENTIAL PATTERN (Perfect order = unusual)
        $answered_sequentially = self::check_sequential_pattern($attempt->uniqueid, $questioncount);
        if ($answered_sequentially && $questioncount >= 4) {
            $suspicion_score += 20;
            $reasons[] = 'perfect_sequential_answering';
        }
        
        // 4. INSTANT NAVIGATION (Review/submit pages clicked instantly)
        $instant_navigation_score = self::check_instant_navigation($attempt->uniqueid);
        if ($instant_navigation_score > 0) {
            $suspicion_score += $instant_navigation_score;
            $reasons[] = 'instant_navigation_clicks';
        }
        
        // Get interaction metrics
        $interaction_metrics = self::get_interaction_metrics($attempt->uniqueid);
        
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
                $attempt->id,
                $timing_variance,
                $question_pages,
                $total_pages,
                $interaction_metrics
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
     * @param float|null $timing_variance CV% for timing consistency
     * @param int $question_pages Number of question pages analyzed
     * @param int $total_pages Total pages in attempt
     * @param array $interaction_metrics Interaction step counts
     */
    private static function log_timing_detection($userid, $courseid, $contextid, $cmid, $quizname, 
                                                  $score, $reasons, $duration, $minutes, 
                                                  $questioncount, $grade_percent, $threshold, $attemptid,
                                                  $timing_variance, $question_pages, $total_pages, $interaction_metrics) {
        global $DB;
        
        // Build page URL to the quiz attempt review
        $pageurl = new \moodle_url('/mod/quiz/review.php', [
            'attempt' => $attemptid
        ]);
        
        // Create detailed user agent string with timing info
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // Create browser field with comprehensive timing details
        $browser_info = sprintf(
            'Time: %.1f min (%d sec) | Questions: %d | Pages: %d/%d | Steps: %d (%.1f/pg) | CV%%: %s | Grade: %.1f%% | %s | Reasons: %s',
            $minutes,
            $duration,
            $questioncount,
            $question_pages,
            $total_pages,
            $interaction_metrics['total_steps'],
            $interaction_metrics['steps_per_page'],
            $timing_variance !== null ? round($timing_variance, 1) . '%' : 'N/A',
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
        $record->suspicion_score = $score;
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
     * Analyze timing consistency across questions (FIXED VERSION)
     * Returns CV% and page counts - excludes navigation/submit pages
     *
     * @param int $uniqueid Quiz attempt unique ID
     * @return array|null Array with cv_percent, question_pages, total_pages, or null if can't calculate
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
        
        $total_pages = count($questions);
        
        if ($total_pages < 3) {
            return null; // Need at least 3 pages total
        }
        
        // Calculate time spent on each question
        $times = [];
        foreach ($questions as $q) {
            $time_spent = $q->end_time - $q->start_time;
            
            // *** FIX: Only include pages where student spent >= 10 seconds ***
            // This excludes review/submit pages (typically 2-5 seconds)
            if ($time_spent >= 10) {
                $times[] = $time_spent;
            }
        }
        
        $question_pages = count($times);
        
        if ($question_pages < 3) {
            return null; // Need at least 3 actual question pages to calculate variance
        }
        
        // Calculate variance (standard deviation)
        $mean = array_sum($times) / count($times);
        $squared_diffs = array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $times);
        $std_dev = sqrt(array_sum($squared_diffs) / count($times));
        
        // Calculate coefficient of variation as percentage
        $cv_percent = ($std_dev / $mean) * 100;
        
        return [
            'cv_percent' => $cv_percent,
            'question_pages' => $question_pages,
            'total_pages' => $total_pages,
            'mean_time' => $mean,
            'std_dev' => $std_dev
        ];
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
     * Check for instant navigation (clicking through review/submit pages in < 2 seconds)
     * AI agents often click through these pages instantly
     *
     * @param int $uniqueid Quiz attempt unique ID
     * @return int Suspicion score (0-25)
     */
    private static function check_instant_navigation($uniqueid) {
        global $DB;
        
        $sql = "SELECT qa.slot, MIN(qas.timecreated) as start_time, MAX(qas.timecreated) as end_time
                FROM {question_attempts} qa
                JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                WHERE qa.questionusageid = :uniqueid
                GROUP BY qa.slot
                ORDER BY qa.slot";
        
        $pages = $DB->get_records_sql($sql, ['uniqueid' => $uniqueid]);
        
        $instant_clicks = 0;
        foreach ($pages as $page) {
            $time = $page->end_time - $page->start_time;
            if ($time < 2) { // Less than 2 seconds
                $instant_clicks++;
            }
        }
        
        // AI agents often click through review/submit instantly
        if ($instant_clicks >= 3) {
            return 25;
        } else if ($instant_clicks >= 2) {
            return 15;
        }
        
        return 0;
    }
    
    /**
     * Get interaction metrics (total steps, steps per page)
     *
     * @param int $uniqueid Quiz attempt unique ID
     * @return array Array with total_steps, steps_per_page, total_pages
     */
    private static function get_interaction_metrics($uniqueid) {
        global $DB;
        
        // Count total interaction steps
        $sql = "SELECT COUNT(qas.id) as total_steps
                FROM {question_attempts} qa
                JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                WHERE qa.questionusageid = :uniqueid";
        
        $result = $DB->get_record_sql($sql, ['uniqueid' => $uniqueid]);
        $total_steps = $result ? $result->total_steps : 0;
        
        // Count total pages
        $sql_pages = "SELECT COUNT(DISTINCT qa.slot) as total_pages
                      FROM {question_attempts} qa
                      WHERE qa.questionusageid = :uniqueid";
        
        $result_pages = $DB->get_record_sql($sql_pages, ['uniqueid' => $uniqueid]);
        $total_pages = $result_pages ? $result_pages->total_pages : 1;
        
        $steps_per_page = $total_pages > 0 ? ($total_steps / $total_pages) : 0;
        
        return [
            'total_steps' => $total_steps,
            'steps_per_page' => round($steps_per_page, 1),
            'total_pages' => $total_pages
        ];
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

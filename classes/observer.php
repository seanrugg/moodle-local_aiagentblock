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
 * Event observers for AI Agent Blocker plugin - BEHAVIORAL ANALYSIS MODE
 *
 * @package    local_aiagentblock
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiagentblock;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer class for detecting AI agent activity
 * 
 * BEHAVIORAL ANALYSIS MODE:
 * - Logs ALL quiz attempts (not just suspicious ones)
 * - Comprehensive data collection for pattern analysis
 * - Calculates detailed behavioral metrics
 * - Enables statistical analysis and threshold refinement
 */
class observer {
    
    /**
     * Observer for quiz attempt submitted event
     * NOW LOGS ALL ATTEMPTS FOR BEHAVIORAL ANALYSIS
     *
     * @param \mod_quiz\event\attempt_submitted $event
     */
    public static function quiz_attempt_submitted(\mod_quiz\event\attempt_submitted $event) {
        global $DB;
        
        // Check if timing detection is enabled
        if (!get_config('local_aiagentblock', 'detect_timing')) {
            return; // Skip if timing detection is disabled
        }
        
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
        
        // === COMPREHENSIVE BEHAVIORAL ANALYSIS ===
        
        // 1. Answer Change Analysis
        $answer_changes = self::count_answer_changes($attempt->uniqueid);
        
        // 2. Timing Consistency Analysis
        $timing_result = self::analyze_timing_consistency($attempt->uniqueid);
        $cv_percent = $timing_result ? $timing_result['cv_percent'] : null;
        $test_pages = $timing_result ? $timing_result['question_pages'] : null;
        $total_pages = $timing_result ? $timing_result['total_pages'] : null;
        $mean_time = $timing_result ? $timing_result['mean_time'] : null;
        $std_dev = $timing_result ? $timing_result['std_dev'] : null;
        
        // 3. Sequential Pattern Analysis
        $answered_sequentially = self::check_sequential_pattern($attempt->uniqueid, $questioncount);
        
        // 4. Navigation Pattern Analysis
        $instant_navigation_count = self::count_instant_navigation($attempt->uniqueid);
        
        // 5. Interaction Metrics
        $interaction_metrics = self::get_interaction_metrics($attempt->uniqueid);
        
        // 6. Per-Question Timing Distribution
        $timing_distribution = self::get_timing_distribution($attempt->uniqueid);
        
        // === SUSPICION SCORING (FOR FLAGGING) ===
        
        $suspicion_score = 0;
        $reasons = [];
        $behavior_flags = [];
        
        // SPEED-BASED SCORING
        $seconds_per_question = $questioncount > 0 ? ($duration / $questioncount) : 0;
        
        if ($seconds_per_question < 3) {
            // Physically impossible
            $suspicion_score += 100;
            $reasons[] = 'impossible_speed_' . round($seconds_per_question, 1) . 's_per_q';
            $behavior_flags[] = 'IMPOSSIBLE_TIME';
        } else if ($seconds_per_question < 10) {
            // Very fast
            $suspicion_score += 50;
            $reasons[] = 'very_fast_' . round($seconds_per_question, 1) . 's_per_q';
            $behavior_flags[] = 'VERY_FAST';
        } else if ($seconds_per_question < 20) {
            // Fast
            $suspicion_score += 30;
            $reasons[] = 'fast_' . round($seconds_per_question, 1) . 's_per_q';
            $behavior_flags[] = 'FAST';
        }
        
        // TIMING CONSISTENCY SCORING
        if ($cv_percent !== null) {
            if ($cv_percent < 5) {
                // Robotic consistency
                $suspicion_score += 35;
                $reasons[] = 'robotic_timing_cv_' . round($cv_percent, 1) . '%';
                $behavior_flags[] = 'VERY_LOW_VARIANCE';
            } else if ($cv_percent < 10) {
                // Very consistent
                $suspicion_score += 20;
                $reasons[] = 'very_consistent_cv_' . round($cv_percent, 1) . '%';
                $behavior_flags[] = 'LOW_VARIANCE';
            } else if ($cv_percent >= 10 && $cv_percent < 30) {
                // Normal human variance
                $behavior_flags[] = 'MODERATE_VARIANCE';
            } else {
                // High variance
                $behavior_flags[] = 'HIGH_VARIANCE';
            }
        }
        
        // ANSWER CHANGE SCORING
        if ($answer_changes === 0 && $questioncount >= 3) {
            $suspicion_score += 30;
            $reasons[] = 'no_answer_corrections';
            $behavior_flags[] = 'NO_CORRECTIONS';
        } else if ($answer_changes <= 1 && $questioncount >= 5) {
            $suspicion_score += 20;
            $reasons[] = 'minimal_corrections';
            $behavior_flags[] = 'MINIMAL_CORRECTIONS';
        }
        
        // PERFECT SCORE + SPEED COMBINATION
        if ($grade_percent >= 100) {
            if ($minutes < 2) {
                $suspicion_score += 30;
                $reasons[] = 'perfect_and_very_fast';
                $behavior_flags[] = 'PERFECT_AND_FAST';
            } else if ($minutes < 5) {
                $suspicion_score += 15;
                $reasons[] = 'perfect_and_fast';
                $behavior_flags[] = 'HIGH_SCORE_FAST';
            }
        }
        
        // LOW SCORE + SPEED (probably guessing, less suspicious)
        if ($grade_percent < 50 && $seconds_per_question < 20) {
            // Reduce suspicion - probably just guessing/rushing
            $suspicion_score = max(0, $suspicion_score - 20);
            $behavior_flags[] = 'LOW_SCORE_FAST';
        }
        
        // INSTANT NAVIGATION SCORING
        if ($instant_navigation_count >= 3) {
            $suspicion_score += 25;
            $reasons[] = 'instant_navigation_' . $instant_navigation_count . '_pages';
            $behavior_flags[] = 'INSTANT_NAVIGATION';
        } else if ($instant_navigation_count >= 2) {
            $suspicion_score += 15;
            $reasons[] = 'quick_navigation_' . $instant_navigation_count . '_pages';
        }
        
        // SEQUENTIAL PATTERN (informational, not scored - most students do this)
        if ($answered_sequentially) {
            $behavior_flags[] = 'SEQUENTIAL';
        } else {
            $behavior_flags[] = 'NON_SEQUENTIAL';
        }
        
        // LOW INTERACTION SCORING
        if ($interaction_metrics['steps_per_page'] < 2.0 && $questioncount >= 3) {
            $suspicion_score += 20;
            $reasons[] = 'very_low_interaction_' . round($interaction_metrics['steps_per_page'], 1) . '_steps_per_page';
            $behavior_flags[] = 'VERY_LOW_INTERACTION';
        }
        
        // === CHECK USER AGENT FOR AI SIGNATURES ===
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (\local_aiagentblock\detector::is_ai_user_agent($user_agent)) {
            $suspicion_score += 50;
            $reasons[] = 'ai_user_agent_detected';
            $behavior_flags[] = 'AI_USER_AGENT';
        }
        
        // === CALCULATE THRESHOLD DESCRIPTION ===
        $threshold_triggered = self::get_threshold_description($suspicion_score, $seconds_per_question);
        
        // === LOG ALL ATTEMPTS (NOT JUST SUSPICIOUS ONES) ===
        // This is the key change for behavioral analysis mode
        
        self::log_timing_detection(
            $event->userid,
            $event->courseid,
            $event->contextid,
            $cm->id,
            $quiz->name,
            $suspicion_score,
            $reasons,
            $behavior_flags,
            $duration,
            $minutes,
            $questioncount,
            $grade_percent,
            $threshold_triggered,
            $attempt->id,
            $cv_percent,
            $test_pages,
            $total_pages,
            $interaction_metrics,
            $answer_changes,
            $answered_sequentially,
            $instant_navigation_count,
            $mean_time,
            $std_dev,
            $timing_distribution
        );
        
        // === OPTIONAL: SEND ADMIN NOTIFICATION FOR HIGH-SUSPICION ONLY ===
        if ($suspicion_score >= 70 && get_config('local_aiagentblock', 'notify_admin')) {
            self::notify_admin_timing_detection(
                $event->userid, 
                $event->courseid, 
                $quiz->name, 
                $minutes, 
                $grade_percent, 
                $threshold_triggered, 
                new \moodle_url('/mod/quiz/review.php', ['attempt' => $attempt->id])
            );
        }
    }
    
    /**
     * Get human-readable threshold description
     */
    private static function get_threshold_description($score, $seconds_per_question) {
        if ($seconds_per_question < 3) {
            return 'IMPOSSIBLE: < 3 sec/question';
        } else if ($seconds_per_question < 10) {
            return 'VERY FAST: < 10 sec/question';
        } else if ($seconds_per_question < 20) {
            return 'FAST: < 20 sec/question';
        } else if ($seconds_per_question < 30) {
            return 'MODERATE: < 30 sec/question';
        } else {
            return 'NORMAL: ≥ 30 sec/question';
        }
    }
    
    /**
     * Log timing-based detection to database
     * NOW LOGS ALL ATTEMPTS WITH COMPREHENSIVE METRICS
     */
    private static function log_timing_detection(
        $userid, $courseid, $contextid, $cmid, $quizname, 
        $score, $reasons, $behavior_flags, $duration, $minutes, 
        $questioncount, $grade_percent, $threshold, $attemptid,
        $cv_percent, $test_pages, $total_pages, $interaction_metrics,
        $answer_changes, $answered_sequentially, $instant_navigation_count,
        $mean_time, $std_dev, $timing_distribution
    ) {
        global $DB;
        
        // Build page URL to the quiz attempt review
        $pageurl = new \moodle_url('/mod/quiz/review.php', [
            'attempt' => $attemptid
        ]);
        
        // Get user agent
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // Create comprehensive browser field for backward compatibility
        $browser_info = sprintf(
            'Time: %.1f min (%d sec) | Questions: %d | Pages: %d/%d | Interactions: %d (%.1f/pg) | CV%%: %s | Mean: %ss | StdDev: %ss | Grade: %.1f%% | Changes: %d | Sequential: %s | InstantNav: %d | %s | Reasons: %s',
            $minutes,
            $duration,
            $questioncount,
            $test_pages ?? 0,
            $total_pages ?? 0,
            $interaction_metrics['total_steps'],
            $interaction_metrics['steps_per_page'],
            $cv_percent !== null ? round($cv_percent, 1) . '%' : 'N/A',
            $mean_time !== null ? round($mean_time) : 'N/A',
            $std_dev !== null ? round($std_dev) : 'N/A',
            $grade_percent,
            $answer_changes,
            $answered_sequentially ? 'Yes' : 'No',
            $instant_navigation_count,
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
        
        // Store metrics in dedicated columns
        $record->duration_seconds = $duration;
        $record->question_count = $questioncount;
        $record->grade_percent = round($grade_percent, 2);
        $record->cv_percent = $cv_percent !== null ? round($cv_percent, 2) : null;
        $record->test_pages = $test_pages;
        $record->total_pages = $total_pages;
        $record->total_steps = $interaction_metrics['total_steps'];
        $record->steps_per_page = round($interaction_metrics['steps_per_page'], 2);
        
        $record->timecreated = time();
        
        $DB->insert_record('local_aiagentblock_log', $record);
    }
    
    /**
     * Count how many times student changed their answers
     */
    private static function count_answer_changes($uniqueid) {
        global $DB;
        
        $sql = "SELECT qa.id, COUNT(qas.id) as step_count
                FROM {question_attempts} qa
                JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                WHERE qa.questionusageid = :uniqueid
                AND qas.state != 'todo'
                AND qas.state != 'complete'
                GROUP BY qa.id
                HAVING COUNT(qas.id) > 1";
        
        $results = $DB->get_records_sql($sql, ['uniqueid' => $uniqueid]);
        
        return count($results);
    }
    
    /**
     * Analyze timing consistency across questions
     * FIXED: Only includes pages ≥10 seconds (excludes review/submit pages)
     */
    private static function analyze_timing_consistency($uniqueid) {
        global $DB;
        
        $sql = "SELECT qa.slot, MIN(qas.timecreated) as start_time, MAX(qas.timecreated) as end_time
                FROM {question_attempts} qa
                JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                WHERE qa.questionusageid = :uniqueid
                GROUP BY qa.slot
                ORDER BY qa.slot";
        
        $questions = $DB->get_records_sql($sql, ['uniqueid' => $uniqueid]);
        
        $total_pages = count($questions);
        
        if ($total_pages < 3) {
            return null;
        }
        
        // Only include pages with ≥10 seconds (actual question pages)
        $times = [];
        foreach ($questions as $q) {
            $time_spent = $q->end_time - $q->start_time;
            if ($time_spent >= 10) {
                $times[] = $time_spent;
            }
        }
        
        $question_pages = count($times);
        
        if ($question_pages < 3) {
            return null;
        }
        
        $mean = array_sum($times) / count($times);
        $squared_diffs = array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $times);
        $std_dev = sqrt(array_sum($squared_diffs) / count($times));
        
        $cv_percent = ($std_dev / $mean) * 100;
        
        return [
            'cv_percent' => $cv_percent,
            'question_pages' => $question_pages,
            'total_pages' => $total_pages,
            'mean_time' => $mean,
            'std_dev' => $std_dev,
            'times' => $times
        ];
    }
    
    /**
     * Check if questions were answered in perfect sequential order
     */
    private static function check_sequential_pattern($uniqueid, $total_questions) {
        global $DB;
        
        if ($total_questions < 4) {
            return false;
        }
        
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
                return false;
            }
            $expected_slot++;
        }
        
        return true;
    }
    
    /**
     * Count instant navigation (pages < 2 seconds)
     * Returns count instead of score for better analysis
     */
    private static function count_instant_navigation($uniqueid) {
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
            if ($time < 2) {
                $instant_clicks++;
            }
        }
        
        return $instant_clicks;
    }
    
    /**
     * Get interaction metrics (total steps, steps per page)
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
     * Get detailed timing distribution for statistical analysis
     * Returns array of times spent on each question page
     */
    private static function get_timing_distribution($uniqueid) {
        global $DB;
        
        $sql = "SELECT qa.slot, MIN(qas.timecreated) as start_time, MAX(qas.timecreated) as end_time
                FROM {question_attempts} qa
                JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                WHERE qa.questionusageid = :uniqueid
                GROUP BY qa.slot
                ORDER BY qa.slot";
        
        $questions = $DB->get_records_sql($sql, ['uniqueid' => $uniqueid]);
        
        $distribution = [
            'min' => null,
            'max' => null,
            'median' => null,
            'q1' => null,
            'q3' => null,
            'all_times' => []
        ];
        
        if (empty($questions)) {
            return $distribution;
        }
        
        $times = [];
        foreach ($questions as $q) {
            $time = $q->end_time - $q->start_time;
            $times[] = $time;
        }
        
        sort($times);
        
        $distribution['all_times'] = $times;
        $distribution['min'] = min($times);
        $distribution['max'] = max($times);
        
        $count = count($times);
        if ($count > 0) {
            // Calculate median
            $mid = floor($count / 2);
            if ($count % 2 == 0) {
                $distribution['median'] = ($times[$mid - 1] + $times[$mid]) / 2;
            } else {
                $distribution['median'] = $times[$mid];
            }
            
            // Calculate Q1 and Q3
            $q1_pos = floor($count / 4);
            $q3_pos = floor(3 * $count / 4);
            $distribution['q1'] = $times[$q1_pos];
            $distribution['q3'] = $times[$q3_pos];
        }
        
        return $distribution;
    }
    
    /**
     * Send admin notification for timing-based detection
     * Only called for high-suspicion attempts (≥70)
     */
    private static function notify_admin_timing_detection($userid, $courseid, $quizname, 
                                                          $minutes, $grade, $threshold, $pageurl) {
        global $DB;
        
        $user = $DB->get_record('user', ['id' => $userid]);
        $course = $DB->get_record('course', ['id' => $courseid]);
        $admins = get_admins();
        
        $subject = get_string('email_subject', 'local_aiagentblock', $course->shortname);
        
        $message = "HIGH SUSPICION AI Agent detected via timing analysis\n\n";
        $message .= "Student: " . fullname($user) . " (" . $user->email . ")\n";
        $message .= "Course: " . $course->fullname . "\n";
        $message .= "Quiz: " . $quizname . "\n";
        $message .= "Completion Time: " . $minutes . " minutes\n";
        $message .= "Grade: " . round($grade, 1) . "%\n";
        $message .= "Threshold: " . $threshold . "\n";
        $message .= "Quiz URL: " . $pageurl->out(false) . "\n\n";
        $message .= "View full report: " . (new \moodle_url('/local/aiagentblock/report.php', 
                                                           ['id' => $courseid]))->out(false);
        
        foreach ($admins as $admin) {
            email_to_user($admin, $admin, $subject, $message);
        }
    }
}

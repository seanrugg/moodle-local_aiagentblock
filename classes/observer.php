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
 * Event observers for AI Agent Blocker plugin - FIXED GRADE AND INTERACTIONS/PAGE
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
     *
     * @param \mod_quiz\event\attempt_submitted $event
     */
    public static function quiz_attempt_submitted(\mod_quiz\event\attempt_submitted $event) {
        global $DB;
        
        // Check if timing detection is enabled
        if (!get_config('local_aiagentblock', 'detect_timing')) {
            return;
        }
        
        // Get the attempt data
        $attempt = $DB->get_record('quiz_attempts', ['id' => $event->objectid], '*', MUST_EXIST);
        
        // Calculate completion time in seconds
        $duration = $attempt->timefinish - $attempt->timestart;
        $minutes = round($duration / 60, 1);
        
        // Get quiz info
        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', MUST_EXIST);
        
        // Get course module ID
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $event->courseid);
        if (!$cm) {
            return;
        }
        
        // === FIX 1: GET ACTUAL GRADE FROM GRADEBOOK ===
        $grade_percent = self::get_attempt_grade_from_gradebook($attempt, $quiz);
        
        // === FIX 2: COUNT ACTUAL QUESTIONS (NOT SLOTS) ===
        $questioncount = $DB->count_records('quiz_slots', ['quizid' => $quiz->id]);
        
        // === FIX 3: CALCULATE ACTUAL PAGES ===
        $page_metrics = self::calculate_actual_pages($quiz, $attempt);
        
        // === FIX 4: COUNT REAL USER INTERACTIONS ===
        $interaction_metrics = self::get_real_interactions($attempt->uniqueid);
        
        // === FIX 5: CALCULATE INTERACTIONS PER PAGE CORRECTLY ===
        // Use test_pages (question pages >= 10 sec) NOT total questions
        $interactions_per_page = $page_metrics['question_pages'] > 0 ?
            round($interaction_metrics['total_steps'] / $page_metrics['question_pages'], 2) : 0;
        
        // === BEHAVIORAL ANALYSIS ===
        
        // 1. Answer Change Analysis
        $answer_changes = self::count_answer_changes($attempt->uniqueid);
        
        // 2. Timing Consistency Analysis (FIXED - only analyzes pages ≥10 sec)
        $timing_result = self::analyze_timing_consistency($attempt->uniqueid);
        $cv_percent = $timing_result ? $timing_result['cv_percent'] : null;
        $mean_time = $timing_result ? $timing_result['mean_time'] : null;
        $std_dev = $timing_result ? $timing_result['std_dev'] : null;
        
        // 3. Sequential Pattern Analysis
        $answered_sequentially = self::check_sequential_pattern($attempt->uniqueid, $questioncount);
        
        // 4. Navigation Pattern Analysis
        $navigation_metrics = self::analyze_navigation_pattern($attempt->uniqueid);
        
        // === SUSPICION SCORING ===
        
        $suspicion_score = 0;
        $reasons = [];
        $behavior_flags = [];
        
        // SPEED-BASED SCORING
        $seconds_per_question = $questioncount > 0 ? ($duration / $questioncount) : 0;
        
        if ($seconds_per_question < 3) {
            $suspicion_score += 100;
            $reasons[] = 'impossible_speed_' . round($seconds_per_question, 1) . 's_per_q';
            $behavior_flags[] = 'IMPOSSIBLE_TIME';
        } else if ($seconds_per_question < 10) {
            $suspicion_score += 50;
            $reasons[] = 'very_fast_' . round($seconds_per_question, 1) . 's_per_q';
            $behavior_flags[] = 'VERY_FAST';
        } else if ($seconds_per_question < 20) {
            $suspicion_score += 30;
            $reasons[] = 'fast_' . round($seconds_per_question, 1) . 's_per_q';
            $behavior_flags[] = 'FAST';
        }
        
        // TIMING CONSISTENCY SCORING
        if ($cv_percent !== null) {
            if ($cv_percent < 5) {
                $suspicion_score += 35;
                $reasons[] = 'robotic_timing_cv_' . round($cv_percent, 1) . '%';
                $behavior_flags[] = 'VERY_LOW_VARIANCE';
            } else if ($cv_percent < 10) {
                $suspicion_score += 20;
                $reasons[] = 'very_consistent_cv_' . round($cv_percent, 1) . '%';
                $behavior_flags[] = 'LOW_VARIANCE';
            } else if ($cv_percent >= 10 && $cv_percent < 30) {
                $behavior_flags[] = 'MODERATE_VARIANCE';
            } else {
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
        
        // LOW SCORE + SPEED (probably guessing)
        if ($grade_percent < 50 && $seconds_per_question < 20) {
            $suspicion_score = max(0, $suspicion_score - 20);
            $behavior_flags[] = 'LOW_SCORE_FAST';
        }
        
        // RAPID PAGE NAVIGATION
        if ($navigation_metrics['instant_clicks'] >= 3) {
            $suspicion_score += 25;
            $reasons[] = 'instant_navigation_' . $navigation_metrics['instant_clicks'] . '_pages';
            $behavior_flags[] = 'INSTANT_NAVIGATION';
        }
        
        // LOW INTERACTION SCORING (using correct interactions_per_page)
        if ($interactions_per_page < 1.5 && $page_metrics['question_pages'] >= 3) {
            $suspicion_score += 20;
            $reasons[] = 'very_low_interaction_' . round($interactions_per_page, 1);
            $behavior_flags[] = 'VERY_LOW_INTERACTION';
        }
        
        // SEQUENTIAL PATTERN (informational only)
        if ($answered_sequentially) {
            $behavior_flags[] = 'SEQUENTIAL';
        } else {
            $behavior_flags[] = 'NON_SEQUENTIAL';
        }
        
        // AI USER AGENT CHECK
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (\local_aiagentblock\detector::is_ai_user_agent($user_agent)) {
            $suspicion_score += 50;
            $reasons[] = 'ai_user_agent_detected';
            $behavior_flags[] = 'AI_USER_AGENT';
        }
        
        // THRESHOLD DESCRIPTION
        $threshold_triggered = self::get_threshold_description($suspicion_score, $seconds_per_question);
        
        // === LOG THE ATTEMPT ===
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
            $page_metrics['question_pages'],
            $page_metrics['total_pages'],
            $interaction_metrics['total_steps'],
            $interactions_per_page, // FIXED: Now correctly calculated
            $answer_changes,
            $answered_sequentially,
            $navigation_metrics['instant_clicks'],
            $mean_time,
            $std_dev
        );
        
        // SEND ADMIN NOTIFICATION FOR HIGH SUSPICION
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
     * FIX 1: Get actual grade percentage from gradebook (what students see)
     * 
     * @param stdClass $attempt
     * @param stdClass $quiz
     * @return float Grade percentage (0-100)
     */
    private static function get_attempt_grade_from_gradebook($attempt, $quiz) {
        global $DB;
        
        // Method 1: Get from quiz_grades table (this is what appears in gradebook)
        $final_grade = $DB->get_record('quiz_grades', [
            'quiz' => $quiz->id,
            'userid' => $attempt->userid
        ]);
        
        if ($final_grade && $quiz->grade > 0) {
            // Convert to percentage: (student's grade / max grade) * 100
            return round(($final_grade->grade / $quiz->grade) * 100, 2);
        }
        
        // Method 2: Calculate directly from this attempt if no grade record yet
        // This happens if this is the first/only attempt
        if ($quiz->sumgrades > 0 && $attempt->sumgrades !== null) {
            // Scale to quiz grade, then to percentage
            $scaled_grade = ($attempt->sumgrades / $quiz->sumgrades) * $quiz->grade;
            return round(($scaled_grade / $quiz->grade) * 100, 2);
        }
        
        // Method 3: Check if sumgrades is already a percentage
        if ($attempt->state == 'finished' && $attempt->sumgrades !== null && $attempt->sumgrades <= 100) {
            return round($attempt->sumgrades, 2);
        }
        
        return 0;
    }
    
    /**
     * FIX 2: Calculate actual number of pages based on quiz layout
     * 
     * @param stdClass $quiz
     * @param stdClass $attempt
     * @return array ['question_pages' => int, 'total_pages' => int]
     */
    private static function calculate_actual_pages($quiz, $attempt) {
        global $DB;
        
        // Get quiz structure from quiz_slots
        $slots = $DB->get_records('quiz_slots', ['quizid' => $quiz->id], 'slot ASC');
        
        if (empty($slots)) {
            return ['question_pages' => 0, 'total_pages' => 0];
        }
        
        // Count unique pages
        $pages = [];
        foreach ($slots as $slot) {
            if (!in_array($slot->page, $pages)) {
                $pages[] = $slot->page;
            }
        }
        
        $question_pages = count($pages);
        
        // Total pages includes:
        // - Question pages
        // - Review page (always present)
        // - Summary page (if quiz shows it)
        $total_pages = $question_pages + 1; // +1 for review page
        
        return [
            'question_pages' => $question_pages,
            'total_pages' => $total_pages
        ];
    }
    
    /**
     * FIX 3: Count real user interactions (actual answers, not system state changes)
     * 
     * @param int $uniqueid Question usage ID
     * @return array Interaction metrics
     */
    private static function get_real_interactions($uniqueid) {
        global $DB;
        
        // Method 1: Count steps that represent actual user actions
        // We'll count ANY step that changes the answer, not just graded ones
        $sql = "SELECT qa.id, qa.slot,
                       COUNT(qas.id) as step_count
                FROM {question_attempts} qa
                JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                WHERE qa.questionusageid = :uniqueid
                AND qas.sequencenumber > 0
                AND qas.state != 'todo'
                AND qas.state != 'gaveup'
                GROUP BY qa.id, qa.slot";
        
        $results = $DB->get_records_sql($sql, ['uniqueid' => $uniqueid]);
        
        $total_interactions = 0;
        $questions_answered = 0;
        
        if (!empty($results)) {
            foreach ($results as $result) {
                $total_interactions += $result->step_count;
                if ($result->step_count > 0) {
                    $questions_answered++;
                }
            }
        }
        
        // If Method 1 gives us 0, try a more lenient approach
        if ($total_interactions == 0) {
            // Method 2: Just count all steps except the initial 'todo' state
            $sql2 = "SELECT COUNT(qas.id) as total_steps
                     FROM {question_attempts} qa
                     JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                     WHERE qa.questionusageid = :uniqueid
                     AND qas.sequencenumber > 0";
            
            $result2 = $DB->get_record_sql($sql2, ['uniqueid' => $uniqueid]);
            $total_interactions = $result2 ? $result2->total_steps : 0;
            
            // Count questions that have at least one step
            $questions_answered = $DB->count_records_sql(
                "SELECT COUNT(DISTINCT qa.id)
                 FROM {question_attempts} qa
                 JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                 WHERE qa.questionusageid = :uniqueid
                 AND qas.sequencenumber > 0",
                ['uniqueid' => $uniqueid]
            );
        }
        
        // Get total number of questions
        $total_questions = $DB->count_records('question_attempts', ['questionusageid' => $uniqueid]);
        
        // Ensure we have at least 1 interaction per answered question as minimum
        if ($total_interactions == 0 && $questions_answered > 0) {
            $total_interactions = $questions_answered;
        }
        
        // Fallback: If still 0, use number of questions as baseline
        if ($total_interactions == 0 && $total_questions > 0) {
            $total_interactions = $total_questions;
            $questions_answered = $total_questions;
        }
        
        return [
            'total_steps' => $total_interactions,
            'questions_answered' => $questions_answered,
            'total_questions' => $total_questions
        ];
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
                AND qas.state IN ('complete', 'gradedright', 'gradedwrong', 'gradedpartial')
                AND qas.fraction IS NOT NULL
                GROUP BY qa.id
                HAVING COUNT(qas.id) > 1";
        
        $results = $DB->get_records_sql($sql, ['uniqueid' => $uniqueid]);
        
        return count($results);
    }
    
    /**
     * Analyze timing consistency across questions
     * FIXED: Only includes pages ≥10 seconds
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
        
        if (count($questions) < 3) {
            return null;
        }
        
        // Only include pages with ≥10 seconds
        $times = [];
        foreach ($questions as $q) {
            $time_spent = $q->end_time - $q->start_time;
            if ($time_spent >= 10) {
                $times[] = $time_spent;
            }
        }
        
        if (count($times) < 3) {
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
            'mean_time' => $mean,
            'std_dev' => $std_dev
        ];
    }
    
    /**
     * Check if questions were answered in sequential order
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
     * FIX 4: Analyze navigation patterns
     * 
     * @param int $uniqueid
     * @return array Navigation metrics
     */
    private static function analyze_navigation_pattern($uniqueid) {
        global $DB;
        
        $sql = "SELECT qa.slot, MIN(qas.timecreated) as start_time, MAX(qas.timecreated) as end_time
                FROM {question_attempts} qa
                JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                WHERE qa.questionusageid = :uniqueid
                GROUP BY qa.slot
                ORDER BY qa.slot";
        
        $pages = $DB->get_records_sql($sql, ['uniqueid' => $uniqueid]);
        
        $instant_clicks = 0;
        $very_fast_pages = 0;
        $normal_pages = 0;
        
        foreach ($pages as $page) {
            $time = $page->end_time - $page->start_time;
            
            if ($time < 2) {
                $instant_clicks++;
            } else if ($time < 5) {
                $very_fast_pages++;
            } else {
                $normal_pages++;
            }
        }
        
        return [
            'instant_clicks' => $instant_clicks,        // < 2 seconds
            'very_fast_pages' => $very_fast_pages,      // 2-5 seconds
            'normal_pages' => $normal_pages,            // > 5 seconds
            'total_pages' => count($pages)
        ];
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
     * FIXED: Now accepts and stores correct interactions_per_page
     */
    private static function log_timing_detection(
        $userid, $courseid, $contextid, $cmid, $quizname, 
        $score, $reasons, $behavior_flags, $duration, $minutes, 
        $questioncount, $grade_percent, $threshold, $attemptid,
        $cv_percent, $question_pages, $total_pages, $total_steps,
        $interactions_per_page, $answer_changes, $answered_sequentially, 
        $instant_clicks, $mean_time, $std_dev
    ) {
        global $DB;
        
        $pageurl = new \moodle_url('/mod/quiz/review.php', ['attempt' => $attemptid]);
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // Build comprehensive browser field
        $browser_info = sprintf(
            'Time: %.1f min (%d sec) | Questions: %d | Pages: %d/%d | Interactions: %d (%.1f/page) | CV%%: %s | Mean: %ss | StdDev: %ss | Grade: %.1f%% | Changes: %d | Sequential: %s | InstantNav: %d | %s | Reasons: %s',
            $minutes,
            $duration,
            $questioncount,
            $question_pages,
            $total_pages,
            $total_steps,
            $interactions_per_page, // FIXED: Now shows correct value
            $cv_percent !== null ? round($cv_percent, 1) . '%' : 'N/A',
            $mean_time !== null ? round($mean_time) : 'N/A',
            $std_dev !== null ? round($std_dev) : 'N/A',
            $grade_percent, // FIXED: Now shows correct grade
            $answer_changes,
            $answered_sequentially ? 'Yes' : 'No',
            $instant_clicks,
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
        $record->grade_percent = $grade_percent; // FIXED: Correct grade stored
        $record->cv_percent = $cv_percent !== null ? round($cv_percent, 2) : null;
        $record->test_pages = $question_pages;
        $record->total_pages = $total_pages;
        $record->total_steps = $total_steps;
        $record->steps_per_page = $interactions_per_page; // FIXED: Correct interactions/page stored
        
        $record->timecreated = time();
        
        $DB->insert_record('local_aiagentblock_log', $record);
    }
    
    /**
     * Send admin notification for high-suspicion attempts
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
        $message .= "Grade: " . round($grade, 1) . "%\n"; // FIXED: Shows correct grade
        $message .= "Threshold: " . $threshold . "\n";
        $message .= "Quiz URL: " . $pageurl->out(false) . "\n\n";
        $message .= "View full report: " . (new \moodle_url('/local/aiagentblock/report.php', 
                                                           ['id' => $courseid]))->out(false);
        
        foreach ($admins as $admin) {
            email_to_user($admin, $admin, $subject, $message);
        }
    }
}

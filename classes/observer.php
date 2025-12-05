<?php
namespace local_aiagentblock;

class observer {
    
    /**
     * Observer for quiz attempt finished event
     */
    public static function quiz_attempt_finished(\mod_quiz\event\attempt_submitted $event) {
        global $DB;
        
        $attempt = $event->get_record_snapshot('quiz_attempts', $event->objectid);
        
        // Calculate completion time in seconds
        $duration = $attempt->timefinish - $attempt->timestart;
        $minutes = $duration / 60;
        
        // Get number of questions
        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz]);
        $questioncount = $DB->count_records('quiz_slots', ['quizid' => $quiz->id]);
        
        // Flag if completed too quickly
        $suspicion_score = 0;
        $reasons = [];
        
        // Less than 1 minute total = impossible
        if ($minutes < 1) {
            $suspicion_score += 100;
            $reasons[] = 'completed_under_1min';
        }
        // Less than 2 minutes = almost certain AI
        else if ($minutes < 2) {
            $suspicion_score += 80;
            $reasons[] = 'completed_under_2min';
        }
        // Less than 3 minutes = highly suspicious
        else if ($minutes < 3) {
            $suspicion_score += 60;
            $reasons[] = 'completed_under_3min';
        }
        // Less than 30 seconds per question
        else if ($questioncount > 0 && ($duration / $questioncount) < 30) {
            $suspicion_score += 50;
            $reasons[] = 'less_than_30sec_per_question';
        }
        
        // Log if suspicious
        if ($suspicion_score >= 60) {
            self::log_detection($event->userid, $event->courseid, $event->contextid, 
                               $suspicion_score, implode(',', $reasons));
        }
    }
    
    private static function log_detection($userid, $courseid, $contextid, $score, $reasons) {
        global $DB;
        
        $record = new \stdClass();
        $record->userid = $userid;
        $record->courseid = $courseid;
        $record->contextid = $contextid;
        $record->cmid = null;
        $record->pageurl = '';
        $record->protection_level = 'server_side';
        $record->detection_method = 'timing_analysis';
        $record->user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $record->browser = 'Server-side detection';
        $record->ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $record->timecreated = time();
        
        $DB->insert_record('local_aiagentblock_log', $record);
        
        // Notify admin if configured
        if (get_config('local_aiagentblock', 'notify_admin')) {
            // Send notification
        }
    }
}

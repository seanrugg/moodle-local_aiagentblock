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
 * AI Agent Detection Report for a course - Enhanced for behavioral analysis
 *
 * @package    local_aiagentblock
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tablelib.php');

$courseid = required_param('id', PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/aiagentblock:viewreports', $context);

$PAGE->set_url('/local/aiagentblock/report.php', ['id' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('report_title', 'local_aiagentblock'));
$PAGE->set_heading($course->fullname);

// Define the table
$table = new flexible_table('local_aiagentblock_report');

// Define columns - comprehensive data for analysis
$table->define_columns([
    'username',
    'timecreated',
    'location',
    'duration_minutes',
    'question_count',
    'grade_percent',
    'suspicionscore',
    'timing_variance',
    'answer_changes',
    'sequential_order',
    'browser',
    'os',
    'device_type',
    'ipaddress',
    'flags'
]);

// Define headers
$table->define_headers([
    get_string('col_username', 'local_aiagentblock'),
    'Date/Time',
    'Activity',
    'Duration (min)',
    'Questions',
    'Grade %',
    'Suspicion Score',
    'Timing CV %',
    'Answer Changes',
    'Sequential',
    'Browser',
    'OS',
    'Device',
    'IP Address',
    'Behavior Flags'
]);

$table->define_baseurl($PAGE->url);
$table->sortable(true, 'timecreated', SORT_DESC);
$table->no_sorting('location');
$table->no_sorting('flags');
$table->collapsible(false);
$table->is_downloadable(true);
$table->show_download_buttons_at([TABLE_P_BOTTOM]);

// Setup the table
$table->setup();

// Handle download
if ($table->is_downloading($download, 'ai_agent_analysis_' . $course->shortname, 
    'AI Agent Detection Analysis - ' . $course->shortname)) {
    // Don't output header for downloads
} else {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('report_course_title', 'local_aiagentblock', $course->shortname));
}

// Get detection records
$sql = "SELECT l.*, u.firstname, u.lastname, u.username, u.email
        FROM {local_aiagentblock_log} l
        JOIN {user} u ON l.userid = u.id
        WHERE l.courseid = :courseid";

$params = ['courseid' => $courseid];

// Apply sorting
$sort = $table->get_sql_sort();
if ($sort) {
    $sql .= " ORDER BY " . $sort;
} else {
    $sql .= " ORDER BY l.timecreated DESC";
}

$records = $DB->get_records_sql($sql, $params);

if (empty($records)) {
    if (!$table->is_downloading()) {
        echo $OUTPUT->notification(
            get_string('no_detections', 'local_aiagentblock'),
            \core\output\notification::NOTIFY_INFO
        );
    }
} else {
    if (!$table->is_downloading()) {
        echo $OUTPUT->notification(
            get_string('detections_found', 'local_aiagentblock', count($records)),
            \core\output\notification::NOTIFY_WARNING
        );
        
        // Add summary statistics
        $total_records = count($records);
        $high_suspicion = 0;
        $perfect_scores = 0;
        $very_fast = 0;
        
        foreach ($records as $r) {
            if ($r->suspicion_score >= 70) $high_suspicion++;
            if ($r->grade_percent >= 95) $perfect_scores++;
            if ($r->duration_minutes < 2) $very_fast++;
        }
        
        echo html_writer::div('', 'mb-3');
        echo html_writer::start_div('alert alert-info');
        echo html_writer::tag('h5', 'Summary Statistics');
        echo html_writer::tag('p', "Total Detections: {$total_records}");
        echo html_writer::tag('p', "High Suspicion (≥70): {$high_suspicion}");
        echo html_writer::tag('p', "Perfect/Near-Perfect Scores: {$perfect_scores}");
        echo html_writer::tag('p', "Very Fast Completion (<2 min): {$very_fast}");
        echo html_writer::end_div();
    }
    
    foreach ($records as $record) {
        $user = $DB->get_record('user', ['id' => $record->userid]);
        
        // Username with link to profile
        if ($table->is_downloading()) {
            $username = fullname($user) . ' (' . $user->username . ')';
        } else {
            $userurl = new moodle_url('/user/view.php', ['id' => $user->id, 'course' => $courseid]);
            $username = html_writer::link($userurl, fullname($user)) . 
                        html_writer::tag('div', $user->username, ['class' => 'small text-muted']);
        }
        
        // Timestamp with link to attempt review
        $timestamp_display = userdate($record->timecreated, get_string('strftimedatetimeshort'));
        if (!$table->is_downloading() && !empty($record->pageurl) && strpos($record->pageurl, '/mod/quiz/review.php') !== false) {
            $timestamp_display = html_writer::link($record->pageurl, $timestamp_display, ['title' => 'View quiz attempt']);
        }
        
        // Location (activity)
        if ($record->cmid) {
            $cm = get_coursemodule_from_id('', $record->cmid);
            if ($cm) {
                if ($table->is_downloading()) {
                    $location = $cm->name;
                } else {
                    $moduleurl = new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]);
                    $location = html_writer::link($moduleurl, $cm->name);
                }
            } else {
                $location = 'Deleted activity';
            }
        } else {
            $location = 'Course: ' . $course->shortname;
        }
        
        // Duration
        $duration_display = $record->duration_minutes !== null ? 
            number_format($record->duration_minutes, 1) : 'N/A';
        
        // Question count
        $question_count_display = $record->question_count !== null ? 
            $record->question_count : 'N/A';
        
        // Grade percentage
        $grade_display = $record->grade_percent !== null ? 
            number_format($record->grade_percent, 1) . '%' : 'N/A';
        
        if (!$table->is_downloading() && $record->grade_percent >= 95) {
            $grade_display = html_writer::tag('strong', $grade_display, ['class' => 'text-success']);
        }
        
        // Suspicion Score with color coding
        $suspicion_score = $record->suspicion_score;
        
        if (!$table->is_downloading()) {
            if ($suspicion_score >= 80) {
                $score_class = 'badge badge-danger';
            } else if ($suspicion_score >= 60) {
                $score_class = 'badge badge-warning';
            } else if ($suspicion_score >= 40) {
                $score_class = 'badge badge-info';
            } else {
                $score_class = 'badge badge-secondary';
            }
            $suspicion_display = html_writer::tag('span', $suspicion_score, ['class' => $score_class]);
        } else {
            $suspicion_display = $suspicion_score;
        }
        
        // Timing Variance (Coefficient of Variation) - FIXED NULL HANDLING
        if ($record->timing_variance !== null && $record->timing_variance !== '') {
            $timing_variance_display = number_format($record->timing_variance, 1) . '%';
            
            if (!$table->is_downloading() && $record->timing_variance < 15) {
                $timing_variance_display = html_writer::tag('span', $timing_variance_display, 
                    ['class' => 'badge badge-warning', 'title' => 'Very consistent timing']);
            }
        } else {
            // Check if this is old data before timing_variance was added
            if ($record->question_count == 1) {
                $timing_variance_display = '0.0%'; // Single question = no variance
            } else {
                $timing_variance_display = 'N/A'; // Truly missing data
            }
        }
        
        // Answer changes
        $answer_changes_display = $record->answer_changes !== null ? 
            $record->answer_changes : 'N/A';
        
        if (!$table->is_downloading() && $record->answer_changes === 0) {
            $answer_changes_display = html_writer::tag('span', '0', 
                ['class' => 'badge badge-info', 'title' => 'No corrections made']);
        }
        
        // Sequential order
        $sequential_display = $record->sequential_order ? 'Yes' : 'No';
        
        // Browser
        $browser_display = $record->browser ?: 'Unknown';
        if ($record->browser_version) {
            $browser_display .= ' ' . $record->browser_version;
        }
        
        // OS
        $os_display = $record->os ?: 'Unknown';
        
        // Device type
        $device_display = $record->device_type ?: 'Unknown';
        
        // IP Address
        $ipaddress = $record->ip_address;
        
        // Behavior flags
        $flags_display = '';
        if ($record->behavior_flags) {
            $flags = json_decode($record->behavior_flags, true);
            if ($flags) {
                if ($table->is_downloading()) {
                    $flags_display = implode(', ', $flags);
                } else {
                    $flag_badges = [];
                    foreach ($flags as $flag) {
                        $badge_class = 'badge-secondary';
                        
                        // Color code important flags
                        if (in_array($flag, ['IMPOSSIBLE_TIME', 'AI_USER_AGENT', 'VERY_LOW_VARIANCE'])) {
                            $badge_class = 'badge-danger';
                        } else if (in_array($flag, ['VERY_FAST', 'PERFECT_AND_FAST', 'NO_CORRECTIONS'])) {
                            $badge_class = 'badge-warning';
                        } else if (in_array($flag, ['LOW_VARIANCE', 'HIGH_SCORE_FAST'])) {
                            $badge_class = 'badge-info';
                        }
                        
                        $flag_badges[] = html_writer::tag('span', $flag, 
                            ['class' => 'badge ' . $badge_class . ' mr-1']);
                    }
                    $flags_display = implode(' ', $flag_badges);
                }
            }
        }
        
        // Add row with all values
        $table->add_data([
            $username,
            $timestamp_display,
            $location,
            $duration_display,
            $question_count_display,
            $grade_display,
            $suspicion_display,
            $timing_variance_display,
            $answer_changes_display,
            $sequential_display,
            $browser_display,
            $os_display,
            $device_display,
            $ipaddress,
            $flags_display
        ]);
    }
}

$table->finish_output();

if (!$table->is_downloading()) {
    // Add explanation of metrics
    echo html_writer::div('', 'mt-4');
    echo html_writer::start_div('alert alert-secondary');
    echo html_writer::tag('h5', 'Metric Explanations:');
    echo html_writer::tag('p', '<strong>Timing CV %:</strong> Coefficient of Variation - measures consistency of time spent per question. Lower values (<15%) indicate very consistent timing.');
    echo html_writer::tag('p', '<strong>Answer Changes:</strong> Number of times answers were modified. Zero changes may indicate confidence or pre-knowledge.');
    echo html_writer::tag('p', '<strong>Sequential:</strong> Whether questions were answered in order (most students do this).');
    echo html_writer::tag('p', '<strong>Behavior Flags:</strong> Detailed indicators used for detection. Multiple red flags increase suspicion score.');
    echo html_writer::end_div();
    
    // Add link back to course
    echo html_writer::div('', 'mt-3');
    echo html_writer::tag('a',
        get_string('returntocourse', 'local_aiagentblock'),
        [
            'href' => new moodle_url('/course/view.php', ['id' => $courseid]),
            'class' => 'btn btn-secondary'
        ]
    );
    
    // Add link to settings if user has permission
    if (has_capability('local/aiagentblock:managecourse', $context)) {
        echo html_writer::tag('a',
            get_string('nav_settings', 'local_aiagentblock'),
            [
                'href' => new moodle_url('/local/aiagentblock/course_settings.php', ['id' => $courseid]),
                'class' => 'btn btn-secondary ml-2'
            ]
        );
    }
    
    echo $OUTPUT->footer();
}

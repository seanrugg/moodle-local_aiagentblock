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
 * AI Agent Detection Report for a course
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

// CRITICAL: Set up PAGE before any output, but only if not downloading
if (empty($download)) {
    $PAGE->set_url('/local/aiagentblock/report.php', ['id' => $courseid]);
    $PAGE->set_context($context);
    $PAGE->set_course($course);
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_title(get_string('report_title', 'local_aiagentblock'));
    $PAGE->set_heading($course->fullname);
}

// Define the table
$table = new flexible_table('local_aiagentblock_report');
$table->define_columns([
    'username',
    'timecreated',
    'ipaddress',
    'agent',
    'duration',
    'questions',
    'grade',
    'cvpercent',
    'testpages',
    'totalinteractions',
    'interactionsperpage',
    'suspicionscore',
    'location',
    'detectionreasons'
]);

$table->define_headers([
    get_string('col_username', 'local_aiagentblock'),
    get_string('col_timestamp', 'local_aiagentblock'),
    get_string('col_ipaddress', 'local_aiagentblock'),
    get_string('col_useragent', 'local_aiagentblock'),
    get_string('col_duration', 'local_aiagentblock'),
    get_string('col_questions', 'local_aiagentblock'),
    get_string('col_grade', 'local_aiagentblock'),
    get_string('col_cvpercent', 'local_aiagentblock'),
    get_string('col_testpages', 'local_aiagentblock'),
    get_string('col_totalinteractions', 'local_aiagentblock'),
    get_string('col_interactionsperpage', 'local_aiagentblock'),
    get_string('col_suspicionscore', 'local_aiagentblock'),
    get_string('col_location', 'local_aiagentblock'),
    get_string('col_detectionreasons', 'local_aiagentblock')
]);

$table->define_baseurl($PAGE->url);
$table->sortable(true, 'timecreated', SORT_DESC);
$table->no_sorting('agent');
$table->no_sorting('location');
$table->collapsible(false);
$table->is_downloadable(true);
$table->show_download_buttons_at([TABLE_P_BOTTOM]);

// Setup the table
$table->setup();

// Handle download
if ($table->is_downloading($download, 'ai_agent_detections_' . $course->shortname, 
    get_string('report_course_title', 'local_aiagentblock', $course->shortname))) {
    
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

/**
 * Helper function to parse detection reasons from browser field
 */
function parse_detection_reasons($browser_text) {
    if (empty($browser_text)) {
        return [];
    }
    
    // Extract reasons from "Reasons: x, y, z" pattern
    if (preg_match('/Reasons:\s*(.+?)(\||$)/', $browser_text, $matches)) {
        $reasons_str = trim($matches[1]);
        $reasons = array_map('trim', explode(',', $reasons_str));
        
        // Clean up and humanize reason names
        $cleaned_reasons = [];
        foreach ($reasons as $reason) {
            // Remove weight numbers (e.g., "completed_under_3min_70" -> "completed_under_3min")
            $reason = preg_replace('/_\d+$/', '', $reason);
            // Convert underscores to spaces and capitalize
            $reason = ucwords(str_replace('_', ' ', $reason));
            $cleaned_reasons[] = $reason;
        }
        
        return array_unique($cleaned_reasons);
    }
    
    return [];
}

if (empty($records)) {
    if (!$table->is_downloading()) {
        echo $OUTPUT->notification(
            get_string('no_detections', 'local_aiagentblock'),
            \core\output\notification::NOTIFY_INFO
        );
    }
} else {
    if (!$table->is_downloading()) {
        // Add metric explanations box
        echo html_writer::start_div('alert alert-info');
        echo html_writer::tag('h5', get_string('metric_explanations_title', 'local_aiagentblock'));
        echo html_writer::start_tag('ul');
        echo html_writer::tag('li', html_writer::tag('strong', get_string('col_cvpercent', 'local_aiagentblock') . ': ') . 
            get_string('metric_cv_explain', 'local_aiagentblock'));
        echo html_writer::tag('li', html_writer::tag('strong', get_string('col_suspicionscore', 'local_aiagentblock') . ': ') . 
            get_string('metric_suspicion_explain', 'local_aiagentblock'));
        echo html_writer::tag('li', html_writer::tag('strong', get_string('col_testpages', 'local_aiagentblock') . ': ') . 
            get_string('metric_testpages_explain', 'local_aiagentblock'));
        echo html_writer::tag('li', html_writer::tag('strong', get_string('col_totalinteractions', 'local_aiagentblock') . ': ') . 
            get_string('metric_interactions_explain', 'local_aiagentblock'));
        echo html_writer::end_tag('ul');
        echo html_writer::end_div();
        
        echo $OUTPUT->notification(
            get_string('detections_found', 'local_aiagentblock', count($records)),
            \core\output\notification::NOTIFY_WARNING
        );
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
        
        // Timestamp with link to attempt review (if pageurl points to review)
        $timestamp_display = userdate($record->timecreated, get_string('strftimedatetimeshort'));
        if (!$table->is_downloading() && !empty($record->pageurl) && strpos($record->pageurl, '/mod/quiz/review.php') !== false) {
            $timestamp_display = html_writer::link($record->pageurl, $timestamp_display, ['title' => 'View quiz attempt']);
        }
        
        // IP Address
        $ipaddress = $record->ip_address;
        
        // AI Agent identification
        $agent = \local_aiagentblock\detector::identify_agent($record->user_agent);
        if (!$table->is_downloading()) {
            $agent .= html_writer::tag('div', 
                html_writer::tag('small', s(substr($record->user_agent, 0, 50)) . '...', ['class' => 'text-muted']),
                ['class' => 'mt-1']
            );
        }
        
        // Duration (from dedicated column)
        if ($record->duration_seconds) {
            $minutes = round($record->duration_seconds / 60, 1);
            $duration_display = $minutes . ' min';
            if (!$table->is_downloading()) {
                $duration_display .= html_writer::tag('div', 
                    $record->duration_seconds . ' sec', 
                    ['class' => 'small text-muted']
                );
            } else {
                $duration_display .= ' (' . $record->duration_seconds . ' sec)';
            }
        } else {
            $duration_display = 'N/A';
        }
        
        // Questions
        $questions_display = $record->question_count ?? 'N/A';
        
        // Grade
        if ($record->grade_percent !== null) {
            $grade_display = number_format($record->grade_percent, 0) . '%';
            if (!$table->is_downloading()) {
                if ($record->grade_percent >= 90) {
                    $grade_class = 'badge badge-success';
                } else if ($record->grade_percent >= 70) {
                    $grade_class = 'badge badge-info';
                } else if ($record->grade_percent >= 50) {
                    $grade_class = 'badge badge-warning';
                } else {
                    $grade_class = 'badge badge-danger';
                }
                $grade_display = html_writer::tag('span', $grade_display, ['class' => $grade_class]);
            }
        } else {
            $grade_display = 'N/A';
        }
        
        // CV Percent (Timing Variance)
        if ($record->cv_percent !== null) {
            $cv_display = number_format($record->cv_percent, 1) . '%';
            if (!$table->is_downloading()) {
                // Low CV% = suspicious (robotic), High CV% = normal (human variance)
                if ($record->cv_percent < 5) {
                    $cv_class = 'badge badge-danger';
                    $cv_label = 'Robotic';
                } else if ($record->cv_percent < 10) {
                    $cv_class = 'badge badge-warning';
                    $cv_label = 'Very Consistent';
                } else if ($record->cv_percent < 30) {
                    $cv_class = 'badge badge-success';
                    $cv_label = 'Normal';
                } else {
                    $cv_class = 'badge badge-success';
                    $cv_label = 'High Variance (Normal)';
                }
                $cv_display = html_writer::tag('span', $cv_display, ['class' => $cv_class]) .
                             html_writer::tag('div', $cv_label, ['class' => 'small text-muted']);
            }
        } else {
            $cv_display = 'N/A';
        }
        
        // Test Pages (question pages / total pages)
        if ($record->test_pages !== null && $record->total_pages !== null) {
            $testpages_display = $record->test_pages . '/' . $record->total_pages;
            if (!$table->is_downloading()) {
                $testpages_display = html_writer::tag('span', $testpages_display, [
                    'title' => 'Question pages (≥10sec) / Total pages',
                    'data-toggle' => 'tooltip'
                ]);
            }
        } else {
            $testpages_display = 'N/A';
        }
        
        // Total Steps
        $totalsteps_display = $record->total_steps ?? 'N/A';
        if (!$table->is_downloading() && $totalsteps_display !== 'N/A') {
            $totalsteps_display = html_writer::tag('span', $totalsteps_display, [
                'title' => 'Total interaction steps recorded',
                'data-toggle' => 'tooltip'
            ]);
        }
        
        // Steps Per Page
        if ($record->steps_per_page !== null && $record->steps_per_page > 0) {
            $stepsperpage_display = number_format($record->steps_per_page, 1);
            if (!$table->is_downloading()) {
                // Low steps per page = suspicious
                // Use high-contrast colors for visibility
                if ($record->steps_per_page < 2) {
                    $steps_class = 'text-danger font-weight-bold';  // Red - very suspicious
                } else if ($record->steps_per_page < 3) {
                    $steps_class = 'font-weight-bold';  // Black bold - somewhat suspicious
                    $stepsperpage_display = '⚠️ ' . $stepsperpage_display;  // Add warning icon
                } else {
                    $steps_class = 'text-success';  // Green - normal
                }
                $stepsperpage_display = html_writer::tag('span', $stepsperpage_display, [
                    'class' => $steps_class,
                    'title' => 'Average interaction steps per page (Low = suspicious)',
                    'data-toggle' => 'tooltip'
                ]);
            }
        } else {
            $stepsperpage_display = 'N/A';
        }
        
        // Suspicion Score with color coding - display as percentage
        $suspicion_score = $record->suspicion_score ?? 0;
        $suspicion_percent = min($suspicion_score, 100); // Cap at 100% for display
        
        if (!$table->is_downloading()) {
            $score_class = '';
            $confidence_text = '';
            if ($suspicion_score >= 90) {
                $score_class = 'badge badge-danger';
                $confidence_text = 'Critical';
            } else if ($suspicion_score >= 70) {
                $score_class = 'badge badge-warning';
                $confidence_text = 'High';
            } else if ($suspicion_score >= 50) {
                $score_class = 'badge badge-info';
                $confidence_text = 'Moderate';
            } else {
                $score_class = 'badge badge-secondary';
                $confidence_text = 'Low';
            }
            $suspicion_display = html_writer::tag('span', $suspicion_percent . '%', ['class' => $score_class]) .
                                html_writer::tag('div', $confidence_text, ['class' => 'small text-muted']);
        } else {
            $suspicion_display = $suspicion_percent . '% (' . ($suspicion_score >= 90 ? 'Critical' : 
                                ($suspicion_score >= 70 ? 'High' : 
                                ($suspicion_score >= 50 ? 'Moderate' : 'Low'))) . ')';
        }
        
        // Detection Reasons - parse from browser field
        $detection_reasons = self::parse_detection_reasons($record->browser);
        if (!$table->is_downloading() && !empty($detection_reasons)) {
            $reasons_html = '';
            foreach ($detection_reasons as $reason) {
                $reasons_html .= html_writer::tag('span', $reason, [
                    'class' => 'badge badge-warning mr-1 mb-1',
                    'style' => 'display: inline-block;'
                ]);
            }
            $detectionreasons = $reasons_html;
        } else if (!empty($detection_reasons)) {
            $detectionreasons = implode(', ', $detection_reasons);
        } else {
            $detectionreasons = get_string('detection_method_timing', 'local_aiagentblock');
        }
        
        // Location (course page or activity)
        if ($record->cmid) {
            $cm = get_coursemodule_from_id('', $record->cmid);
            if ($cm) {
                if ($table->is_downloading()) {
                    $location = $cm->name . ' (' . get_string('modulename', $cm->modname) . ')';
                } else {
                    $moduleurl = new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]);
                    $location = html_writer::link($moduleurl, $cm->name);
                    $location .= html_writer::tag('div', 
                        get_string('modulename', $cm->modname), 
                        ['class' => 'small text-muted']
                    );
                }
            } else {
                $location = get_string('deletedactivity', 'moodle');
            }
        } else {
            $location = get_string('course') . ': ' . $course->shortname;
        }
        
        // Detection method
        switch ($record->detection_method) {
            case 'user_agent':
                $detectionmethod = get_string('detection_method_user_agent', 'local_aiagentblock');
                break;
            case 'headers':
                $detectionmethod = get_string('detection_method_headers', 'local_aiagentblock');
                break;
            case 'client_side':
                $detectionmethod = get_string('detection_method_client_side', 'local_aiagentblock');
                break;
            case 'timing_analysis':
                $detectionmethod = get_string('detection_method_timing', 'local_aiagentblock');
                break;
            default:
                $detectionmethod = $record->detection_method;
        }
        
        $table->add_data([
            $username,
            $timestamp_display,
            $ipaddress,
            $agent,
            $duration_display,
            $questions_display,
            $grade_display,
            $cv_display,
            $testpages_display,
            $totalsteps_display,
            $stepsperpage_display,
            $suspicion_display,
            $location,
            $detectionreasons
        ]);
    }
}

$table->finish_output();

if (!$table->is_downloading()) {
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

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

$PAGE->set_url('/local/aiagentblock/report.php', ['id' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('report_title', 'local_aiagentblock'));
$PAGE->set_heading($course->fullname);

// Define the table
$table = new flexible_table('local_aiagentblock_report');
$table->define_columns([
    'username',
    'timecreated',
    'ipaddress',
    'agent',
    'browser',
    'location',
    'testpages',
    'stepsperpage',
    'totalsteps',
    'suspicionscore',
    'protectionlevel',
    'detectionmethod'
]);

$table->define_headers([
    get_string('col_username', 'local_aiagentblock'),
    get_string('col_timestamp', 'local_aiagentblock'),
    get_string('col_ipaddress', 'local_aiagentblock'),
    get_string('col_useragent', 'local_aiagentblock'),
    get_string('col_browser', 'local_aiagentblock'),
    get_string('col_location', 'local_aiagentblock'),
    get_string('col_testpages', 'local_aiagentblock'),
    get_string('col_stepsperpage', 'local_aiagentblock'),
    get_string('col_totalsteps', 'local_aiagentblock'),
    get_string('col_suspicionscore', 'local_aiagentblock'),
    get_string('col_protectionlevel', 'local_aiagentblock'),
    get_string('col_detectionmethod', 'local_aiagentblock')
]);

$table->define_baseurl($PAGE->url);
$table->sortable(true, 'timecreated', SORT_DESC);
$table->no_sorting('location');
$table->no_sorting('agent');
$table->no_sorting('browser');
$table->no_sorting('testpages');
$table->no_sorting('stepsperpage');
$table->no_sorting('totalsteps');
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
                html_writer::tag('small', s($record->user_agent), ['class' => 'text-muted']),
                ['class' => 'mt-1']
            );
        }
        
        // Parse browser info to extract metrics
        $browser_text = $record->browser ?: get_string('unknown', 'moodle');
        
        // Extract metrics from browser field using regex
        $test_pages = 'N/A';
        $steps_per_page = 'N/A';
        $total_steps = 'N/A';
        
        // Pattern: "Pages: 3/5" means 3 question pages out of 5 total
        if (preg_match('/Pages:\s*(\d+)\/(\d+)/', $browser_text, $matches)) {
            $question_pages = $matches[1];
            $total_pages = $matches[2];
            $test_pages = $question_pages . '/' . $total_pages;
        }
        
        // Pattern: "Steps: 15 (3.0/pg)"
        if (preg_match('/Steps:\s*(\d+)\s*\(([\d.]+)\/pg\)/', $browser_text, $matches)) {
            $total_steps = $matches[1];
            $steps_per_page = $matches[2];
        }
        
        // Format for display
        if ($table->is_downloading()) {
            $test_pages_display = $test_pages;
            $steps_per_page_display = $steps_per_page;
            $total_steps_display = $total_steps;
        } else {
            // Add helpful tooltips
            $test_pages_display = html_writer::tag('span', $test_pages, [
                'title' => 'Question pages / Total pages (excluding review/submit)',
                'data-toggle' => 'tooltip'
            ]);
            
            $steps_per_page_display = html_writer::tag('span', $steps_per_page, [
                'title' => 'Average interaction steps per page',
                'data-toggle' => 'tooltip'
            ]);
            
            $total_steps_display = html_writer::tag('span', $total_steps, [
                'title' => 'Total interaction steps recorded',
                'data-toggle' => 'tooltip'
            ]);
        }
        
        // Browser info (shortened for readability in table)
        if (!$table->is_downloading()) {
            // For web display, show compact version with full details in tooltip
            $browser_short = $browser_text;
            if (strlen($browser_text) > 100) {
                $browser_short = substr($browser_text, 0, 97) . '...';
            }
            $browser_display = html_writer::tag('span', $browser_short, [
                'title' => $browser_text,
                'data-toggle' => 'tooltip',
                'style' => 'cursor: help;'
            ]);
        } else {
            $browser_display = $browser_text;
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
        
        // Suspicion Score with color coding (cap at 100 for display)
        $suspicion_score = isset($record->suspicion_score) ? $record->suspicion_score : 0;
        $display_score = min($suspicion_score, 100); // Cap at 100 for display
        
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
            $suspicion_display = html_writer::tag('span', $display_score, ['class' => $score_class]) .
                                html_writer::tag('div', $confidence_text, ['class' => 'small text-muted']);
        } else {
            $suspicion_display = $display_score . ' (' . ($suspicion_score >= 90 ? 'Critical' : 
                                ($suspicion_score >= 70 ? 'High' : 
                                ($suspicion_score >= 50 ? 'Moderate' : 'Low'))) . ')';
        }
        
        // Protection level
        if ($record->protection_level === 'activity') {
            $protectionlevel = get_string('protection_level_activity', 'local_aiagentblock');
        } else {
            $protectionlevel = get_string('protection_level_course', 'local_aiagentblock');
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
            $browser_display,
            $location,
            $test_pages_display,
            $steps_per_page_display,
            $total_steps_display,
            $suspicion_display,
            $protectionlevel,
            $detectionmethod
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

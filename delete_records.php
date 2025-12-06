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
 * Admin interface for deleting AI agent detection records
 *
 * @package    local_aiagentblock
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_aiagentblock_delete');

$courseid = optional_param('courseid', 0, PARAM_INT);
$deleteall = optional_param('deleteall', 0, PARAM_INT);
$deletecourse = optional_param('deletecourse', 0, PARAM_INT);
$deleteold = optional_param('deleteold', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

require_login();
require_capability('local/aiagentblock:configure', context_system::instance());

$PAGE->set_url('/local/aiagentblock/delete_records.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('delete_records', 'local_aiagentblock'));
$PAGE->set_heading(get_string('delete_records', 'local_aiagentblock'));

// Handle deletions
if ($confirm && confirm_sesskey()) {
    if ($deleteall) {
        // Delete ALL records
        $count = $DB->count_records('local_aiagentblock_log');
        $DB->delete_records('local_aiagentblock_log');
        
        redirect(
            $PAGE->url,
            get_string('records_deleted', 'local_aiagentblock', $count),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else if ($deletecourse && $courseid) {
        // Delete records for specific course
        $count = $DB->count_records('local_aiagentblock_log', ['courseid' => $courseid]);
        $DB->delete_records('local_aiagentblock_log', ['courseid' => $courseid]);
        
        redirect(
            $PAGE->url,
            get_string('records_deleted', 'local_aiagentblock', $count),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else if ($deleteold) {
        // Delete records older than 90 days
        $cutoff = time() - (90 * 24 * 60 * 60);
        $count = $DB->count_records_select('local_aiagentblock_log', 'timecreated < ?', [$cutoff]);
        $DB->delete_records_select('local_aiagentblock_log', 'timecreated < ?', [$cutoff]);
        
        redirect(
            $PAGE->url,
            get_string('records_deleted', 'local_aiagentblock', $count),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('delete_records', 'local_aiagentblock'));

// Show current record counts
$total_records = $DB->count_records('local_aiagentblock_log');
$cutoff = time() - (90 * 24 * 60 * 60);
$old_records = $DB->count_records_select('local_aiagentblock_log', 'timecreated < ?', [$cutoff]);

echo html_writer::div('', 'mb-3');
echo $OUTPUT->notification(
    get_string('total_records', 'local_aiagentblock', $total_records),
    \core\output\notification::NOTIFY_INFO
);

if ($old_records > 0) {
    echo $OUTPUT->notification(
        get_string('old_records', 'local_aiagentblock', $old_records),
        \core\output\notification::NOTIFY_WARNING
    );
}

// Delete all records section
echo html_writer::div('', 'mt-4 mb-3');
echo $OUTPUT->heading(get_string('delete_all_records', 'local_aiagentblock'), 3);
echo html_writer::div(
    get_string('delete_all_warning', 'local_aiagentblock'),
    'alert alert-danger'
);

$deleteallurl = new moodle_url($PAGE->url, [
    'deleteall' => 1,
    'confirm' => 1,
    'sesskey' => sesskey()
]);

echo $OUTPUT->single_button(
    $deleteallurl,
    get_string('delete_all_records', 'local_aiagentblock'),
    'post',
    ['class' => 'btn-danger']
);

// Delete records by course section
echo html_writer::div('', 'mt-4 mb-3');
echo $OUTPUT->heading(get_string('delete_course_records', 'local_aiagentblock'), 3);

$courses = $DB->get_records_sql("
    SELECT DISTINCT c.id, c.fullname, c.shortname, COUNT(l.id) as record_count
    FROM {course} c
    INNER JOIN {local_aiagentblock_log} l ON l.courseid = c.id
    GROUP BY c.id, c.fullname, c.shortname
    ORDER BY c.fullname
");

if (empty($courses)) {
    echo $OUTPUT->notification(
        get_string('no_course_records', 'local_aiagentblock'),
        \core\output\notification::NOTIFY_INFO
    );
} else {
    echo html_writer::start_tag('table', ['class' => 'table table-striped']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('course'));
    echo html_writer::tag('th', get_string('col_recordcount', 'local_aiagentblock'));
    echo html_writer::tag('th', get_string('actions'));
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    
    foreach ($courses as $course) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $course->fullname . ' (' . $course->shortname . ')');
        echo html_writer::tag('td', $course->record_count);
        
        $deleteurl = new moodle_url($PAGE->url, [
            'courseid' => $course->id,
            'deletecourse' => 1,
            'confirm' => 1,
            'sesskey' => sesskey()
        ]);
        
        echo html_writer::start_tag('td');
        echo $OUTPUT->single_button(
            $deleteurl,
            get_string('delete'),
            'post',
            ['class' => 'btn-sm btn-danger']
        );
        echo html_writer::end_tag('td');
        echo html_writer::end_tag('tr');
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}

// Delete old records section
echo html_writer::div('', 'mt-4 mb-3');
echo $OUTPUT->heading(get_string('delete_old_records', 'local_aiagentblock'), 3);
echo html_writer::div(
    get_string('delete_old_warning', 'local_aiagentblock', 90),
    'alert alert-warning'
);

if ($old_records > 0) {
    $deleteoldurl = new moodle_url($PAGE->url, [
        'deleteold' => 1,
        'confirm' => 1,
        'sesskey' => sesskey()
    ]);
    
    echo $OUTPUT->single_button(
        $deleteoldurl,
        get_string('delete_old_records', 'local_aiagentblock') . " ({$old_records})",
        'post',
        ['class' => 'btn-warning']
    );
} else {
    echo $OUTPUT->notification(
        get_string('no_old_records', 'local_aiagentblock'),
        \core\output\notification::NOTIFY_INFO
    );
}

echo $OUTPUT->footer();

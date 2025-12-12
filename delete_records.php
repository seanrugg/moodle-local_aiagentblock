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
 * Manual deletion of all detection records
 *
 * @package    local_aiagentblock
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$confirm = optional_param('confirm', false, PARAM_BOOL);
$deleteid = optional_param('deleteid', 0, PARAM_INT);

$PAGE->set_url('/local/aiagentblock/delete_records.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('delete_records', 'local_aiagentblock'));
$PAGE->set_heading(get_string('delete_records', 'local_aiagentblock'));

echo $OUTPUT->header();

if ($deleteid > 0 && $confirm && confirm_sesskey()) {
    // Delete single record
    if ($DB->record_exists('local_aiagentblock_log', ['id' => $deleteid])) {
        $DB->delete_records('local_aiagentblock_log', ['id' => $deleteid]);
        
        echo $OUTPUT->notification(
            get_string('record_deleted', 'local_aiagentblock'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        echo $OUTPUT->notification(
            get_string('record_not_found', 'local_aiagentblock'),
            \core\output\notification::NOTIFY_ERROR
        );
    }
    
    echo html_writer::tag('a',
        get_string('continue'),
        [
            'href' => new moodle_url('/admin/settings.php', ['section' => 'local_aiagentblock']),
            'class' => 'btn btn-primary'
        ]
    );
    
} else if ($confirm && confirm_sesskey()) {
    // Delete all records
    $count = $DB->count_records('local_aiagentblock_log');
    
    if ($count > 0) {
        $DB->delete_records('local_aiagentblock_log');
        
        echo $OUTPUT->notification(
            get_string('records_deleted', 'local_aiagentblock', $count),
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        echo $OUTPUT->notification(
            get_string('no_records_to_delete', 'local_aiagentblock'),
            \core\output\notification::NOTIFY_INFO
        );
    }
    
    echo html_writer::tag('a',
        get_string('continue'),
        [
            'href' => new moodle_url('/admin/settings.php', ['section' => 'local_aiagentblock']),
            'class' => 'btn btn-primary'
        ]
    );
    
} else {
    // Show confirmation
    $count = $DB->count_records('local_aiagentblock_log');
    
    echo $OUTPUT->heading(get_string('confirm_delete_all', 'local_aiagentblock'));
    
    if ($count > 0) {
        echo html_writer::tag('p', 
            get_string('about_to_delete', 'local_aiagentblock', $count),
            ['class' => 'alert alert-warning']
        );
        
        echo $OUTPUT->confirm(
            get_string('confirm_delete_all', 'local_aiagentblock'),
            new moodle_url('/local/aiagentblock/delete_records.php', [
                'confirm' => 1,
                'sesskey' => sesskey()
            ]),
            new moodle_url('/admin/settings.php', ['section' => 'local_aiagentblock'])
        );
    } else {
        echo $OUTPUT->notification(
            get_string('no_records_to_delete', 'local_aiagentblock'),
            \core\output\notification::NOTIFY_INFO
        );
        
        echo html_writer::tag('a',
            get_string('continue'),
            [
                'href' => new moodle_url('/admin/settings.php', ['section' => 'local_aiagentblock']),
                'class' => 'btn btn-primary'
            ]
        );
    }
}

echo $OUTPUT->footer();

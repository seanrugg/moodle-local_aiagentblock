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
 * Course-level AI Agent Protection settings page
 *
 * @package    local_aiagentblock
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('id', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/aiagentblock:managecourse', $context);

$PAGE->set_url('/local/aiagentblock/course_settings.php', ['id' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('course_settings_header', 'local_aiagentblock'));
$PAGE->set_heading($course->fullname);

// Handle form submission
if (optional_param('save', false, PARAM_BOOL) && confirm_sesskey()) {
    $enabled = optional_param('enabled', 0, PARAM_INT);
    
    if (local_aiagentblock_set_course_setting($courseid, $enabled)) {
        redirect(
            $PAGE->url,
            get_string('course_protection_updated', 'local_aiagentblock'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        redirect(
            $PAGE->url,
            get_string('error_save_failed', 'local_aiagentblock'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

// Get current setting
$enabled = local_aiagentblock_get_course_setting($courseid);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('course_settings_header', 'local_aiagentblock'));

// Display current status
if ($enabled) {
    echo $OUTPUT->notification(
        get_string('course_protection_enabled_notice', 'local_aiagentblock'),
        \core\output\notification::NOTIFY_INFO
    );
} else {
    echo $OUTPUT->notification(
        get_string('course_protection_disabled', 'local_aiagentblock'),
        \core\output\notification::NOTIFY_WARNING
    );
}

// Display help text
echo html_writer::div(get_string('help_legitimate_use', 'local_aiagentblock'), 'alert alert-info');

// Settings form
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $PAGE->url->out(false),
    'class' => 'mform'
]);

echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'sesskey',
    'value' => sesskey()
]);

echo html_writer::start_div('form-group');

echo html_writer::tag('label', 
    get_string('course_protection_enabled', 'local_aiagentblock'),
    ['for' => 'id_enabled', 'class' => 'font-weight-bold']
);

echo html_writer::start_div('form-check mt-2');

// Enable radio button
echo html_writer::start_div('form-check mb-2');
echo html_writer::empty_tag('input', [
    'type' => 'radio',
    'name' => 'enabled',
    'id' => 'id_enabled_yes',
    'value' => '1',
    'checked' => $enabled ? 'checked' : null,
    'class' => 'form-check-input'
]);
echo html_writer::tag('label', get_string('yes'), [
    'for' => 'id_enabled_yes',
    'class' => 'form-check-label ml-2'
]);
echo html_writer::end_div();

// Disable radio button
echo html_writer::start_div('form-check mb-2');
echo html_writer::empty_tag('input', [
    'type' => 'radio',
    'name' => 'enabled',
    'id' => 'id_enabled_no',
    'value' => '0',
    'checked' => !$enabled ? 'checked' : null,
    'class' => 'form-check-input'
]);
echo html_writer::tag('label', get_string('no'), [
    'for' => 'id_enabled_no',
    'class' => 'form-check-label ml-2'
]);
echo html_writer::end_div();

echo html_writer::end_div(); // form-check

echo html_writer::div(
    get_string('course_protection_enabled_help', 'local_aiagentblock'),
    'form-text text-muted small'
);

echo html_writer::end_div(); // form-group

// Help text for disabling
if (!$enabled) {
    echo html_writer::div(
        get_string('help_course_disable', 'local_aiagentblock'),
        'alert alert-warning mt-3'
    );
}

// Submit button
echo html_writer::start_div('form-group mt-3');
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'name' => 'save',
    'value' => get_string('savechanges'),
    'class' => 'btn btn-primary'
]);

echo html_writer::tag('a',
    get_string('cancel'),
    [
        'href' => new moodle_url('/course/view.php', ['id' => $courseid]),
        'class' => 'btn btn-secondary ml-2'
    ]
);
echo html_writer::end_div();

echo html_writer::end_tag('form');

// Link to reports
if (has_capability('local/aiagentblock:viewreports', $context)) {
    echo html_writer::div('', 'mt-4');
    echo html_writer::tag('a',
        get_string('view_report', 'local_aiagentblock'),
        [
            'href' => new moodle_url('/local/aiagentblock/report.php', ['id' => $courseid]),
            'class' => 'btn btn-secondary'
        ]
    );
}

echo $OUTPUT->footer();

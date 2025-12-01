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
 * AJAX endpoint for client-side AI agent detection reporting
 *
 * @package    local_aiagentblock
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Must be logged in
require_login();

// Check if detection is enabled
if (!get_config('local_aiagentblock', 'enabled')) {
    http_response_code(200);
    exit;
}

// Get POST data
$detection = optional_param('detection', 0, PARAM_INT);
$score = optional_param('score', 0, PARAM_INT);

if ($detection && $score >= 2) {
    // Mark this session as detected
    $SESSION->ai_agent_detected = true;
    $SESSION->ai_agent_score = $score;
    
    // If blocking is enabled, we'll handle it on the next page load
    // The before_standard_html_head hook will catch it
    
    http_response_code(200);
    echo json_encode(['status' => 'logged']);
} else {
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
}

exit;

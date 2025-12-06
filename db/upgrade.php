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
 * Upgrade script for AI Agent Blocker plugin
 *
 * @package    local_aiagentblock
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute AI Agent Blocker upgrade from the given old version
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_aiagentblock_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Add suspicion_score column
    if ($oldversion < 2025120500) {
        $table = new xmldb_table('local_aiagentblock_log');
        $field = new xmldb_field('suspicion_score', XMLDB_TYPE_INTEGER, '3', null, null, null, '0', 'ip_address');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2025120500, 'local', 'aiagentblock');
    }

    return true;
}

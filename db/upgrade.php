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

    // Placeholder for future upgrades
    // Example upgrade pattern:
    /*
    if ($oldversion < 2024120101) {
        // Define field newfield to be added to local_aiagentblock_log
        $table = new xmldb_table('local_aiagentblock_log');
        $field = new xmldb_field('newfield', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'previousfield');

        // Conditionally add field
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Savepoint reached
        upgrade_plugin_savepoint(true, 2024120101, 'local', 'aiagentblock');
    }
    */

    return true;
}

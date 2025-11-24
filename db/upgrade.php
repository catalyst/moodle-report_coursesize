<?php
// This file is part of Moodle - http://moodle.org/.
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
 * Upgrade script for the report_coursesize plugin.
 *
 * This file defines the steps required to upgrade the plugin between versions.
 *
 * @package   report_coursesize
 * @copyright 2025 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade script for the report_coursesize plugin.
 *
 * @param int $oldversion the version we are upgrading from.
 * @return bool
 */
function xmldb_report_coursesize_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2021030802) {
        // Delete previous table.
        $table = new xmldb_table('course_filessize');

        // Conditionally launch drop table for course_filessize.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Define table report_coursesize to be created.
        $table = new xmldb_table('report_coursesize');

        // Adding fields to table report_coursesize.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('course', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('filesize', XMLDB_TYPE_INTEGER, '15', null, XMLDB_NOTNULL, null, null);
        $table->add_field('backupsize', XMLDB_TYPE_INTEGER, '15', null, null, null, null);

        // Adding keys to table report_coursesize.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table report_coursesize.
        $table->add_index('course', XMLDB_INDEX_NOTUNIQUE, ['course']);

        // Conditionally launch create table for report_coursesize.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        } else {
            // Throw warning - some old unsupported branches use a similar table that is not compatible with this version,
            // these must be cleaned up manually.
            throw new \moodle_exception('error_unsupported_branch', 'report_coursesize');
        }

        // Coursesize savepoint reached.
        upgrade_plugin_savepoint(true, 2021030802, 'report', 'coursesize');
    }

    if ($oldversion < 2025111206) {
        // Update existing table report_coursesize.
        $table = new xmldb_table('report_coursesize');

        $index = new xmldb_index('course', XMLDB_INDEX_NOTUNIQUE, ['course']);
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        $field = new xmldb_field('course');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Contextlevel.
        $field = new xmldb_field('contextlevel', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 50);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Instanceid.
        $field = new xmldb_field('instanceid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, 0);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Autobackupsize.
        $field = new xmldb_field('autobackupsize', XMLDB_TYPE_INTEGER, '15', null, XMLDB_NOTNULL, null, 0);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $DB->execute("UPDATE {report_coursesize} SET backupsize = 0 WHERE backupsize IS NULL");
        $field = new xmldb_field('backupsize', XMLDB_TYPE_INTEGER, '15', null, XMLDB_NOTNULL, null, 0);
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_notnull($table, $field);
            $dbman->change_field_default($table, $field);
        }

        // Indexes on contextlevel and instanceid.
        $index = new xmldb_index('contextlevel', XMLDB_INDEX_NOTUNIQUE, ['contextlevel']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index = new xmldb_index('instanceid', XMLDB_INDEX_NOTUNIQUE, ['instanceid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // New table report_coursesize_components.
        $table = new xmldb_table('report_coursesize_components');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL);
            $table->add_field('filesize', XMLDB_TYPE_INTEGER, '15', null, XMLDB_NOTNULL);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            $table->add_index('component', XMLDB_INDEX_NOTUNIQUE, ['component']);
            $table->add_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);

            $dbman->create_table($table);
        }

        // New table report_coursesize_users.
        $table = new xmldb_table('report_coursesize_users');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('filesize', XMLDB_TYPE_INTEGER, '15', null, XMLDB_NOTNULL);
            $table->add_field('backupsize', XMLDB_TYPE_INTEGER, '15', null, XMLDB_NOTNULL, null, 0);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2025111206, 'report', 'coursesize');
    }

    return true;
}

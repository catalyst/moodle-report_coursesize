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
 * report_coursesize tasks
 *
 * @package   report_async
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report_coursesize\task;

/**
 * report_async tasks
 *
 * @package   report_async
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_async extends \core\task\scheduled_task {

    /**
     * Get task name
     */
    public function get_name() {
        return get_string('pluginname', 'report_coursesize');
    }

    /**
     * Execute task
     */
    public function execute() {
        global $DB, $CFG;

        mtrace("Generating report_coursesize cache...");

        set_time_limit(0);

        // First we delete the old data, then we re-populate it, wrap in a transaction to help keep it together.
        $transaction = $DB->start_delegated_transaction();

        // Clean up cache table.
        $DB->delete_records('report_coursesize');

        $sqlunion = "UNION ALL
                        SELECT c.id, f.filesize
                        FROM {block_instances} bi
                        JOIN {context} cx1 ON cx1.contextlevel = ".CONTEXT_BLOCK. " AND cx1.instanceid = bi.id
                        JOIN {context} cx2 ON cx2.contextlevel = ". CONTEXT_COURSE. " AND cx2.id = bi.parentcontextid
                        JOIN {course} c ON c.id = cx2.instanceid
                        JOIN {files} f ON f.contextid = cx1.id
                    UNION ALL
                        SELECT c.id, f.filesize
                        FROM {course_modules} cm
                        JOIN {context} cx ON cx.contextlevel = ".CONTEXT_MODULE." AND cx.instanceid = cm.id
                        JOIN {course} c ON c.id = cm.course
                        JOIN {files} f ON f.contextid = cx.id";
        // Generate report_coursesize table.
        $basesql = "SELECT id AS course, SUM(filesize) AS filesize
                      FROM (SELECT c.id, f.filesize
                              FROM {course} c
                              JOIN {context} cx ON cx.contextlevel = ".CONTEXT_COURSE." AND cx.instanceid = c.id
                              JOIN {files} f ON f.contextid = cx.id {$sqlunion}) x
                    GROUP BY id";
        $sql = "INSERT INTO {report_coursesize} (course, filesize) $basesql ";
        $DB->execute($sql);

        // Now calculate size of backups.
        $basesql = "SELECT id AS course, SUM(filesize) AS filesize
                      FROM (SELECT c.id, f.filesize
                              FROM {course} c
                              JOIN {context} cx ON cx.contextlevel = ".CONTEXT_COURSE." AND cx.instanceid = c.id
                              JOIN {files} f ON f.contextid = cx.id AND f.component = 'backup') x
                    GROUP BY id";

        $sql = "UPDATE {report_coursesize} rc
                   SET backupsize = (SELECT bf.filesize FROM ($basesql) bf WHERE bf.course = rc.course)";
        $DB->execute($sql);

        $transaction->allow_commit();

        set_config('coursesizeupdated', time(), 'report_coursesize');

        mtrace("report_coursesize cache updated.");

        // Check if the path ends with a "/" otherwise an exception will be thrown.
        $sitedatadir = $CFG->dataroot;
        if (is_dir($sitedatadir)) {
            // Only append a "/" if it doesn't already end with one.
            if (substr($sitedatadir, -1) !== '/') {
                $sitedatadir .= '/';
            }
        }

        // Total files usage either hasn't been stored, or is out of date.
        $totalusage = get_directory_size($sitedatadir);
        set_config('filessize', $totalusage, 'report_coursesize');
        set_config('filessizeupdated', time(), 'report_coursesize');

        mtrace("report_coursesize overall directory size updated");
    }
}

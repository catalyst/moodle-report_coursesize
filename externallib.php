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
 * WS functions
 *
 * @package    report_coursesize
 * @copyright  2017 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->libdir . "/adminlib.php");
require_once($CFG->dirroot . "/report/coursesize/lib.php");

/**
 * Class report_coursesize_external class for ws functions
 *
 * @copyright  2017 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_coursesize_external extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters Return empty parameter array for WS function
     */
    public static function site_parameters() {
        return new external_function_parameters(array());
    }

    /**
     * WS function implementation for all site size
     *
     * @return array for the size size in MB
     * @throws dml_exception
     */
    public static function site() {
        global $DB;

        $courses = $DB->get_records('course');
        $sumsize = 0;
        foreach ($courses as $course) {

            $context = context_course::instance($course->id);
            $contextcheck = $context->path . '/%';

            $size = report_coursesize_calculate_filesize_shared_courses($contextcheck);

            if (!empty($size)) {
                $sumsize += $size;
            }
        }

        $return = number_format(ceil($sumsize / 1048576));

        return ['size' => $return];
    }

    /**
     * Site size return structure implementation
     *
     * @return external_single_structure Site service return structure implementation
     */
    public static function site_returns() {
        return new external_single_structure([
            'size' => new external_value(PARAM_FLOAT, 'Total courses size in MB')
        ]);
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters Return empty parameter array for WS function
     */
    public static function site_details_parameters() {
        return new external_function_parameters(array());
    }

    /**
     * Site details WS function implementation
     *
     * @return array array of size and courses data
     * @throws coding_exception A Coding specific exception is thrown for any errors.
     * @throws dml_exception A DML specific exception is thrown for any errors.
     */
    public static function site_details() {
        global $DB;

        list($totalusage, $totaldate) = report_coursesize_totalusage(false);

        $coursesizes = array(); // To track a mapping of courseid to filessize.
        $coursebackupsizes = array(); // To track a mapping of courseid to backup filessize.
        $systemsize = $systembackupsize = 0;
        $coursesql = 'SELECT cx.id, c.id as courseid ' .
            'FROM {course} c ' .
            ' INNER JOIN {context} cx ON cx.instanceid=c.id AND cx.contextlevel = ' . CONTEXT_COURSE;
        $courselookup = $DB->get_records_sql($coursesql, array());
        $cxsizes = report_coursesize_cxsizes();

        foreach ($cxsizes as $cxdata) {
            $contextlevel = $cxdata->contextlevel;
            $instanceid = $cxdata->instanceid;
            $contextsize = $cxdata->filessize;
            $contextbackupsize = (empty($cxdata->backupsize) ? 0 : $cxdata->backupsize);

            if ($contextlevel == CONTEXT_COURSE) {
                $coursesizes[$instanceid] = $contextsize;
                $coursebackupsizes[$instanceid] = $contextbackupsize;
                continue;
            }
            if (($contextlevel == CONTEXT_SYSTEM) || ($contextlevel == CONTEXT_COURSECAT)) {
                $systemsize = $contextsize;
                $systembackupsize = $contextbackupsize;
                continue;
            }
            $path = explode('/', $cxdata->path);
            array_shift($path); // Get rid of the leading (empty) array item.
            array_pop($path); // Trim the contextid of the current context itself.

            $success = false; // Course not yet found.
            while (count($path)) {
                $contextid = array_pop($path);
                if (isset($courselookup[$contextid])) {
                    $success = true; // Course found.
                    // Record the files for the current context against the course.
                    $courseid = $courselookup[$contextid]->courseid;
                    if (!empty($coursesizes[$courseid])) {
                        $coursesizes[$courseid] += $contextsize;
                        $coursebackupsizes[$courseid] += $contextbackupsize;
                    } else {
                        $coursesizes[$courseid] = $contextsize;
                        $coursebackupsizes[$courseid] = $contextbackupsize;
                    }
                    break;
                }
            }
            if (!$success) {
                // Didn't find a course
                // A module or block not under a course?
                $systemsize += $contextsize;
                $systembackupsize += $contextbackupsize;
            }
        }

        $sql = "SELECT c.id, c.shortname, c.category, ca.name FROM {course} c "
            ."JOIN {course_categories} ca on c.category = ca.id";
        $courses = $DB->get_records_sql($sql, array());

        $coursedata = array();
        foreach ($coursesizes as $courseid => $size) {
            $course = $courses[$courseid];
            $coursedata[] = array(
                'id' => $course->id,
                'name' => $course->shortname,
                'category' => $course->name,
                'total' => number_format(ceil($size / 1048576)),
                'backup' => $coursebackupsizes[$courseid]
            );
        }

        return array(
            'total_sitedata' => number_format(ceil($totalusage / 1048576)),
            'total_sitedata_recorded' => $totaldate,
            'system_and_category' => number_format(ceil($systemsize / 1048576)),
            'system_and_category_backup' => number_format(ceil($systembackupsize / 1048576)),
            'courses' => $coursedata
        );
    }

    /**
     * Implement site details function return stucture
     *
     * @return external_single_structure Return structure definition
     */
    public static function site_details_returns() {
        return new external_single_structure(array(
            'total_sitedata' => new external_value(PARAM_FLOAT, "Total sitedata usage in MB"),
            'total_sitedata_recorded' => new external_value(PARAM_RAW, "Total sitedata usage record date"),
            'system_and_category' => new external_value(PARAM_FLOAT, "System and category use outside users and courses in MB"),
            'system_and_category_backup' => new external_value(PARAM_FLOAT, "System and category backup use in MB"),
            'courses' => new external_multiple_structure(new external_single_structure(array(
                'id' => new external_value(PARAM_INT, "Course ID"),
                'name' => new external_value(PARAM_RAW, "Course name"),
                'category' => new external_value(PARAM_RAW, "Category name"),
                'total' => new external_value(PARAM_FLOAT, "Total size in MB"),
                'backup' => new external_value(PARAM_FLOAT, "Backup size in MB")
            )))
        ));
    }

}
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
* Course breakdown.
*
* @package    report_coursesize
* @copyright  2017 Catalyst IT {@link http://www.catalyst.net.nz}
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/
require_once($CFG->libdir . "/externallib.php");
require_once($CFG->libdir.'/adminlib.php');

class report_coursesize_external extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function site_parameters() {
        return new external_function_parameters(array(

        ));
    }

    public static function site() {
        global $CFG, $DB;
        $courses = $DB->get_records('course');
        $sum_size = 0;
        foreach($courses as $course) {

            $context = context_course::instance($course->id);
            $contextcheck = $context->path . '/%';

            $sizesql = "SELECT SUM(filesize) FROM (SELECT DISTINCT contenthash, filesize
            FROM {files} f
            JOIN {context} ctx ON f.contextid = ctx.id
            WHERE ".$DB->sql_concat('ctx.path', "'/'")." NOT LIKE ?
                AND f.contenthash IN (SELECT DISTINCT f.contenthash
                                      FROM {files} f
                                      JOIN {context} ctx ON f.contextid = ctx.id
                                     WHERE ".$DB->sql_concat('ctx.path', "'/'")." LIKE ?
                                       AND f.filename != '.')) b";
            $size = $DB->get_field_sql($sizesql, array($contextcheck, $contextcheck));

            if (!empty($size)) {
                //$return = number_format(ceil($size / 1048576)) . "MB";
                $sum_size += $size;
            }

        }

        $return = number_format(ceil($sum_size / 1048576));

        return ['size' => $return];
    }

    public static function site_returns() {
        return new external_single_structure([
            'size' => new external_value(PARAM_FLOAT, 'Total courses size in MB')
        ]);
    }

}
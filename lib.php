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

defined('MOODLE_INTERNAL') || die;

/**
 * Calculate filesize shared with other courses.
 *
 * @param $contextcheck
 * @return bool|mixed
 * @throws dml_exception
 */

function report_coursesize_calculate_filesize_shared_courses($contextcheck) {
    global $DB;

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
        return $size;
    }
}

/**
 * Get CX sizes
 *
 * @throws coding_exception
 * @throws dml_exception
 */
function report_coursesize_cxsizes() {
    global $DB;

    $subsql = 'SELECT f.contextid, sum(f.filesize) as filessize' .
        ' FROM {files} f';
    $wherebackup = ' WHERE component like \'backup\' AND referencefileid IS NULL';
    $groupby = ' GROUP BY f.contextid';
    $reverse = 'reverse(cx2.path)';
    $poslast = $DB->sql_position("'/'", $reverse);
    $length = $DB->sql_length('cx2.path');
    $substr = $DB->sql_substr('cx2.path', 1, $length ." - " . $poslast);
    $likestr = $DB->sql_concat($substr, "'%'");

    $sizesql = 'SELECT cx.id, cx.contextlevel, cx.instanceid, cx.path, cx.depth,
            size.filessize, backupsize.filessize as backupsize' .
        ' FROM {context} cx ' .
        ' INNER JOIN ( ' . $subsql . $groupby . ' ) size on cx.id=size.contextid' .
        ' LEFT JOIN ( ' . $subsql . $wherebackup . $groupby . ' ) backupsize on cx.id=backupsize.contextid' .
        ' ORDER by cx.depth ASC, cx.path ASC';
    return $DB->get_recordset_sql($sizesql);
}

/**
 * Get total usage
 *
 * @param bool $returnWithKeys
 * @return array
 */
function report_coursesize_totalusage($returnwithkeys = true) {
    global $CFG;
    // If we should show or hide empty courses.
    if (!defined('REPORT_COURSESIZE_SHOWEMPTYCOURSES')) {
        define('REPORT_COURSESIZE_SHOWEMPTYCOURSES', false);
    }
    // How many users should we show in the User list.
    if (!defined('REPORT_COURSESIZE_NUMBEROFUSERS')) {
        define('REPORT_COURSESIZE_NUMBEROFUSERS', 10);
    }
    // How often should we update the total sitedata usage.
    if (!defined('REPORT_COURSESIZE_UPDATETOTAL')) {
        define('REPORT_COURSESIZE_UPDATETOTAL', 1 * DAYSECS);
    }

    $reportconfig = get_config('report_coursesize');
    if (!empty($reportconfig->filessize) && !empty($reportconfig->filessizeupdated)
        && ($reportconfig->filessizeupdated > time() - REPORT_COURSESIZE_UPDATETOTAL)) {
        $totalusage = $reportconfig->filessize;
        $totaldate = date("Y-m-d H:i", $reportconfig->filessizeupdated);
    } else {
        $sitedatadir = $CFG->dataroot;
        if (is_dir($sitedatadir)) {
            if (substr($sitedatadir, -1) !== '/') {
                $sitedatadir .= '/';
            }
        }

        // Total files usage either hasn't been stored, or is out of date.
        $totaldate = date("Y-m-d H:i", time());
        $totalusage = get_directory_size($sitedatadir);
        set_config('filessize', $totalusage, 'report_coursesize');
        set_config('filessizeupdated', time(), 'report_coursesize');
    }

    if ($returnwithkeys) {
        return array(
            'totalusage' => $totalusage,
            'totaldate' => $totaldate
        );
    } else {
        return array($totalusage, $totaldate);
    }
}

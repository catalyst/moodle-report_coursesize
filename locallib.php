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
 * Local functions
 *
 * @package    report
 * @subpackage coursesize
 * @author     Kirill Astashov <kirill.astashov@gmail.com>
 * @copyright  2012 NetSpot Pty Ltd {@link http://netspot.com.au}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Calculates and caches course and category sizes
 */
function report_coursesize_task() {
    global $CFG, $DB;

    set_time_limit(0);

    $totalsize = 0;
    $totalsizeexcludingbackups = 0;

    // delete orphaned COURSE rows from cache table
    $sql = "
DELETE FROM
        {report_coursesize}
  USING {course} c
  WHERE instanceid = c.id
  AND   (contextlevel = :ctxc OR contextlevel = :ctxm)
  AND   c.id IS NULL
    ";
    $params = array('ctxc' => CONTEXT_COURSE, 'ctxm' => CONTEXT_MODULE);
    if (!$DB->execute($sql, $params)) {
        return false;
    }

    // delete orphaned COURSE rows from no backups cache table
    $sql = str_replace('report_coursesize', 'report_coursesize_no_backups', $sql);
    if (!$DB->execute($sql, $params)) {
        return false;
    }

    // get COURSE sizes and populate db
    $sql = "
SELECT id AS id, category AS category, SUM(filesize) AS filesize
FROM (
        SELECT c.id, c.category, f.filesize
        FROM {course} c
        JOIN {context} cx ON cx.contextlevel = :ctxc1 AND cx.instanceid = c.id
        JOIN {files} f ON f.contextid = cx.id
    UNION ALL
        SELECT c.id, c.category, f.filesize
        FROM {block_instances} bi
        JOIN {context} cx1 ON cx1.contextlevel = :ctxb AND cx1.instanceid = bi.id
        JOIN {context} cx2 ON cx2.contextlevel = :ctxc2 AND cx2.id = bi.parentcontextid
        JOIN {course} c ON c.id = cx2.instanceid
        JOIN {files} f ON f.contextid = cx1.id
    UNION ALL
        SELECT c.id, c.category, f.filesize
        FROM {course_modules} cm
        JOIN {context} cx ON cx.contextlevel = :ctxm AND cx.instanceid = cm.id
        JOIN {course} c ON c.id = cm.course
        JOIN {files} f ON f.contextid = cx.id
) x
GROUP BY id, category;
";

    $params = array('ctxc1' => CONTEXT_COURSE, 'ctxc2' => CONTEXT_COURSE, 'ctxm' => CONTEXT_MODULE, 'ctxb' => CONTEXT_BLOCK);
    if (($courses = $DB->get_records_sql($sql, $params)) === false) {
        mtrace('Failed to query course file sizes. Aborting...');
        return false;
    }

    // get COURSE sizes with no backups and populate db
    $sql = "
SELECT id AS id, category AS category, SUM(filesize) AS filesize
FROM (
        SELECT c.id, c.category, f.filesize
        FROM {course} c
        JOIN {context} cx ON cx.contextlevel = :ctxc1 AND cx.instanceid = c.id
        JOIN {files} f ON f.contextid = cx.id AND component != 'backup'
    UNION ALL
        SELECT c.id, c.category, f.filesize
        FROM {block_instances} bi
        JOIN {context} cx1 ON cx1.contextlevel = :ctxb AND cx1.instanceid = bi.id
        JOIN {context} cx2 ON cx2.contextlevel = :ctxc2 AND cx2.id = bi.parentcontextid
        JOIN {course} c ON c.id = cx2.instanceid
        JOIN {files} f ON f.contextid = cx1.id
    UNION ALL
        SELECT c.id, c.category, f.filesize
        FROM {course_modules} cm
        JOIN {context} cx ON cx.contextlevel = :ctxm AND cx.instanceid = cm.id
        JOIN {course} c ON c.id = cm.course
        JOIN {files} f ON f.contextid = cx.id
) x
GROUP BY id, category;
";

    $params = array('ctxc1' => CONTEXT_COURSE, 'ctxc2' => CONTEXT_COURSE, 'ctxm' => CONTEXT_MODULE, 'ctxb' => CONTEXT_BLOCK);
    if (($courseexcludingbackups = $DB->get_records_sql($sql, $params)) === false) {
        mtrace('Failed to query course file sizes. Aborting...');
        return false;
    }

    $coursesizecache = array();
    $coursesizeexcludingbackupscache = array();
    foreach ($courses as $course) {
        if (!report_coursesize_storecacherow(CONTEXT_COURSE, $course->id, $course->filesize, false)) {
            return false;
        }
        if (!empty($courseexcludingbackups[$course->id]) &&
            !report_coursesize_storecacherow(CONTEXT_COURSE, $courseexcludingbackups[$course->id]->id, $courseexcludingbackups[$course->id]->filesize, true)) {
            return false;
        }
        $totalsize += $course->filesize;
        $coursesizecache[$course->id] = $course->filesize;
        if (!empty($courseexcludingbackups[$course->id])) {
            $totalsizeexcludingbackups += $courseexcludingbackups[$course->id]->filesize;
            $coursesizeexcludingbackupscache[$course->id] = $courseexcludingbackups[$course->id]->filesize;
        }
    }

    // delete orphaned CATEGORY rows from cache table
    $sql = "
DELETE FROM
    {report_coursesize}
USING
    {course_categories} c
WHERE
        instanceid = c.id
    AND contextlevel = :ctxcc
    AND c.id IS NULL
    ";
    $params = array('ctxcc' => CONTEXT_COURSECAT);
    if (!$DB->execute($sql, $params)) {
        return false;
    }

    // delete orphaned CATEGORY rows from no backups cache table
    $sql = str_replace('report_coursesize', 'report_coursesize_no_backups', $sql);
    if (!$DB->execute($sql, $params)) {
        return false;
    }

    // get CATEGORY sizes and populate db
    // first, get courses under each category (no matter how deeply nested)
    // We have to have the first column unique ;-(
    $sql = "
SELECT
        " . $DB->sql_concat('ct.id', "'_'", 'cx.instanceid') . " AS blah, ct.id AS catid, cx.instanceid AS courseid
FROM
        {course_categories} ct
        JOIN {context} ctx ON ctx.instanceid = ct.id
        LEFT JOIN {context} cx ON ( cx.path LIKE " . $DB->sql_concat('ctx.path', "'/%'") . " )
WHERE
        ctx.contextlevel = :ctxcc
    AND cx.contextlevel =  :ctxc
";

    $params = array('ctxc' => CONTEXT_COURSE, 'ctxcc' => CONTEXT_COURSECAT);
   if (($cats = $DB->get_records_sql($sql, $params)) === false) {
        mtrace('Failed to query categories. Aborting...');
        return false;
   }

    // second, add up course sizes (which we already have) to their categories
    $catsizecache = array();
    $catsizeexcludingbackupscache = array();
    foreach ($cats as $cat) {
        if (!isset($catsizecache[$cat->catid])) {
            $catsizecache[$cat->catid] = 0;
        }
        if (!isset($catsizeexcludingbackupscache[$cat->catid])) {
            $catsizeexcludingbackupscache[$cat->catid] = 0;
        }
        if (isset($coursesizecache[$cat->courseid])) {
            $catsizecache[$cat->catid] += $coursesizecache[$cat->courseid];
        }
        if (isset($coursesizeexcludingbackupscache[$cat->courseid])) {
            $catsizeexcludingbackupscache[$cat->catid] += $coursesizeexcludingbackupscache[$cat->courseid];
        }
    }

    // populate db
    foreach ($cats as $cat) {
        if (!report_coursesize_storecacherow(CONTEXT_COURSECAT, $cat->catid, $catsizecache[$cat->catid], false)) {
            return false;
        }
        if (!report_coursesize_storecacherow(CONTEXT_COURSECAT, $cat->catid, $catsizeexcludingbackupscache[$cat->catid], true)) {
            return false;
        }
    }

    // calculate and update component results
    $modulecalc = report_coursesize_modulecalc();

    // calculate and update user total, add results to totalsize
    $usercalc = report_coursesize_usercalc();
    if ($usercalc !== false) {
        $totalsize += $usercalc;
    }

    // calculate and update user total, add results to totalsize
    $usercalc = report_coursesize_usercalc(false);
    if ($usercalc !== false) {
        $totalsizeexcludingbackups += $usercalc;
    }

    // update grand total
    if (!report_coursesize_storecacherow(0, 0, $totalsize, false)) {
        return false;
    }

    // update grand total without backups
    if (!report_coursesize_storecacherow(0, 0, $totalsizeexcludingbackups, true)) {
        return false;
    }

    // calculate and update unique grand total
    if (report_coursesize_uniquetotalcalc(false) === false) {
        return false;
    }

    // calculate and update unique grand total without backups
    if (report_coursesize_uniquetotalcalc(true) === false) {
        return false;
    }

    return true;
}

/**
 * Calculates size of a single category.
 */
function report_coursesize_catcalc($catid, $excludebackups = false) {
    global $DB;

    // first, get the list of courses nested under the category
    // we have to make the first column unique
    $sql = "
SELECT
        " . $DB->sql_concat('ct.id', "'_'", 'cx.instanceid') . " AS blah, ct.id AS catid, cx.instanceid AS courseid
FROM
        {course_categories} ct
        JOIN {context} ctx ON ctx.instanceid = ct.id
        LEFT JOIN {context} cx ON ( cx.path LIKE " . $DB->sql_concat('ctx.path', "'/%'") . " )
WHERE
        ctx.contextlevel = :ctxcc
    AND cx.contextlevel =  :ctxc
    AND ct.id = :id
";
    $params = array('ctxc' => CONTEXT_COURSE, 'ctxcc' => CONTEXT_COURSECAT, 'id' => $catid);
    $rows = $DB->get_records_sql($sql, $params);
    if ($rows === false OR sizeof($rows) == 0) {
        if (!report_coursesize_storecacherow(CONTEXT_COURSECAT, $catid, 0, false)) {
            return false;
        }
        if (!report_coursesize_storecacherow(CONTEXT_COURSECAT, $catid, 0, true)) {
            return false;
        }
        return 0;
    }

    // get couseids as array
    $courseids = array();
    foreach ($rows AS $row) {
        $courseids[] = $row->courseid;
    }

    // second, get total size of those courses
    list($insql, $params) = $DB->get_in_or_equal($courseids);
    $params = array_merge($params, $params, $params);
    $excludebackupssql = '';
    if ($excludebackups) {
        $excludebackupssql = " AND component != 'backup'";
    }
    $sql = "
SELECT SUM(filesize) AS filesize
FROM (
        SELECT f.filesize AS filesize
        FROM {course} c
        JOIN {context} cx ON cx.contextlevel = 50 AND cx.instanceid = c.id
        JOIN {files} f ON f.contextid = cx.id
        WHERE c.id $insql $excludebackupssql
    UNION ALL
        SELECT f.filesize AS filesize
        FROM {block_instances} bi
        JOIN {context} cx1 ON cx1.contextlevel = 80 AND cx1.instanceid = bi.id
        JOIN {context} cx2 ON cx2.contextlevel = 50 AND cx2.id = bi.parentcontextid
        JOIN {course} c ON c.id = cx2.instanceid
        JOIN {files} f ON f.contextid = cx1.id
        WHERE c.id $insql
    UNION ALL
        SELECT f.filesize AS filesize
        FROM {course_modules} cm
        JOIN {context} cx ON cx.contextlevel = 70 AND cx.instanceid = cm.id
        JOIN {course} c ON c.id = cm.course
        JOIN {files} f ON f.contextid = cx.id
        WHERE c.id $insql
) x
";
    $row = $DB->get_record_sql($sql, $params);
    if ($row === false) {
        return false;
    }
    if (!report_coursesize_storecacherow(CONTEXT_COURSECAT, $catid, $row->filesize, $excludebackups)) {
        return false;
    }
    if (strval($row->filesize) == '') {
        $row->filesize = 0;
    }
    return $row->filesize;
}

/**
 * Calculates granular size of a single course broken down
 * per each file.
 */
function report_coursesize_coursecalc_granular($courseid) {
    global $DB;

    $sql = "
SELECT x.id, x.filesize, x.filename, x.component, x.filearea, x.userid FROM (
    SELECT f.id, f.filesize, f.filename, f.component, f.filearea, f.userid
        FROM {course} c
        JOIN {context} cx ON cx.contextlevel = :contextcourse1 AND cx.instanceid = c.id
        JOIN {files} f ON f.contextid = cx.id
        WHERE c.id = :courseid1
            AND filesize > 0
    UNION ALL
        SELECT f.id, f.filesize, f.filename, f.component, f.filearea, f.userid
        FROM {block_instances} bi
        JOIN {context} cx1 ON cx1.contextlevel = :contextblock AND cx1.instanceid = bi.id
        JOIN {context} cx2 ON cx2.contextlevel = :contextcourse2 AND cx2.id = bi.parentcontextid
        JOIN {course} c ON c.id = cx2.instanceid
        JOIN {files} f ON f.contextid = cx1.id
        WHERE c.id = :courseid2
            AND filesize > 0
    UNION ALL
        SELECT f.id, f.filesize, f.filename, f.component, f.filearea, f.userid
        FROM {course_modules} cm
        JOIN {context} cx ON cx.contextlevel = :contextmodule AND cx.instanceid = cm.id
        JOIN {course} c ON c.id = cm.course
        JOIN {files} f ON f.contextid = cx.id
        WHERE c.id = :courseid3
            AND filesize > 0
) x
ORDER BY x.filesize DESC
";

    $params = array(
        'contextcourse1' => CONTEXT_COURSE,
        'courseid1' => $courseid,
        'contextblock' => CONTEXT_BLOCK,
        'contextcourse2' => CONTEXT_COURSE,
        'courseid2' => $courseid,
        'contextmodule' => CONTEXT_MODULE,
        'courseid3' => $courseid,
    );
    $filelist = $DB->get_records_sql($sql, $params);
    if (!$filelist) {
        return false;
    }
    return $filelist;
}

//
// calculates size of a single course
//
function report_coursesize_coursecalc($courseid, $excludebackups = false) {
    global $DB;

    $excludebackupssql = '';
    if ($excludebackups) {
        $excludebackupssql = " AND component != 'backup'";
    }
    $sql = "
SELECT SUM(filesize) AS filesize
FROM (
        SELECT f.filesize AS filesize
        FROM {course} c
        JOIN {context} cx ON cx.contextlevel = 50 AND cx.instanceid = c.id
        JOIN {files} f ON f.contextid = cx.id
        WHERE c.id = :id1 $excludebackupssql
    UNION ALL
        SELECT f.filesize AS filesize
        FROM {block_instances} bi
        JOIN {context} cx1 ON cx1.contextlevel = 80 AND cx1.instanceid = bi.id
        JOIN {context} cx2 ON cx2.contextlevel = 50 AND cx2.id = bi.parentcontextid
        JOIN {course} c ON c.id = cx2.instanceid
        JOIN {files} f ON f.contextid = cx1.id
        WHERE c.id = :id2
    UNION ALL
        SELECT f.filesize AS filesize
        FROM {course_modules} cm
        JOIN {context} cx ON cx.contextlevel = 70 AND cx.instanceid = cm.id
        JOIN {course} c ON c.id = cm.course
        JOIN {files} f ON f.contextid = cx.id
        WHERE c.id = :id3
) x
";

    $course = $DB->get_record_sql($sql, array('id1' => $courseid, 'id2' => $courseid, 'id3' => $courseid));
    if (!$course) {
        return false;
    }
    if (!report_coursesize_storecacherow(CONTEXT_COURSE, $courseid, $course->filesize, $excludebackups)) {
        return false;
    }
    if (strval($course->filesize) == '') {
        $course->filesize = 0;
    }
    return $course->filesize;
}

//
// calculates size of user files
//
function report_coursesize_usercalc($excludebackups = false) {
    global $DB;

    $sql = "
SELECT
        SUM(fs.filesize) as filesize
FROM
        {context} cx
        LEFT JOIN {files} fs ON fs.contextid = cx.id
WHERE
        cx.contextlevel = " . CONTEXT_USER . "
    ";
    if ($excludebackups) {
        $sql .= " AND filearea != 'backup'";
    }
    $row = $DB->get_record_sql($sql);
    if ($row === false) {
        return false;
    }
    $filesize = $row->filesize;
    if (strval($filesize) == '') {
        $filesize = 0;
    }
    if (!report_coursesize_storecacherow(0, 1, $filesize, $excludebackups)) {
        return false;
    }
    return $filesize;
}

//
// calculates grand total for unique files records in Moodle (unique hashes)
//
function report_coursesize_uniquetotalcalc($excludebackups = false) {
    global $DB;

    $sql = "SELECT SUM(filesize) AS filesize FROM (SELECT DISTINCT(f.contenthash), f.filesize AS filesize FROM {files} f) fs";
    if ($excludebackups) {
        $sql = "SELECT SUM(filesize) AS filesize FROM (SELECT DISTINCT(f.contenthash), f.filesize AS filesize FROM {files} f where component != 'backup' AND filearea != 'backup') fs";
    }
    $row = $DB->get_record_sql($sql, array());
    if ($row === false) {
        return false;
    }
    $filesize = $row->filesize;
    if (strval($filesize) == '') {
        $filesize = 0;
    }
    if (!report_coursesize_storecacherow(0, 2, $filesize, $excludebackups)) {
        return false;
    }
    return $filesize;
}

//
// checks if record exists in cache table and then inserts or updates
//
function report_coursesize_storecacherow($contextlevel, $instanceid, $filesize, $excludebackups = false) {
    global $DB;
    $r = new stdClass();
    $r->contextlevel = $contextlevel;
    $r->instanceid = $instanceid;
    $r->filesize = $filesize;
    if (strval($r->filesize) == '') {
        $r->filesize = 0;
    }
    $table = 'report_coursesize';
    if ($excludebackups) {
        $table = 'report_coursesize_no_backups';
    }
    if ($er = $DB->get_record($table, array('contextlevel' => $r->contextlevel, 'instanceid' => $r->instanceid))) {
        if (strval($er->filesize) != $r->filesize) {
            $r->id = $er->id;
            if (!($DB->update_record($table, $r))) {
                return false;
            }
        }
    } else {
        if (!($DB->insert_record($table, $r))) {
            return false;
        }
    }
    return true;
}

//
// checks if component record exists in cache table and then inserts or updates
//
function report_coursesize_storecomponentcacherow($component, $courseid, $filesize) {
    global $DB;
    $r = new stdClass();
    $r->component = $component;
    $r->courseid = $courseid;
    $r->filesize = $filesize;
    if (strval($r->filesize) == '') {
        $r->filesize = 0;
    }
    $table = 'report_coursesize_components';
    if ($er = $DB->get_record($table, array('component' => $r->component, 'courseid' => $r->courseid))) {
        if (strval($er->filesize) != $r->filesize) {
            $r->id = $er->id;
            if (!($DB->update_record($table, $r))) {
                return false;
            }
        }
    } else if (!($DB->insert_record($table, $r))) {
        return false;
    }
    return true;
}

//
// formats file size for display
//
function report_coursesize_displaysize($size, $type='auto') {

    static $gb, $mb, $kb, $b;
    if (empty($gb)) {
        $gb = ' ' . get_string('sizegb');
        $mb = ' ' . get_string('sizemb');
        $kb = ' ' . get_string('sizekb');
        $b  = ' ' . get_string('sizeb');
    }

    if ($size == '') {
        $size = 0;
    }

    switch ($type) {
        case 'gb':
            $size = number_format(round($size / 1073741824 * 10, 1) / 10, 1) . $gb;
            break;
        case 'mb':
            $size = number_format(round($size / 1048576 * 10) / 10) . $mb;
            break;
        case 'kb':
            $size = number_format(round($size / 1024 * 10) / 10) . $kb;
            break;
        case 'b':
            $size = number_format($size) . $b;
            break;
        case 'auto':
        default:
            if ($size >= 1073741824) {
                $size = number_format(round($size / 1073741824 * 10, 1) / 10, 1) . $gb;
            } else if ($size >= 1048576) {
                $size = number_format(round($size / 1048576 * 10) / 10) . $mb;
            } else if ($size >= 1024) {
                $size = number_format(round($size / 1024 * 10) / 10) . $kb;
            } else {
                $size = number_format($size) . $b;
            }
    }

    return $size;
}

//
// These sort array of objects by filesize property
//
function report_coursesize_cmpasc($a, $b)
{
    if ($a->filesize == $b->filesize) {
        return 0;
    }
    return ($a->filesize < $b->filesize) ? -1 : 1;
}

function report_coursesize_cmpdesc($a, $b)
{
    if ($a->filesize == $b->filesize) {
        return 0;
    }
    return ($a->filesize > $b->filesize) ? -1 : 1;
}

    /**
     * Generate export data for a csv based on display size, sorting and if backups are excluded
     * @param string $displaysize Whether size is shown in formats auto, bytes, Mb or Mb
     * @param string $sortorder Order to sort by
     * @param string $sortdir Order direction
     * @return array An array of category and course data sorted by input paramaters
     */
function report_coursesize_export($displaysize, $sortorder, $sortdir) {
    global $CFG, $DB;

    $config = get_config('report_coursesize');
    $data = array();
    $output = array();

    // get categories
    switch($sortorder) {
        case 'salphan':
        case 'salphas':
            $orderby = 'catname';
            break;

        case 'sorder':
            $orderby = 'sortorder';
            break;

        case 'ssize':
        default:
            $orderby = 'filesize';
            break;
    }

    switch($sortdir) {
        case 'asc':
            $orderby .= ' ASC';
            break;

        case 'desc':
        default:
            $orderby .= ' DESC';
            break;
    }

    $params = array('ctxcc' => CONTEXT_COURSECAT, 'order' => $orderby);
    if (!empty($config->excludebackups)) {
        $coursesizetable = 'report_coursesize_no_backups';
        $sql = '
        SELECT
                ct.id AS catid,
                ct.name AS catname,
                ct.parent AS catparent,
                ct.sortorder AS sortorder,
                rc.filesize as filesize
        FROM
                {course_categories} ct
                LEFT JOIN {' . $coursesizetable . '} rc ON ct.id = rc.instanceid AND rc.contextlevel = :ctxcc
        ORDER BY :order';

        $catsnobackups = $DB->get_records_sql($sql, $params);
    }

    $coursesizetable = 'report_coursesize';
    $sql = '
    SELECT
            ct.id AS catid,
            ct.name AS catname,
            ct.parent AS catparent,
            ct.sortorder AS sortorder,
            rc.filesize as filesize
    FROM
            {course_categories} ct
            LEFT JOIN {' . $coursesizetable . '} rc ON ct.id = rc.instanceid AND rc.contextlevel = :ctxcc
    ORDER BY :order';

    if ($cats = $DB->get_records_sql($sql, $params)) {

        // recalculate
        $dosort = false;
        foreach ($cats as $cat) {
            $newsize = report_coursesize_catcalc($cat->catid);
            if (!$dosort && $cat->filesize != $newsize) {
                $dosort = true;
            }
            $cat->filesize = $newsize;
        }

        // sort by size manually as we cannot
        // rely on DB sorting with live calculation
        if ($dosort && $sortorder == 'ssize') {
            usort($cats, 'report_coursesize_cmp' . $sortdir);
        }

        foreach ($cats AS $cat) {
            $url = '=hyperlink("' . $CFG->wwwroot . '/course/category.php?id=' . $cat->catid . '", "' . $cat->catname . '")';
            $totalfilesize = report_coursesize_displaysize($cat->filesize, $displaysize);
            if (!empty($config->excludebackups)) {
                if (empty($catsnobackups[$cat->catid])) {
                    $catsnobackups[$cat->catid]->filesize = 0;
                }
                $coursefilesize = report_coursesize_displaysize($catsnobackups[$cat->catid]->filesize, $displaysize);
                $backupfilesize = report_coursesize_displaysize($cat->filesize - $catsnobackups[$cat->catid]->filesize, $displaysize);
                $data['category'][$cat->catid] = array($url, $totalfilesize, $coursefilesize, $backupfilesize);
            } else {
                $data['category'][$cat->catid] = array($url, $totalfilesize);
            }
        }
    }

    $params = array('ctxc' => CONTEXT_COURSE, 'order' => $orderby);
    if (!empty($config->excludebackups)) {
        $coursesizetable = 'report_coursesize_no_backups';
        $sql = "
        SELECT
                c.id AS courseid,
                c.fullname AS coursename,
                c.shortname AS courseshortname,
                c.sortorder AS sortorder,
                c.category AS coursecategory,
                rc.filesize as filesize
        FROM
                {course} c
                LEFT JOIN {" . $coursesizetable . "} rc ON c.id = rc.instanceid AND rc.contextlevel = :ctxc
        ORDER BY
                :order
        ";

        $coursesnobackups = $DB->get_records_sql($sql, $params);
    }

    $coursesizetable = 'report_coursesize';
    $sql = "
    SELECT
            c.id AS courseid,
            c.fullname AS coursename,
            c.shortname AS courseshortname,
            c.sortorder AS sortorder,
            c.category AS coursecategory,
            rc.filesize as filesize
    FROM
            {course} c
            LEFT JOIN {" . $coursesizetable . "} rc ON c.id = rc.instanceid AND rc.contextlevel = :ctxc
    ORDER BY
            :order
    ";

    $categories = coursecat::make_categories_list('', 0);
    $categories[0] = '/';
    if ($courses = $DB->get_records_sql($sql, $params)) {

        if ($config->calcmethod == 'live') {
            // recalculate
            $dosort = false;
            foreach ($courses as $course) {
                $newsize = report_coursesize_coursecalc($course->courseid);
                if (!$dosort && $course->filesize != $newsize) {
                    $dosort = true;
                }
                $course->filesize = $newsize;
            }

            // sort by size manually as we cannot
            // rely on DB sorting with live calculation
            if ($dosort && $sortorder == 'ssize') {
                usort($courses, 'report_coursesize_cmp' . $sortdir);
            }
        }

        foreach ($courses AS $course) {
            $url = '=hyperlink("' . $CFG->wwwroot . '/course/view.php?id=' . $course->courseid . '", "' . $course->coursename . '")';
            $totalfilesize = report_coursesize_displaysize($course->filesize, $displaysize);
            if (!empty($config->excludebackups)) {
                if (empty($coursesnobackups[$course->courseid])) {
                    $coursesnobackups[$course->courseid]->filesize = 0;
                }
                $coursefilesize = report_coursesize_displaysize($coursesnobackups[$course->courseid]->filesize, $displaysize);
                $backupfilesize = report_coursesize_displaysize($course->filesize - $coursesnobackups[$course->courseid]->filesize, $displaysize);
                $data['course'][$course->coursecategory][$course->courseid] = array($categories[$course->coursecategory], $url, $totalfilesize, $coursefilesize, $backupfilesize);
            } else {
                $data['course'][$course->coursecategory][$course->courseid] = array($categories[$course->coursecategory], $url, $totalfilesize);
            }
        }
    }

    // Convert data into category based flat layout
    foreach ($data['category'] as $categoryid => $category) {
        if (!empty($data['course'][$categoryid])) {
            foreach ($data['course'][$categoryid] as $course) {
                $output[] = $course;
            }
        }
    }

    return $output;
}

function report_coursesize_modulecalc () {
    global $DB;

    $config = get_config('report_coursesize');
    if (!$config->showcoursecomponents) {
        return false;
    }

    $sql = "SELECT cm.course || '_' || f.component as blah, cm.course as id, f.component, sum(f.filesize) as filesize
              FROM {course_modules} cm
              JOIN {context} cx ON cx.contextlevel = :ctxm AND cx.instanceid = cm.id
              JOIN {files} f ON f.contextid = cx.id
             GROUP BY cm.course, f.component";
    $params = array('ctxm' => CONTEXT_MODULE);
    $data = $DB->get_records_sql($sql, $params);
    foreach ($data as $row) {
        report_coursesize_storecomponentcacherow($row->component, $row->id, $row->filesize);
    }

    return true;
}

function report_coursesize_modulestats($id, $displaysize) {
    global $DB;

    $data = array();

    $config = get_config('report_coursesize');

    $sql = 'SELECT *
              FROM {report_coursesize_components} rcc
             WHERE courseid = :id';
    $params = array('id' => $id);
    if ($modules = $DB->get_records_sql($sql, $params)) {
        foreach ($modules as $module) {
            $size = report_coursesize_displaysize($module->filesize, $displaysize);
            $data[] = array('', $module->component, $size);
        }
    }
    return $data;
}

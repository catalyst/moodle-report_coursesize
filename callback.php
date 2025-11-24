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
 * Callback.
 *
 * @package    report_coursesize
 * @author     Kirill Astashov <kirill.astashov@gmail.com>
 * @copyright  Copyright (c) 2021 Open LMS (https://www.openlms.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_login();
require_once($CFG->libdir . '/filelib.php');

$context = context_system::instance();
$PAGE->set_context($context);
require_capability('report/coursesize:view', $context);

// Retrieve options safely (replaces extract()).
$options = \report_coursesize\local\helper::get_options();
$sortorder = $options['sortorder'] ?? 'ssize';
$sortdir = $options['sortdir'] ?? 'desc';
$displaysize = $options['displaysize'] ?? 'auto';
$excludebackups = $options['excludebackups'] ?? false;

// The column where to insert the granular report icon links if enabled.
define('REPORT_COURSESIZE_GRANULAR_COL', 2);

$id = optional_param('id', 0, PARAM_INT);
$course = optional_param('course', 0, PARAM_INT);

$config = get_config('report_coursesize');
$out = '';

switch ($sortorder) {
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

switch ($sortdir) {
    case 'asc':
        $orderby .= ' ASC';
        break;
    case 'desc':
    default:
        $orderby .= ' DESC';
        break;
}

$sql = "
    SELECT
        ct.id AS catid,
        ct.name AS catname,
        ct.parent AS catparent,
        ct.sortorder AS sortorder,
        rc.filesize AS filesize,
        rc.backupsize AS backupsize
    FROM
        {course_categories} ct
        LEFT JOIN {report_coursesize} rc ON ct.id = rc.instanceid AND rc.contextlevel = :ctxcc
    WHERE
        ct.parent = :id
    ORDER BY {$orderby}
";

$params = ['ctxcc' => CONTEXT_COURSECAT, 'id' => $id];
$tout = false;
$totalsize = 0;

if ($cats = $DB->get_records_sql($sql, $params)) {
    if ($config->calcmethod === 'live') {
        $dosort = false;
        foreach ($cats as $cat) {
            $newsize = report_coursesize_catcalc($cat->catid, $excludebackups);
            if (!$dosort && $cat->filesize != $newsize) {
                $dosort = true;
            }
            $cat->filesize = $newsize;
        }
        if ($dosort && $sortorder === 'ssize') {
            usort($cats, 'report_coursesize_cmp' . $sortdir);
        }
    }

    foreach ($cats as $cat) {
        $table = new html_table();
        $table->align = ['left', 'left', 'right'];
        $table->width = '100%';
        $table->size = ['22px', '', '130px'];
        $table->attributes = ['style' => 'margin-bottom: 0;'];

        if (!empty($config->showgranular)) {
            array_splice($table->align, REPORT_COURSESIZE_GRANULAR_COL, 0, 'center');
            array_splice($table->size, REPORT_COURSESIZE_GRANULAR_COL, 0, '22px');
        }

        if (!$id && !$tout) {
            $table->head = ['', get_string('ttitle', 'report_coursesize'), get_string('tsize', 'report_coursesize')];
            if (!empty($config->showgranular)) {
                array_splice($table->head, REPORT_COURSESIZE_GRANULAR_COL, 0, '');
            }
            $tout = true;
        }

        $hascontent = $DB->record_exists('course_categories', ['parent' => $cat->catid])
            || $DB->record_exists('course', ['category' => $cat->catid]);

        $expandstr = get_string('tdtoggle', 'report_coursesize');
        if ($hascontent) {
            $pix = new \pix_icon('t/switch_plus', $expandstr, 'moodle', ['role' => 'button']);
            $options = [
                'class' => 'cattoggle',
                'data-id' => $cat->catid,
                'aria-label' => $expandstr,
                'role' => 'button',
                'aria-expanded' => 'false',
            ];
            $icon = html_writer::link('', $OUTPUT->render($pix), $options);
        } else {
            $pix = new \pix_icon('empty', $expandstr, 'report_coursesize', ['role' => 'button']);
            $icon = $OUTPUT->render($pix);
        }

        $divicon = html_writer::tag('div', $icon, ['id' => 'icon' . $cat->catid]);
        $title = html_writer::tag('strong', $cat->catname);
        $rawsize = $excludebackups ? $cat->filesize - $cat->backupsize : $cat->filesize;
        $filesize = report_coursesize_displaysize($rawsize, $displaysize);
        $size = html_writer::tag('strong', $filesize);

        $table->data[] = [$divicon, $title, $size];
        if (!empty($config->showgranular)) {
            array_splice($table->data[key($table->data)], REPORT_COURSESIZE_GRANULAR_COL, 0, '');
        }

        $out .= html_writer::table($table);
        $out .= html_writer::tag('div', '', ['style' => 'display:none', 'id' => 'cat' . $cat->catid]);
        $totalsize += $rawsize;
    }
}

switch ($sortorder) {
    case 'salphan':
        $orderby = 'coursename';
        break;
    case 'salphas':
        $orderby = 'courseshortname';
        break;
    case 'sorder':
        $orderby = 'sortorder';
        break;
    case 'ssize':
    default:
        $orderby = 'filesize';
        break;
}

switch ($sortdir) {
    case 'asc':
        $orderby .= ' ASC';
        break;
    case 'desc':
    default:
        $orderby .= ' DESC';
        break;
}

$sql = "
    SELECT
        c.id AS courseid,
        c.fullname AS coursename,
        c.shortname AS courseshortname,
        c.sortorder AS sortorder,
        c.category AS coursecategory,
        rc.filesize AS filesize,
        rc.backupsize AS backupsize
    FROM
        {course} c
        LEFT JOIN {report_coursesize} rc ON c.id = rc.instanceid AND rc.contextlevel = :ctxc
    WHERE
        c.category = :id
    ORDER BY {$orderby}
";

$params = ['ctxc' => CONTEXT_COURSE, 'id' => $id];

if ($courses = $DB->get_records_sql($sql, $params)) {
    if ($config->calcmethod === 'live') {
        report_coursesize_modulecalc();
        $dosort = false;

        foreach ($courses as $course) {
            $newsize = report_coursesize_coursecalc($course->courseid, $excludebackups);
            if (!$dosort && $course->filesize != $newsize) {
                $dosort = true;
            }
            $course->filesize = $newsize;
        }

        if ($dosort && $sortorder === 'ssize') {
            usort($courses, 'report_coursesize_cmp' . $sortdir);
        }
    }

    $table = new html_table();
    $table->align = ['left', 'left', 'right'];
    $table->width = '100%';
    $table->size = ['22px', '', '130px'];
    $table->attributes = ['style' => 'margin-bottom: 0;'];

    foreach ($courses as $course) {
        $coursestats = html_writer::link(
            $CFG->wwwroot . '/report/coursesize/course.php?id=' . $course->courseid,
            get_string('viewcoursestats', 'report_coursesize')
        );

        $rawsize = $excludebackups ? $course->filesize - $course->backupsize : $course->filesize;
        $size = report_coursesize_displaysize($rawsize, $displaysize);
        $data = report_coursesize_modulestats($course->courseid, $displaysize, $excludebackups);

        $title = html_writer::link(
            $CFG->wwwroot . '/course/view.php?id=' . $course->courseid,
            $course->coursename
        );

        $backupinfo = 'Backup size: ' . format_float($course->backupsize ?? 0, 0) . ' bytes';

        $expandstr = get_string('tdtoggle', 'report_coursesize');
        if (!empty($data)) {
            $pix = new \pix_icon('t/switch_plus', $expandstr, 'moodle', ['role' => 'button']);
            $options = [
                'class' => 'coursetoggle',
                'data-id' => $course->courseid,
                'aria-label' => $expandstr,
                'role' => 'button',
                'aria-expanded' => 'false',
            ];
            $icon = html_writer::link('', $OUTPUT->render($pix), $options);
        } else {
            $pix = new \pix_icon('empty', $expandstr, 'report_coursesize', ['role' => 'button']);
            $icon = $OUTPUT->render($pix);
        }

        $divicon = html_writer::tag('div', $icon, ['id' => 'iconcourse' . $course->courseid]);


        $sizecell = new html_table_cell($size);
        $sizecell->attributes['class'] = 'sizecelloverflow';

        $row = new html_table_row([
            new html_table_cell($divicon),
            new html_table_cell($title),
            new html_table_cell($coursestats),
            new html_table_cell($backupinfo),
            $sizecell,
        ]);

        $table->data[] = $row;

        if (!empty($config->showgranular)) {
            $granularicon = $OUTPUT->pix_icon('i/report', '', 'moodle');
            $granular = html_writer::link('granular.php?courseid=' . $course->courseid, $granularicon);

            // Insert granular cell into the row object.
            array_splice($row->cells, REPORT_COURSESIZE_GRANULAR_COL, 0, [
              new html_table_cell($granular),
            ]);

            // Also update table metadata.
            array_splice($table->align, REPORT_COURSESIZE_GRANULAR_COL, 0, 'center');
            array_splice($table->size, REPORT_COURSESIZE_GRANULAR_COL, 0, '22px');
        }

        if (!empty($data)) {
            $out .= html_writer::start_tag('div', ['style' => 'display:none', 'id' => 'course' . $course->courseid]);
            $table = new html_table();
            $table->align = ['left', 'left', 'right'];
            $table->width = '100%';
            $table->size = ['22px', '', '130px'];
            $table->attributes = ['style' => 'margin-bottom: 0;'];
            foreach ($data as $row) {
                $table->data[] = $row;
            }
            $out .= html_writer::table($table);
            $out .= html_writer::end_tag('div');
        }

        $totalsize += $rawsize;
    }
}

        $out .= html_writer::table($table);
$out = html_writer::div($out, '', ['style' => 'margin-left: 25px;']);

if (!$id) {
    $usersize = 0;

    if ($DB->record_exists('report_coursesize', ['contextlevel' => 0, 'instanceid' => 1])) {
        $row = $DB->get_record('report_coursesize', ['contextlevel' => 0, 'instanceid' => 1]);
        $totalsize += $row->filesize;
        $usersize = $row->filesize;
    } else if ($config->calcmethod === 'live') {
        $usersize = report_coursesize_usercalc($excludebackups);
        $totalsize += $usersize;
    }

    if ($config->calcmethod === 'live') {
        report_coursesize_storecacherow(0, 0, $totalsize);
    }

    $out .= html_writer::empty_tag('br') . html_writer::empty_tag('br');
    $out .= get_string('userfilesize', 'report_coursesize') . ': ' .
        report_coursesize_displaysize($usersize, $displaysize) . html_writer::empty_tag('br');
    $out .= get_string('totalfilesize', 'report_coursesize') . ': ' .
        report_coursesize_displaysize($totalsize, $displaysize);

    $uniquefilesize = 0;
    if ($DB->record_exists('report_coursesize', ['contextlevel' => 0, 'instanceid' => 2])) {
        $row = $DB->get_record('report_coursesize', ['contextlevel' => 0, 'instanceid' => 2]);
        $uniquefilesize = $row->filesize;
    } else if ($config->calcmethod === 'live') {
        $uniquefilesize = report_coursesize_uniquetotalcalc($excludebackups);
    }

    $out .= html_writer::empty_tag('br');
    $out .= get_string('uniquefilesize', 'report_coursesize') . ': ' .
        report_coursesize_displaysize($uniquefilesize, $displaysize);
}

echo json_encode($out);

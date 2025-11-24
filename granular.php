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
 * Course size report granular breakdown
 *
 * @package    report_coursesize
 * @subpackage coursesize
 * @author     Damien Bezborodov <dbezborodov@netspot.com.au>
 * @author     Kirill Astashov <kirill.astashov@gmail.com>
 * @copyright  Copyright (c) 2021 Open LMS (https://www.openlms.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/locallib.php');

require_login();
require_capability('report/coursesize:view', context_system::instance());
admin_externalpage_setup('reportcoursesize', '', null, '', ['pagelayout' => 'report']);

// Retrieve safe options (no extract()).
$options = \report_coursesize\local\helper::get_options();
$displaysize = $options['displaysize'] ?? 'auto';

$courseid = required_param('courseid', PARAM_INT);
$courseshortname = $DB->get_field('course', 'shortname', ['id' => $courseid]);

$PAGE->navbar->add($courseshortname);

// Determine output format.
$exportformat = optional_param('export', 'html', PARAM_ALPHA);
$doexcel = ($exportformat === 'excel');
$dohtml = !$doexcel;

// Get the granular file list.
$filelist = report_coursesize_coursecalc_granular($courseid);

if ($filelist) {
    $table = new html_table();
    $table->head = [
        get_string('granularfilename', 'report_coursesize'),
        get_string('granularfiletype', 'report_coursesize'),
        get_string('granularcomponent', 'report_coursesize'),
        get_string('granularfilearea', 'report_coursesize'),
        get_string('granularusername', 'report_coursesize'),
        get_string('granularfilesize', 'report_coursesize'),
    ];
    $table->align = ['left', 'left', 'left', 'left', 'left', 'right'];
    $table->data = [];

    foreach ($filelist as $fileinfo) {
        $filename = $fileinfo->filename;

        if ($dohtml) {
            // Soft-break long lines on underscores with a zero-width space.
            $filename = str_replace('_', '_&#8203;', $filename);
        }

        // Derive file extension safely.
        $ext = '';
        if (str_contains($fileinfo->filename, '.')) {
            $parts = explode('.', $fileinfo->filename);
            $ext = end($parts);
        }

        $username = $DB->get_field('user', 'username', ['id' => $fileinfo->userid]);
        $filesize = $doexcel ? $fileinfo->filesize : report_coursesize_displaysize($fileinfo->filesize, $displaysize);

        $table->data[] = [
            $filename,
            $ext,
            $fileinfo->component,
            $fileinfo->filearea,
            $username,
            $filesize,
        ];
    }

    if ($doexcel) {
        require_once($CFG->libdir . '/excellib.class.php');
        $workbook = new MoodleExcelWorkbook('-');
        $safeshortname = str_replace('/', '_', $courseshortname);
        $workbook->send('report_coursesize-' . $safeshortname . '.xlsx');

        $worksheet = $workbook->add_worksheet(get_string('pluginname', 'report_coursesize'));

        $rows = array_merge([$table->head], $table->data);
        foreach ($rows as $r => $row) {
            foreach ($row as $c => $cell) {
                if ($c === 5 && $r > 0) {
                    // For the bytes column, strip non-numeric and write as number.
                    $worksheet->write_number($r, $c, (int)preg_replace('/[^\d]/', '', $cell));
                } else {
                    $worksheet->write($r, $c, $cell);
                }
            }
        }
        $workbook->close();
        exit;
    }

    echo $OUTPUT->header();
    echo html_writer::table($table);
    echo html_writer::link(
        new moodle_url('granular.php', ['courseid' => $courseid, 'export' => 'excel']),
        get_string('exporttoexcel', 'report_coursesize')
    );
    echo $OUTPUT->footer();
} else {
    echo $OUTPUT->header();
    echo get_string('granularnofiles', 'report_coursesize');
    echo $OUTPUT->footer();
}

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
 * @package    report
 * @subpackage coursesize
 * @author     Damien Bezborodov <dbezborodov@netspot.com.au>
 * @author     Kirill Astashov <kirill.astashov@gmail.com>
 * @copyright  2012-2014 NetSpot Pty Ltd {@link http://netspot.com.au}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once(dirname(__file__) . '/locallib.php');
require_login();
require_once(dirname(__file__) . '/getoptions.php');
admin_externalpage_setup('reportcoursesizepage', '', null, '', array('pagelayout'=>'report'));

$courseid = required_param('courseid', PARAM_INT);
$courseshortname = $DB->get_field('course','shortname',array('id' => $courseid));

$PAGE->navbar->add($courseshortname);

// What format are we outputting?
$doexcel = optional_param('export', 'html', PARAM_ALPHA) == 'excel';
$dohtml = !$doexcel;

$filelist = report_coursesize_coursecalc_granular($courseid);
if ($filelist) {
    $table = new html_table();
    $table->head = array(
        get_string('granularfilename', 'report_coursesize'),
        get_string('granularfiletype', 'report_coursesize'),
        get_string('granularcomponent', 'report_coursesize'),
        get_string('granularfilearea', 'report_coursesize'),
        get_string('granularusername', 'report_coursesize'),
        get_string('granularfilesize', 'report_coursesize'),
    );
    $table->align = array('left', 'left', 'left', 'left', 'left', 'right');
    $table->data = array();
    foreach ($filelist as $fileinfo) {
        if ($dohtml) {
            // Soft-break long lines on underscores with a zero-width space.
            $fileinfo->filename = str_replace('_', '_&#8203;', $fileinfo->filename);
        }
        $table->data[] = array(
            $fileinfo->filename,
            @array_pop(explode('.', $fileinfo->filename)),
            $fileinfo->component,
            $fileinfo->filearea,
            $DB->get_field('user', 'username', array('id' => $fileinfo->userid)),
            $doexcel ? $fileinfo->filesize : report_coursesize_displaysize($fileinfo->filesize),
        );
    }
    if ($doexcel) {
        require_once $CFG->libdir . '/excellib.class.php';
        $workbook = new MoodleExcelWorkbook('-');
        $workbook->send('report_coursesize-'.(str_replace('/', '_', $courseshortname).'.xlsx'));
        $worksheet = $workbook->add_worksheet(get_string('pluginname', 'report_coursesize'));
        foreach(array_merge(array($table->head), $table->data) as $r => $row) {
            foreach ($row as $c => $cell) {
                if ($c == 5 && $r) {
                    // For the bytes column.
                    $worksheet->write_number($r, $c, (int)preg_replace('/[^\d]/', '', $cell));
                    continue;
                }
                $worksheet->write($r, $c, $cell);
            }
        }
        $workbook->close();
    } else {
        echo $OUTPUT->header();
        echo html_writer::table($table);
        echo html_writer::link(new moodle_url('granular.php', array('courseid' => $courseid, 'export' => 'excel')), get_string('exporttoexcel', 'report_coursesize'));
    echo $OUTPUT->footer();
    }
} else {
    echo $OUTPUT->header();
    echo get_string('granularnofiles', 'report_coursesize');
    echo $OUTPUT->footer();
}





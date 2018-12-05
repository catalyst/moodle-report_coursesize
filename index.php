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
 * Version information
 *
 * @package    report_coursesize
 * @copyright  2014 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once(dirname(__file__) . '/locallib.php');
require_login();
require_once(dirname(__file__) . '/getoptions.php');
admin_externalpage_setup('reportcoursesizepage', '', null, '', array('pagelayout'=>'report'));

$config = get_config('report_coursesize');
if (!empty($export)) {
    require_once($CFG->libdir.'/csvlib.class.php');
    $csv = new \csv_export_writer();
    $csv->set_filename('report_coursesize_export');
    $head = array(get_string('tcategories', 'report_coursesize'));
    $head[] = get_string('tcourse', 'report_coursesize');
    if (!empty($config->excludebackups)) {
        $head[] = get_string('ttsize', 'report_coursesize');
        $head[] = get_string('tcsize', 'report_coursesize');
        $head[] = get_string('tbsize', 'report_coursesize');
    } else {
        $head[] = get_string('tsize', 'report_coursesize');
    }
    $csv->add_data($head);
    $data = report_coursesize_export($displaysize, $sortorder, $sortdir);
    if (!empty($data)) {
        foreach ($data as $row) {
            $csv->add_data((array)$row);
        }
    }

    $csv->download_file();
    exit;
}

echo $OUTPUT->header();

$lastruntime = (!isset($config->lastruntime)) ? get_string('nevercap', 'report_coursesize') : date('r', $config->lastruntime);
$livecalcenabled = (isset($config->calcmethod) && $config->calcmethod == 'live') ? get_string('enabledcap', 'report_coursesize') : get_string('disabledcap', 'report_coursesize');

echo html_writer::tag('div', get_string('lastcalculated', 'report_coursesize') . $lastruntime, array('style' => 'margin-bottom:10px;'));
echo html_writer::tag('div', get_string('livecalc', 'report_coursesize') . $livecalcenabled, array('style' => 'margin-bottom:10px;'));

// for catsize.js to be able to pass these parameters to callback script
echo "
<script type=\"text/javascript\">
//<![CDATA[
var csize_sortorder = '{$sortorder}';
var csize_sortdir = '{$sortdir}';
var csize_displaysize = '{$displaysize}';
var csize_excludebackups = '{$excludebackups}';
//]]>
</script>
";

// output form
$forminputs = array();
$forminputs[] = get_string('sortby', 'report_coursesize') . html_writer::select($orderoptions, 'sorder', $sortorder, array());
$forminputs[] = get_string('sortdir', 'report_coursesize') . html_writer::select($diroptions, 'sdir', $sortdir, array());
if (empty($config->alwaysdisplaymb)) {
    $forminputs[] = get_string('displaysize', 'report_coursesize') . html_writer::select($sizeoptions, 'display', $displaysize, array());
}
else {
    $forminputs[] = html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'display', 'value' => 'mb' ));
}
if (!empty($config->excludebackups)) {
    $forminputs[] = get_string('excludebackup', 'report_coursesize') . html_writer::checkbox("excludebackups", 1, $excludebackups, '');
}
$forminputs[] = html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'go', 'value' => get_string('refresh')));
$forminputs[] = html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'export', 'value' => get_string('export', 'report_coursesize')));
echo html_writer::start_tag('div', array('style' => 'text-align:center;margin-bottom:10px;'));
echo html_writer::start_tag('form', array('name' => 'sortoptions', 'method' => 'POST', 'action' => new moodle_url('/report/coursesize/index.php')));
echo implode('&nbsp;&nbsp;&nbsp;', $forminputs);
echo html_writer::end_tag('form');
echo html_writer::end_tag('div');

$PAGE->requires->js(new moodle_url('/report/coursesize/catsize.js'));
echo html_writer::tag('div', '', array('id' => 'cat0', 'style' => 'display:none;'));

echo $OUTPUT->footer();

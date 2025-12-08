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
 * Course size report
 *
 * @package    report_coursesize
 * @subpackage coursesize
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

// Retrieve options safely.
$options = \report_coursesize\local\helper::get_options();
$sortorder = $options['sortorder'] ?? 'ssize';
$sortdir = $options['sortdir'] ?? 'desc';
$displaysize = $options['displaysize'] ?? 'auto';
$excludebackups = $options['excludebackups'] ?? false;
$orderoptions = $options['orderoptions'] ?? [];
$diroptions = $options['diroptions'] ?? [];
$sizeoptions = $options['sizeoptions'] ?? [];

$config = get_config('report_coursesize');

// New dataformat-based export.
$dataformat = optional_param('dataformat', '', PARAM_ALPHANUMEXT);
if ($dataformat !== '') {
    // Define column metadata for the export.
    $columns = [
        'category' => get_string('tcategories', 'report_coursesize'),
        'course'   => get_string('tcourse', 'report_coursesize'),
    ];

    if (!empty($config->excludebackups)) {
        $columns['totalsize']  = get_string('ttsize', 'report_coursesize');
        $columns['coursesize'] = get_string('tcsize', 'report_coursesize');
        $columns['backupsize'] = get_string('tbsize', 'report_coursesize');
    } else {
        $columns['size'] = get_string('tsize', 'report_coursesize');
    }

    // Get the existing export data (already formatted by locallib).
    $exportdata = report_coursesize_export($displaysize, $sortorder, $sortdir);
    $iterator = new ArrayIterator($exportdata);

    // Stream the file in the selected format using the Dataformat API.
    \core\dataformat::download_data(
        'report_coursesize_export',
        $dataformat,
        $columns,
        $iterator,
        function ($row, bool $supportshtml) use ($config): array {
            $row = (array)$row;

            if (!empty($config->excludebackups)) {
                return [
                    'category'   => $row[0] ?? '',
                    'course'     => $row[1] ?? '',
                    'totalsize'  => $row[2] ?? '',
                    'coursesize' => $row[3] ?? '',
                    'backupsize' => $row[4] ?? '',
                ];
            }

            return [
                'category' => $row[0] ?? '',
                'course'   => $row[1] ?? '',
                'size'     => $row[2] ?? '',
            ];
        }
    );

    exit;
}

echo $OUTPUT->header();

$view = optional_param('view', 'courses', PARAM_ALPHA);
$baseurl = new moodle_url('/report/coursesize/index.php');
$tabs = [
    new tabobject(
        'courses',
        new moodle_url($baseurl, ['view' => 'courses']),
        get_string('tab_courses', 'report_coursesize')
    ),
    new tabobject(
        'users',
        new moodle_url($baseurl, ['view' => 'users']),
        get_string('tab_users', 'report_coursesize')
    ),
];
echo $OUTPUT->tabtree($tabs, $view);

if ($view === 'users') {
    $n = (int)get_config('report_coursesize', 'numberofusers');
    if ($n <= 0) {
        $n = 10;
    }

    $sql = "SELECT u.id, u.firstname, u.lastname, u.email, ru.filesize, ru.backupsize
              FROM {report_coursesize_users} ru
              JOIN {user} u ON u.id = ru.userid
          ORDER BY ru.filesize DESC";
    $users = $DB->get_records_sql($sql, null, 0, $n);

    $table = new html_table();
    $table->head = [
        get_string('user'),
        get_string('diskusage', 'report_coursesize'),
        get_string('backupsize', 'report_coursesize'),
    ];
    $table->data = [];

    foreach ($users as $usr) {
        $profileurl = new moodle_url('/user/profile.php', ['id' => $usr->id]);
        $name = fullname($usr);
        $table->data[] = [
            html_writer::link($profileurl, format_string($name)),
            display_size((int)$usr->filesize),
            display_size((int)$usr->backupsize),
        ];
    }

    echo html_writer::tag('h3', get_string('topusers', 'report_coursesize', $n));
    echo html_writer::table($table);
    echo $OUTPUT->footer();
    exit;
}

$lastruntime = !isset($config->lastruntime)
    ? get_string('nevercap', 'report_coursesize')
    : date('r', $config->lastruntime);

$livecalcenabled = (isset($config->calcmethod) && $config->calcmethod === 'live')
    ? get_string('enabledcap', 'report_coursesize')
    : get_string('disabledcap', 'report_coursesize');

echo html_writer::div(
    get_string('lastcalculated', 'report_coursesize') . $lastruntime,
    '',
    ['style' => 'margin-bottom:10px;']
);

echo html_writer::div(
    get_string('livecalc', 'report_coursesize') . $livecalcenabled,
    '',
    ['style' => 'margin-bottom:10px;']
);

$forminputs = [];
$forminputs[] = get_string('sortby', 'report_coursesize') .
    html_writer::select($orderoptions, 'sorder', $sortorder, []);
$forminputs[] = get_string('sortdir', 'report_coursesize') .
    html_writer::select($diroptions, 'sdir', $sortdir, []);

if (empty($config->alwaysdisplaymb)) {
    $forminputs[] = get_string('displaysize', 'report_coursesize') .
        html_writer::select($sizeoptions, 'display', $displaysize, []);
} else {
    $forminputs[] = html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'display',
        'value' => 'mb',
    ]);
}

if (!empty($config->excludebackups)) {
    $forminputs[] = get_string('excludebackup', 'report_coursesize') .
        html_writer::checkbox('excludebackups', 1, $excludebackups, '');
}

$forminputs[] = html_writer::empty_tag('input', [
    'type' => 'submit',
    'name' => 'go',
    'value' => get_string('refresh'),
]);

echo html_writer::start_div('', ['style' => 'text-align:center;margin-bottom:10px;']);
echo html_writer::start_tag('form', [
    'name' => 'sortoptions',
    'method' => 'POST',
    'action' => new moodle_url('/report/coursesize/index.php'),
]);
echo implode('&nbsp;&nbsp;&nbsp;', $forminputs);
echo html_writer::end_tag('form');
echo html_writer::end_div();

// Dataformat download selector (CSV, Excel, ODS, JSON, etc).
$downloadparams = [
    'view'        => $view,
    'sorder'      => $sortorder,
    'sdir'        => $sortdir,
    'display'     => $displaysize,
];

if (!empty($config->excludebackups)) {
    $downloadparams['excludebackups'] = 1;
}

echo html_writer::start_div('', ['style' => 'text-align:center;margin:10px;']);
echo $OUTPUT->download_dataformat_selector(
    get_string('export', 'report_coursesize'),
    new moodle_url('/report/coursesize/index.php'),
    'dataformat',
    $downloadparams
);
echo html_writer::end_div();

$PAGE->requires->js_call_amd(
    'report_coursesize/catsize',
    'init',
    [$sortorder, $sortdir, $displaysize, $excludebackups]
);

echo html_writer::div('', '', ['id' => 'cat0', 'style' => 'display:none;']);
echo $OUTPUT->footer();

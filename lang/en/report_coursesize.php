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

$string['backupsize'] = 'Backup size';
$string['catsystemuse'] = 'System and category use outside users and courses is {$a}.';
$string['catsystembackupuse'] = 'System and category backup use is {$a}.';
$string['coursesize'] = 'Course size';
$string['coursebytes'] = '{$a->bytes} bytes used by course {$a->shortname}';
$string['coursebackupbytes'] = '{$a->backupbytes} bytes used for backup by course {$a->shortname}';
$string['coursereport'] = 'Summary of plugin types - NOTE this may be lower than the main course listing and is probably more accurate.';
$string['coursesize:view'] = 'View course size report';
$string['diskusage'] = 'Total';
$string['nouserfiles'] = 'No user files listed.';
$string['pluginname'] = 'Course size';
$string['sizerecorded'] = '(Recorded {$a})';
$string['sizepermitted'] = '(Permitted usage {$a}MB)';
$string['sitefilesusage'] = 'File usage report';
$string['totalsitedata'] = 'Total sitedata usage: {$a}';
$string['userstopnum'] = 'Users (top {$a})';
$string['emptycourseshidden'] = 'Courses that do not use any file storage have been excluded from this report.';
$string['coursesize_desc'] = '<p>This report only provides approximate values, if a file is used multiple times within a course or in multiple courses the report counts each instance even though Moodle only stores one physical version on disk.</p>';
$string['sharedusage'] = 'Shared Usage';
$string['coursesummary'] = '(view stats)';
$string['sharedusagecourse'] = 'Approx {$a} is shared with other courses.';
$string['privacy:metadata'] = 'The Course size plugin does not store any personal data.';
$string['pluginsettings'] = 'Course size settings';
$string['lastcalculated'] = 'Category and course sizes last calculated by cron at: ';
$string['nevercap'] = 'Never';
$string['enabledcap'] = 'Enabled';
$string['disabledcap'] = 'Disabled';
$string['livecalc'] = 'Live calculations: ';

// Table.
$string['ttitle'] = 'Course Category';
$string['tcategories'] = 'Full Category';
$string['tcourse'] = 'Course';
$string['tsize'] = 'Size';
$string['tddown'] = 'Drill down this category';
$string['tbsize'] = 'Only Course backup size';
$string['tcsize'] = 'Overall course size (excluding course backups)';
$string['ttsize'] = 'Overall course size (including course backups)';

$string['userfilesize'] = 'User file size';
$string['totalfilesize'] = 'Total file size';
$string['uniquefilesize'] = 'Total unique file size';
$string['bytes'] = 'bytes';

// Settings.
$string['alwaysdisplaymb'] = 'Always display in MB';
$string['alwaysdisplaymbhelp'] = 'If enabled, the course information will be always displayed in MB.';
$string['calcmethod'] = 'Calculations';
$string['calcmethodcron'] = 'By cron';
$string['calcmethodlive'] = 'Live calculations';
$string['calcmethodhelp'] = 'If calculated by cron, the report will run at the scheduled time and cache the results for later viewing.  This is recommended over live calculations, since it will only place load on your site once per day during a quiet period. Please use extra care with live calculations since heavy database queries may put high load on the DB server and slow down the whole instance. Enabling this feature on instances with more than 10,000 file records in not recommended and you are encouraged to rely on daily cron calculations.';
$string['executeathelp'] = 'Course size calculations start time (for cron calculations). Select time when your Moodle is idle (e.g. in the night).';
$string['showgranular'] = 'Show granular';
$string['showgranularhelp'] = 'If enabled, a granular breakdown of files per course will be available with file size details.';
$string['excludebackups'] = 'Exclude backups';
$string['excludebackupshelp'] = 'If enabled, an option will be available to exclude backups from course size details.';
$string['showcoursecomponents'] = 'Show course components';
$string['showcoursecomponentshelp'] = 'If enabled, an extra expandable option will be available show component based filesize details.';

// Options.
$string['sizeauto'] = 'Auto';
$string['sortby'] = 'Sort by: ';
$string['ssize'] = 'Size';
$string['salphan'] = 'A-Z (course name)';
$string['salphas'] = 'A-Z (course shortname)';
$string['sorder'] = 'Moodle sort order';
$string['sortdir'] = 'Sort direction: ';
$string['displaysize'] = 'Display sizes as: ';
$string['excludebackup'] = 'Exclude backups: ';

// Granular course file breakdown.
$string['granularfilename'] = 'Filename';
$string['granularfilesize'] = 'Filesize';
$string['granularfiletype'] = 'Type';
$string['granularusername'] = 'Username';
$string['granularcomponent'] = 'Component';
$string['granularfilearea'] = 'File area';
$string['granularnofiles'] = 'There are no files to view within the selected course.';
$string['granularlink'] = 'Details';

// Export.
$string['exporttoexcel'] = 'Export as an Excel file';
$string['exporttocsv'] = 'Export as a CSV file';
$string['export'] = 'Export';

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
 * Strings for component 'report_coursesize'.
 *
 * @package    report_coursesize
 * @subpackage coursesize
 * @author     Kirill Astashov <kirill.astashov@gmail.com>
 * @copyright  Copyright (c) 2021 Open LMS (https://www.openlms.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['alwaysdisplaymb'] = 'Always display in MB';
$string['alwaysdisplaymb_desc'] = 'If enabled, the course information will be always displayed in MB.';
$string['backupsize'] = 'Backup size';
$string['bytes'] = 'bytes';
$string['calcmethod'] = 'Calculations';
$string['calcmethodcron'] = 'By cron';
$string['calcmethodhelp'] = 'If calculated by cron, the report will run at the scheduled time and cache the results for later viewing.  This is recommended over live calculations, since it will only place load on your site once per day during a quiet period. Please use extra care with live calculations since heavy database queries may put high load on the DB server and slow down the whole instance. Enabling this feature on instances with more than 10,000 file records in not recommended and you are encouraged to rely on daily cron calculations.';
$string['calcmethodlive'] = 'Live calculations';
$string['coursefilearea'] = 'Course file area';
$string['coursereport'] = 'Course usage report';
$string['coursesize'] = 'Course size';
$string['coursesize:view'] = 'View course size report';
$string['disabledcap'] = 'Disabled';
$string['diskusage'] = 'Disk usage';
$string['displaysize'] = 'Display sizes as: ';
$string['emailrecipients'] = 'Email recipients';
$string['emailrecipients_desc'] = 'A comma delimited list of email addresses to send storage report to each day.';
$string['enabledcap'] = 'Enabled';
$string['excludebackup'] = 'Exclude backups: ';
$string['excludebackups'] = 'Exclude backups';
$string['excludebackups_desc'] = 'If enabled, an option will be available to exclude backups from course size details.';
$string['export'] = 'Export';
$string['exporttocsv'] = 'Export as a CSV file';
$string['exporttoexcel'] = 'Export as an Excel file';
$string['granularcomponent'] = 'Component';
$string['granularfilearea'] = 'File area';
$string['granularfilename'] = 'Filename';
$string['granularfilesize'] = 'Filesize';
$string['granularfiletype'] = 'Type';
$string['granularlink'] = 'Details';
$string['granularnofiles'] = 'There are no files to view within the selected course.';
$string['granularusername'] = 'Username';
$string['lastcalculated'] = 'Category and course sizes last calculated by cron at: ';
$string['livecalc'] = 'Live calculations: ';
$string['nevercap'] = 'Never';
$string['numberofusers'] = 'Top users number';
$string['numberofusers_desc'] = 'Number of top users of usage to display.';
$string['pluginname'] = 'Course size';
$string['pluginsettings'] = 'Course size settings';
$string['privacy:metadata'] = 'The Course size plugin does not store any personal data.';
$string['salphan'] = 'A-Z (course name)';
$string['salphas'] = 'A-Z (course shortname)';
$string['sharedusagecourse'] = 'Shared usage';
$string['showcoursecomponents'] = 'Show course components';
$string['showcoursecomponents_desc'] = 'If enabled, an extra expandable option will be available show component based filesize details.';
$string['showgranular'] = 'Show granular';
$string['showgranular_desc'] = 'If enabled, a granular breakdown of files per course will be available with file size details.';
$string['sizeauto'] = 'Auto';
$string['sorder'] = 'Moodle sort order';
$string['sortby'] = 'Sort by: ';
$string['sortdir'] = 'Sort direction: ';
$string['ssize'] = 'Size';
$string['tab_courses'] = 'Course size';
$string['tab_users'] = 'Top users of usage';
$string['taskcalculate'] = 'Calculate course sizes';
$string['tasksendreport'] = 'Send disk usage report';
$string['tbsize'] = 'Only Course backup size';
$string['tcategories'] = 'Full Category';
$string['tcourse'] = 'Course';
$string['tcsize'] = 'Overall course size (excluding course backups)';
$string['tdtoggle'] = 'Toggle';
$string['topusers'] = 'Users (top {$a})';
$string['totalfilesize'] = 'Total file size';
$string['tsize'] = 'Size';
$string['ttitle'] = 'Course Category';
$string['ttsize'] = 'Overall course size (including course backups)';
$string['uniquefilesize'] = 'Total unique file size';
$string['userfilesize'] = 'User file size';
$string['viewcoursestats'] = 'View stats';

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
 * Send a report about disk usage.
 *
 * @package report_coursesize
 * @author Adam Olley <adam.olley@openlms.net>
 * @copyright Copyright (c) 2021 Open LMS (https://www.openlms.net)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_coursesize\task;

/**
 * Task class responsible for sending the storage usage report.
 *
 * This scheduled task retrieves cached course size statistics and emails
 * a summary report to configured recipients.
 */
class send_report extends \core\task\scheduled_task {
    /**
     * Returns the task name as shown in the scheduled tasks admin page.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('tasksendreport', 'report_coursesize');
    }

    /**
     * Executes the scheduled task logic.
     *
     * Generates and sends a summary report of course size usage to
     * configured recipients via email.
     *
     * @return void
     */
    public function execute(): void {
        global $CFG;

        require_once($CFG->dirroot . '/report/coursesize/locallib.php');

        $config = get_config('report_coursesize');
        if (empty($config->emailrecipients)) {
            return;
        }

        $recipients = array_map('trim', explode(',', $config->emailrecipients));
        $siteidentifier = preg_replace('#^https?://#', '', $CFG->wwwroot);
        $formatteddate = date('jS M Y', $config->lastruntime);

        $stats = [
            'user' => report_coursesize_getcachevalue(0, 1, false),
            'total' => report_coursesize_getcachevalue(0, 0, false),
            'unique' => report_coursesize_getcachevalue(0, 2, false),
        ];
        $statsnobackups = [
            'user' => report_coursesize_getcachevalue(0, 1, true),
            'total' => report_coursesize_getcachevalue(0, 0, true),
            'unique' => report_coursesize_getcachevalue(0, 2, true),
        ];
        $statsnoautobackups = [
            'user' => report_coursesize_getcachevalue(0, 1, false, true),
            'total' => report_coursesize_getcachevalue(0, 0, false, true),
            'unique' => report_coursesize_getcachevalue(0, 2, false, true),
        ];

        $subject = "Storage Monitor Report for {$siteidentifier} ({$formatteddate})";
        $body = "Storage Monitor Report\n";
        $body .= "======================\n";
        $body .= "{$siteidentifier} - {$formatteddate}\n\n";

        foreach ($stats as $key => $value) {
            if ($value === false) {
                continue;
            }
            $value = report_coursesize_displaysize($value, 'mb');
            $body .= get_string("{$key}filesize", 'report_coursesize') . ": {$value}\n";
        }

        $body .= "\nExcluding course backup files:\n\n";
        foreach ($statsnobackups as $key => $value) {
            if ($value === false) {
                continue;
            }
            $value = report_coursesize_displaysize($value, 'mb');
            $body .= get_string("{$key}filesize", 'report_coursesize') . ": {$value}\n";
        }

        $body .= "\nExcluding automated backup files:\n\n";
        foreach ($statsnoautobackups as $key => $value) {
            if ($value === false) {
                continue;
            }
            $value = report_coursesize_displaysize($value, 'mb');
            $body .= get_string("{$key}filesize", 'report_coursesize') . ": {$value}\n";
        }

        foreach ($recipients as $recipient) {
            $fakeuser = (object) [
                'email' => $recipient,
                'mailformat' => 1,
                'id' => -1,
            ];
            email_to_user($fakeuser, \core_user::get_noreply_user(), $subject, $body);
        }
    }
}

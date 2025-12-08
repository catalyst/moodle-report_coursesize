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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace report_coursesize;

use advanced_testcase;
use core\task\manager;

/**
 * Tests for the send_report scheduled task.
 *
 * @package   report_coursesize
 * @author    Alex Damsted <alexdamsted@catalyst-au.net>
 * @copyright 2025, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_coursesize\task\send_report
 */
final class send_report_task_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    public function test_task_is_registered(): void {
        $task = manager::get_scheduled_task('\report_coursesize\task\send_report');
        $this->assertNotEmpty($task);
    }

    public function test_no_email_sent_when_no_recipients(): void {
        set_config('emailrecipients', '', 'report_coursesize');

        $sink = $this->redirectEmails();

        $task = manager::get_scheduled_task('\report_coursesize\task\send_report');
        $task->execute();

        $this->assertCount(0, $sink->get_messages());
        $sink->close();
    }

    public function test_email_sent_with_correct_body(): void {
        global $DB;

        // Prepare required config.
        set_config('emailrecipients', 'test@example.com', 'report_coursesize');
        set_config('lastruntime', time(), 'report_coursesize');

        // Insert cache values used by send_report.
        $DB->insert_record('report_coursesize', (object)[
            'contextlevel' => 0,
            'instanceid' => 0,
            'filesize' => 123000000,
            'backupsize' => 23000000,
            'autobackupsize' => 10000000,
        ]);
        $DB->insert_record('report_coursesize', (object)[
            'contextlevel' => 0,
            'instanceid' => 1,
            'filesize' => 50000000,
            'backupsize' => 0,
            'autobackupsize' => 0,
        ]);
        $DB->insert_record('report_coursesize', (object)[
            'contextlevel' => 0,
            'instanceid' => 2,
            'filesize' => 80000000,
            'backupsize' => 20000000,
            'autobackupsize' => 5000000,
        ]);

        $sink = $this->redirectEmails();

        $task = manager::get_scheduled_task('\report_coursesize\task\send_report');
        $task->execute();

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);

        $msg = reset($messages);

        // Validate the subject format.
        $this->assertStringContainsString('Storage Monitor Report for', $msg->subject);

        // Validate the body contains formatted values.
        $this->assertStringContainsString('Storage Monitor Report', $msg->body);
        $this->assertStringContainsString('Excluding course backup files', $msg->body);
        $this->assertStringContainsString('Excluding automated backup files', $msg->body);

        $sink->close();
    }

    public function test_multiple_recipients_receive_email(): void {
        global $DB;

        set_config('emailrecipients', 'a@example.com,b@example.com', 'report_coursesize');
        set_config('lastruntime', time(), 'report_coursesize');

        // Minimal viable cache row.
        $DB->insert_record('report_coursesize', (object)[
            'contextlevel' => 0,
            'instanceid' => 0,
            'filesize' => 1,
            'backupsize' => 0,
            'autobackupsize' => 0,
        ]);
        $DB->insert_record('report_coursesize', (object)[
            'contextlevel' => 0,
            'instanceid' => 1,
            'filesize' => 1,
            'backupsize' => 0,
            'autobackupsize' => 0,
        ]);
        $DB->insert_record('report_coursesize', (object)[
            'contextlevel' => 0,
            'instanceid' => 2,
            'filesize' => 1,
            'backupsize' => 0,
            'autobackupsize' => 0,
        ]);

        $sink = $this->redirectEmails();

        $task = manager::get_scheduled_task('\report_coursesize\task\send_report');
        $task->execute();

        $messages = $sink->get_messages();
        $this->assertCount(2, $messages);

        $emails = array_map(fn($m) => $m->to, $messages);
        $this->assertContains('a@example.com', $emails);
        $this->assertContains('b@example.com', $emails);

        $sink->close();
    }
}

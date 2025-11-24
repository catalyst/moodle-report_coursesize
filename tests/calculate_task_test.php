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
use context_course;

/**
 * Tests for the calculate scheduled task.
 *
 * @package   report_coursesize
 * @author    Alex Damsted <alexdamsted@catalyst-au.net>
 * @copyright 2025, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_coursesize\task\calculate
 */
final class calculate_task_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('calcmethod', 'cron', 'report_coursesize');
    }

    public function test_task_is_registered(): void {
        $task = manager::get_scheduled_task('\report_coursesize\task\calculate');
        $this->assertNotEmpty($task);
    }

    public function test_task_runs_and_populates_tables(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = context_course::instance($course->id);

        // Create files.
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_page',
            'filearea'  => 'content',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'a.txt',
        ], str_repeat('X', 5000));

        $task = manager::get_scheduled_task('\report_coursesize\task\calculate');
        $task->execute();

        // Validate course cache record.
        $rec = $DB->get_record('report_coursesize', [
            'contextlevel' => CONTEXT_COURSE,
            'instanceid' => $course->id,
        ]);
        $this->assertNotEmpty($rec);
        $this->assertEquals(5000, (int)$rec->filesize);
    }

    public function test_task_skips_when_live_calculation(): void {
        set_config('calcmethod', 'live', 'report_coursesize');

        $task = manager::get_scheduled_task('\report_coursesize\task\calculate');

        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('Cron calculations are disabled', $output);
    }

    public function test_lastruntime_is_updated(): void {
        $task = manager::get_scheduled_task('\report_coursesize\task\calculate');
        $task->execute();

        $lastrun = get_config('report_coursesize', 'lastruntime');
        $this->assertNotEmpty($lastrun);
        $this->assertGreaterThan(0, $lastrun);
    }
}

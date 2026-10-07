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
use context_course;

/**
 * Tests for functions in locallib.php
 *
 * @package   report_coursesize
 * @author    Alex Damsted <alexdamsted@catalyst-au.net>
 * @copyright 2025, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \locallib.php
 */
final class locallib_test extends advanced_testcase {
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/report/coursesize/locallib.php');
    }

    public function test_store_and_get_cache_value(): void {
        global $DB;

        $result = report_coursesize_storecacherow(50, 123, 1000, 200, 50);
        $this->assertTrue($result);

        $size = report_coursesize_getcachevalue(50, 123, false);
        $this->assertEquals(1000, $size);

        $sizewithoutbackups = report_coursesize_getcachevalue(50, 123, true);
        $this->assertEquals(800, $sizewithoutbackups);
    }

    public function test_cmpasc_and_desc(): void {
        $a = (object)['filesize' => 10];
        $b = (object)['filesize' => 20];

        $this->assertEquals(-1, report_coursesize_cmpasc($a, $b));
        $this->assertEquals(1, report_coursesize_cmpdesc($a, $b));
    }

    public function test_coursecalc_basic(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = context_course::instance($course->id);

        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_page',
            'filearea'  => 'content',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'x.txt',
        ], str_repeat('A', 3000));

        $result = report_coursesize_coursecalc($course->id);
        $this->assertEquals(3000, $result);

        $rec = $DB->get_record('report_coursesize', [
            'contextlevel' => CONTEXT_COURSE,
            'instanceid' => $course->id,
        ]);
        $this->assertNotEmpty($rec);
    }

    /**
     * Course batches preserve context, component and nested category totals.
     *
     * @covers ::report_coursesize_crontask
     */
    public function test_crontask_batches(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $parent = $generator->create_category();
        $category = $generator->create_category(['parent' => $parent->id]);
        $courses = [];
        for ($index = 0; $index < 101; $index++) {
            $courses[] = $generator->create_course(['category' => $category->id]);
        }
        $firstcourse = reset($courses);
        $lastcourse = end($courses);
        $coursecontext = context_course::instance($firstcourse->id);
        $module = $generator->create_module('page', ['course' => $firstcourse->id]);
        $block = $generator->create_block('html', ['parentcontextid' => $coursecontext->id]);

        $files = [
            [$coursecontext->id, 'mod_page', 'content', 100],
            [\context_module::instance($module->cmid)->id, 'mod_page', 'content', 200],
            [\context_block::instance($block->id)->id, 'mod_page', 'content', 300],
            [$coursecontext->id, 'backup', 'course', 40],
            [$coursecontext->id, 'backup', 'automated', 60],
            [context_course::instance($lastcourse->id)->id, 'mod_page', 'content', 900],
        ];
        $storage = get_file_storage();
        foreach ($files as $index => [$contextid, $component, $filearea, $filesize]) {
            $storage->create_file_from_string([
                'contextid' => $contextid,
                'component' => $component,
                'filearea' => $filearea,
                'itemid' => 0,
                'filepath' => '/',
                'filename' => 'file' . $index . '.txt',
            ], str_repeat('X', $filesize));
        }

        $this->assertTrue(report_coursesize_crontask());
        $this->assertEquals(700, report_coursesize_getcachevalue(CONTEXT_COURSE, $firstcourse->id));
        $this->assertEquals(600, report_coursesize_getcachevalue(CONTEXT_COURSE, $firstcourse->id, true));
        $this->assertEquals(640, report_coursesize_getcachevalue(CONTEXT_COURSE, $firstcourse->id, false, true));
        $this->assertEquals(900, report_coursesize_getcachevalue(CONTEXT_COURSE, $lastcourse->id));
        foreach ([$parent->id, $category->id] as $categoryid) {
            $this->assertEquals(1600, report_coursesize_getcachevalue(CONTEXT_COURSECAT, $categoryid));
            $this->assertEquals(1500, report_coursesize_getcachevalue(CONTEXT_COURSECAT, $categoryid, true));
            $this->assertEquals(1540, report_coursesize_getcachevalue(CONTEXT_COURSECAT, $categoryid, false, true));
        }
        $this->assertEquals(1600, report_coursesize_getcachevalue(0, 0));
        $this->assertEquals(1500, report_coursesize_getcachevalue(0, 0, true));
        $this->assertEquals(1540, report_coursesize_getcachevalue(0, 0, false, true));
        $this->assertEquals(600, $DB->get_field('report_coursesize_components', 'filesize', [
            'courseid' => $firstcourse->id,
            'component' => 'mod_page',
        ], MUST_EXIST));
        $this->assertEquals(100, $DB->get_field('report_coursesize_components', 'filesize', [
            'courseid' => $firstcourse->id,
            'component' => 'backup',
        ], MUST_EXIST));
        $this->assertEquals(900, $DB->get_field('report_coursesize_components', 'filesize', [
            'courseid' => $lastcourse->id,
            'component' => 'mod_page',
        ], MUST_EXIST));
    }

    public function test_coursecalc_granular(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $context = context_course::instance($course->id);

        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'assign',
            'filearea'  => 'submission',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'big.dat',
        ], str_repeat('X', 7777));

        $files = report_coursesize_coursecalc_granular($course->id);
        $this->assertIsArray($files);
        $this->assertEquals(7777, reset($files)->filesize);
    }

    public function test_catcalc_with_empty_category(): void {
        $generator = $this->getDataGenerator();
        $cat = $generator->create_category();

        $size = report_coursesize_catcalc($cat->id);
        $this->assertEquals(0, $size);
    }

    public function test_usercalc(): void {
        global $DB;

        // Create user and file.
        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $ctx = \context_user::instance($user->id);

        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $ctx->id,
            'component' => 'user',
            'filearea'  => 'private',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'p.txt',
        ], str_repeat('X', 4444));

        $size = report_coursesize_usercalc(false);

        $this->assertGreaterThan(0, $size);

        $rec = $DB->get_record('report_coursesize', [
            'contextlevel' => 0,
            'instanceid' => 1,
        ]);
        $this->assertNotEmpty($rec);
    }

    public function test_export_generates_data(): void {
        $this->setAdminUser();
        set_config('showcoursecomponents', 1, 'report_coursesize');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Export Test']);

        // Add minimal record.
        report_coursesize_storecacherow(CONTEXT_COURSE, $course->id, 2048, 0, 0);

        $data = report_coursesize_export('kb', 'ssize', 'desc');

        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
    }

    public function test_purge_old_components(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        // Create fake components.
        $DB->insert_record('report_coursesize_components', (object)[
            'component' => 'oldone',
            'courseid' => $course->id,
            'filesize' => 123,
        ]);
        $DB->insert_record('report_coursesize_components', (object)[
            'component' => 'keepme',
            'courseid' => $course->id,
            'filesize' => 999,
        ]);

        report_coursesize_purgeoldcomponents($course->id, ['keepme']);

        $remaining = $DB->get_records('report_coursesize_components');

        $this->assertCount(1, $remaining);
        $this->assertEquals('keepme', reset($remaining)->component);
    }
}

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
use report_coursesize\local\helper;

/**
 * Tests for helper::get_options()
 *
 * @package   report_coursesize
 * @author    Alex Damsted <alexdamsted@catalyst-au.net>
 * @copyright 2025, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \report_coursesize\local\helper
 */
final class helper_test extends advanced_testcase {
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/report/coursesize/classes/local/helper.php');
    }

    public function test_default_options(): void {
        // No params provided.
        $options = helper::get_options();

        $this->assertIsArray($options);
        $this->assertEquals('ssize', $options['sortorder']);
        $this->assertEquals('desc', $options['sortdir']);
        $this->assertEquals('auto', $options['displaysize']);
        $this->assertEquals(0, $options['excludebackups']);

        $this->assertArrayHasKey('orderoptions', $options);
        $this->assertArrayHasKey('diroptions', $options);
        $this->assertArrayHasKey('sizeoptions', $options);
    }

    public function test_options_override_via_optional_param(): void {
        global $CFG;

        // Fake URL params.
        $_GET['sorder'] = 'salphas';
        $_GET['sdir'] = 'asc';
        $_GET['display'] = 'kb';
        $_GET['excludebackups'] = '1';

        $options = helper::get_options();

        $this->assertEquals('salphas', $options['sortorder']);
        $this->assertEquals('asc', $options['sortdir']);
        $this->assertEquals('kb', $options['displaysize']);
        $this->assertEquals(1, $options['excludebackups']);
    }

    public function test_invalid_params_fall_back_to_defaults(): void {
        $_GET['sorder'] = 'INVALID';
        $_GET['sdir'] = 'INVALID';
        $_GET['display'] = 'INVALID';

        $options = helper::get_options();

        $this->assertEquals('ssize', $options['sortorder']);
        $this->assertEquals('desc', $options['sortdir']);
        $this->assertEquals('auto', $options['displaysize']);
    }

    public function test_alwaysdisplaymb_overrides_displaysize(): void {
        set_config('alwaysdisplaymb', 1, 'report_coursesize');

        $_GET['display'] = 'b';

        $options = helper::get_options();

        $this->assertEquals('mb', $options['displaysize']);
    }
}

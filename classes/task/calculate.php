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
 * Calculate course sizes.
 *
 * @package report_coursesize
 * @author Adam Olley <adam.olley@openlms.net>
 * @copyright Copyright (c) 2021 Open LMS (https://www.openlms.net)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_coursesize\task;

/**
 * Task class responsible for calculating and caching course sizes.
 *
 * This task runs via Moodle's scheduled task system and performs size
 * calculations for all courses, caching results for later display.
 */
class calculate extends \core\task\scheduled_task {
    /**
     * Returns the task name as shown in the scheduled task admin page.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskcalculate', 'report_coursesize');
    }

    /**
     * Executes the scheduled task logic.
     *
     * Performs course size calculations and logs results via mtrace().
     *
     * @return void
     */
    public function execute(): void {
        global $CFG;

        $config = get_config('report_coursesize');

        if ($config->calcmethod === 'live') {
            mtrace('Cron calculations are disabled for report_coursesize, see plugin settings. Aborting.');
            return;
        }

        require_once($CFG->dirroot . '/report/coursesize/locallib.php');

        report_coursesize_calculate_users();

        $result = report_coursesize_crontask();
        if ($result === true) {
            mtrace('Task complete.');
        } else {
            mtrace('Task failed.');
        }

        set_config('lastruntime', time(), 'report_coursesize');
    }
}

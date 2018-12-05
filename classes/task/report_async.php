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
 * report_coursesize tasks
 *
 * @package   report_async
 * @author    Matt Porritt <mattp@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report_coursesize\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/report/coursesize/locallib.php');

/**
 * report_async tasks
 *
 * @package   report_async
 * @author    Matt Porritt <mattp@catalyst-au.net>
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_async extends \core\task\scheduled_task {

    /**
     * Get task name
     */
    public function get_name() {
        return get_string('pluginname', 'report_async');
    }

    /**
     * Execute task
     */
    public function execute() {
        global $CFG, $DB;

        $config = get_config('report_coursesize');

        if (!$config->calcmethod == 'live'){
            mtrace("Cron calculations are disabled for report_coursesize, see plugin settings. Aborting.");
            return;
        }

        mtrace("Starting report_coursesize tasks...");

        $result = report_coursesize_task();

        if ($result === true) {
            mtrace("Task complete");
        } else {
            mtrace("Task failed");
        }

    }
}

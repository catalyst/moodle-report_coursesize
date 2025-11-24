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
 * Helper
 *
 * @package report_coursesize
 * @author Adam Olley <adam.olley@openlms.net>
 * @copyright Copyright (c) 2021 Open LMS (https://www.openlms.net)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_coursesize\local;

/**
 * Utility helper class for common operations used by report_coursesize.
 */
class helper {
    /**
     * Returns an array of display and sorting options for the report.
     *
     * This method processes optional URL parameters and returns a structured
     * list of options used to render the report output consistently.
     *
     * @return array associative array of report options.
     */
    public static function get_options(): array {
        $sortorderdefault = 'ssize';
        $sortdirdefault = 'desc';
        $displaysizedefault = 'auto';
        $excludebackupsdefault = 0;

        $sortorder = optional_param('sorder', $sortorderdefault, PARAM_TEXT);
        $sortdir = optional_param('sdir', $sortdirdefault, PARAM_TEXT);
        $displaysize = optional_param('display', $displaysizedefault, PARAM_TEXT);
        $excludebackups = optional_param('excludebackups', $excludebackupsdefault, PARAM_INT);

        // Display options.
        $orderoptions = [
            'ssize' => new \lang_string('ssize', 'report_coursesize'),
            'salphan' => new \lang_string('salphan', 'report_coursesize'),
            'salphas' => new \lang_string('salphas', 'report_coursesize'),
            'sorder' => new \lang_string('sorder', 'report_coursesize'),
        ];
        $diroptions = [
            'asc' => new \lang_string('asc'),
            'desc' => new \lang_string('desc'),
        ];
        $sizeoptions = [
            'auto' => new \lang_string('sizeauto', 'report_coursesize'),
            'gb' => new \lang_string('sizegb'),
            'mb' => new \lang_string('sizemb'),
            'kb' => new \lang_string('sizekb'),
            'b' => new \lang_string('sizeb'),
        ];

        $sortorder = array_key_exists($sortorder, $orderoptions) ? $sortorder : $sortorderdefault;
        $sortdir = array_key_exists($sortdir, $diroptions) ? $sortdir : $sortdirdefault;
        $displaysize = array_key_exists($displaysize, $sizeoptions) ? $displaysize : $displaysizedefault;

        $config = get_config('report_coursesize');
        if (!empty($config->alwaysdisplaymb)) {
            $displaysize = 'mb';
        }

        return [
            'diroptions' => $diroptions,
            'orderoptions' => $orderoptions,
            'sizeoptions' => $sizeoptions,
            'displaysize' => $displaysize,
            'excludebackups' => $excludebackups,
            'sortdir' => $sortdir,
            'sortorder' => $sortorder,
        ];
    }
}

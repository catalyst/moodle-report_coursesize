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
 * Course size report
 * This file gets included by index.php and callback.php as they are using the same options
 *
 * @package    report
 * @subpackage coursesize
 * @author     Kirill Astashov <kirill.astashov@gmail.com>
 * @copyright  2012 NetSpot Pty Ltd {@link http://netspot.com.au}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

$sortorderdefault = 'ssize';
$sortdirdefault = 'desc';
$displaysizedefault = 'auto';
$excludebackupsdefault = 0;
$exportdefault = '';

$sortorder = optional_param('sorder', $sortorderdefault, PARAM_TEXT);
$sortdir = optional_param('sdir', $sortdirdefault, PARAM_TEXT);
$displaysize = optional_param('display', $displaysizedefault, PARAM_TEXT);
$excludebackups = optional_param('excludebackups', $excludebackupsdefault, PARAM_INT);
$export = optional_param('export', $exportdefault, PARAM_TEXT);

// display options
$orderoptions = array('ssize' => get_string('ssize', 'report_coursesize'),
                      'salphan' => get_string('salphan', 'report_coursesize'),
                      'salphas' => get_string('salphas', 'report_coursesize'),
                      'sorder' => get_string('sorder', 'report_coursesize'),
                );
$diroptions = array('asc' => get_string('asc'),
                    'desc' => get_string('desc'),
               );
$sizeoptions = array('auto' => get_string('sizeauto', 'report_coursesize'), 
                     'gb' => get_string('sizegb'),
                     'mb' => get_string('sizemb'),
                     'kb' => get_string('sizekb'),
                     'b' => get_string('sizeb'),
               );
$sortorder = array_key_exists($sortorder, $orderoptions) ? $sortorder : $sortorderdefault;
$sortdir = array_key_exists($sortdir, $diroptions) ? $sortdir : $sortdirdefault;
$displaysize = array_key_exists($displaysize, $sizeoptions) ? $displaysize : $displaysizedefault;

$config = get_config('report_coursesize');
if (!empty($config->alwaysdisplaymb)) {
    $displaysize = 'mb';
}

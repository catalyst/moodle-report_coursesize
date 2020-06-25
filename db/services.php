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
 * Course breakdown.
 *
 * @package    report_coursesize
 * @copyright  2017 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// We defined the web service functions to install.
$functions = array(
    'report_coursesize_site' => array(
        'classname' => 'report_coursesize_external',
        'methodname' => 'site',
        'classpath' => 'report/coursesize/externallib.php',
        'description' => 'Return full site size',
        'type' => 'read',
    ),
    'report_coursesize_site_details' => array(
        'classname' => 'report_coursesize_external',
        'methodname' => 'site_details',
        'classpath' => 'report/coursesize/externallib.php',
        'description' => 'Return full site size with details',
        'type' => 'read',
    )
);

// We define the services to install as pre-build services. A pre-build service is not editable by administrator.
$services = array(
    'coursesizeservice' => array(
        'functions' => array('report_coursesize_site', 'report_coursesize_site_details'),
        'restrictedusers' => 0,
        'enabled' => 1,
    )
);
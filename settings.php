<?php
defined('MOODLE_INTERNAL') || die;
$ADMIN->add('reports', new admin_externalpage('reportcoursesize', get_string('pluginname', 'report_coursesize'), "$CFG->wwwroot/report/coursesize/index.php"));
$settings = null;
?>

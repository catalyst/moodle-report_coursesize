<?php
    require_once('../../config.php');
    require_once($CFG->libdir.'/adminlib.php');

    admin_externalpage_setup('reportcoursesize');
    if (!empty($CFG->filessize) && !empty($CFG->filessizeupdated) && ($CFG->filessizeupdated > time() - 2 * DAYSECS)) {
        // Total files usage has been recently calculated, and stored by another process - use that:
        $totalusage = $CFG->filessize;
        $totaldate = date("Y-m-d H:i", $CFG->filessizeupdated);
    } else {
        // Total files usage either hasn't been stored, or is out of date:
        $totaldate = date("Y-m-d H:i", time());
        $totalusage = du($CFG->dataroot);
        // TODO: check if CFG->pathtodu is set, and if so, use it
        //       this will speed up linux systems.
        //       for now, all OS are the same speed
        // TODO: Save this result in $CFG->filessize and $CFG->filessizeupdated
        //       so that it's available for the next report hit
    }
    $totalusagereadable = number_format(ceil($totalusage/1048576)) . " MB";

    // TODO: display the sizes of directories (other than filedir) in dataroot
    //       eg old 1.9 course dirs, temp, sessions etc

    // Generate a full list of context sitedata usage stats
    $subsql = 'SELECT f.contextid, sum(f.filesize) as filessize' .
            ' FROM {files} f';
    $wherebackup = ' WHERE component like \'backup\'';
    $groupby = ' GROUP BY f.contextid';
    $sizesql = 'SELECT cx.id, cx.contextlevel, cx.instanceid, cx.path, cx.depth, size.filessize, backupsize.filessize as backupsize' .
            ' FROM {context} cx ' .
            ' INNER JOIN ( ' . $subsql . $groupby . ' ) size on cx.id=size.contextid' .
            ' LEFT JOIN ( ' . $subsql . $wherebackup . $groupby . ' ) backupsize on cx.id=backupsize.contextid' .
            ' ORDER by cx.depth ASC, cx.path ASC';
    $cxsizes = $DB->get_records_sql($sizesql);
    $coursesizes = array(); // To track a mapping of courseid to filessize
    $coursebackupsizes = array(); // To track a mapping of courseid to backup filessize
    $usersizes = array(); // To track a mapping of users to filesize
    $systemsize = $systembackupsize = 0;
    $coursesql = 'SELECT cx.id, c.id as courseid ' .
            'FROM {course} c ' .
            ' INNER JOIN {context} cx ON cx.instanceid=c.id AND cx.contextlevel = ' . CONTEXT_COURSE;
    $courselookup = $DB->get_records_sql($coursesql);
    $courses = $DB->get_records('course');
    $users = $DB->get_records('user');

    foreach($cxsizes as $cxid => $cxdata) {
        $contextlevel = $cxdata->contextlevel;
        $instanceid = $cxdata->instanceid;
        $contextsize = $cxdata->filessize;
        $contextbackupsize = (empty($cxdata->backupsize) ? 0 : $cxdata->backupsize);
        if ($contextlevel == CONTEXT_USER) {
            $usersizes[$instanceid] = $contextsize;
            $userbackupsizes[$instanceid] = $contextbackupsize;
            continue;
        }
        if ($contextlevel == CONTEXT_COURSE) {
            $coursesizes[$instanceid] = $contextsize;
            $coursebackupsizes[$instanceid] = $contextbackupsize;
            continue;
        }
        if (($contextlevel == CONTEXT_SYSTEM) || ($contextlevel == CONTEXT_COURSECAT)) {
            $systemsize = $contextsize;
            $systembackupsize = $contextbackupsize;
            continue;
        }
        // Not a course, user, system, category, see it it's something that should be listed under a course:
        // Modules & Blocks mostly:
        $path = explode('/', $cxdata->path);
        array_shift($path); // get rid of the leading (empty) array item
        array_pop($path); // Trim the contextid of the current context itself

        $success = false; // Course not yet found.
        // Look up through the parent contexts of this item until a course is found:
        while(count($path)) {
            $contextid = array_pop($path);
            if (isset($courselookup[$contextid])) {
                $success = true; //Course found
                // record the files for the current context against the course
                $courseid = $courselookup[$contextid]->courseid;
                if (!empty($coursesizes[$courseid])) {
                    $coursesizes[$courseid] += $contextsize;
                    $coursebackupsizes[$courseid] += $contextbackupsize;
                } else {
                    $coursesizes[$courseid] = $contextsize;
                    $coursebackupsizes[$courseid] = $contextbackupsize;
                }
                break;
            }
        }
        if (!$success) {
            // Didn't find a course
            // A module or block not under a course?
            $systemsize += $contextsize;
            $systembackupsize += $contextbackupsize;
        }
    }

    $coursetable = new html_table();
    $coursetable->align = array('right','right', 'right');
    $coursetable->head = array(get_string('course'),
            get_string('diskusage','report_coursesize'),
            get_string('backupsize', 'report_coursesize'));
    $coursetable->data=array();

    arsort($coursesizes);
    foreach ($coursesizes as $courseid => $size) {
        $backupsize = $coursebackupsizes[$courseid];
        $course = $courses[$courseid];
        $row = array();
        $row[] = '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">' . $course->shortname . '</a>';
        $readablesize = number_format(ceil($size/1048576)) . "MB";
        $a = new stdClass;
        $a->bytes = $size;
        $a->shortname = $course->shortname;
        $a->backupbytes = $backupsize;
        $bytesused = get_string('coursebytes', 'report_coursesize', $a);
        $backupbytesused = get_string('coursebackupbytes', 'report_coursesize', $a);
        $row[] = "<span title=\"$bytesused\">$readablesize</span>";
        $row[] = "<span title=\"$backupbytesused\">" . number_format(ceil($backupsize/1048576)) . " MB</span>";
        $coursetable->data[] = $row;
        unset($courses[$courseid]);
    }

    // Now add the courses that had no sitedata into the table
    $a = new stdClass;
    $a->bytes = 0;
    $a->backupbytes = 0;
    foreach($courses as $cid => $course) {
        $a->shortname = $course->shortname;
        $bytesused = get_string('coursebytes', 'report_coursesize', $a);
        $bytesused = get_string('coursebackupbytes', 'report_coursesize', $a);
        $row = array();
        $row[] = '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">' . $course->shortname . '</a>';
        $row[] = "<span title=\"$bytesused\">0 MB</span>";
        $row[] = "<span title=\"$bytesused\">0 MB</span>";
        $coursetable->data[] = $row;
    }


    if (!empty($usersizes)) {
        arsort($usersizes);
        $usertable = new html_table();
        $usertable->align = array('right','right');
        $usertable->head = array(get_string('user'),'Disk Usage');
        $usertable->data=array();
        foreach ($usersizes as $userid => $size) {
            $user = $users[$userid];
            $row = array();
            $row[] = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$userid.'">' . fullname($user) . '</a>';
            $row[] = number_format(ceil($size/1048576)) . "MB";
            $usertable->data[] = $row;
        }
    }
    $systemsizereadable = number_format(ceil($systemsize/1048576)) . "MB";
    $systembackupreadable = number_format(ceil($systembackupsize/1048576)) . "MB";

    // All the processing done, the rest is just output stuff.

    print $OUTPUT->header();
    print $OUTPUT->heading(get_string("sitefilesusage", 'report_coursesize'));
    print get_string("totalsitedata", 'report_coursesize', $totalusagereadable) . "<br/>\n";
    print get_string("sizerecorded", "report_coursesize", $totaldate) . "<br/>\n";
    if (!empty($CFG->filessizelimit)) {
        print get_string("sizepermitted", 'report_coursesize', number_format($CFG->filessizelimit)). "<br/>\n";
    }

    print $OUTPUT->heading(get_string('coursesize', 'report_coursesize'));
    print html_writer::table($coursetable);
    print $OUTPUT->heading(get_string('users'));
    if (!isset($usertable)) {
        print get_string('nouserfiles', 'report_coursesize');
    } else {
        print html_writer::table($usertable);
    }
    print $OUTPUT->heading(get_string('system', 'report_coursesize'));
    print get_string('catsystemuse', 'report_coursesize', $systemsizereadable) . "<br/>";
    print get_string('catsystembackupuse', 'report_coursesize', $systembackupreadable) . "<br/>";

    print $OUTPUT->footer();

function du ($dirname) {
    if (empty($dirname) || !is_dir($dirname)) {
        return 0;
    }

    $du = 0;
    $handle = opendir($dirname);
    if (!$handle) {
        return 0;
    }
    while ($item = readdir($handle)) {
        if (!$item) {
            continue;
        }
        if ($item == '.' || $item == '..') {
            // Ignore implied directories
            continue;
        }
        $path = $dirname . '/' . $item;
        $itemsize = filesize($path);
        $du += $itemsize;
        if (is_dir($path)) {
            $subdirsize = du($dirname . '/' . $item);
            $du += $subdirsize;
        }
    }
    closedir($handle);
    return $du;
}
?>

<?php

class block_grade_notify extends block_list {
    function init() {
        $this->title = get_string('pluginname', 'block_grade_notify');
    }

    function applicable_formats() {
        return array('site' => true, 'my' => true);
    }

    function get_content() {
        global $USER;

        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';

        // User has permission
        $student_courses = watchtower_get_user_courses_as_student($USER->id);

        // The user isn't enrolled as a student in his courses
        if (empty($student_courses)) {
            return $this->content;
        }

        $select_script = $CFG->wwwroot.'/blocks/grade_notify/select.php';
        $name = get_string('select', 'block_grade_notify');
        $helpbutton = helpbutton('select', $name, 'block_grade_notify', true, false, '', true);

        $this->content->items[] = $helpbutton . '<a href="'.$select_script.'">'. $name .'</a>';

        return $this->content;
    }

    // Cron work here does the email notifications
    function cron() {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/blocks/grade_notify/lib.php');

        $now = time();

        // Make sure we're running at midnight
        $sitetimezone = $CFG->timezone;

        // Midnight "tonight" is actually tomorrow morning
        $midnight = usergetmidnight($now, $sitetimezone) + GRADE_NOTIFY_INTERVAL;

        // 11 PM
        $before_midnight = $midnight - 3600;

        // It's not time to run yet
        if ($now < $before_midnight) {
            return false;
        }

        $attempts = array('success' => 0, 'failure' => 0);

        // First get config users
        $users = watchtower_get_distinct_config_users();

        foreach ($users as $userid => $user) {
            // Attempt to get courses he/she is capable of viewing
            $student_courses = watchtower_get_user_courses_as_student($userid);
            $changes = array();

            // empty means we can process the next student
            if (empty($student_courses)) {
                continue;
            }

            // Now get the configs for this user
            $configs = watchtower_get_configs($userid);

            // We're only concerned where the configs exists and valid courses
            $courseids = array_intersect(array_keys($configs), array_keys($student_courses));

            // For each valid course, see if a grade changed
            foreach ($courseids as $courseid) {
                $course = $student_courses[$courseid];

                $change = watchtower_gather_changes($now, $userid, $course);
                if ($change != '') {
                    $changes[] = $change;
                }
            }

            $attempt = (watchtower_notify_grade_changes($userid, $changes)) ?
                        'success' : 'failure';
            $attempts[$attempt] += 1;
        }

        mtrace('Successfully emailed: ' . $attempts['success']);
        mtrace('Failed email attempts: ' . $attempts['failure']);
        return true;
    }
}

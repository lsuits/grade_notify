<?php

class block_grade_notify extends block_list {
    function init() {
        $this->title = get_string('pluginname', 'block_grade_notify');
    }

    function applicable_formats() {
        return array('site' => true, 'my' => true);
    }
    
    function has_config() {
        return true;
    }

    function get_content() {
        global $CFG, $USER;
        
        $this->content = is_null($this->content) ? new stdClass : $this->content;
        
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';

        require_once($CFG->dirroot . '/blocks/grade_notify/lib.php');

        $student_courses = grade_notify::courses_as_student($USER->id);

        if (empty($student_courses)) {
            return $this->content;
        }

        $select_str = get_string('select', 'block_grade_notify');
        $select_url = new moodle_url('/blocks/grade_notify/select.php');

        $this->content->items[] = html_writer::link($select_url, $select_str);

        return $this->content;
    }

    function cron() {
        global $CFG, $USER, $DB;

        require_once($CFG->dirroot . '/blocks/grade_notify/lib.php');

        $blockname = 'block_grade_notify';

        $interval = (int)get_config($blockname, 'croninterval');
        $lastcron = $DB->get_field('block', 'lastcron', array('name' => 'grade_notify'));

        $now = time();

        $runready = ($now - $lastcron) >= ($interval * 60 * 60);

        if (!$runready) {
            return false;
        }

        $attempts = array('success' => 0, 'failure' => 0);

        $users = grade_notify::distinct_config_users();

        foreach ($users as $userid => $user) {
            $student_courses = grade_notify::courses_as_student($userid);
            $changes = array();

            if (empty($student_courses)) {
                continue;
            }

            $configs = grade_notify::user_configs($userid);

            // We're only concerned where the configs exists and valid courses
            $courseids = array_intersect(
                array_keys($configs), array_keys($student_courses)
            );

            // For each valid course, see if a grade changed
            foreach ($courseids as $courseid) {
                $course = $student_courses[$courseid];

                $change = grade_notify::gather_changes($now, $userid, $course);
                if (!empty($change)) {
                    $obj = new stdClass;
                    $obj->userid = $userid;
                    $obj->courseid = $course->id;

                    $a = new stdClass;
                    $a->link = grade_notify::generate_gradebook_link($obj)->out();
                    $a->fullname = $course->fullname;

                    $changes[] = get_string('course_link', $blockname, $a);
                    $changes[] = $change;
                    $changes[] = '-------------------------------------------';
                }
            }

            $attempt = (grade_notify::notify_grade_changes($userid, $changes)) ?
                'success' : 'failure';

            $attempts[$attempt] += 1;
        }

        mtrace(get_string('success_notified', $blockname, $attempts['success']));
        mtrace(get_string('failure_notified', $blockname, $attempts['failure']));

        return true;
    }
}

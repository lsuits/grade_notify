<?php

abstract class grade_notify_handler {
    private static function cleanup($params) {
        global $DB;

        return $DB->delete_records('block_grade_notify_entries', $params);
    }

    private static function retrieve($params) {
        global $DB;

        return $DB->get_record('block_grade_notify_entries', $params);
    }

    private static function always($userid) {
        global $DB;

        $params = array('userid' => $userid, 'courseid' => 0);
        return self::retrieve($params);
    }

    private static function assignment_to_params($ra) {
        $context = context::instance_by_id($ra->contextid, MUST_EXIST);

        return array('userid' => $ra->userid, 'courseid' => $context->instanceid);
    }

    public static function user_deleted($user) {
        return self::cleanup(array('userid' => $user->id));
    }

    public static function course_deleted($course) {
        return self::cleanup(array('courseid' => $course->id));
    }

    public static function role_assigned($ra) {
        $params = self::assignment_to_params($ra);

        if (self::retrieve($params)) {
            return true;
        } else if (self::always($ra->userid)) {
            global $DB;

            $entry = (object)$params;

            $DB->insert_record('block_grade_notify_entries', $entry);
        }

        return true;
    }

    public static function role_unassigned($ra) {
        $params = self::assignment_to_params($ra);

        // Only delete is exists (better DB performance)
        if (self::retrieve($params)) {
            return self::cleanup($params);
        }

        return true;
    }
}

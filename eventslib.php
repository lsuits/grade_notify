<?php

abstract class grade_notify_handler {
    private static function cleanup($params) {
        global $DB;

        return $DB->delete_records('block_grade_notify_entries', $params);
    }

    public static function user_deleted($user) {
        return self::cleanup(array('userid' => $user->id));
    }

    public static function course_deleted($course) {
        return self::cleanup(array('courseid' => $course->id));
    }
}

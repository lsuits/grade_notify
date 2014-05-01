<?php

abstract class grade_notify {
    const TABLE_NAME = 'block_grade_notify_entries';

    public static function distinct_config_users() {
        global $DB;

        $sql = 'SELECT DISTINCT(userid) FROM {'.self::TABLE_NAME.'}';

        return $DB->get_records_sql($sql);
    }

    public static function user_configs($userid) {
        global $DB;

        $params = array('userid' => $userid);

        return $DB->get_records_menu(self::TABLE_NAME, $params, '', 'courseid, id');
    }

    public static function courses_as_student($userid) {
        $my_courses = enrol_get_users_courses($userid, true);

        // Anonymous function to pass the user id value
        return array_filter($my_courses, function($course) use ($userid) {
            if (empty($course->visible)) return false;

            $gradebookroles = get_config('moodle', 'gradebookroles');

            // If the user is in the gradeable roles, then he/she is a student
            $context = context_course::instance($course->id);
            $roles = explode(",", $gradebookroles);

            $students = get_role_users($roles, $context);

            return isset($students[$userid]);
        });
    }

    public static function notify_grade_changes($userid, $changes) {
        // don't even bother with no changes
        if (empty($changes)) {
            return true;
        }

        $allow_messaging = (bool) get_config('moodle', 'messaging');

        // A student will get notified regardless if messaging is enabled
        return $allow_messaging ?
            self::notify_via_messaging($userid, $changes) :
            self::notify_via_email($userid, $changes);
    }

    private static function notify_via_email($userid, $changes) {
        global $DB;

        $user = $DB->get_record('user', array('id' => $userid));

        $subject = get_string('subject', 'block_grade_notify');

        $body = implode("\n\n", $changes);

        $body_html = self::text_to_html($body);

        // Only care if the message was sent or not
        return (bool) email_to_user($user, null, $subject, $body, $body_html);
    }

    private static function notify_via_messaging($userid, $changes) {
        $my_url = new moodle_url('/my');

        $eventdata = new stdClass;
        $eventdata->userfrom = $userid;
        $eventdata->userto = $userid;
        $eventdata->subject = get_string('subject', 'block_grade_notify');
        $eventdata->fullmessage = implode("\n\n", $changes);
        $eventdata->fullmessagehtml = self::text_to_html($eventdata->fullmessage);
        $eventdata->fullmessageformat = FORMAT_HTML;
        $eventdata->smallmessage = $eventdata->fullmessagehtml;

        $eventdata->name = 'grade_changes';
        $eventdata->component = 'block_grade_notify';
        $eventdata->notification = 1;

        // Only care if the message was saved or sent
        $messageid = message_send($eventdata);
        return (bool)$messageid;
    }

    public static function gather_changes($now, $userid, $course) {
        $interval = (int)get_config('block_grade_notify', 'croninterval');
        $from = $now - ($interval * 60 * 60);

        $item_changes = self::item_history_changes($from, $now, $userid, $course);
        $grade_changes = self::grade_changes($from, $now, $userid, $course);

        $item_formatter = array('grade_notify', 'format_grade_item');
        $grade_formatter= array('grade_notify', 'format_grade_grades');

        $changes = array_merge(
            array_map($item_formatter, $item_changes),
            array_map($grade_formatter, $grade_changes)
        );

        return implode("\n", $changes);
    }

    public static function grade_changes($from, $now, $userid, $course) {
        global $DB;

        // Attempt to get grade grade changes
        /* Rules: grade must be visible
                grade item must be visible
                grade item must NOT be a category of any kind
        */
        $sql = "SELECT gg.id, gg.userid, gi.courseid, gi.itemname
                    FROM {grade_items} gi,
                         {grade_grades} gg
                    WHERE gg.userid = :userid
                    AND gi.courseid = :courseid
                    AND gg.itemid = gi.id
                    AND gi.hidden = 0
                    AND gg.hidden = 0
                    AND (gi.itemtype = 'manual' OR gi.itemtype = 'mod')
                    AND (gg.timemodified > :from AND gg.timemodified < :now)";

        $params = array(
            'userid' => $userid,
            'courseid' => $course->id,
            'now' => $now,
            'from' => $from
        );

        // This will get me all the grade changes
        return $DB->get_records_sql($sql, $params);
    }

    public static function text_to_html($text) {
        $patn = '/\((.+)\)/';
        $replr = '(<a href="\1">'.get_string('view_grades', 'block_grade_notify').'</a>)';
        return str_replace("\n", "<br/>", preg_replace($patn, $replr, $text));
    }

    public static function generate_gradebook_link($obj) {
        $params = array('id' => $obj->courseid, 'userid' => $obj->userid);

        return new moodle_url('/grade/report/user/index.php', $params);
    }

    public static function format_grade_item($item) {
        return get_string('item_visible', 'block_grade_notify', $item);
    }

    public static function format_grade_grades($grade) {
        return get_string('grade_changed', 'block_grade_notify', $grade);
    }

    public static function item_history_changes($from, $now, $userid, $course) {
        global $DB;

        // Attempt to get all grade item changes
        $sql = "SELECT gi.*, {$userid} AS userid
                    FROM {grade_items} gi
                    WHERE gi.courseid = :courseid
                    AND gi.hidden = 0
                    AND (gi.timemodified > :from AND gi.timemodified < :now)";

        $params = array('courseid' => $course->id, 'from' => $from, 'now' => $now);

        $grade_item_changes = $DB->get_records_sql($sql, $params);

        $filter = function($grade_item) use ($params, $DB) {
            // Test that each grade item had a hidden history change
            $sql = "SELECT *
                        FROM {grade_items_history} gih
                        WHERE oldid = :itemid
                        AND courseid = :courseid
                        AND hidden > 0
                        AND (timemodified > :from AND timemodified < :now)";

            $item_params = array('itemid' => $grade_item->id) + $params;

            $results = $DB->get_records_sql($sql, $item_params);

            return !empty($results);
        };

        return array_filter($grade_item_changes, $filter);
    }
}

<?php

function watchtower_get_distinct_config_users() {
    global $CFG;

    $sql = "SELECT DISTINCT(usersid)
                FROM {$CFG->prefix}block_grade_notify_entries";

    $rtn = get_records_sql($sql);
    return ($rtn) ? $rtn : array();
}

function watchtower_get_configs($usersid) {
    return get_records_select_menu('block_grade_notify_entries', 'usersid='.$usersid,
                                '', 'coursesid, id');
}

function watchtower_get_checked($setting) {
    return ($setting) ? 'checked="CHECKED"' : '';
}

function watchtower_print_checkbox_selector($course) {
    global $USER;

    $config = get_record('block_grade_notify_entries', 'coursesid', $course->id,
                         'usersid', $USER->id);

    $derived = "selected_{$course->id}";

    $html = array();
    $html[] = '<input id="'.$derived.'" type="checkbox" name="'.$derived.'"
                value="1" '. watchtower_get_checked($config).'/>';
    $html[] = '<label for="'.$derived.'">'.$course->fullname.'</label>';

    return implode(" ", $html);
}


function watchtower_get_user_courses_as_student($userid) {
    $my_courses = get_my_courses($userid);
    $visible_courses = array_filter($my_courses, 'watchtower_filter_visible');

    // Anonymous function to pass the user id value
    return array_filter($visible_courses, create_function('$course', '
        global $CFG;

        // If the user is in the gradeable roles, then he/she is a student
        $context = get_context_instance(CONTEXT_COURSE, $course->id);
        $roles = explode(",", $CFG->gradebookroles);
        $students = get_role_users($roles, $context);

        return isset($students['.$userid.']);
    '));
}

function watchtower_filter_visible($course) {
    return $course->visible == 1;
}

function watchtower_notify_grade_changes($userid, $changes) {
    // don't even bother with no changes
    if(empty($changes)) {
        return true;
    }

    $user = get_record('user', 'id', $userid);

    // A null $from will default to no-reply address
    $subject = get_string('subject', 'block_grade_notify');
    $body_no_html = implode("\n\n", $changes);
    $body_html = watchtower_text_to_html($body_no_html);

    return email_to_user($user, null, $subject, $body_no_html, $body_html);
}

function watchtower_gather_changes($now, $userid, $course) {
    $from = $now - GRADE_NOTIFY_INTERVAL;

    $item_changes = watchtower_get_item_history_change($from, $now, $userid, $course);
    $grade_changes = watchtower_get_grade_changes($from, $now, $userid, $course);

    $changes =  array_merge(array_map('watchtower_format_grade_item', $item_changes),
                array_map('watchtower_format_grade_grades', $grade_changes));

    return implode("\n", $changes);
}

function watchtower_get_grade_changes($from, $now, $usersid, $course) {
    global $CFG;

    // Attempt to get grade grade changes
    /* Rules: grade must be visible
              grade item must be visible
              grade item must NOT be a category of any kind
    */
    $sql = "SELECT gg.id, gg.userid, gi.courseid, gi.itemname, c.fullname
                FROM {$CFG->prefix}grade_items gi,
                     {$CFG->prefix}grade_grades gg,
                     {$CFG->prefix}course c
                WHERE gg.userid = {$usersid}
                  AND gi.courseid = {$course->id}
                  AND c.id = gi.courseid
                  AND gg.itemid = gi.id
                  AND gi.hidden = 0
                  AND gg.hidden = 0
                  AND (gi.itemtype = 'manual' OR gi.itemtype = 'mod')
                  AND (gg.timemodified > {$from} AND gg.timemodified < {$now})";

    // This will get me all the grade changes
    $grade_changes = get_records_sql($sql);
    return (!$grade_changes) ? array() : $grade_changes;

}

function watchtower_text_to_html($text) {
    $pattern = '/\((.+)\)/';
    $replace = '(<a href="\1">\1</a>)';
    return str_replace("\n", "<br/>", preg_replace($pattern, $replace, $text));
}

function watchtower_generate_gradebook_link($obj) {
    global $CFG;
    return "$CFG->wwwroot/grade/report/user/index.php?id=$obj->courseid&userid=$obj->userid";
}

function watchtower_format_grade_item($item) {
    $item->link = watchtower_generate_gradebook_link($item);
    return get_string('item_visible', 'block_grade_notify', $item);
}

function watchtower_format_grade_grades($grade) {
    $grade->link = watchtower_generate_gradebook_link($grade);
    return get_string('grade_changed','block_grade_notify', $grade);
}

function watchtower_get_item_history_change($from, $now, $usersid, $course) {
    global $CFG;

    // Attempt to get all grade item changes
    $sql = "SELECT gi.*, c.fullname, {$userid} AS userid
                FROM {$CFG->prefix}grade_items gi,
                     {$CFG->prefix}course c
                WHERE gi.courseid = {$course->id}
                  AND gi.courseid = c.id
                  AND gi.hidden = 0
                  AND (gi.timemodified > {$from} AND gi.timemodified < {$now})";

    $temp = get_records_sql($sql);
    $grade_item_changes = ($temp) ? $temp : array();

    $filter = create_function('$grade_item', '
        global $CFG;

        // Test that each grade item had a hidden history change
        $sql = "SELECT *
                    FROM {$CFG->prefix}grade_items_history gih
                    WHERE oldid = {$grade_item->id}
                      AND courseid = {$grade_item->courseid}
                      AND hidden > 0
                      AND (timemodified > '.$from.' AND timemodified < '.$now.')";

        return (get_records_sql($sql)) ? true : false;
    ');

    return array_filter($grade_item_changes, $filter);
}

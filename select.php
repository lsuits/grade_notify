<?php

require_once '../../config.php';
require_once 'lib.php';
require_once 'select_form.php';

$student_courses = grade_notify::courses_as_student($USER->id);

$course = $DB->get_record('course', array('id' => SITEID), '*', MUST_EXIST);

$context = context_system::instance();

$base_url = new moodle_url('/blocks/grade_notify/select.php');

$blockname = get_string('pluginname', 'block_grade_notify');
$heading = get_string('select', 'block_grade_notify');

$title = "$course->shortname: $heading";

$PAGE->set_url($base_url);
$PAGE->set_context($context);
$PAGE->set_heading($title);
$PAGE->set_title($title);

$PAGE->navbar->add($blockname);
$PAGE->navbar->add($heading);

$module = array(
    'name' => 'block_grade_notify',
    'fullpath' => '/blocks/grade_notify/js/module.js',
    'requires' => array('base', 'dom')
);

$PAGE->requires->js_init_call('M.block_grade_notify.init', array(), false, $module);

$form = new select_form(null, array('courses' => $student_courses));

if ($form->is_cancelled()) {
    redirect(new moodle_url('/my'));
} else if ($data = $form->get_data()) {
    $current_configs = grade_notify::user_configs($USER->id);

    $fields = (array)$data;
    unset($fields['submitbutton']);

    foreach ($fields as $courseid => $checked) {
        if (isset($current_configs[$courseid])) {
            unset($current_configs[$courseid]);
            continue;
        }

        $config = new stdClass;
        $config->userid = $USER->id;
        $config->courseid = $courseid;

        $DB->insert_record(grade_notify::TABLE_NAME, $config);
    }

    // We don't want to delete configs in hidden courses
    foreach ($current_configs as $courseid => $userid) {
        if (!isset($student_courses[$courseid])) {
            continue;
        }

        $params = array('courseid' => $courseid, 'userid' => $USER->id);

        $DB->delete_records(grade_notify::TABLE_NAME, $params);
    }

    $posted = true;
}

$current_configs = grade_notify::user_configs($USER->id);

$data = array();
foreach (array_keys($current_configs) as $courseid) {
    $data[$courseid] = 1;
}

$form->set_data($data);

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);

if (!empty($posted)) {
    echo $OUTPUT->notification(get_string('changessaved'), 'notifysuccess');
}

$form->display();

echo $OUTPUT->footer();

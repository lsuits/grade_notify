<?php

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $title = get_string('croninterval', 'block_grade_notify');
    $desc = get_string('croninterval_desc', 'block_grade_notify');

    $settings->add(new admin_setting_configtext('block_grade_notity/croninterval',
        $title, $desc, 24));
}

<?php

require_once $CFG->libdir . '/formslib.php';

class select_form extends moodleform {
    function definition() {
        global $USER;

        $m =& $this->_form;

        $courses = $this->_customdata['courses'];

        $m->addElement('header', 'header', get_string('select', 'block_grade_notify'));

        $all = html_writer::link('#', get_string('all'), array('class' => 'all'));
        $none = html_writer::link('#', get_string('none'), array('class' => 'none'));

        $links = implode(' / ', array($all, $none));
        $m->addElement('static', 'label', '', $links);

        $m->addElement('checkbox', "0", '', ' ' . get_string('all_future', 'block_grade_notify'));

        foreach ($courses as $course) {

            $course_url = new moodle_url('/course/view.php', array('id' => $course->id));

            $attrs = array('target' => '_blank');
            $course_link = html_writer::link($course_url, get_string('go'), $attrs);

            $course_label = $course->fullname . ' (' . $course_link . ')';

            $name = $course->id;
            $m->addElement('checkbox', $name, '', ' ' . $course_label);
            $m->setType($name, PARAM_INT);
        }

        $this->add_action_buttons();
    }
}

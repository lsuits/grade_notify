<?php

$mapper = function($event_name) {
    return array(
        'handlerfile' => '/blocks/grade_notify/eventslib.php',
        'handlerfunction' => array('grade_notify_handler', $event_name),
        'schedule' => 'instant'
    );
};

$events = array('course_deleted', 'user_deleted');

$handlers = array_combine($events, array_map($mapper, $events));

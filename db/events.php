<?php

$mapper = function($event_name) {
    return array(
        'handlerfile' => '/blocks/grade_notify/eventslib.php',
        'handlerfunction' => array('grade_notify_handler', $event_name),
        'schedule' => 'instant'
    );
};

$events = array(
    'course_deleted', 'user_deleted', 'role_assigned', 'role_unassigned'
);

$handlers = array_combine($events, array_map($mapper, $events));

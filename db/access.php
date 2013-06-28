<?php

$capabilities = array(
    'block/grade_notify:myaddinstance' => array(
        
        'captype'       => 'read',
        'contextlevel'  => CONTEXT_MODULE,
        'archetypes'    => array(
            'user'      => CAP_ALLOW,
        ),
        'clonepermissionsfrom' => 'moodle/my:manageblocks',
    ),
    
    'block/grade_notify:addinstance' => array(
        
        'captype'       => 'read',
        'contextlevel'  => CONTEXT_MODULE,
        'archetypes'    => array(
            'manager'   => CAP_ALLOW,
        ),
        'clonepermissionsfrom' => 'moodle/site:manageblocks',
    ),
);
?>

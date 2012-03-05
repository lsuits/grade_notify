<?php

require_once("../../config.php");
require_once("lib.php");

$student_courses = watchtower_get_user_courses_as_student($USER->id);

if(empty($student_courses)) {
    print_error('no_permission', 'block_grade_notify');
}

require_js(array($CFG->wwwroot . '/blocks/grade_notify/jquery-1.4.2.min.js',
                 $CFG->wwwroot . '/blocks/grade_notify/functions.js'));

$blockname = get_string('blockname', 'block_grade_notify');
$script = get_string('select', 'block_grade_notify');
$navigation = array(
              array('name' => $blockname, 'link' => '', 'type' => 'title'),
              array('name' => $script, 'link' => '', 'type' => 'title')
              );
print_header_simple($script, '', build_navigation($navigation));
print_heading_with_help($script, 'select', 'block_grade_notify');

// They posted their config
if($data = data_submitted()) {
    // Get all entries they have
    $configs = watchtower_get_configs($USER->id);

    // Empty failures
    $failures = array();

    foreach($student_courses as $course) {
        // If it's set in the POST, then the user wants notifications
        // make sure that they currently don't have any settings
        if(isset($data->{'selected_'.$course->id})) {

            // Unset, continue
            if(isset($configs[$course->id])) {
                unset($configs[$course->id]);
                continue;
            }

            $config = new stdclass;
            $config->usersid = $USER->id;
            $config->coursesid = $course->id;

            if(!$id = insert_record('block_grade_notify_entries', $config, true)) {
                $failures[] = '<div class="failure">'.
                     get_string('failure', 'block_grade_notify', $course).'</div>';
            }
        }
    }

    // Delete remaining entries
    if(!empty($configs)) {
        $ids = implode(",", array_values($configs));
        delete_records_select('block_grade_notify_entries', 'id IN('.$ids.')');
    }

    // Print out message
    echo '<div class="changes">';
    if(!empty($failures)) {
        echo implode(' ', $failures);
    } else {
        echo '<div class="success">'.get_string('success', 'block_grade_notify').'</div>';
    }
    echo '</div>';
}

$mapped_html = array_map('watchtower_print_checkbox_selector', $student_courses);

// For the button
$saved = count_records('block_grade_notify_entries', 'usersid', $USER->id);
$disabled = ($saved == 0) ? "DISABLED" : '';
$class = ($saved == 0) ? 'no ' : '';

// Print out the form
echo '<form method="POST">
        <div class="grades_form">
            '.(($saved > 0) ? print_explanation() : '').'
            <div class="buttons">
                <a class="all" href="select.php?selected=all">'.get_string('all').'</a> |
                <a class="none" href="select.php?selected=none">'.get_string('none').'</a>
            </div>
        '.implode("<br/>", $mapped_html) .'
        </div>
        <br/>

        <div class="buttons">
            <input class="'.$class.'saved" '.$disabled.' name="submit" type="submit" value="'.get_string("submit").'"/>
        </div>
      </form>';

print_footer();

function print_explanation() {
    $info = get_string('explain', 'block_grade_notify');
    echo '<div class="info_courses" style="text-align: center; font-style: italic; margin-bottom: 17px;">
            <span>
            '.$info.'
            </span>
          </div>';
}

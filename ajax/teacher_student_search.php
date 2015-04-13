<?php

define('AJAX_SCRIPT', true);

require('../../../config.php');
require('../renderer.php');
global $DB,$USER;

$post = optional_param('value','',PARAM_RAW);
$t_sql = "SELECT DISTINCT c.id, c.fullname,u.lastname,r.name
                    FROM {$CFG->prefix}role_assignments ra
                    JOIN {$CFG->prefix}user u ON u.id = ra.userid
                    JOIN {$CFG->prefix}role r ON r.id = ra.roleid
                    JOIN {$CFG->prefix}context ct ON ct.id = ra.contextid
                    JOIN {$CFG->prefix}course c ON c.id = ct.instanceid
                   WHERE (r.shortname = 'teacher'
                          OR r.shortname = 'editingteacher'
                          OR r.shortname = 'masterteacher')
                         AND u.id={$USER->id};";
$teacher_courses = $DB->get_records_sql($t_sql);
//print_object($teacher_courses);
$students_first = array();
$students_last = array();
$roleid = $DB->get_record('role',array('shortname'=>'student'))->id;
foreach($teacher_courses as $course){
    $coursecontext = context_course::instance($course->id);
    $enroled_users = get_enrolled_users($coursecontext, $withcapability = '', $groupid = 0, $userfields = 'u.id,u.firstname,u.lastname', $orderby = null,$limitfrom = 0, $limitnum = 0, $onlyactive = true);
    foreach($enroled_users as $e_user){
        if(user_has_role_assignment($e_user->id, $roleid, $contextid = $coursecontext->id)){
            if(in_array($e_user,$students_first)){
                continue;
            } elseif(!$post){
                $students_first[]=$e_user;
                continue;
            }
            if(strpos($e_user->firstname,$post) !== false){
                $students_first[]= $e_user;
            }
            if(strpos($e_user->lastname,$post) !== false){
                $students_last[]=$e_user;
            }
        }
    }
}
usort($students_first,'flexdates_sort_array_by_lastname');
usort($students_last,'flexdates_sort_array_by_lastname');

if($post){
  $return = render_list($students_first);
  $return[]= html_writer::tag('li','',array('class'=>'divider'));
  $return = array_merge($return,render_list($students_last));
  echo html_writer::alist($return, array('class'=>'nav navmenu-nav'), $tag = 'ul');
} else{
  echo render_list($students_first,true);
}

function render_list($students,$wrapped=false){
    $items = array();
    foreach($students as $student){
        $url = new moodle_url('/local/flexdates/teacher.php', array('student' => $student->id));
        $text = $student->firstname.' '.$student->lastname;
        $items[] = html_writer::link($url, $text);
    }
    if($wrapped){
        return html_writer::alist($items, array('class'=>'nav navmenu-nav'), $tag = 'ul');
    } else{
        return $items;
    }
}
?>

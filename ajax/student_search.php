<?php

define('AJAX_SCRIPT', true);

require('../../../config.php');
require('../renderer.php');
global $DB,$USER;

$post = $_POST['value'];
$where_first = "department='{$USER->idnumber}' AND LOWER(firstname) LIKE LOWER('$post%')";
$advisees_first = $DB->get_records_select('user',$where_first);

$where_last = "department='{$USER->idnumber}' AND LOWER(lastname) LIKE LOWER('$post%')";
$advisees_last = $DB->get_records_select('user',$where_last);

if($post){
  $return = render_list($advisees_first);
  $return[]= html_writer::tag('li','',array('class'=>'divider'));
  $return = array_merge($return,render_list($advisees_last));
  echo html_writer::alist($return, array('class'=>'nav navmenu-nav'), $tag = 'ul');
} else{
  $advisees = $DB->get_records('user',array('department'=>$USER->idnumber));
  echo render_list($advisees,true);
}

function render_list($advisees,$wrapped=false){
    $items = array();
    foreach($advisees as $advisee){
        $url = new moodle_url('/local/flexdates_dashboard/advisor.php', array('student' => $advisee->id));
        $text = $advisee->firstname.' '.$advisee->lastname;
        $items[] = html_writer::link($url, $text);
    }
    if($wrapped){
        return html_writer::alist($items, array('class'=>'nav navmenu-nav'), $tag = 'ul');
    } else{
        return $items;
    }
}
?>

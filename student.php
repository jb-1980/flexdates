<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Student view of flexdates dashboard.
 *
 * @package    local_flexdates_dashboard
 * @copyright  2014 Joseph Gilgen 
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require('../../config.php');
require_once('lib.php');

require_login();
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/flexdates_dashboard/student.php');
$output = $PAGE->get_renderer('local_flexdates_dashboard');
//$PAGE->requires->css();

#echo '<head>
#        <link href="bootstrap3/css/bootstrap.min.css" rel="stylesheet">
#        <link href="bootstrap3/css/bootstrap-theme.min.css" rel="stylesheet">
#        <link href="bootstrap3/css/dashboard.css" rel="stylesheet">
#      </head>';
echo $OUTPUT->header();

//Get user data, and enrolled courses
global $USER;

$courses = flexdates_get_tracked_courses($USER->id);
//print_object($courses);
//Get grade data for each of user's courses
$courses_data = array ();

foreach($courses as $course){
    $sql = "SELECT mdl_user_enrolments.timestart 
            FROM mdl_user_enrolments 
            INNER JOIN mdl_enrol 
            ON mdl_enrol.id=mdl_user_enrolments.enrolid 
            WHERE mdl_enrol.enrol = 'manual'
            AND mdl_enrol.courseid = {$course->id}
            AND mdl_user_enrolments.userid = {$USER->id};";
    $enrol_record = $DB->get_record_sql($sql, array(), $strictness=MUST_EXIST);
    $data = new stdClass;
    $data->id  = $course->id;
    $data->title = $course->shortname;
    $data->name = $course->fullname;
    $data->summary = $course->summary;
    $data->startdate = $enrol_record->timestart;
    $data->grades = flexdates_get_student_grades($course->id,$USER->id);
    $courses_data[$data->id] = $data;
}
//print_object($courses_data);
#$html = "
#<div class='navbar navbar-inverse navbar-fixed-top' role='navigation'>
#  <div class='container-fluid'>
#    <div class='navbar-header'>
#      <button type='button' class='navbar-toggle collapsed' data-toggle='collapse' data-target='.navbar-collapse'>
#        <span class='sr-only'>Toggle navigation</span>
#        <span class='icon-bar'></span>
#        <span class='icon-bar'></span>
#        <span class='icon-bar'></span>
#      </button>
#      <a class='navbar-brand' href='{$CFG->wwwroot}'>Home</a>
#    </div>
#    <!-- Collect the nav links, forms, and other content for toggling -->
#    <div class='collapse navbar-collapse' id='bs-example-navbar-collapse-1'>
#      <ul class='nav navbar-nav'>
#        <li class='active'><a href='#'>Link</a></li>
#        <li><a href='#'>Link</a></li>
#        <li class='dropdown'>
#          <a href='#' class='dropdown-toggle' data-toggle='dropdown'>Dropdown <span class='caret'></span></a>
#          <ul class='dropdown-menu' role='menu'>
#            <li><a href='#'>Action</a></li>
#            <li><a href='#'>Another action</a></li>
#            <li><a href='#'>Something else here</a></li>
#            <li class='divider'></li>
#            <li><a href='#'>Separated link</a></li>
#            <li class='divider'></li>
#            <li><a href='#'>One more separated link</a></li>
#          </ul>
#        </li>
#      </ul>
#      <form class='navbar-form navbar-left' role='search'>
#        <div class='form-group'>
#          <input type='text' class='form-control' placeholder='Search'>
#        </div>
#        <button type='submit' class='btn btn-default'>Submit</button>
#      </form>
#      <ul class='nav navbar-nav navbar-right'>
#        <li><a href='#'>Link</a></li>
#        <li class='dropdown'>
#          <a href='#' class='dropdown-toggle' data-toggle='dropdown'>Dropdown <span class='caret'></span></a>
#          <ul class='dropdown-menu' role='menu'>
#            <li><a href='#'>Action</a></li>
#            <li><a href='#'>Another action</a></li>
#            <li><a href='#'>Something else here</a></li>
#            <li class='divider'></li>
#            <li><a href='#'>Separated link</a></li>
#          </ul>
#        </li>
#      </ul>
#    </div>
#  </div>
#</div>";
$html = "<div class='container-fluid'>";

$today = new DateTime();
//Parse data and display
$html .= "<ul class='nav nav-tabs' role='tablist'>";
$first = true;
foreach($courses_data as $data){
    $link = flexdates_string_to_url($data->title);
    if($first){
        $html.= "<li class='active'><a href='#{$link}' data-toggle='tab'>{$data->title}</a></li>";
        $first = false;
    } else{
        $html.= "<li><a href='#{$link}' data-toggle='tab'>{$data->title}</a></li>";
    }
}
$html.="</ul>";
echo $html;
$html = "<div class='tab-content'>";
$first = true;
foreach($courses_data as $data){
    $completed = 0.0;
    $num_assign = 0;
    $mastered = 0.0;
    $expected = 0.0;
    $lessondurations = new stdClass;
    usort($data->grades->items,'flexdates_sort_array_by_sortorder');
    foreach($data->grades->items as $assignment){
        $grades = $assignment->grades;
        if($assignment->itemtype == 'course'){
            $course_grade = $grades->str_grade;
            continue;
        } else if($assignment->itemtype == 'category'){
            continue;
        } else{
            $item_id = $assignment->id;
            $lessondurations->$item_id = new stdClass;
            $lessondurations->$item_id->duration = $assignment->duration;
            $num_assign ++;
            if($grades->mastered){
                $completed ++;
                $mastered ++;
                $lessondurations->$item_id->notsubmitted = false;
            } else if($grades->datesubmitted or $grades->dategraded){
                $completed ++;
                $lessondurations->$item_id->notsubmitted = false;
            } else{
                $lessondurations->$item_id->notsubmitted = true;
            }
            if($grades->duedate < $today->getTimestamp()){
                $expected ++;
            }
        }
    }
    //print_object($lessondurations);
    $per_complete = $num_assign ? $completed/$num_assign : 0;
    $per_master = $num_assign ? $mastered/$num_assign : 0;
    $per_expected = $num_assign ? $expected/$num_assign : 0;
    $anglelist = array($per_master,$per_complete,$per_expected);
    $grade_graph = flexdates_makesvg($anglelist, $course_grade, $cx = 100, $cy = 100, $radius=95);
    $link = flexdates_string_to_url($data->title);
    if($first){
        $html.= "<div class='tab-pane active' id='{$link}'>";
        $first = false;
    } else{
        $html.= "<div class='tab-pane' id='{$link}'>";
    }    
    if($completion_record = $DB->get_record('local_fd_completion_dates',array('userid'=>$USER->id,'courseid'=>$data->id))){
        $sem_length = flexdates_get_days_in_semester($completion_record->startdate,$completion_record->completiondate,$excluded_dates = array());
        //print_object($sem_length);
        $projected_completion_date = flexdates_get_projected_completion_date($lessondurations,$sem_length,$excluded_dates=array());
        $completion_date = DateTime::createFromFormat('U', $completion_record->completiondate);
        $date_diff = $projected_completion_date->getTimestamp()-$completion_date->getTimestamp();
    } else{
        $completion_date = date();//'No end date is set';
        $date_diff = 0;
    }
    $days_in_course = flexdates_get_days_in_course($data->startdate);
    $raw_grade = round(flexdates_get_raw_grade($data->id,$data->grades->items),1);
    //$date_bucket = studentdash_get_available_due_dates($d1, $d2, array());
    //studentdash_get_category_children($data->id);
    
    $html.= $output->render_course_well($data);
#    $html.= "
#              <div class='well span12'>
#                <h2 style='text-align:center;'>
#                  <a href='{$CFG->wwwroot}/course/view.php?id={$data->id}'>
#                    <button type='button' class='btn btn-primary btn-large' style='font-size:130%;'>{$data->name}</button>
#                  </a>
#                </h2>
#                <div style='height:auto;'>{$data->summary}</div>
#              </div>
#            
#            
    $html.="<div class='row'>
              <div class='span4'>
                <div class='flexdates-panel flexdates-panel-success'>
                  <div class='flexdates-panel-heading' style='text-align:center;'>Grade Info: <i class='icon-question-sign' style='font-size:120%;float:right;' title='Grading and progress information. The inner circle is the amount of work expected. Red indicates you are far behind, orange is slightly behind, green is on schedule, and blue is far ahead. The outer circle is the amount of work completed and/or mastered. Details are reported in the center of the circle.'></i></div>
                  <div class='flexdates-panel-body' style='text-align:center;'>{$grade_graph}</div>
               </div>
              </div>";
    $html.= "<div class='span4'>";
    if($days_in_course < 100){
        $html.="<div class='flexdates-panel flexdates-panel-success'>
                <div class='flexdates-panel-heading' style='text-align:center;'>Number of days enrolled in course: <i class='icon-question-sign' style='font-size:120%;float:right;' title='The number of school days, excluding weekends and holidays, you have been enrolled. If this goes beyond 180 you will be in danger of being dropped from the course with an F.'></i></div>
                <div class='flexdates-panel-body' style='text-align:center;font-size:20px;'>{$days_in_course}</div>
               </div>";
    } else if($days_in_course < 130){
        $html.="<div class='flexdates-panel flexdates-panel-warning'>
                <div class='flexdates-panel-heading' style='text-align:center;'>Number of schools days enrolled in course: <i class='icon-question-sign' style='font-size:120%;float:right;' title='The number of school days, excluding weekends and holidays, you have been enrolled. If this goes beyond 180 you will be in danger of being dropped from the course with an F.'></i></div>
                <div class='flexdates-panel-body' style='text-align:center;font-size:20px;'>{$days_in_course}</div>
               </div>";
    } else{
        $html.="<div class='flexdates-panel flexdates-panel-danger'>
                <div class='flexdates-panel-heading' style='text-align:center;'>Number of schools days enrolled in course: <i class='icon-question-sign' style='font-size:120%;float:right;' title='The number of school days, excluding weekends and holidays, you have been enrolled. If this goes beyond 180 you will be in danger of being dropped from the course with an F.'></i></div>
                <div class='flexdates-panel-body' style='text-align:center;font-size:20px;'>{$days_in_course}</div></span>
               </div>";
    }
    if($raw_grade < 50){
        $html.="<div class='flexdates-panel flexdates-panel-danger'>
                <div class='flexdates-panel-heading' style='text-align:center;'>Raw Grade: <i class='icon-question-sign' style='font-size:120%;float:right;' title='This is what your grade would be if all ungraded assignments were counted as 0. It should start at 0 and approach 100 as you get to the end of the course.'></i></div>
                <div class='flexdates-panel-body' style='text-align:center;font-size:20px;'>{$raw_grade}%</div>
               </div>";
    } else if($raw_grade < 80){
        $html.="<div class='flexdates-panel flexdates-panel-warning'>
                <div class='flexdates-panel-heading' style='text-align:center;'>Raw Grade: <i class='icon-question-sign' style='font-size:120%;float:right;' title='This is what your grade would be if all ungraded assignments were counted as 0. It should start at 0 and approach 100 as you get to the end of the course.'></i></div>
                <div class='flexdates-panel-body' style='text-align:center;font-size:20px;'>{$raw_grade}%</div>
               </div>";
    } else{
        $html.="<div class='flexdates-panel flexdates-panel-success'>
                <div class='flexdates-panel-heading' style='text-align:center;'>Raw Grade: <i class='icon-question-sign' style='font-size:120%;float:right;' title='This is what your grade would be if all ungraded assignments were counted as 0. It should start at 0 and approach 100 as you get to the end of the course.'></i></div>
                <div class='flexdates-panel-body' style='text-align:center;font-size:20px;'>{$raw_grade}%</div>
               </div>";
    }
    $html.="</div>";
    $html.= "<div class='span4'>
               <div class='flexdates-panel flexdates-panel-default'>
                <div class='flexdates-panel-heading' style='text-align:center;'>Expected completion date: <i class='icon-question-sign' style='font-size:120%;float:right;' title='This is the target date by which you should complete all work in the course. Generally, it is 90 school days after enrolment.'></i></div>
                <div class='flexdates-panel-body' style='text-align:center;font-size:20px;'>{$completion_date->format('Y-m-d')}</div>
               </div>";
    if($date_diff >864000){
        $html.="<div class='flexdates-panel flexdates-panel-danger'>
                <div class='flexdates-panel-heading' style='text-align:center;'>Projected completion date: <i class='icon-question-sign' style='font-size:120%;float:right;' title='Based on current work completed and pacing, this is the calculated date that you will complete the course by. If it is later than the expected completion date, you should make adjustments to get caught up.'></i></div>
                <div class='flexdates-panel-body' style='text-align:center;font-size:20px;'>{$projected_completion_date->format('Y-m-d')}</div>
               </div>";
    } else if($date_diff > 432000){
        $html.="<div class='flexdates-panel flexdates-panel-warning'>
                <div class='flexdates-panel-heading' style='text-align:center;'>Projected completion date: <i class='icon-question-sign' style='font-size:120%;float:right;' title='Based on current work completed and pacing, this is the calculated date that you will complete the course by. If it is later than the expected completion date, you should make adjustments to get caught up.'></i></div>
                <div class='flexdates-panel-body' style='text-align:center;font-size:20px;'>{$projected_completion_date->format('Y-m-d')}</div>
               </div>";
    } else{
        $html.="<div class='flexdates-panel flexdates-panel-success'>
                <div class='flexdates-panel-heading' style='text-align:center;'>Projected completion date: <i class='icon-question-sign' style='font-size:120%;float:right;' title='Based on current work completed and pacing, this is the calculated date that you will complete the course by. If it is later than the expected completion date, you should make adjustments to get caught up.'></i></div>
                <div class='flexdates-panel-body' style='text-align:center;font-size:20px;'>{$projected_completion_date->format('Y-m-d')}</div>
               </div>";
    }
    $html.= "</div></div>";
    $html.= "<div class='clearfix'></div>";
    $html.= "<div class='flexdates-panel flexdates-panel-default'>";
    $html.= "<div class='flexdates-panel-heading' data-toggle='collapse' data-target='.assignments-{$link}' style='cursor:pointer;'>";
    $html.= "<div class='assignments-{$link} collapse in'>Show Assignments &#9660;</div>"; 
    $html.= "<div class='assignments-{$link} collapse'>Hide Assignments &#9650;</div>";
    $html.= "</div>";
    $html.= "<div class='assignments-{$link} panel-collapse collapse'>";
    $html.= "<div class='flexdates-panel-body'><p><span style='font-size:20px;font-weight:bold;'>Legend:</span> <span class='label label-important'>ASSIGNMENT PAST DUE</span> <span class='label label-warning'>SUBMITTED NOT GRADED</span> <span class='label label-success'>SUBMITTED AND GRADED</span> <span class='label label-primary'>MASTERED</span></p></div>";
    $html.= "<table class='table'>";
    $html.= "<tr>
                <th>Assignment</th>
                <th>Due Date</th>
                <th>Grade</th>
              </tr>";

    foreach($data->grades->items as $assignment){
        //print_object($assignment);
        if($assignment->itemtype == 'course' or $assignment->itemtype == 'category'){
            continue;
        }
        $due_date = date('Y-m-d',$assignment->grades->duedate);
        if($assignment->grades->str_grade != '-'){
            if($assignment->grades->mastered){
                $style = 'info';
            } else{
                $style = 'success';
            }
        } else if ($assignment->grades->datesubmitted){
            $style = 'warning';
        } else if ($due_date < date("Y-m-d")){
            $style = 'error';
        } else{
            $style = '';
        }
        $html.="<tr class='{$style}'>
                  <td>{$assignment->name}</th>
                  <td>{$due_date}</th>
                  <td>{$assignment->grades->str_grade}</th>
                </tr>";
    }
    $html.="</table>";
    $html.="</div>";
    $html.="</div>";
    $html.= "</div>";
}
$html.="</div></div></div></div>";
echo $html;

echo "<script type='text/javascript' src='{$CFG->wwwroot}/lib/jquery/jquery-1.11.0.js'></script>
<script src='bootstrap3/js/bootstrap.min.js'></script>";
echo $OUTPUT->footer();





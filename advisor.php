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
$PAGE->set_url('/local/flexdates_dashboard/advisor.php');
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

$html = "<div class='container-fluid'>";

$today = new DateTime();
//Parse data and display
$html .= $output->render_pilltabs($courses_data);
$html .= "<div class='tab-content'>";
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
    //$grade_graph = flexdates_makesvg($anglelist, $course_grade, $cx = 100, $cy = 100, $radius=95);
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
        $completion_date = date();
        $date_diff = 0;
    }
    $days_in_course = flexdates_get_days_in_course($data->startdate);
    $raw_grade = round(flexdates_get_raw_grade($data->id,$data->grades->items),1);

    $html.= $output->render_course_well($data);

    $grade_graph = new stdClass;
    $grade_graph->anglelist = array($per_master,$per_complete,$per_expected);
    $grade_graph->course_grade = $course_grade;
    
    $html.="<div class='row'><div class='span4'>";
    $html.= $output->render_gradegraph_panel($grade_graph);
    $html.="</div><div class='span4'>";
    $html.= $output->render_daysenrolled_panel($days_in_course);
    $html.= $output->render_rawgrade_panel($raw_grade);
    $html.="</div><div class='span4'>";
    $html.= $output->completion_date_panel($completion_date);
    $html.= $output->projected_completion_date_panel($projected_completion_date,$date_diff);
    $html.= "</div></div>";
    $html.= $output->clearfix();
    $html.= "<div class='flexdates-panel flexdates-panel-default'>";
    $html.= "<div class='flexdates-panel-heading' data-toggle='collapse' data-target='.assignments-{$link}' style='cursor:pointer;'>";
    $html.= "<div class='assignments-{$link} collapse in'>Show Assignments &#9660;</div>"; 
    $html.= "<div class='assignments-{$link} collapse'>Hide Assignments &#9650;</div>";
    $html.= "</div>";
    $html.= "<div class='assignments-{$link} panel-collapse collapse'>";
    $html.= "<div class='flexdates-panel-body'><p><span style='font-size:20px;font-weight:bold;'>Legend:</span> <span class='label label-important'>ASSIGNMENT PAST DUE</span> <span class='label label-warning'>SUBMITTED NOT GRADED</span> <span class='label label-success'>SUBMITTED AND GRADED</span> <span class='label label-info'>MASTERED</span></p></div>";
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





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
 * Defines the renderer for the flexdates plugin.
 *
 * @package    local
 * @subpackage flexdates
 * @copyright  2014 Joseph Gilgen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();
require_once('lib.php');

/**
 * Renderer for the flexdates plugin
 *
 * @copyright  2014 Joseph Gilgen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_flexdates_renderer extends plugin_renderer_base {
    /**
     * @var int id of user we want to display dashboard of
     */
    public $userid = null;
    
    public function set_userid($userid){
        $this->userid = $userid;
    }
    
    public function render_student_dashboard($userid){
        global $CFG;
        $this->set_userid($userid);
        // get courses info //
        $courses = flexdates_get_tracked_courses($this->userid);
        // Parse data and prepare for display //
        $container_content = $this->render_navtabs($courses->active);
        $tab_content = '';
        $dash_tab_data = array();
        foreach($courses->active as $data){
            // create data //
            $course_data = new flexdates_course($data,$this->userid);
            $dash_tab_data[]=$course_data;
            $grade_graph = flexdates_make_anglelist($course_data->num_assign,$course_data->completed,$course_data->mastered,$course_data->expected,$course_data->course_grade);

            // begin output // 
            $tabpane_content= $this->render_course_well($data);
            $tabpane_content.= $this->render_progress_report($grade_graph,$course_data->days_in_course,$course_data->raw_grade,$course_data->completion_date,$course_data->projected_completion_date,$course_data->date_diff);
            $tabpane_content.= $this->clearfix();
            $tabpane_content.= "<h3>{$course_data->title} assignments:</h3>";
            $tabpane_content.= "<div style='padding:2px;'>";
            $tabpane_content.= $this->render_assignment_buttons($course_data->link);
            $tabpane_content.= "</div>";
            $tabpane_content.="<div id=\"{$course_data->link}-pastdue\" class=\"collapse\">{$this->render_assignment_legend()}{$this->render_pastdue_table($data->grades->items)}</div>";
            $tabpane_content.="<div id=\"{$course_data->link}-upcoming\" class=\"collapse\">{$this->render_assignment_legend()}{$this->render_upcoming_table($data->grades->items)}</div>";
            $tabpane_content.= "<div id='{$course_data->link}-allassignments' class='collapse'>{$this->render_assignment_legend()}{$this->render_assignments_table($data->grades->items)}</div>";
            $tab_content.= $this->render_tab_pane($tabpane_content,$course_data->link);
        }
        //print_object($dash_tab_data);
        $dashtab_assignments = array();
            foreach($dash_tab_data as $assignments){
                //print_object($assignments);
                foreach($assignments->grades->items as $id=>$assignment){
                    //print_object($assignment);
                    $assignment->course = $assignments->title;
                }
                $dashtab_assignments += $assignments->grades->items;
            }
            //print_object($dashtab_assignments);
            usort($dashtab_assignments,'flexdates_sort_array_by_duedate');
            $dashtab_content = $this->render_dashboard_tabpane('This is where the announcements go!',$dash_tab_data,$dashtab_assignments,$courses->resources);
        $tab_content.= "<div class='tab-pane active' id='flexdates'>{$dashtab_content}</div>";
        $container_content.= $this->render_tab_content($tab_content);
        return $this->render_container_fluid($container_content);
    }
    
    public function render_advisor_dashboard($advisor,$advisee=null){
        global $CFG,$USER,$DB;
        $this->set_userid($advisor);
        $advisees = $DB->get_records('user',array('department'=>$USER->idnumber),$sort='lastname');
        $sidepane = $this->render_advisor_menu($advisees);
        $output = html_writer::div($sidepane,'col-md-2 ',array('id'=>'advisor-sidebar','role'=>'navigation'));
        if($advisee){
            // get courses info //
            $courses = flexdates_get_tracked_courses($advisee->id);
            // Parse data and prepare for display //
#            $toggle_button = html_writer::tag('button','Show students',array('class'=>'btn btn-primary btn-xs','data-toggle'=>'offcanvas'));
#            $dash_content = html_writer::tag('p',$toggle_button,array('class'=>"pull-left visible-xs"));
            $dash_content = $this->render_navtabs($courses->active);
            $tab_content = '';
            $dash_tab_data = array();
            foreach($courses->active as $data){
                // create data //
                $course_data = new flexdates_course($data,$advisee->id);
                $dash_tab_data[]=$course_data;
                $grade_graph = flexdates_make_anglelist($course_data->num_assign,$course_data->completed,$course_data->mastered,$course_data->expected,$course_data->course_grade);

                // begin output // 
                $tabpane_content= $this->render_course_well($data);
                $tabpane_content.= $this->render_progress_report($grade_graph,$course_data->days_in_course,$course_data->raw_grade,$course_data->completion_date,$course_data->projected_completion_date,$course_data->date_diff);
                $tabpane_content.= $this->clearfix();
                $tabpane_content.= "<h3>{$course_data->title} assignments:</h3>";
                $tabpane_content.= "<div style='padding:2px;'>";
                $tabpane_content.= $this->render_assignment_buttons($course_data->link);
                $tabpane_content.= "</div>";
                $tabpane_content.="<div id=\"{$course_data->link}-pastdue\" class=\"collapse\">{$this->render_assignment_legend()}{$this->render_pastdue_table($data->grades->items)}</div>";
                $tabpane_content.="<div id=\"{$course_data->link}-upcoming\" class=\"collapse\">{$this->render_assignment_legend()}{$this->render_upcoming_assignments_dropdown_menu()}{$this->render_upcoming_table($data->grades->items)}</div>";
                $tabpane_content.= "<div id='{$course_data->link}-allassignments' class='collapse'>{$this->render_assignment_legend()}{$this->render_assignments_table($data->grades->items)}</div>";
                $tab_content.= $this->render_tab_pane($tabpane_content,$course_data->link);
            }
            //print_object($dash_tab_data);
            $dashtab_assignments = array();
            foreach($dash_tab_data as $assignments){
                //print_object($assignments);
                foreach($assignments->grades->items as $id=>$assignment){
                    //print_object($assignment);
                    $assignment->course = $assignments->title;
                }
                $dashtab_assignments += $assignments->grades->items;
            }
            //print_object($dashtab_assignments);
            usort($dashtab_assignments,'flexdates_sort_array_by_duedate');
            $dashtab_content = $this->render_dashboard_tabpane('This is where the announcements go!',$dash_tab_data,$dashtab_assignments);
            $tab_content.= "<div class='tab-pane active' id='flexdates'>{$dashtab_content}</div>";
            $dash_content.= $this->render_tab_content($tab_content);
            $output.= $this->render_col_md_x($dash_content,10);
        } else{
            $output.= $this->render_col_md_x('Please choose a student from the menu',10);
        }
        $output.= $this->render_customdates_modal();
        $output = $this->render_container_fluid(html_writer::div($output,'row row-offcanvas row-offcanvas-left'));
        return $output;
    }
    
    public function render_teacher_dashboard($teacher,$student=null){
        global $CFG,$USER,$DB;
        $this->set_userid($teacher);
        // get all courses where user is teacher
        $t_sql = "SELECT DISTINCT c.id, c.fullname,u.lastname,r.name
                    FROM {$CFG->prefix}role_assignments ra
                    JOIN {$CFG->prefix}user u ON u.id = ra.userid
                    JOIN {$CFG->prefix}role r ON r.id = ra.roleid
                    JOIN {$CFG->prefix}context ct ON ct.id = ra.contextid
                    JOIN {$CFG->prefix}course c ON c.id = ct.instanceid
                   WHERE (r.shortname = 'teacher' OR r.shortname = 'editingteacher')
                         AND u.id={$teacher};";
        $teacher_courses = $DB->get_records_sql($t_sql);
        //print_object($teacher_courses);
        $students = array();
        $roleid = $DB->get_record('role',array('shortname'=>'student'))->id;
        foreach($teacher_courses as $course){
            $coursecontext = context_course::instance($course->id);
            $enroled_users = get_enrolled_users($coursecontext, $withcapability = '', $groupid = 0, $userfields = 'u.id,u.firstname,u.lastname', $orderby = null,$limitfrom = 0, $limitnum = 0, $onlyactive = true);
            foreach($enroled_users as $e_user){
                if(user_has_role_assignment($e_user->id, $roleid, $contextid = $coursecontext->id)){
                    if(in_array($e_user,$students)){
                        continue;
                    } else{
                        $students[]= $e_user;
                    }
                }
            }
        }
        usort($students,'flexdates_sort_array_by_lastname');
        $sidepane = $this->render_teacher_menu($students);
        $output = html_writer::div($sidepane,'col-md-2 sidebar-offcanvas',array('id'=>'advisor-sidebar','role'=>'navigation'));
        if($student){
            // get courses info //
            $courses = flexdates_get_tracked_courses($student->id);
            // Parse data and prepare for display //
            $toggle_button = html_writer::tag('button','Show students',array('class'=>'btn btn-primary btn-xs','data-toggle'=>'offcanvas'));
            $dash_content = html_writer::tag('p',$toggle_button,array('class'=>"pull-left visible-xs"));
            $dash_content.= $this->render_navtabs($courses->active);
            $tab_content = '';
            $dash_tab_data = array();
            foreach($courses->active as $data){
                // create data //
                $course_data = new flexdates_course($data,$student->id);
                $dash_tab_data[]=$course_data;
                $grade_graph = flexdates_make_anglelist($course_data->num_assign,$course_data->completed,$course_data->mastered,$course_data->expected,$course_data->course_grade);
//print_object($course_data);
                // begin output // 
                $tabpane_content= $this->render_course_well($data);
                $tabpane_content.= $this->render_progress_report($grade_graph,$course_data->days_in_course,$course_data->raw_grade,$course_data->completion_date,$course_data->projected_completion_date,$course_data->date_diff);
                $tabpane_content.= $this->clearfix();
                $tabpane_content.= "<h3>{$course_data->title} assignments:</h3>";
                $tabpane_content.= "<div style='padding:2px;'>";
                $tabpane_content.= $this->render_assignment_buttons($course_data->link);
                $tabpane_content.= "</div>";
                $tabpane_content.="<div id=\"{$course_data->link}-pastdue\" class=\"collapse\">{$this->render_assignment_legend()}{$this->render_pastdue_table($data->grades->items)}</div>";
                $tabpane_content.="<div id=\"{$course_data->link}-upcoming\" class=\"collapse\">{$this->render_assignment_legend()}{$this->render_upcoming_assignments_dropdown_menu()}{$this->render_upcoming_table($data->grades->items)}</div>";
                $tabpane_content.= "<div id='{$course_data->link}-allassignments' class='collapse'>{$this->render_assignment_legend()}{$this->render_assignments_table($data->grades->items)}</div>";
                $tab_content.= $this->render_tab_pane($tabpane_content,$course_data->link);
            }
            //print_object($dash_tab_data);
            $dashtab_assignments = array();
            foreach($dash_tab_data as $assignments){
                //print_object($assignments);
                foreach($assignments->grades->items as $id=>$assignment){
                    //print_object($assignment);
                    $assignment->course = $assignments->title;
                }
                $dashtab_assignments += $assignments->grades->items;
            }
            //print_object($dashtab_assignments);
            usort($dashtab_assignments,'flexdates_sort_array_by_duedate');
            $dashtab_content = $this->render_dashboard_tabpane('This is where the announcements go!',$dash_tab_data,$dashtab_assignments,$courses->resources);
            $tab_content.= "<div class='tab-pane active' id='flexdates'>{$dashtab_content}</div>";
            $dash_content.= $this->render_tab_content($tab_content);
            $output.= $this->render_col_md_x($dash_content,10);
        } else{
            $output.= $this->render_col_md_x('Please choose a student from the menu',10);
        }
        $output.= $this->render_customdates_modal();
        $output = $this->render_container_fluid(html_writer::div($output,'row row-offcanvas row-offcanvas-left'));
        return $output;
    }
    
    public function render_dashboard_tabpane($announcement,$courses_info,$courses_assignments,$untracked){
        $output = $this->render_announcement_well($announcement);
        $output.= $this->render_dashboard_tab_progress_report_table($courses_info);
        //print_object($untracked);
        $output.= $this->render_dashboard_tab_untracked_courses($untracked);
        $output.= "<div style='padding:2px;'>";
        $output.= $this->render_assignment_buttons('dashboard-tabpane');
        $output.= "</div>";
        $output.="<div id=\"dashboard-tabpane-pastdue\" class=\"collapse\">{$this->render_assignment_legend()}{$this->render_pastdue_table($courses_assignments,true)}</div>";
        $output.="<div id=\"dashboard-tabpane-upcoming\" class=\"collapse\">{$this->render_assignment_legend()}{$this->render_upcoming_assignments_dropdown_menu()}{$this->render_upcoming_table($courses_assignments,true)}</div>";
        $output.= "<div id='dashboard-tabpane-allassignments' class='collapse'>{$this->render_assignment_legend()}{$this->render_assignments_table($courses_assignments,true)}</div>";
        
        return $output;
        
    }
    
    public function render_dashboard_tab_untracked_courses($untracked_courses){
        global $CFG;
        $untracked = html_writer::start_tag('div')."\n";
        foreach($untracked_courses as $course){
            $button=html_writer::tag('a',$course->title,array('class'=>'btn btn-primary btn-large','href'=>"{$CFG->wwwroot}/course/view.php?id={$course->id}",'role'=>'button'));
            $untracked.=html_writer::tag('p',$button,array('style'=>"padding:5px;"))."\n";
        }
        $untracked.= html_writer::end_tag('div')."\n";
        $untracked_div=html_writer::div($untracked,'panel-collapse collapse',array('role'=>'tabpanel','aria-labelledby'=>'untracked-courses','aria-expanded'=>'false',"id"=>"untracked-courses"));
        
        $panel_title=html_writer::tag('h4',get_string('untrackedcourses','local_flexdates'),array("class"=>"panel-title"));
        $header_panel = html_writer::div($panel_title,'panel-heading',array("role"=>"tab","id"=>"untracked-courses-heading",'data-toggle'=>"collapse",'data-target'=>'#untracked-courses'));
        //$content = html_writer::div($collapse_panel."\n".$untracked_div,'panel panel-default',array('data-toggle'=>'collapse','aria-expanded'=>'true','aria-controls'=>'untracked-courses','data-target'=>'#untracked-courses'));
        $untracked = html_writer::div($header_panel."\n".$untracked_div, 'panel panel-default');
        
        
      
      
    
  
        
#        "<span data-toggle='collapse' data-target='#untracked-courses' aria-expanded='false' aria-controls='untracked-courses'>"
#                          .get_string('untrackedcourses','local_flexdates')
#                     ."</span>";
#        $untracked.= "<div class='collapse' id='untracked-courses'>";
#        
#        $untracked.="</div>";
        return $untracked;
    }
    
    public function render_announcement_well($announcement){
        $content= html_writer::div($announcement,'well col-md-12');
        return $this->render_row($content);
    }
    
    /**
     * @param flexdates_course object $course_info Course information for all user's courses
     * @return html table
     */
    public function render_dashboard_tab_progress_report_table($courses_info){
        global $CFG;
        $table = new html_table();
        $table->attributes['class'] = 'table';
        $table->head = array(
            get_string('course','local_flexdates').' '.html_writer::span('',"glyphicon glyphicon-question-sign",array('data-toggle'=>'tooltip','data-trigger'=>'click focus hover','title'=>"Link to your courses")),
            get_string('grade','local_flexdates').' '.html_writer::span('',"glyphicon glyphicon-question-sign",array('data-toggle'=>'tooltip','data-trigger'=>'click focus hover','title'=>"Your current grade based on completed work.")),
            get_string('rawgrade','local_flexdates').' '.html_writer::span('',"glyphicon glyphicon-question-sign",array('data-toggle'=>'tooltip','data-trigger'=>'click focus hover','title'=>"This is what your grade would be if all ungraded assignments were counted as 0. It should start at 0 and approach 100 as you get to the end of the course.")),
            get_string('progress','local_flexdates').' '.html_writer::span('',"glyphicon glyphicon-question-sign",array('data-toggle'=>'tooltip','data-trigger'=>'click focus hover','title'=>"The top bar indicates how much you have completed/mastered, and the bottom bar is how much work is expected by today's date.")),
            get_string('daysincourse','local_flexdates').' '.html_writer::span('',"glyphicon glyphicon-question-sign",array('data-toggle'=>'tooltip','data-trigger'=>'click focus hover','title'=>"The number of school days, excluding weekends and holidays, you have been enrolled. If this goes beyond 180 you will be in danger of being dropped from the course with an F.")),
            get_string('completiondate','local_flexdates').' '.html_writer::span('',"glyphicon glyphicon-question-sign",array('data-toggle'=>'tooltip','data-trigger'=>'click focus hover','title'=>"This is the target date by which you should complete all work in the course. Generally, it is 90 school days after enrolment.")),
            get_string('projectedcompletiondate','local_flexdates').' '.html_writer::span('',"glyphicon glyphicon-question-sign",array('data-toggle'=>'tooltip','data-trigger'=>'click focus hover','title'=>"Based on current work completed and pacing, this is the calculated date that you will complete the course by. If it is later than the expected completion date, you should make adjustments to get caught up."))
        );
        $table->align = array('center','center','center','center','center','center','center');
        foreach($courses_info as $course){
            if($course->completion_date){
                $completiondate = $course->completion_date->format('m-d-Y');
            } else{
                $completiondate = 'No Data';
            }
            
            if($course->projected_completion_date){
                $p_date = $course->projected_completion_date->format('m-d-Y');
            } else{
                $p_date = 'No Data';
            }
            //$table->rowclasses[] = $this->assignment_row_state($assignment);
            $table->data[] = array(
                html_writer::tag('a',$course->title,array('class'=>'btn btn-primary btn-block','href'=>"{$CFG->wwwroot}/course/view.php?id={$course->id}",'role'=>'button')),
                $course->course_grade,
                $course->raw_grade,
                $this->render_progress_bars($course->completed,$course->expected,$course->num_assign),
                $course->days_in_course,
                $completiondate,
                $p_date
            );
        }
        return html_writer::table($table);
    }
    
    public function render_advisor_menu($advisees){
        $items = array();
        foreach($advisees as $advisee){
            $url = new moodle_url('/local/flexdates/advisor.php', array('student' => $advisee->id));
            $text = $advisee->firstname.' '.$advisee->lastname;
            $items[] = html_writer::link($url, $text);
        }
        $students_list = html_writer::alist($items, array('class'=>'nav navmenu-nav'), $tag = 'ul');
        $side_content = html_writer::tag('h4','STUDENTS');
        $side_content.= html_writer::tag('input','',array('id'=>'advisor-students-search','class'=>'form-control','placeholder'=>'search students'));
        $side_content.= html_writer::div($students_list,'',array('id'=>'students-list'));
        return html_writer::div($side_content,'navmenu navmenu-default navmenu-fixed-left offcanvas-sm');
    }
    
    public function render_teacher_menu($students){
        $items = array();
        foreach($students as $student){
            $url = new moodle_url('/local/flexdates/teacher.php', array('student' => $student->id));
            $text = $student->firstname.' '.$student->lastname;
            $items[] = html_writer::link($url, $text);
        }
        $students_list = html_writer::alist($items, array('class'=>'nav navmenu-nav'), $tag = 'ul');
        $side_content = html_writer::tag('h4','STUDENTS');
        $side_content.= html_writer::tag('input','',array('id'=>'teacher-students-search','class'=>'form-control','placeholder'=>'search students'));
        $side_content.= html_writer::div($students_list,'',array('id'=>'students-list'));
        return html_writer::div($side_content,'navmenu navmenu-default navmenu-fixed-left offcanvas-sm');
    }
    
    /**
     * Render the pill tabs for each course in the dashboard
     * @param course object $course the object with course information
     * @return string html to output.
     */
    public function render_navtabs($courses) {
        $output = html_writer::start_tag( 'ul', array('class'=>'nav nav-tabs','role'=>'tablist'));
        $a_content = html_writer::tag('a',get_string('dashboard','local_flexdates'),array('href'=>"#flexdates",'data-toggle'=>'tab'));
        $output.= html_writer::tag('li',$a_content,array('class'=>'active'));
        foreach($courses as $data){
            $link = flexdates_string_to_url($data->title);
            $a_content = html_writer::tag('a',$data->title,array('href'=>"#{$link}",'data-toggle'=>'tab'));
            $output.= html_writer::tag('li',$a_content);
        }
        $output.=html_writer::end_tag('ul');
        return $output;
    }
    
    /**
     * Render the jumbotron well for a course
     * @param course object $course the object with course information
     * @return string html to output.
     */
    public function render_course_well($course) {
        global $CFG;
        $output = html_writer::start_tag('div', array('class'=>'well col-md-12'));
        $output.= html_writer::start_tag('h2', array('style'=>'text-align:center;'));
        $output.= html_writer::start_tag('a', array('href'=>"{$CFG->wwwroot}/course/view.php?id={$course->id}"));
        $output.= html_writer::tag('button', $course->name, array('type'=>'button','class'=>'btn btn-primary btn-large','style'=>'font-size:130%;'));
        $output.= html_writer::end_tag('a');
        $output.= html_writer::end_tag('h2');
        $output.= html_writer::tag('div', $course->summary, array('style'=>'height:auto;'));
        $output.= html_writer::end_tag('div');
        return $this->render_row($output);
    }
    /**
     * Render the progress report row of the dashboard
     * @param str $grade_graph svg element with grade data
     * @param int $days number of days in course
     * @param float $raw_grade raw course grade
     * @param date object $completion_date date of expected completion
     * @param date object $proj_comp_date date of projected completion
     * @param int $date_diff number of seconds between projected date and completion date
     * @return str returns html for progress report row
     */
    public function render_progress_report($grade_graph,$days,$raw_grade,$completion_date,$proj_comp_date,$date_diff){
        $content = $this->render_col_md_x($this->render_gradegraph_panel($grade_graph),4);
        $content.= $this->render_col_md_x($this->render_daysenrolled_panel($days).$this->render_rawgrade_panel($raw_grade),4);
        $content.= $this->render_col_md_x($this->render_completion_date_panel($completion_date).$this->render_projected_completion_date_panel($proj_comp_date,$date_diff),4);
        return $this->render_row($content);
    }
    /**
     * Render the progress report row of the dashboard
     * @param str $grade_graph svg element with grade data
     * @param int $days number of days in course
     * @param float $raw_grade raw course grade
     * @param date object $completion_date date of expected completion
     * @param date object $proj_comp_date date of projected completion
     * @param int $date_diff number of seconds between projected date and completion date
     * @return str returns html for progress report row
     */
    public function render_assignments_row($course,$assignments){
        
    }
    /**
     * @param object $grade object with grade properties to render graph
     */
    public function render_gradegraph_panel($grade){
        $content = flexdates_makesvg($grade->anglelist, $grade->course_grade, $cx = 100, $cy = 100, $radius=95);
        $helper = 'Grading and progress information. The inner circle is the amount of work expected. Red indicates you are far behind, orange is slightly behind, green is on schedule, and blue is far ahead. The outer circle is the amount of work completed and/or mastered. Details are reported in the center of the circle.';
        $header = 'Grade Info: ';
        $state = $this->grade_state($grade->course_grade);
        $head_style = 'text-align:center';
        $body_style = 'text-align:center;font-size:20px;';
        $output = $this->render_panel($header,$helper,$content,$state,$head_style,$body_style);
        return $output;
    }
    
    /**
     * @param int $days number of days in course
     */
    public function render_daysenrolled_panel($days){
        $content = $days;
        $state = $this->days_enrolled_state($days);
        $helper = 'The number of school days, excluding weekends and holidays, you have been enrolled. If this goes beyond 180 you will be in danger of being dropped from the course with an F.';
        $header = 'Number of days enrolled in course: ';
        $head_style = 'text-align:center';
        $body_style = 'text-align:center;font-size:20px;';
        $output = $this->render_panel($header,$helper,$content,$state,$head_style,$body_style);
        return $output;
    }
    
    /**
     * @param float $grade raw course grade
     */
    public function render_rawgrade_panel($grade){
        $content = $grade.'%';
        $state = $this->raw_grade_state($grade);
        $helper = 'This is what your grade would be if all ungraded assignments were counted as 0. It should start at 0 and approach 100 as you get to the end of the course.';
        $header = 'Raw Grade: ';
        $head_style = 'text-align:center';
        $body_style = 'text-align:center;font-size:20px;';
        $output = $this->render_panel($header,$helper,$content,$state,$head_style,$body_style);
        return $output;
    }
    
    /**
     * @param date object $date date of expected completion
     * 
     */
    public function render_completion_date_panel($date){
        $content = $date ? $date->format('m-d-Y') : 'Completion has not been assigned for this course';
        $state = 'default';
        $helper = 'This is the target date by which you should complete all work in the course. Generally, it is 90 school days after enrolment.';
        $header = 'Expected completion date: ';
        $head_style = 'text-align:center';
        $body_style = 'text-align:center;font-size:20px;';
        $output = $this->render_panel($header,$helper,$content,$state,$head_style,$body_style);
        return $output;
    }
    
    /**
     * @param date object $date date of projected completion
     * @param int $date_diff number of seconds between projected date and completion date
     */
    public function render_projected_completion_date_panel($date,$date_diff){
        $content = $date ? $date->format('m-d-Y') : 'Completion has not been assigned for this course';
        $state = $this->projected_completion_state($date_diff);
        $helper = 'Based on current work completed and pacing, this is the calculated date that you will complete the course by. If it is later than the expected completion date, you should make adjustments to get caught up.';
        $header = 'Projected completion date: ';
        $head_style = 'text-align:center';
        $body_style = 'text-align:center;font-size:20px;';
        $output = $this->render_panel($header,$helper,$content,$state,$head_style,$body_style);
        return $output;
    }
    
    /**
     * @param str $header text to write in header
     * @param str $helper text to write in title attribute of helper icon
     * @param str $content text that goes in the panel body
     * @param str $state one of the four bootstrap contextual classes: danger, warning, success, info
     * @param str $head_style extra, inline css styling for the panel head
     * @param str $body_style extra, inline css styling for the panel body
     * @return str returns the html panel
     */
    public function render_panel($header,$helper,$content,$state,$head_style='',$body_style=''){
        $output = '';
        $output.= html_writer::start_tag('div', array('class'=>"panel panel-{$state}"));
        $header_content = $header.html_writer::tag('span','',array('class'=>'glyphicon glyphicon-question-sign','data-toggle'=>'tooltip','data-trigger'=>'click focus hover','style'=>'font-size:120%;float:right;','title'=>$helper));
        $output.= html_writer::tag('div', $header_content, array('class'=>'panel-heading','style'=>$head_style));
        $output.= html_writer::tag('div', $content, array('class'=>'panel-body','style'=>$body_style));
        $output.= html_writer::end_tag('div');
        return $output;
    }
    
    public function render_assignment_buttons($course){
        $output = html_writer::tag('button',get_string('pastdueassignments','local_flexdates'),array('class'=>'btn btn-danger','data-toggle'=>'collapse','data-collapse-group'=>'assignment-divs','data-target'=>"#{$course}-pastdue"));
        $output.= html_writer::tag('button',get_string('upcomingassignments','local_flexdates'),array('class'=>'btn btn-warning','data-toggle'=>'collapse','data-collapse-group'=>'assignment-divs','data-target'=>"#{$course}-upcoming"));
        $output.= html_writer::tag('button',get_string('allassignments','local_flexdates'),array('class'=>'btn btn-success','data-toggle'=>'collapse','data-collapse-group'=>'assignment-divs','data-target'=>"#{$course}-allassignments"));
        return $output;
    }
    
    public function render_assignment_legend(){
        $content = html_writer::span('Legend: ', $class ='', array('style'=>'font-size:20px;font-weight:bold;'));
        $content.= ' '.html_writer::span('ASSIGNMENT PAST DUE', $class ='label label-danger');
        $content.= ' '.html_writer::span('SUBMITTED NOT GRADED', $class ='label label-warning');
        $content.= ' '.html_writer::span('SUBMITTED AND GRADED', $class ='label label-success');
        $content.= ' '.html_writer::span('MASTERED', $class ='label label-primary');
        $output = html_writer::div($content, $class = 'well well-sm');
        return $output;
    }
    
    public function render_pastdue_table($assignments,$dashtab=false){
        //print_object($assignments);
        foreach($assignments as $id=>$assignment){
            if(date('Y-m-d',$assignment->grades->duedate) >= date('Y-m-d') or $assignment->grades->datesubmitted or $assignment->grades->dategraded){
                unset($assignments[$id]);
            }
        }
        return $this->render_assignments_table($assignments,$dashtab);
    }
    
    public function render_upcoming_table($assignments,$dashtab=false){
        foreach($assignments as $id=>$assignment){
            if(date('Y-m-d',$assignment->grades->duedate) < date('Y-m-d')){
                unset($assignments[$id]);
            }
        }
        return $this->render_assignments_table($assignments,$dashtab,true);
    }
    
    
    public function render_assignments_table($assignments,$dashtab=false,$upcoming=false){
        $table = new html_table();
        $table->attributes['class'] = 'table table-hover';
        if($dashtab){
            $table->head = array(
                get_string('course','local_flexdates'),
                get_string('assignment','local_flexdates'),
                get_string('duedate','local_flexdates'),
                get_string('grade','local_flexdates')
            );
        } else{
            $table->head = array(
                get_string('assignment','local_flexdates'),
                get_string('duedate','local_flexdates'),
                get_string('grade','local_flexdates')
            );
        }
        
        foreach($assignments as $assignment){
            if($assignment->itemtype == 'course' or $assignment->itemtype == 'category'){
                continue;
            }
            if(!$upcoming){
                $table->rowclasses[] = $this->assignment_row_state($assignment);
            } else{
                $table->rowclasses[] = $this->assignment_row_state($assignment).' '.'upcoming-assignments';
            }
            if($dashtab){
                $table->data[] = array(
                    $assignment->course,
                    $assignment->name,
                    date('m-d-Y',$assignment->grades->duedate),
                    $assignment->grades->str_grade
                );
            } else{
                $table->data[] = array(
                    $assignment->name,
                    date('m-d-Y',$assignment->grades->duedate),
                    $assignment->grades->str_grade
                );
            }
        }
        if($upcoming){
            if($dashtab){
                $table->colclasses = array(null,null,'upcoming-date',null);
            } else{
                $table->colclasses = array(null,'upcoming-date',null);
            }
        }
        return html_writer::table($table);
    }
    
    public function render_upcoming_assignments_dropdown_menu(){
    
        $dropdown_text = html_writer::span('All time','flexdates-upcoming-assignments-dropdown-text',array('style'=>'font-weight:bold;cursor:pointer;'));
        $dropdown_text.= html_writer::span('','caret');
        $dropdown_box  = html_writer::span($dropdown_text,'dropdown-toggle',array('type'=>'button','id'=>'dropdownmenu','data-toggle'=>'dropdown'));
        $items = array(
            html_writer::tag('a','Next 2 days',array('class'=>'flexdates-assignments-range','role'=>'menuitem','id'=>'flexdates-assignments-next-2')),
            html_writer::tag('a','Next 3 days',array('class'=>'flexdates-assignments-range','role'=>'menuitem','id'=>'flexdates-assignments-next-3')),
            html_writer::tag('a','Next 7 days',array('class'=>'flexdates-assignments-range','role'=>'menuitem','id'=>'flexdates-assignments-next-7')),
            html_writer::tag('a','Next 30 days',array('class'=>'flexdates-assignments-range','role'=>'menuitem','id'=>'flexdates-assignments-next-30')),
            html_writer::tag('a','All time',array('class'=>'flexdates-assignments-range','role'=>'menuitem','id'=>'flexdates-assignments-all-time')),
            html_writer::tag('a','Custom range',array('role'=>'menuitem','class'=>'flexdates-assignments-custom-range','data-toggle'=>'modal','data-target'=>'#customdates-modal'))
        );
        $lists = '';
        foreach ($items as $item) {
            $lists .= html_writer::tag('li', $item,array('role'=>'presentation'))."\n";
        }
        $list = html_writer::tag('ul',$lists,array('class'=>'dropdown-menu','role'=>'menu'))."\n";
        $menu = html_writer::span($dropdown_box.$list,'dropdown',array('style'=>'display:inline-block;'));
        
        return html_writer::div('Show assignments for: '.$menu,'');
    }
    
    public function render_customdates_modal(){
      //modal header
        $modal_header_cont = html_writer::tag('button','&times',array('type'=>'button','class'=>'close','data-dismiss'=>'modal'))."\n";
        $modal_header_cont.= html_writer::tag('h4','Custom Date Range',array('class'=>'modal-title','id'=>'customdates-modal-label'));
        $modal_header = html_writer::div($modal_header_cont,'modal-header');
        
      //modal body
        $table = new html_table();
        $table->attributes['class'] = 'table table-hover';
        $r1c1 = html_writer::start_tag('div',array('class'=>'datepicker_start_input'))."\n";
        $r1c1.= html_writer::tag('label','Start',array('for'=>'datepicker_start'))."\n";
        $r1c1.= html_writer::tag('input','',array('class'=>'btn btn-primary','disabled'=>'true','type'=>'text','name'=>'datepicker_start','value'=>''))."\n";
        $r1c1.= html_writer::end_tag('div');
        $r1c2 = html_writer::start_tag('div',array('class'=>'datepicker_end_input'))."\n";
        $r1c2.= html_writer::tag('label','End',array('for'=>'datepicker_end'))."\n";
        $r1c2.= html_writer::tag('input','',array('class'=>'btn btn-primary','disabled'=>'true','type'=>'text','name'=>'datepicker_end','value'=>''))."\n";
        $r1c2.= html_writer::end_tag('div');
        $table->data[] = array($r1c1,$r1c2);
        $table->data[] = array('<div id="flexdates-datepicker-start"></div>','<div id="flexdates-datepicker-end"></div>');
        $modal_body_cont = html_writer::table($table);
        $modal_body = html_writer::div($modal_body_cont,'modal-body');
      //modal footer
        $modal_footer_cont = html_writer::tag('button','Close',array('type'=>'button','class'=>'btn btn-default','data-dismiss'=>'modal'))."\n";
        $modal_footer_cont.= html_writer::tag('button','Set Custom Range',array('type'=>'button','class'=>'btn btn-primary','id'=>'flexdates-set-custom-range'));
        $modal_footer = html_writer::div($modal_footer_cont,'modal-footer');
      //modal content
        $modal_content = html_writer::div($modal_header.$modal_body.$modal_footer,'modal-content');
      //modal dialog box
        $modal_dialog = html_writer::div($modal_content,'modal-dialog');
        return html_writer::div($modal_dialog,'modal fade',array('id'=>'customdates-modal','tabindex'=>'-1','role'=>'dialog'));
    }
    /**
     * private function used to determine the state for the grade panel class attribute
     * @param int $value the grade in course
     * @return str $state the panel class state
     */
    private function grade_state($value){
        if(in_array($value,array('A+','A','A-','B+','B'))){
            return 'success';
        } elseif(in_array($value,array('B-','C+','C-'))){
            return 'warning';
        }
        return 'danger';
    }
    
    /**
     * private function used to determine the state for the daysenrolled panel class attribute
     * @param int $value the number of days enrolled in course
     * @return str $state the panel class state
     */
    private function days_enrolled_state($value){
        if($value < 100){
            return 'success';
        } elseif($value < 130){
            return 'warning';
        }
        return 'danger';
    }
    
    /**
     * private function used to determine the state for the raw grade panel class attribute
     * @param float $value the raw grade value
     * @return str $state the panel class state
     */
    private function raw_grade_state($value){
        if($value < 50){
            return 'danger';
        } elseif($value < 80){
            return 'warning';
        }
        return 'success';
    }
    
    /**
     * private function used to determine the state for the projected panel class attribute
     * @param float $value the raw grade value
     * @return str $state the panel class state
     */
    private function projected_completion_state($value){
        if($value > 864000){
            return 'danger';
        } elseif($value > 432000){
            return 'warning';
        }
        return 'success';
    }
    
    /**
     * private function used to determine the state for the assignment rows class attribute
     * @param stdClass $value a grade items stdclass
     * @return str $state the panel class state
     */
    private function assignment_row_state($value){
        if($value->grades->str_grade != '-'){
              if($value->grades->mastered){
                  return 'info';
              } else{
                  return 'success';
              }
        } else if ($value->grades->datesubmitted){
            return 'warning';
        } else if (date('Y-m-d',$value->grades->duedate) < date("Y-m-d")){
            return 'danger';
        } else{
             return '';
        }
    }
    
    public function render_progress_bars($completed,$expected,$total){
        $per_comp = round($total ? $completed/$total*100 : 0);
        $per_exp = round($total ? $expected/$total*100 : 0);
        $state = $this->progress_bars_state($per_comp,$per_exp);
        $comp_params = array(
            'data-toggle'=>'tooltip',
            'data-trigger'=>'click focus hover',
            'title'=>"You have completed {$per_comp}% of the assignments",
            'style'=>"margin:3px 0px 3px 0px;"
        );
        $exp_params = array(
            'data-toggle'=>'tooltip',
            'data-trigger'=>'click focus hover',
            'title'=>"By today, you should have completed {$per_exp}% of the assignments",
            'style'=>"margin:3px 0px;"
        );
        $completed_bar = html_writer::div($per_comp.'%',"progress-bar",array('role'=>'progressbar','style'=>"width:{$per_comp}%"));
        $expected_bar = html_writer::div($per_exp.'%',"progress-bar $state",array('role'=>'progressbar','style'=>"width:{$per_exp}%"));
        $ouput = html_writer::div($completed_bar,'progress',$comp_params);
        $ouput.= html_writer::div($expected_bar,'progress',$exp_params);
        return $ouput;
    }
    
    private function progress_bars_state($per_comp,$per_exp){
        if($per_comp == 0 and $per_exp == 0){
            // Boostrap success state
            return 'progress-bar-success';
        }
        $diff = ($per_comp - $per_exp);
        if(abs($diff) < 3){
            // Boostrap success state
            return 'progress-bar-success';
        }
        if($diff < 0){
            if($diff < -10){
                // Bootstrap danger state
                return 'progress-bar-danger';
            } else{
                // Bootstrap warning state
                return 'progress-bar-warning';
            }
        } else{
            if($diff > 10){
                // Boostrap info state
                return 'progress-bar-info';
            } else{
                // Boostrap success state
                return 'progress-bar-success';
            }
        }
    }
    
    public function clearfix(){
        return "<div class='clearfix'></div>";
    }
    
    public function render_container_fluid($content){
        return html_writer::div($content,'container_fluid');
    }
    
    public function render_tab_content($content){
        return html_writer::div($content,'tab-content');
    }
    
    public function render_tab_pane($content,$link_id,$active=false){
        if($active){
            return html_writer::div($content,'tab-pane active',array('id'=>$link_id));
        }
        return html_writer::div($content,'tab-pane',array('id'=>$link_id));
    }
    
    public function render_row($content){
        return html_writer::div($content,'row');
    }
    
    public function render_col_md_x($content,$x){
        return html_writer::div($content,"col-md-{$x}");
    }
    
    public function include_js($js){
        $output = '';
        foreach($js as $script){
            $output.= html_writer::tag('script','',array('src'=>$script))."\n";
        }
        return $output;
    }
}

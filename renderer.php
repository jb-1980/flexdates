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
 * Defines the renderer for the flexdates_dashboard plugin.
 *
 * @package    local
 * @subpackage flexdates_dashboard
 * @copyright  2014 Joseph Gilgen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();
require_once('lib.php');

/**
 * Renderer for the flexdates_dashboard plugin
 *
 * @copyright  2014 Joseph Gilgen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_flexdates_dashboard_renderer extends plugin_renderer_base {
    
    /**
     * Render the pill tabs for each course in the dashboard
     * @param course object $course the object with course information
     * @return string html to output.
     */
    public function render_pilltabs($courses) {
        $output = html_writer::start_tag( 'ul', array('class'=>'nav nav-tabs','role'=>'tablist'));
        $first = true;
        foreach($courses as $data){
            $link = flexdates_string_to_url($data->title);
            $a_content = html_writer::tag('a',$data->title,array('href'=>"#{$link}",'data-toggle'=>'tab'));
            if($first){
                $output.= html_writer::tag('li',$a_content,array('class'=>'active'));
                $first = false;
            } else{
                $output.= html_writer::tag('li',$a_content);
            }
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
        $output = '';
        $output.= html_writer::start_tag('div', array('class'=>'well span12'));
        $output.= html_writer::start_tag('h2', array('style'=>'text-align:center;'));
        $output.= html_writer::start_tag('a', array('href'=>"{$CFG->wwwroot}/course/view.php?id={$course->id}"));
        $output.= html_writer::tag('button', $course->name, array('type'=>'button','class'=>'btn btn-primary btn-large','style'=>'font-size:130%;'));
        $output.= html_writer::end_tag('a');
        $output.= html_writer::end_tag('h2');
        $output.= html_writer::tag('div', $course->summary, array('style'=>'height:auto;'));
        $output.= html_writer::end_tag('div');
        return $output;
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
    public function completion_date_panel($date){
        $content = $date->format('Y-m-d');
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
    public function projected_completion_date_panel($date,$date_diff){
        $content = $date->format('Y-m-d');
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
        $output.= html_writer::start_tag('div', array('class'=>"flexdates-panel flexdates-panel-{$state}"));
        $header_content = $header.html_writer::tag('i','',array('class'=>'icon-question-sign','style'=>'font-size:120%;float:right;','title'=>$helper));
        $output.= html_writer::tag('div', $header_content, array('class'=>'flexdates-panel-heading','style'=>$head_style));
        $output.= html_writer::tag('div', $content, array('class'=>'flexdates-panel-body','style'=>$body_style));
        $output.= html_writer::end_tag('div');
        return $output;
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
    
    public function clearfix(){
        return "<div class='clearfix'></div>";
    }
}

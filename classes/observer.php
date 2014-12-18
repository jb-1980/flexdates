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
 * Event observers used in flexdates.
 *
 * @package    local_flexdates
 * @copyright  2014 Joseph Gilgen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/../../../config.php');
require_once(dirname(__FILE__) . '/../lib.php');
/**
 * Event observer for flexdates.
 */
class local_flexdates_observer {

    /**
     * Triggered via course_module_deleted event.
     *
     * @param \core\event\course_module_deleted $event
     */
    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event) {
        global $CFG,$DB;
        return $DB->delete_records('local_fd_completion_dates',array('userid'=>$event->userid,'courseid'=>$event->courseid));
        
    }
    
    /**
     * Observer for \core\event\course_module_created event.
     *
     * @param \core\event\course_module_created $event
     * @return void
     */
    public static function course_module_duration_updated(\local_flexdates\event\course_module_duration_updated $event) {
        global $CFG,$DB;
        //echo ("I am a studentdash observer, and a module lesson duration was updated. Should I do some work?<br/>");
        // get users in course to update their due dates
        $coursecontext = context_course::instance($event->courseid);
        $users = get_enrolled_users($coursecontext, $withcapability = '', $groupid = 0, $userfields = 'u.id,u.firstname,u.lastname', $orderby = null,$limitfrom = 0, $limitnum = 0, $onlyactive = true);
        $roleid = $DB->get_record('role',array('shortname'=>'student'))->id;
        $lessondurations = $DB->get_records('local_fd_mod_duration',array('courseid'=>$event->courseid),'itemorder');
        foreach($users as $key=>$user){
            //if(user_has_role_assignment($user->id, $roleid, $contextid = $coursecontext->id)){
                // Double check to make sure there is a record in the completion dates table so as to not break the due dates function
                // This should never be executed if everything is set up correctly
                if(!$enrolled = $DB->get_record('local_fd_completion_dates',array('userid'=>$user->id,'courseid'=>$event->courseid))){
                   $enrol_record = new stdClass;
                   $enrol_record->userid = $user->id;
                   $enrol_record->courseid = $event->courseid;
                   $sql = "SELECT mdl_user_enrolments.timestart 
                           FROM mdl_user_enrolments 
                           INNER JOIN mdl_enrol 
                           ON mdl_enrol.id=mdl_user_enrolments.enrolid 
                           WHERE mdl_enrol.enrol = 'manual'
                           AND mdl_enrol.courseid = {$event->courseid}
                           AND mdl_user_enrolments.userid = {$user->id};";
                   // We are just creating a start and end date here, they may be incorrect so we will flag this
                   $enrol_record->startdate = $DB->get_record_sql($sql, array(), $strictness=MUST_EXIST)->timestart;
                   $enrol_record->completiondate = flexdates_get_available_due_dates($enrol_record->startdate, $enrol_record->startdate+140*24*3600,array())[90];
                   $enrol_record->flag = 1;
                   $DB->insert_record('local_fd_completion_dates',$enrol_record);
                }
                echo 'here<br/>';
                flexdates_update_student_due_dates($user->id,$event->courseid,$lessondurations,$excluded_dates=array());
            //}
        }
        
    }
    
    /**
     * Triggered via course_module_deleted event.
     *
     * @param \core\event\course_module_deleted $event
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event) {
        global $CFG,$DB;
        // delete activity duration record from local_fd_activity_duration
        // get gradeitem id from $event
        $gradeitemid = $DB->get_record('grade_items',array('itemmodule'=>$event->other->modulename,'iteminstance'=>$event->other->instanceid))->id;
        $DB->delete_records('local_fd_activity_duration',array('gradeitemid'=>$gradeitem));
        // get users in course to update their due dates
        $coursecontext = context_course::instance($event->courseid);
        $users = get_enrolled_users($coursecontext, $withcapability = '', $groupid = 0, $userfields = 'u.id,u.firstname,u.lastname', $orderby = null,$limitfrom = 0, $limitnum = 0, $onlyactive = true);
        $activitydurations = $DB->get_records('local_fd_mod_duration',array('courseid'=>$event->courseid),'itemorder');
        foreach($users as $key=>$user){
           if(!$enrolled = $DB->get_record('local_fd_completion_dates',array('userid'=>$user->id))){
               $enrol_record = new stdClass;
               $enrol_record->userid = $user->id;
               $enrol_record->courseid = $event->courseid;
               $enrol_record->startdate = 0;
               $enrol_record->completiondate = 0;
               $DB->insert_record('local_fd_completion_dates',$enrol_record);
           }
           flexdates_update_student_due_dates($user->id,$event->courseid,$activitydurations,$excluded_dates=array());
        }

    }
    
    /**
     * Observer for \core\event\course_module_created event.
     *
     * @param \core\event\course_module_created $event
     * @return void
     */
    public static function course_module_created(\core\event\course_module_created $event) {
        global $CFG,$DB;
        # create activity duration record from local_fd_activity_duration with default 0
        // get gradeitem id from $event
        $gradeitemid = $DB->get_record('grade_items',array('itemmodule'=>$event->other->modulename,'iteminstance'=>$event->other->instanceid))->id;
        // build object to populate database with, and create record
        $dataobject = new stdClass;
        $dataobject->gradeitemid = $gradeitemid;
        $dataobject->courseid = $event->courseid;
        $dataobject->duration = 0;
        $dataobject->itemorder = 0;
        $DB->insert_record('local_fd_mod_duration',$dataobject);
        // get users in course to update their due dates
        $coursecontext = context_course::instance($event->courseid);
        $users = get_enrolled_users($coursecontext, $withcapability = '', $groupid = 0, $userfields = 'u.id,u.firstname,u.lastname', $orderby = null,$limitfrom = 0, $limitnum = 0, $onlyactive = true);
        $activitydurations = $DB->get_records('local_fd_mod_duration',array('courseid'=>$event->courseid),'itemorder');
        foreach($users as $key=>$user){
           if(!$enrolled = $DB->get_record('local_fd_completion_dates',array('userid'=>$user->id))){
               $enrol_record = new stdClass;
               $enrol_record->userid = $user->id;
               $enrol_record->courseid = $event->courseid;
               $enrol_record->startdate = 0;
               $enrol_record->completiondate = 0;
               $DB->insert_record('local_fd_completion_dates',$enrol_record);
           }
           flexdates_update_student_due_dates($user->id,$event->courseid,$activitydurations,$excluded_dates=array());
        }
        
    }

}

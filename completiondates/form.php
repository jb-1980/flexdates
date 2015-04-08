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


defined('MOODLE_INTERNAL') || die;

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir.'/formslib.php');
require_once($CFG->libdir.'/gradelib.php');
require_once(dirname(__FILE__) . '/../lib.php');


/**
 * The form for editing the user end dates throughout a course.
 *
 * @copyright 2014 Joseph Gilgen
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_flexdates_completiondates_form extends moodleform {

    public function definition() {
        global $CFG, $COURSE, $DB;
        $mform = $this->_form;
        // Context instance of the course.
        $coursecontext = context_course::instance($COURSE->id);
        //print_object($coursecontext);
        // Check if user has capability to enrol users.
        $canenrol = has_capability('enrol/manual:enrol', $coursecontext);

        // Fetching users in course.
        $enroled_users = get_enrolled_users($coursecontext, $withcapability = '', $groupid = 0, $userfields = 'u.id,u.firstname,u.lastname', $orderby = null,$limitfrom = 0, $limitnum = 0, $onlyactive = true);
        //print_object($enroled_users);
        $mform->addElement('header', 'completiondates',
                get_string('completiondates', 'local_flexdates'));
        $mform->setExpanded('completiondates', True);
        $roleid = $DB->get_record('role',array('shortname'=>'student'))->id;
        // Looping through each user
        foreach($enroled_users as $enroled_user){
            if(user_has_role_assignment($enroled_user->id, $roleid, $contextid = $coursecontext->id)){
                $elname = "student--$enroled_user->id";
                if($record = $DB->get_record('local_fd_completion_dates',array('userid'=>$enroled_user->id,'courseid'=>$COURSE->id))){
                    if($record->flag){
                        $mform->addElement('date_selector',$elname,"$enroled_user->firstname $enroled_user->lastname",array('startyear'=>2014,'stopyear'=>2040),array('style'=>'color:#a94442;background-color:#F2DEDE;'));
                        $mform->setType($elname, PARAM_RAW);
                        $mform->setDefault($elname,$record->completiondate);
                    } else{
                        $mform->addElement('date_selector',$elname,"$enroled_user->firstname $enroled_user->lastname",array('startyear'=>2014,'stopyear'=>2040));
                        $mform->setType($elname, PARAM_RAW);
                        $mform->setDefault($elname,$record->completiondate);
                    }
                } else{
                    $mform->addElement('date_selector',$elname,"$enroled_user->firstname $enroled_user->lastname",array('startyear'=>2014,'stopyear'=>2040),array('style'=>'color:#a94442;background-color:#F2DEDE;'));
                    $mform->setType($elname, PARAM_RAW);
                }
                $mform->addHelpButton($elname, 'completiondate','local_flexdates');
            }
        }

        $this->add_action_buttons();

    }
}

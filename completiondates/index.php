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
 * Script to let users edit expected course completion dates for users in a course.
 *
 * @package   local_flexdates_completiondates
 * @copyright 2014 Joseph Gilgen
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once(dirname(__FILE__) . '/form.php');

$id = required_param('id', PARAM_INT); // Course id.

// Should be a valid course id.
$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

require_login($course);

// Setup page.
$PAGE->set_url('/local/flexdates/completiondates/index.php', array('id'=>$id));
$PAGE->set_pagelayout('admin');

// Check permissions.
$coursecontext = context_course::instance($course->id);
require_capability('local/flexdates:viewcompletiondates', $coursecontext);


// Creating form instance, passed course id as parameter to action url.
$baseurl = new moodle_url('/local/flexdates/completiondates/index.php', array('id' => $id));
$mform = new local_flexdates_completiondates_form($baseurl);

$returnurl = new moodle_url('/course/view.php', array('id' => $id));
if ($mform->is_cancelled()) {
    // Redirect to course view page if form is cancelled.
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    $transaction = $DB->start_delegated_transaction();
    foreach ($data as $key=>$value){
        $enroled_user = explode('--',$key);
        if($enroled_user[0] == 'student'){
            if(!$record = $DB->get_record('local_fd_completion_dates',array('userid'=>$enroled_user[1],'courseid'=>$id))){
                $sql = "SELECT mdl_user_enrolments.timestart 
                        FROM mdl_user_enrolments 
                        INNER JOIN mdl_enrol 
                        ON mdl_enrol.id=mdl_user_enrolments.enrolid 
                        WHERE mdl_enrol.enrol = 'manual'
                        AND mdl_enrol.courseid = {$id}
                        AND mdl_user_enrolments.userid = {$enroled_user[1]};";
                $enrol_record = $DB->get_record_sql($sql, array(), $strictness=MUST_EXIST);
                $dataobject = new stdClass;
                $dataobject->userid = $enroled_user[1];
                $dataobject->courseid = $id;
                $dataobject->startdate = $enrol_record->timestart;
                $dataobject->completiondate = $value;
                $dataobject->flag = 0;
                $DB->insert_record('local_fd_completion_dates',$dataobject);
            } else{
                $sql = "SELECT mdl_user_enrolments.timestart 
                      FROM mdl_user_enrolments 
                      INNER JOIN mdl_enrol 
                      ON mdl_enrol.id=mdl_user_enrolments.enrolid 
                      WHERE mdl_enrol.enrol = 'manual'
                      AND mdl_enrol.courseid = {$id}
                      AND mdl_user_enrolments.userid = {$enroled_user[1]};";
                $enrol_record = $DB->get_record_sql($sql, array(), $strictness=MUST_EXIST);
                $record->startdate = $enrol_record->timestart;
                $record->completiondate = $value;
                $record->flag = 0;
                $DB->update_record('local_fd_completion_dates', $record);
            }
        }
    }
    // Commit transaction.
    $transaction->allow_commit();
    rebuild_course_cache($course->id);
    redirect($returnurl);

} else {
    $PAGE->set_title($course->shortname .': '. get_string('completiondates', 'local_flexdates'));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($course->fullname));
    $mform->display();
    echo $OUTPUT->footer();
}


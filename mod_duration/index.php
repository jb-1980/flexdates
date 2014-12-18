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
 * Script to let users edit duration values for grade items throughout a course.
 *
 * @package   local_flexdates_mod_duration
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
$PAGE->set_url('/local/flexdates_mod_duration/index.php', array('id'=>$id));
$PAGE->set_pagelayout('admin');

// Check permissions.
$coursecontext = context_course::instance($course->id);
require_capability('local/flexdates:viewmodduration', $coursecontext);


// Creating form instance, passed course id as parameter to action url.
$baseurl = new moodle_url('/local/flexdates/mod_duration/index.php', array('id' => $id));
$mform = new local_flexdates_mod_duration_form($baseurl);

$returnurl = new moodle_url('/course/view.php', array('id' => $id));
if ($mform->is_cancelled()) {
    // Redirect to course view page if form is cancelled.
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    //print_object($data);
    // Process data if submitted, update only if user can manage activities in course context
    // lessonduration values from the $data.
    $lessonvalues = $data->lessonvalues;
    //print_object($lessonvalues);
#    // Start transaction.
    $transaction = $DB->start_delegated_transaction();
#    // Cycle through all the gradeitems in the data.
    foreach ($lessonvalues as $key=>$value) {
        //print_object($key);
        //print_object($value);
        if(!$record = $DB->get_record('local_fd_mod_duration',array('gradeitemid'=>$key))){
          $dataobject = new stdClass;
          $dataobject->gradeitemid = $key;
          $dataobject->courseid = $id;
          $dataobject->duration = $value['duration'];
          $dataobject->itemorder = $value['itemorder'];
          $DB->insert_record('local_fd_mod_duration',$dataobject);
        } else{
            $record->duration = $value['duration'];
            $record->itemorder = $value['itemorder'];
            $DB->update_record('local_fd_mod_duration', $record);
        }
    }
    // Commit transaction.
    $transaction->allow_commit();
    $event = \local_flexdates\event\course_module_duration_updated::create(array('context' => $coursecontext));
    $event->trigger();
    rebuild_course_cache($course->id);
    redirect($returnurl);
} else {
    $PAGE->set_title($course->shortname .': '. get_string('activityduration', 'local_flexdates'));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($course->fullname));
    $mform->display();
    echo $OUTPUT->footer();
}


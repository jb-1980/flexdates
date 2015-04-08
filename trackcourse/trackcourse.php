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
$PAGE->set_url('/local/flexdates/trackcourse/index.php', array('id'=>$id));
$PAGE->set_pagelayout('admin');

// Check permissions.
$coursecontext = context_course::instance($course->id);
require_capability('local/flexdates:modify', $coursecontext);


// Creating form instance, passed course id as parameter to action url.
$baseurl = new moodle_url('/local/flexdates/trackcourse/index.php', array('id' => $id));
$mform = new local_flexdates_dashboard_trackcourse_form($baseurl);

$returnurl = new moodle_url('/course/view.php', array('id' => $id));
if ($mform->is_cancelled()) {
    // Redirect to course view page if form is cancelled.
    redirect($returnurl);
} elseif($data = $mform->get_data()) {
    print_object($data);
    if(!$record = $DB->get_record('local_fd_trackcourse',array('courseid'=>$id))){
        $dataobject = new stdClass;
        $dataobject->courseid = $id;
        $dataobject->track = $data->trackoption;
        $DB->insert_record('local_fd_trackcourse',$dataobject);
    } else{
        $record->track = $data->trackoption;
        $DB->update_record('local_fd_trackcourse', $record);
    }
    rebuild_course_cache($course->id);
    redirect($returnurl);
} else {
    $PAGE->set_title($course->shortname .': '. get_string('trackcourse', 'local_flexdates'));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($course->fullname));
    $mform->display();
    echo $OUTPUT->footer();
}


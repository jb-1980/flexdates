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
$PAGE->set_url('/local/flexdates/teacher.php');
$output = $PAGE->get_renderer('local_flexdates');
$PAGE->requires->css('/local/flexdates/static/css/offcanvas.css');
$PAGE->requires->css('/local/flexdates/static/css/datepicker3.css');
$PAGE->requires->jquery();

echo $output->header();

//Get user data, and enrolled courses
global $DB,$USER;
$t_sql = "SELECT DISTINCT c.id, c.fullname,u.lastname,r.name
            FROM {$CFG->prefix}role_assignments ra
            JOIN {$CFG->prefix}user u ON u.id = ra.userid
            JOIN {$CFG->prefix}role r ON r.id = ra.roleid
            JOIN {$CFG->prefix}context ct ON ct.id = ra.contextid
            JOIN {$CFG->prefix}course c ON c.id = ct.instanceid
           WHERE (r.shortname = 'teacher' OR r.shortname = 'editingteacher')
                 AND u.id={$USER->id};";
$teacher_courses = $DB->get_records_sql($t_sql);
$student = optional_param('student',0,PARAM_INT);
if($student){
    $student = $DB->get_record('user',array('id'=>$student));
    echo $output->render_teacher_dashboard($USER->id,$student);
} else{
    echo $output->render_teacher_dashboard($USER->id);
}

$scripts = array(
  'static/js/bootstrap.min.js',
  'static/js/bootstrap-datepicker.js',
  'static/js/flexdates-datepicker.js',
  'static/js/flexdates-assignments.js'
);

echo $output->include_js($scripts);
//echo $output->footer();






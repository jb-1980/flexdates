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
$PAGE->requires->css('/local/flexdates_dashboard/static/css/offcanvas.css');
$PAGE->requires->css('/local/flexdates_dashboard/static/css/datepicker3.css');
$PAGE->requires->jquery();

echo $output->header();

//Get user data, and enrolled courses
global $DB,$USER;

if(array_key_exists('student', $_GET)){
    $advisee = $DB->get_record('user',array('id'=>$_GET['student']));
    echo $output->render_advisor_dashboard($USER->id,$advisee);
} else{
    echo $output->render_advisor_dashboard($USER->id);
}

$scripts = array(
  'static/js/bootstrap.min.js',
  'static/js/bootstrap-datepicker.js',
  'static/js/flexdates-datepicker.js',
  'static/js/flexdates-assignments.js'
);

echo $output->include_js($scripts);
//echo $output->footer();






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
$PAGE->set_url('/local/flexdates/admin.php');
$output = $PAGE->get_renderer('local_flexdates');
$PAGE->requires->css('/local/flexdates/static/css/datepicker3.css');
$PAGE->requires->css('/local/flexdates/static/css/bootstrap-select.min.css');
$PAGE->requires->jquery();
//$PAGE->requires->js('/local/flexdates_dashboard/static/js/bootstrap.min.js');
//$PAGE->requires->js('/local/flexdates_dashboard/static/js/flexdates.js');
//$PAGE->requires->js('/local/flexdates_dashboard/static/js/bootstrap-datepicker.js');
//$PAGE->requires->js('/local/flexdates_dashboard/static/js/bootstrap-select.min.js');
global $CFG,$DB;
echo $output->header();
$t_sql = "SELECT u.id, u.firstname, u.lastname
            FROM {$CFG->prefix}user u
            JOIN {$CFG->prefix}user_info_data uid ON u.id = uid.userid
           WHERE uid.data = 'Teacher';";
$teachers = $DB->get_records_sql($t_sql);
$ac_sql = "SELECT u.id, u.firstname, u.lastname
            FROM {$CFG->prefix}user u
            JOIN {$CFG->prefix}user_info_data uid ON u.id = uid.userid
           WHERE uid.data = 'Academic Coach';";
$academic_coaches = $DB->get_records_sql($ac_sql);
$courses = get_courses() ?>
<div class="well" ><p>To view student information for select groups, please change the following filters.</p>
  <select  style="overflow:visible;" class="selectpicker" id="teacher-selecter" data-live-search="true" title="teachers">
    <option value="null">Select teachers...</option>
    <?php
      foreach($teachers as $teacher){
          echo "<option value=\"{$teacher->id}\">{$teacher->firstname} {$teacher->lastname}</option>";
      }
    ?>
  </select>
  <select class="selectpicker" id="coach-selecter" data-live-search="true" title="coach">
    <option value="null">Select coaches...</option>
    <?php
      foreach($academic_coaches as $academic_coach){
          echo "<option value=\"{$academic_coach->id}\">{$academic_coach->firstname} {$academic_coach->lastname}</option>";
      }
    ?>
  </select>
  <select class="selectpicker" id="site-selecter" data-live-search="true" title="site">
    <option value="null">Select site...</option>
    <?php
    $sql = "SELECT DISTINCT u.institution FROM {$CFG->prefix}user u;";
    $rs = $DB->get_recordset_sql($sql);
    foreach($rs as $record){
        if($site = $record->institution){
            echo "<option value=\"{$site}\">{$site}</option>";
        }
    }
    $rs->close();
    ?>
  </select>
  <select class="selectpicker" id="course-selecter" data-live-search="true" title="course">
    <option value="null">Select course...</option>
    <?php
    function starts_with($haystack, $needle){
         $length = strlen($needle);
         return (substr($haystack, 0, $length) === $needle);
    }
    foreach($courses as $course){
        if(starts_with($course->shortname,'restoring')){
          continue;
        }
        echo "<option value=\"{$course->id}\">{$course->shortname}</option>";
    }?>
  </select>
  <div class="clearfix">&nbsp;</div>
  <div style="text-align:center;">
    <button type="button" class="btn btn-primary" id="flexdates-apply-filters">Apply Filters</button>
  </div>
  <div class="clearfix"></div>
</div>
<div class="clearfix"></div>
<div>
<p>The footer</p>
</div>
<?php
$scripts = array(
  'static/js/bootstrap.min.js',
  'static/js/bootstrap-datepicker.js',
  'static/js/bootstrap-select.min.js',
  'static/js/flexdates-datepicker.js',
  'static/js/flexdates-assignments.js',
  'static/js/flexdates-select-filter.js'
);
echo $output->include_js($scripts);






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
 * The form for editing the activity duration settings throughout a course.
 *
 * @copyright 2014 Joseph Gilgen
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_flexdates_mod_duration_form extends moodleform {

    public function definition() {
        global $CFG, $COURSE, $DB;
        $mform = $this->_form;
        // Context instance of the course.
        $coursecontext = context_course::instance($COURSE->id);

        // Check if user has capability to upgrade/manage grades.
        $readonlygrades = !has_capability('moodle/grade:manage', $coursecontext);

        // Fetching Gradebook items.
        $gradeitems = grade_item::fetch_all(array('courseid' => $COURSE->id));
        //print_object($gradeitems);
        // Course module will be always fetched,
        // so lenghth will always be 1 if no gread item is fetched.
        if (is_array($gradeitems) && (count($gradeitems) >1)) {
            usort($gradeitems, 'flexdates_sort_array_by_sortorder');

            // Section to display Gradebook ID Numbers.
            $mform->addElement('header', 'gradebookitemsheader',
                    get_string('gradebookitems', 'local_flexdates'));
            $mform->setExpanded('gradebookitemsheader', False);
            
            // Looping through all grade items.
            foreach ($gradeitems as $gradeitem) {
                // Skip course and category grade items.
                if ($gradeitem->itemtype == "course" or $gradeitem->itemtype == "category") {
                    continue;
                }
                // Leave out grade items that are none type
                if (!$gradeitem->gradetype){
                    continue;
                }
                if($lesson_order_item = $DB->get_record('local_fd_mod_duration',array('gradeitemid'=>$gradeitem->id))){
                    $duration = $lesson_order_item->duration;
                    $itemorder = $lesson_order_item->itemorder;
                } else{
                    $duration = 0;
                    $itemorder = 0;
                }

                // Add element to display grade item.
                $item_array = array();
                  $item_array[] =& $mform->createElement('text',"duration",$gradeitem->itemname);
                  $item_array[] =& $mform->createElement('text',"itemorder",$gradeitem->itemname);
                $mform->addGroup($item_array, "lessonvalues[$gradeitem->id]", $gradeitem->itemname);
                $mform->setType("lessonvalues[$gradeitem->id][duration]", PARAM_RAW);
                $mform->setDefault("lessonvalues[$gradeitem->id][duration]", $duration);
                $mform->setType("lessonvalues[$gradeitem->id][itemorder]", PARAM_RAW);
                $mform->setDefault("lessonvalues[$gradeitem->id][itemorder]", $itemorder);

            }
        }
        
        $this->add_action_buttons();
        
    }
}

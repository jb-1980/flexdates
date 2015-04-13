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



/**
 * The form for tracking courses and setting defaults for the flexdates plugin
 *
 * @copyright 2015 Joseph Gilgen
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_flexdates_dashboard_trackcourse_form extends moodleform {

    public function definition() {
        global $CFG, $COURSE, $DB;
        $mform = $this->_form;
        // Context instance of the course.
        $coursecontext = context_course::instance($COURSE->id);
        //print_object($coursecontext);

        $option = $mform->addElement('selectyesno', 'trackoption', get_string('trackcourse', 'local_flexdates'));
        if($record = $DB->get_record('local_fd_trackcourse',array('courseid'=>$COURSE->id))){
            $option = $option->setSelected($record->track);
            $levels = new stdClass;
            $levels->mastered = $record->mastered;
            $levels->level2 = $record->level2;
            $levels->level1 = $record->level1;
            $levels->practiced = $record->practiced;
        } else{
            $levels = new stdClass;
            $levels->mastered = 0.95;
            $levels->level2 = 0.85;
            $levels->level1 = 0.75;
            $levels->practiced = 0.65;
        }
        $mform->addHelpButton('trackoption', 'trackcourse','local_flexdates');
        
        // A sample string variable with a default value.
        $mform->addElement('text', 'level[mastered]', get_string('levelmastered', 'local_flexdates'));
        $mform->setDefault('level[mastered]', $levels->mastered*100);
        $mform->setType('level[mastered]', PARAM_INT);
        $mform->addHelpButton('level[mastered]', 'levelmastered','local_flexdates');
        
        $mform->addElement('text', 'level[level2]', get_string('level2', 'local_flexdates'));
        $mform->setDefault('level[level2]', $levels->level2*100);
        $mform->setType('level[level2]', PARAM_INT);
        $mform->addHelpButton('level[level2]', 'level2','local_flexdates');
        
        $mform->addElement('text', 'level[level1]', get_string('level1', 'local_flexdates'));
        $mform->setDefault('level[level1]', $levels->level1*100);
        $mform->setType('level[level1]', PARAM_INT);
        $mform->addHelpButton('level[level1]', 'level1','local_flexdates');
        
        $mform->addElement('text', 'level[practiced]', get_string('levelpracticed', 'local_flexdates'));
        $mform->setDefault('level[practiced]', $levels->practiced*100);
        $mform->setType('level[practiced]', PARAM_INT);
        $mform->addHelpButton('level[practiced]', 'levelpracticed','local_flexdates');
        
        $this->add_action_buttons();
    }
}

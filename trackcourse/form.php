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
 * The form for tracking courses for the flexdates plugin
 *
 * @copyright 2014 Joseph Gilgen
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
        }
        $mform->addHelpButton('trackoption', 'trackcourse','local_flexdates');
        $this->add_action_buttons();
    }
}

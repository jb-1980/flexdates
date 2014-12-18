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
 * The local_flexdates_mod_duration course module duration updated event.
 *
 * @package    report_lessonduration
 * @copyright  2014 Joseph Gilgen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_flexdates\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The local_flexdates course module duration updated event class.
 *
 * @package    local_flexdates
 * @since      Moodle 2.7
 * @copyright  2014 Joseph Gilgen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_module_duration_updated extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        //$this->data['objecttable'] = 'local_flexdates_activity_duration';
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventcoursemoduledurationupdated', 'local_flexdates');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' updated lesson durations for course '$this->courseid'.";
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('local/flexdates/mod_duration/index.php', array('course' => $this->courseid));
    }

#    /**
#     * custom validations.
#     *
#     * @throws \coding_exception when validation fails.
#     * @return void
#     */
#    protected function validate_data() {
#        parent::validate_data();
#        if ($this->contextlevel != CONTEXT_COURSE) {
#            throw new \coding_exception('Context level must be CONTEXT_COURSE.');
#        }
#    }
}

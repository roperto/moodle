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
 * The mod_workshep course module viewed event.
 *
 * @package    mod_workshep
 * @copyright  2013 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_workshep\event;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->dirroot/mod/workshep/locallib.php");

/**
 * The mod_workshep course module viewed event class.
 *
 * @package    mod_workshep
 * @since      Moodle 2.6
 * @copyright  2013 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_module_viewed extends \core\event\course_module_viewed {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'workshep';
    }

    /**
     * Does this event replace a legacy event?
     *
     * @return string legacy event name
     */
    public static function get_legacy_eventname() {
        return 'workshep_viewed';
    }

    /**
     * Legacy event data if get_legacy_eventname() is not empty.
     *
     * @return mixed
     */
    protected function get_legacy_eventdata() {
        global $USER;

        $workshep = $this->get_record_snapshot('workshep', $this->objectid);
        $course   = $this->get_record_snapshot('course', $this->courseid);
        $cm       = $this->get_record_snapshot('course_modules', $this->contextinstanceid);
        $workshep = new \workshep($workshep, $cm, $course);
        return (object)array('workshep' => $workshep, 'user' => $USER);
    }
}

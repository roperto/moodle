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
 * Best evaluation settings form
 *
 * @package    workshepeval
 * @subpackage best
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

class workshep_examples_calibration_settings_form extends moodleform {

    function definition() {
        $mform = $this->_form;

        $plugindefaults = get_config('workshepcalibration_examples');
        $current        = $this->_customdata['current'];
        $workshep       = $this->_customdata['workshep'];

        $mform->addElement('header', 'general', get_string('settings', 'workshepcalibration_examples'));

        $options = array();
        for ($i = 9; $i >= 1; $i--) {
            $options[$i] = get_string('comparisonlevel' . $i, 'workshepcalibration_examples');
        }
        $label = get_string('comparison', 'workshepcalibration_examples');
        $mform->addElement('select', 'comparison', $label, $options);
        $mform->addHelpButton('comparison', 'comparison', 'workshepcalibration_examples');
        $mform->setDefault('comparison', $plugindefaults->accuracy);

        $label = get_string('consistency', 'workshepcalibration_examples');
        $mform->addElement('select', 'consistency', $label, $options);
        $mform->addHelpButton('consistency', 'consistency', 'workshepcalibration_examples');
        $mform->setDefault('consistency', $plugindefaults->consistence); //we have this fun typo because there's a bug with the settings form

        $mform->addElement('submit', 'submit', get_string('calculatescores', 'workshep'));

        $this->set_data($current);
    }
}

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
 * A form used by teachers to give feedback to reviewers on assessments
 *
 * @package    mod_workshep
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

class workshep_feedbackreviewer_form extends moodleform {

    function definition() {
        $mform = $this->_form;

        $current    = $this->_customdata['current'];
        $workshep   = $this->_customdata['workshep'];
        $editoropts = $this->_customdata['editoropts'];
        $options    = $this->_customdata['options'];

        $mform->addElement('header', 'assessmentsettings', get_string('assessmentsettings', 'workshep'));

        if (!empty($options['editableweight'])) {
            $mform->addElement('select', 'weight',
                    get_string('assessmentweight', 'workshep'), workshep::available_assessment_weights_list());
            $mform->setDefault('weight', 1);
        }

        $mform->addElement('static', 'gradinggrade', get_string('gradinggradecalculated', 'workshep'));
        if (!empty($options['overridablegradinggrade'])) {
            $grades = array('' => get_string('notoverridden', 'workshep'));
            for ($i = (int)$workshep->gradinggrade; $i >= 0; $i--) {
                $grades[$i] = $i;
            }
            $mform->addElement('select', 'gradinggradeover', get_string('gradinggradeover', 'workshep'), $grades);

            $mform->addElement('editor', 'feedbackreviewer_editor', get_string('feedbackreviewer', 'workshep'), null, $editoropts);
            $mform->setType('feedbackreviewer_editor', PARAM_RAW);
        }
		
		if (!empty($options['showflaggingresolution'])) {
			$mform->addElement('static', 'resolution_help', get_string('resolutiontitle', 'workshep'), html_writer::tag('strong', get_string('needsresolution', 'workshep')));
			$mform->addElement('radio', 'resolution', '', get_string('resolutionfair', 'workshep'), 1);
			$mform->addElement('radio', 'resolution', '', get_string('resolutionunfair', 'workshep'), 0);
			$mform->setDefault('resolution', -1);
		}

        $mform->addElement('hidden', 'asid');
        $mform->setType('asid', PARAM_INT);

        $mform->addElement('submit', 'save', get_string('saveandclose', 'workshep'));

        $this->set_data($current);
    }

    function validation($data, $files) {
        global $CFG, $USER, $DB;

        $errors = parent::validation($data, $files);
        return $errors;
    }
}

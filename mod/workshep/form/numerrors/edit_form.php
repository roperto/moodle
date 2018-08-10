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
 * This file defines an mform to edit "Number of errors" grading strategy forms.
 *
 * @package    workshepform_numerrors
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(dirname(dirname(__FILE__))).'/lib.php');   // module library
require_once(dirname(dirname(__FILE__)).'/edit_form.php');    // parent class definition

/**
 * Class for editing "Number of errors" grading strategy forms.
 *
 * @uses moodleform
 */
class workshep_edit_numerrors_strategy_form extends workshep_edit_strategy_form {

    /**
     * Define the elements to be displayed at the form
     *
     * Called by the parent::definition()
     *
     * @return void
     */
    protected function definition_inner(&$mform) {

        $plugindefaults     = get_config('workshepform_numerrors');
        $nodimensions       = $this->_customdata['nodimensions'];       // number of currently filled dimensions
        $norepeats          = $this->_customdata['norepeats'];          // number of dimensions to display
        $descriptionopts    = $this->_customdata['descriptionopts'];    // wysiwyg fields options
        $current            = $this->_customdata['current'];            // current data to be set

        $mform->addElement('hidden', 'norepeats', $norepeats);
        $mform->setType('norepeats', PARAM_INT);
        // value not to be overridden by submitted value
        $mform->setConstants(array('norepeats' => $norepeats));

        for ($i = 0; $i < $norepeats; $i++) {
            $mform->addElement('header', 'dimension'.$i, get_string('dimensionnumber', 'workshepform_numerrors', $i+1));
            $mform->addElement('hidden', 'dimensionid__idx_'.$i);   // the id in workshep_forms
            $mform->setType('dimensionid__idx_'.$i, PARAM_INT);
            $mform->addElement('editor', 'description__idx_'.$i.'_editor',
                    get_string('dimensiondescription', 'workshepform_numerrors'), '', $descriptionopts);
            $mform->addElement('text', 'grade0__idx_'.$i, get_string('grade0', 'workshepform_numerrors'), array('size'=>'15'));
            $mform->setDefault('grade0__idx_'.$i, $plugindefaults->grade0);
            $mform->setType('grade0__idx_'.$i, PARAM_TEXT);
            $mform->addElement('text', 'grade1__idx_'.$i, get_string('grade1', 'workshepform_numerrors'), array('size'=>'15'));
            $mform->setDefault('grade1__idx_'.$i, $plugindefaults->grade1);
            $mform->setType('grade1__idx_'.$i, PARAM_TEXT);
            $mform->addElement('select', 'weight__idx_'.$i,
                    get_string('dimensionweight', 'workshepform_numerrors'), workshep::available_dimension_weights_list());
            $mform->setDefault('weight__idx_'.$i, 1);
        }

        $mform->addElement('header', 'mappingheader', get_string('grademapping', 'workshepform_numerrors'));
        $mform->addElement('static', 'mappinginfo', get_string('maperror', 'workshepform_numerrors'),
                                                            get_string('mapgrade', 'workshepform_numerrors'));

        // get the total weight of all items == maximum weighted number of errors
        $totalweight = 0;
        for ($i = 0; $i < $norepeats; $i++) {
            if (!empty($current->{'weight__idx_'.$i})) {
                $totalweight += $current->{'weight__idx_'.$i};
            }
        }
        $totalweight = max($totalweight, $nodimensions);

        $percents = array();
        $percents[''] = '';
        for ($i = 100; $i >= 0; $i--) {
            $percents[$i] = get_string('percents', 'workshepform_numerrors', $i);
        }
        $mform->addElement('static', 'mappingzero', 0, get_string('percents', 'workshepform_numerrors', 100));
        for ($i = 1; $i <= $totalweight; $i++) {
            $selects = array();
            $selects[] = $mform->createElement('select', 'map__idx_'.$i, $i, $percents);
            $selects[] = $mform->createElement('static', 'mapdefault__idx_'.$i, '',
                                        get_string('percents', 'workshepform_numerrors', floor(100 - $i * 100 / $totalweight)));
            $mform->addGroup($selects, 'grademapping'.$i, $i, array(' '), false);
            $mform->setDefault('map__idx_'.$i, '');
        }

        $mform->registerNoSubmitButton('noadddims');
        $mform->addElement('submit', 'noadddims', get_string('addmoredimensions', 'workshepform_numerrors',
                workshep_numerrors_strategy::ADDDIMS));
        $mform->closeHeaderBefore('noadddims');
        $this->set_data($current);

    }

}

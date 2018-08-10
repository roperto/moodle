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
 * Aggregates the grades for submission and grades for assessments
 *
 * @package    mod_workshep
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');

$cmid       = required_param('cmid', PARAM_INT);            // course module
$confirm    = optional_param('confirm', false, PARAM_BOOL); // confirmation

// the params to be re-passed to view.php
$page       = optional_param('page', 0, PARAM_INT);
$sortby     = optional_param('sortby', 'lastname', PARAM_ALPHA);
$sorthow    = optional_param('sorthow', 'ASC', PARAM_ALPHA);

$cm         = get_coursemodule_from_id('workshep', $cmid, 0, false, MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$workshep   = $DB->get_record('workshep', array('id' => $cm->instance), '*', MUST_EXIST);
$workshep   = new workshep($workshep, $cm, $course);

$PAGE->set_url($workshep->aggregate_url(), compact('confirm', 'page', 'sortby', 'sorthow'));

require_login($course, false, $cm);
require_capability('mod/workshep:overridegrades', $PAGE->context);

// load and init the grading evaluator

$evaluator = $workshep->grading_evaluation_instance();
$settingsform = $evaluator->get_settings_form($PAGE->url);

// this page is now also responsible for automatic aggregation
// after a flagged assessment is discounted
// so we need to load settings data from the database
// if need be

$settingsdata = $settingsform->get_data();
    
if (empty($settingsdata)) {
    $settingsdata = $evaluator->get_settings();
}

if (!empty($settingsdata)) {
    $workshep->aggregate_submission_grades();           // updates 'grade' in {workshep_submissions}
    $evaluator->update_grading_grades($settingsdata);   // updates 'gradinggrade' in {workshep_assessments}
    $workshep->aggregate_grading_grades();              // updates 'gradinggrade' in {workshep_aggregations}

    //New functionality: calibrated plugin needs to update submission grades based on calibration scores
    if (method_exists($evaluator, "update_submission_grades")) {
        $evaluator->update_submission_grades($settingsdata);
    }
}

redirect(new moodle_url($workshep->view_url(), compact('page', 'sortby', 'sorthow')));

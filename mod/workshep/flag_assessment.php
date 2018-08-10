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
 * Flag an assessment for review by a manager.
 *
 * Assessment id parameter must be passed. The user must be the assessment owner
 * or in the assessment owner's team if teammode is enabled.
 *
 * @package    mod
 * @subpackage workshep
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');

$asid       = required_param('asid', PARAM_INT);  // assessment id
$redirect   = required_param('redirect', PARAM_LOCALURL);
$unflag     = optional_param('unflag', false, PARAM_BOOL);

$assessment = $DB->get_record('workshep_assessments', array('id' => $asid), '*', MUST_EXIST);
$submission = $DB->get_record('workshep_submissions', array('id' => $assessment->submissionid, 'example' => 0), '*', MUST_EXIST);
$workshep   = $DB->get_record('workshep', array('id' => $submission->workshepid), '*', MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $workshep->course), '*', MUST_EXIST);
$cm         = get_coursemodule_from_instance('workshep', $workshep->id, $course->id, false, MUST_EXIST);

$workshep = new workshep($workshep, $cm, $course);

if ($workshep->submitterflagging) {

    $ownsubmission  = $submission->authorid == $USER->id;
    if ($workshep->teammode && !$ownsubmission) {
        $group = $workshep->user_group($submission->authorid);
        $ownsubmission = groups_is_member($group->id,$USER->id);
    }

    if ($ownsubmission) {
        $flag = !$unflag;
        $DB->set_field("workshep_assessments", "submitterflagged", $flag, array("id" => $asid));
    }

}

redirect(new moodle_url($redirect));
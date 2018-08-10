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
 * View, create or edit single example submission
 *
 * @package    mod_workshep
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');

$cmid       = required_param('cmid', PARAM_INT);            // course module id
$id         = required_param('id', PARAM_INT);              // example submission id, 0 for the new one
$edit       = optional_param('edit', false, PARAM_BOOL);    // open for editing?
$delete     = optional_param('delete', false, PARAM_BOOL);  // example removal requested
$confirm    = optional_param('confirm', false, PARAM_BOOL); // example removal request confirmed
$assess     = optional_param('assess', false, PARAM_BOOL);  // assessment required

$cm         = get_coursemodule_from_id('workshep', $cmid, 0, false, MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_login($course, false, $cm);
if (isguestuser()) {
    print_error('guestsarenotallowed');
}

$workshep = $DB->get_record('workshep', array('id' => $cm->instance), '*', MUST_EXIST);
$workshep = new workshep($workshep, $cm, $course);

$PAGE->set_url($workshep->exsubmission_url($id), array('edit' => $edit));
$PAGE->set_title($workshep->name);
$PAGE->set_heading($course->fullname);
if ($edit) {
    $PAGE->navbar->add(get_string('exampleediting', 'workshep'));
} else {
    $PAGE->navbar->add(get_string('example', 'workshep'));
}
$output = $PAGE->get_renderer('mod_workshep');

if ($id) { // example is specified
    $example = $workshep->get_example_by_id($id);
} else { // no example specified - create new one
    require_capability('mod/workshep:manageexamples', $workshep->context);
    $example = new stdclass();
    $example->id = null;
    $example->authorid = $USER->id;
    $example->example = 1;
}

$canmanage  = has_capability('mod/workshep:manageexamples', $workshep->context);
$canassess  = has_capability('mod/workshep:peerassess', $workshep->context);
$refasid    = $DB->get_field('workshep_assessments', 'id', array('submissionid' => $example->id, 'weight' => 1));

if ($example->id and ($canmanage or ($workshep->assessing_examples_allowed() and $canassess))) {
    // ok you can go
} elseif (is_null($example->id) and $canmanage) {
    // ok you can go
} else {
    print_error('nopermissions', 'error', $workshep->view_url(), 'view or manage example submission');
}

if ($id and $delete and $confirm and $canmanage) {
    require_sesskey();
    $workshep->delete_submission($example);
    redirect($workshep->view_url());
}

if ($id and $assess and $canmanage) {
    // reference assessment of an example is the assessment with the weight = 1. There should be just one
    // such assessment
    require_sesskey();
    if (!$refasid) {
        $refasid = $workshep->add_allocation($example, $USER->id, 1);
    }
    redirect($workshep->exassess_url($refasid));
}

if ($id and $assess and $canassess) {
    // training assessment of an example is the assessment with the weight = 0
    require_sesskey();
    $asid = $DB->get_field('workshep_assessments', 'id',
            array('submissionid' => $example->id, 'weight' => 0, 'reviewerid' => $USER->id));
    if (!$asid) {
        $asid = $workshep->add_allocation($example, $USER->id, 0);
    }
    if ($asid == workshep::ALLOCATION_EXISTS) {
        // the training assessment of the example was not found but the allocation already
        // exists. this probably means that the user is the author of the reference assessment.
        echo $output->header();
        echo $output->box(get_string('assessmentreferenceconflict', 'workshep'));
        echo $output->continue_button($workshep->view_url());
        echo $output->footer();
        die();
    }
    redirect($workshep->exassess_url($asid));
}

if ($edit and $canmanage) {
    require_once(dirname(__FILE__).'/submission_form.php');

    $maxfiles       = $workshep->nattachments;
    $maxbytes       = $workshep->maxbytes;
    $contentopts    = array(
                        'trusttext' => true,
                        'subdirs'   => false,
                        'maxfiles'  => $maxfiles,
                        'maxbytes'  => $maxbytes,
                        'context'   => $workshep->context
                      );

    $attachmentopts = array('subdirs' => true, 'maxfiles' => $maxfiles, 'maxbytes' => $maxbytes);
    $example        = file_prepare_standard_editor($example, 'content', $contentopts, $workshep->context,
                                        'mod_workshep', 'submission_content', $example->id);
    $example        = file_prepare_standard_filemanager($example, 'attachment', $attachmentopts, $workshep->context,
                                        'mod_workshep', 'submission_attachment', $example->id);

    $mform          = new workshep_submission_form($PAGE->url, array('current' => $example, 'workshep' => $workshep,
                                                    'contentopts' => $contentopts, 'attachmentopts' => $attachmentopts));

    if ($mform->is_cancelled()) {
        redirect($workshep->view_url());

    } elseif ($canmanage and $formdata = $mform->get_data()) {
        if ($formdata->example == 1) {
            // this was used just for validation, it must be set to one when dealing with example submissions
            unset($formdata->example);
        } else {
            throw new coding_exception('Invalid submission form data value: example');
        }
        $timenow = time();
        if (is_null($example->id)) {
            $formdata->workshepid     = $workshep->id;
            $formdata->example        = 1;
            $formdata->authorid       = $USER->id;
            $formdata->timecreated    = $timenow;
            $formdata->feedbackauthorformat = editors_get_preferred_format();
        }
        $formdata->timemodified       = $timenow;
        $formdata->title              = trim($formdata->title);
        $formdata->content            = '';          // updated later
        $formdata->contentformat      = FORMAT_HTML; // updated later
        $formdata->contenttrust       = 0;           // updated later
        if (is_null($example->id)) {
            $example->id = $formdata->id = $DB->insert_record('workshep_submissions', $formdata);
        } else {
            if (empty($formdata->id) or empty($example->id) or ($formdata->id != $example->id)) {
                throw new moodle_exception('err_examplesubmissionid', 'workshep');
            }
        }
        // save and relink embedded images and save attachments
        $formdata = file_postupdate_standard_editor($formdata, 'content', $contentopts, $workshep->context,
                                                      'mod_workshep', 'submission_content', $example->id);
        $formdata = file_postupdate_standard_filemanager($formdata, 'attachment', $attachmentopts, $workshep->context,
                                                           'mod_workshep', 'submission_attachment', $example->id);
        if (empty($formdata->attachment)) {
            // explicit cast to zero integer
            $formdata->attachment = 0;
        }
        // store the updated values or re-save the new example (re-saving needed because URLs are now rewritten)
        $DB->update_record('workshep_submissions', $formdata);
        redirect($workshep->exsubmission_url($formdata->id));
    }
}

// Output starts here
echo $output->header();
echo $output->heading(format_string($workshep->name), 2);

// show instructions for submitting as they may contain some list of questions and we need to know them
// while reading the submitted answer
if (trim($workshep->instructauthors)) {
    $instructions = file_rewrite_pluginfile_urls($workshep->instructauthors, 'pluginfile.php', $PAGE->context->id,
        'mod_workshep', 'instructauthors', 0, workshep::instruction_editors_options($PAGE->context));
    print_collapsible_region_start('', 'workshep-viewlet-instructauthors', get_string('instructauthors', 'workshep'));
    echo $output->box(format_text($instructions, $workshep->instructauthorsformat, array('overflowdiv'=>true)), array('generalbox', 'instructions'));
    print_collapsible_region_end();
}

// if in edit mode, display the form to edit the example
if ($edit and $canmanage) {
    $mform->display();
    echo $output->footer();
    die();
}

// else display the example...
if ($example->id) {
    if ($canmanage and $delete) {
    echo $output->confirm(get_string('exampledeleteconfirm', 'workshep'),
            new moodle_url($PAGE->url, array('delete' => 1, 'confirm' => 1)), $workshep->view_url());
    }
    if ($canmanage and !$delete and !$DB->record_exists_select('workshep_assessments',
            'grade IS NOT NULL AND weight=1 AND submissionid = ?', array($example->id))) {
        echo $output->confirm(get_string('assessmentreferenceneeded', 'workshep'),
                new moodle_url($PAGE->url, array('assess' => 1)), $workshep->view_url());
    }
    echo $output->render($workshep->prepare_example_submission($example));
}
// ...with an option to edit or remove it
echo $output->container_start('buttonsbar');
if ($canmanage) {
    if (empty($edit) and empty($delete)) {
        $aurl = new moodle_url($workshep->exsubmission_url($example->id), array('edit' => 'on'));
        echo $output->single_button($aurl, get_string('exampleedit', 'workshep'), 'get');

        $aurl = new moodle_url($workshep->exsubmission_url($example->id), array('delete' => 'on'));
        echo $output->single_button($aurl, get_string('exampledelete', 'workshep'), 'get');
    }
}
// ...and optionally assess it
if ($canassess or ($canmanage and empty($edit) and empty($delete))) {
    $aurl = new moodle_url($workshep->exsubmission_url($example->id), array('assess' => 'on', 'sesskey' => sesskey()));
    $myassessment = $DB->get_field('workshep_assessments', 'grade',
                array('submissionid' => $example->id, 'weight' => 0, 'reviewerid' => $USER->id));
    $label = ($workshep->examplesreassess or (empty($myassessment))) ? get_string('exampleassess', 'workshep') : get_string('review', 'workshep');
    echo $output->single_button($aurl, $label, 'get');
}
echo $output->container_end(); // buttonsbar
// and possibly display the example's review(s) - todo
echo $output->footer();

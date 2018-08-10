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
 * Strings for component 'workshep', language 'en', branch 'MOODLE_20_STABLE'
 *
 * @package    mod_workshep
 * @copyright  2009 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['aggregategrades'] = 'Re-calculate grades';
$string['aggregation'] = 'Grades aggregation';
$string['allocate'] = 'Allocate submissions';
$string['allocatedetails'] = 'expected: {$a->expected}<br />submitted: {$a->submitted}<br />to allocate: {$a->allocate}';
$string['allocation'] = 'Submission allocation';
$string['allocationdone'] = 'Allocation done';
$string['allocationerror'] = 'Allocation error';
$string['allocationconfigured'] = 'Allocation configured';
$string['allsubmissions'] = 'All submissions ({$a})';
$string['alreadygraded'] = 'Already graded';
$string['areaconclusion'] = 'Conclusion text';
$string['areainstructauthors'] = 'Instructions for submission';
$string['areainstructreviewers'] = 'Instructions for assessment';
$string['areaoverallfeedbackattachment'] = 'Overall feedback attachments';
$string['areaoverallfeedbackcontent'] = 'Overall feedback texts';
$string['areasubmissionattachment'] = 'Submission attachments';
$string['areasubmissioncontent'] = 'Submission texts';
$string['assess'] = 'Assess';
$string['assessedexample'] = 'Assessed example submission';
$string['assessedsubmission'] = 'Assessed submission';
$string['assessingexample'] = 'Assessing example submission';
$string['assessingsubmission'] = 'Assessing submission';
$string['assessment'] = 'Assessment';
$string['assessmentby'] = 'by <a href="{$a->url}">{$a->name}</a>';
$string['assessmentbyfullname'] = 'Assessment by {$a}';
$string['assessmentbyyourself'] = 'Your assessment';
$string['assessmentdeleted'] = 'Assessment deallocated';
$string['assessmentend'] = 'Deadline for assessment';
$string['assessmentendbeforestart'] = 'Deadline for assessment can not be specified before the open for assessment date';
$string['assessmentendevent'] = '{$a} (assessment deadline)';
$string['assessmentenddatetime'] = 'Assessment deadline: {$a->daydatetime} ({$a->distanceday})';
$string['assessmentform'] = 'Assessment form';
$string['assessmentofsubmission'] = '<a href="{$a->assessmenturl}">Assessment</a> of <a href="{$a->submissionurl}">{$a->submissiontitle}</a>';
$string['assessmentreference'] = 'Reference assessment';
$string['assessmentreferenceconflict'] = 'It is not possible to assess an example submission for which you provided a reference assessment.';
$string['assessmentreferenceneeded'] = 'You have to assess this example submission to provide a reference assessment. Click \'Continue\' button to assess the submission.';
$string['assessmentsettings'] = 'Assessment settings';
$string['assessmentstart'] = 'Open for assessment from';
$string['assessmentstartevent'] = '{$a} (opens for assessment)';
$string['assessmentstartdatetime'] = 'Open for assessment from {$a->daydatetime} ({$a->distanceday})';
$string['assessmentweight'] = 'Assessment weight';
$string['assignedassessments'] = 'Assigned submissions to assess';
$string['assignedassessmentsnone'] = 'You have no assigned submission to assess';
$string['backtoeditform'] = 'Back to editing form';
$string['byfullname'] = 'by <a href="{$a->url}">{$a->name}</a>';
$string['calculategradinggrades'] = 'Calculate assessment grades';
$string['calculategradinggradesdetails'] = 'expected: {$a->expected}<br />calculated: {$a->calculated}';
$string['calculatesubmissiongrades'] = 'Calculate submission grades';
$string['calculatesubmissiongradesdetails'] = 'expected: {$a->expected}<br />calculated: {$a->calculated}';
$string['clearaggregatedgrades'] = 'Clear all aggregated grades';
$string['clearaggregatedgrades_help'] = 'The aggregated grades for submission and grades for assessment will be reset. You can re-calculate these grades from scratch in Grading evaluation phase again.';
$string['clearassessments'] = 'Clear assessments';
$string['clearassessments_help'] = 'The calculated grades for submission and grades for assessment will be reset. The information how the assessment forms are filled is still kept, but all the reviewers must open the assessment form again and re-save it to get the given grades calculated again.';
$string['clearassessmentsconfirm'] = 'Are you sure you want to clear all assessment grades? You will not be able to get the information back on your own, reviewers will have to re-assess the allocated submissions.';
$string['clearaggregatedgradesconfirm'] = 'Are you sure you want to clear the calculated grades for submissions and grades for assessment?';
$string['conclusion'] = 'Conclusion';
$string['conclusion_help'] = 'Conclusion text is displayed to participants at the end of the activity.';
$string['configexamplesmode'] = 'Default mode of examples assessment in Enhanced Workshops';
$string['configgrade'] = 'Default maximum grade for submission in Enhanced Workshops';
$string['configgradedecimals'] = 'Default number of digits that should be shown after the decimal point when displaying grades.';
$string['configgradinggrade'] = 'Default maximum grade for assessment in Enhanced Workshops';
$string['configmaxbytes'] = 'Default maximum submission file size for all Enhanced Workshops on the site (subject to course limits and other local settings)';
$string['configstrategy'] = 'Default grading strategy for Enhanced Workshops';
$string['createsubmission'] = 'Start preparing your submission';
$string['daysago'] = '{$a} days ago';
$string['daysleft'] = '{$a} days left';
$string['daystoday'] = 'today';
$string['daystomorrow'] = 'tomorrow';
$string['daysyesterday'] = 'yesterday';
$string['deadlinesignored'] = 'Time restrictions do not apply to you';
$string['editassessmentform'] = 'Edit assessment form';
$string['editassessmentformstrategy'] = 'Edit assessment form ({$a})';
$string['editingassessmentform'] = 'Editing assessment form';
$string['editingsubmission'] = 'Editing submission';
$string['editsubmission'] = 'Edit submission';
$string['err_multiplesubmissions'] = 'While editing this form, another version of the submission has been saved. Multiple submissions per user are not allowed.';
$string['err_removegrademappings'] = 'Unable to remove the unused grade mappings';
$string['evaluategradeswait'] = 'Please wait until the assessments are evaluated and the grades are calculated';
$string['evaluation'] = 'Grading evaluation';
$string['evaluationmethod'] = 'Grading evaluation method';
$string['evaluationmethod_help'] = 'The grading evaluation method determines how the grade for assessment is calculated. You can let it re-calculate grades repeatedly with different settings unless you are happy with the result.';
$string['evaluationsettings'] = 'Grading evaluation settings';
$string['eventassessableuploaded'] = 'A submission has been uploaded.';
$string['eventassessmentevaluationsreset'] = 'Assessment evaluations reset';
$string['eventassessmentevaluated'] = 'Assessment evaluated';
$string['eventassessmentreevaluated'] = 'Assessment re-evaluated';
$string['eventsubmissionassessed'] = 'Submission assessed';
$string['eventsubmissionassessmentsreset'] = 'Submission assessments cleared';
$string['eventsubmissioncreated'] = 'Submission created';
$string['eventsubmissionreassessed'] = 'Submission re-assessed';
$string['eventsubmissionupdated'] = 'Submission updated';
$string['eventsubmissionviewed'] = 'Submission viewed';
$string['eventphaseswitched'] = 'Phase switched';
$string['example'] = 'Example submission';
$string['exampleadd'] = 'Add example submission';
$string['exampleassess'] = 'Assess example submission';
$string['exampleassesstask'] = 'Assess examples';
$string['exampleassesstaskdetails'] = 'expected: {$a->expected}<br />assessed: {$a->assessed}';
$string['exampleassessments'] = 'Example submissions to assess';
$string['examplecomparing'] = 'Comparing assessments of example submission';
$string['exampledelete'] = 'Delete example';
$string['exampledeleteconfirm'] = 'Are you sure you want to delete the following example submission? Click \'Continue\' button to delete the submission.';
$string['exampleedit'] = 'Edit example';
$string['exampleediting'] = 'Editing example';
$string['exampleneedassessed'] = 'You have to assess all example submissions first';
$string['exampleneedsubmission'] = 'You have to submit your work and assess all example submissions first';
$string['examplesbeforeassessment'] = 'Examples are available after own submission and must be assessed before peer assessment';
$string['examplesbeforesubmission'] = 'Examples must be assessed before own submission';
$string['examplesmode'] = 'Mode of examples assessment';
$string['examplesubmissions'] = 'Example submissions';
$string['examplesvoluntary'] = 'Assessment of example submission is voluntary';
$string['feedbackauthor'] = 'Feedback for the author';
$string['feedbackauthorattachment'] = 'Attachment';
$string['feedbackby'] = 'Feedback by {$a}';
$string['feedbackreviewer'] = 'Feedback for the reviewer';
$string['feedbacksettings'] = 'Feedback';
$string['formataggregatedgrade'] = '{$a->grade}';
$string['formataggregatedgradeover'] = '<del>{$a->grade}</del><br /><ins>{$a->over}</ins>';
$string['formatpeergrade'] = '<span class="grade">{$a->grade}</span> <span class="gradinggrade">({$a->gradinggrade})</span>';
$string['formatpeergradehovertext'] = 'Grade Given (Grade for Assessment)';
$string['formatpeergradeover'] = '<span class="grade">{$a->grade}</span> <span class="gradinggrade">(<del>{$a->gradinggrade}</del> / <ins>{$a->gradinggradeover}</ins>)</span>';
$string['formatpeergradeoverhovertext'] = 'Grade Given (~~Grade for Assessment~~ / Overridden Grade for Assessment)';
$string['formatpeergradeoverweighted'] = '<span class="grade">{$a->grade}</span> <span class="gradinggrade">(<del>{$a->gradinggrade}</del> / <ins>{$a->gradinggradeover}</ins>)</span> @ <span class="weight">{$a->weight}</span>';
$string['formatpeergradeoverweightedhovertext'] = 'Grade Given (~~Grade for Assessment~~ / Overridden Grade for Assessment) @ Assessment Weight';
$string['formatpeergradeweighted'] = '<span class="grade">{$a->grade}</span> <span class="gradinggrade">({$a->gradinggrade})</span> @ <span class="weight">{$a->weight}</span>';
$string['formatpeergradeweightedhovertext'] = 'Grade Given (Grade for Assessment) @ Assessment Weight';
$string['formatpeergradenograding'] = '<span class="grade">{$a->grade}</span>';
$string['formatpeergradenogradinghovertext'] = 'Grade Given';
$string['formatpeergradeweightednograding'] = '<span class="grade">{$a->grade}</span> @ <span class="weight">{$a->weight}</span>';
$string['formatpeergradeweightednogradinghovertext'] = 'Grade Given @ Assessment Weight';
$string['givengrades'] = 'Grades given';
$string['gradecalculated'] = 'Calculated grade for submission';
$string['gradedecimals'] = 'Decimal places in grades';
$string['gradegivento'] = '&gt;';
$string['gradeitemassessment'] = '{$a->workshepname} (assessment)';
$string['gradeitemsubmission'] = '{$a->workshepname} (submission)';
$string['gradeover'] = 'Override grade for submission';
$string['gradesreport'] = 'Enhanced Workshop grades report';
$string['gradereceivedfrom'] = '&lt;';
$string['gradeinfo'] = 'Grade: {$a->received} of {$a->max}';
$string['gradetopasssubmission'] = 'Submission grade to pass';
$string['gradetopassgrading'] = 'Assessment grade to pass';
$string['gradinggrade'] = 'Grade for assessment';
$string['gradinggrade_help'] = 'This setting specifies the maximum grade that may be obtained for submission assessment.';
$string['gradinggradecalculated'] = 'Calculated grade for assessment';
$string['gradinggradeof'] = 'Grade for assessment (of {$a})';
$string['gradinggradeover'] = 'Override grade for assessment';
$string['gradingsettings'] = 'Grading settings';
$string['groupnoallowed'] = 'You are not allowed to access any group in this Enhanced Workshop';
$string['chooseuser'] = 'Choose user...';
$string['iamsure'] = 'Yes, I am sure';
$string['info'] = 'Info';
$string['instructauthors'] = 'Instructions for submission';
$string['instructreviewers'] = 'Instructions for assessment';
$string['introduction'] = 'Description';
$string['latesubmissions'] = 'Late submissions';
$string['latesubmissions_desc'] = 'Allow submissions after the deadline';
$string['latesubmissions_help'] = 'If enabled, an author may submit their work after the submissions deadline or during the assessment phase. Late submissions cannot be edited though.';
$string['latesubmissionsallowed'] = 'Late submissions are allowed';
$string['maxbytes'] = 'Maximum submission attachment size';
$string['modulename'] = 'Enhanced Workshop';
$string['modulename_help'] = 'The Enhanced Workshop activity module enables the collection, review and peer assessment of students\' work.

Students can submit any digital content (files), such as word-processed documents or spreadsheets and can also type text directly into a field using the text editor.

Submissions are assessed using a multi-criteria assessment form defined by the teacher. The process of peer assessment and understanding the assessment form can be practised in advance with example submissions provided by the teacher, together with a reference assessment. Students are given the opportunity to assess one or more of their peers\' submissions. Submissions and reviewers may be anonymous if required.

Students obtain two grades in a Enhanced Workshop activity - a grade for their submission and a grade for their assessment of their peers\' submissions. Both grades are recorded in the gradebook.';
$string['modulename_link'] = 'mod/workshep/view';
$string['modulenameplural'] = 'Enhanced Workshops';
$string['mysubmission'] = 'My submission';
$string['nattachments'] = 'Maximum number of submission attachments';
$string['noexamples'] = 'No examples yet in this Enhanced Workshop';
$string['noexamplesformready'] = 'You must define the assessment form before providing example submissions';
$string['nogradeyet'] = 'No grade yet';
$string['nosubmissionfound'] = 'No submission found for this user';
$string['nosubmissions'] = 'No submissions yet in this Enhanced Workshop';
$string['nothingtoreview'] = 'Nothing to review';
$string['notassessed'] = 'Not assessed yet';
$string['notoverridden'] = 'Not overridden';
$string['noworksheps'] = 'There are no Enhanced Workshops in this course';
$string['noyoursubmission'] = 'You have not submitted your work yet';
$string['nullgrade'] = '-';
$string['overallfeedback'] = 'Overall feedback';
$string['overallfeedbackfiles'] = 'Maximum number of overall feedback attachments';
$string['overallfeedbackmaxbytes'] = 'Maximum overall feedback attachment size';
$string['overallfeedbackmode'] = 'Overall feedback mode';
$string['overallfeedbackmode_0'] = 'Disabled';
$string['overallfeedbackmode_1'] = 'Enabled and optional';
$string['overallfeedbackmode_2'] = 'Enabled and required';
$string['overallfeedbackmode_help'] = 'If enabled, a text field is displayed at the bottom of the assessment form. Reviewers can put the overall assessment of the submission there, or provide additional explanation of their assessment.';
$string['page-mod-workshep-x'] = 'Any Enhanced Workshop module page';
$string['participant'] = 'Participant';
$string['participantrevierof'] = 'Participant is reviewer of';
$string['participantreviewedby'] = 'Participant is reviewed by';
$string['phaseassessment'] = 'Assessment phase';
$string['phaseclosed'] = 'Closed';
$string['phaseevaluation'] = 'Grading evaluation phase';
$string['phasesoverlap'] = 'The submission phase and the assessment phase can not overlap';
$string['phasesetup'] = 'Setup phase';
$string['phasesubmission'] = 'Submission phase';
$string['pluginadministration'] = 'Enhanced Workshop administration';
$string['pluginname'] = 'Enhanced Workshop';
$string['prepareexamples'] = 'Prepare example submissions';
$string['previewassessmentform'] = 'Preview';
$string['publishedsubmissions'] = 'Published submissions';
$string['publishsubmission'] = 'Publish submission';
$string['publishsubmission_help'] = 'Published submissions are available to the others when the Enhanced Workshop is closed.';
$string['reassess'] = 'Re-assess';
$string['review'] = 'Review';
$string['receivedgrades'] = 'Grades received';
$string['recentassessments'] = 'Enhanced Workshop assessments:';
$string['recentsubmissions'] = 'Enhanced Workshop submissions:';
$string['resetassessments'] = 'Delete all assessments';
$string['resetassessments_help'] = 'You can choose to delete just allocated assessments without affecting submissions. If submissions are to be deleted, their assessments will be deleted implicitly and this option is ignored. Note this also includes assessments of example submissions.';
$string['resetsubmissions'] = 'Delete all submissions';
$string['resetsubmissions_help'] = 'All the submissions and their assessments will be deleted. This does not affect example submissions.';
$string['resetphase'] = 'Switch to the setup phase';
$string['resetphase_help'] = 'If enabled, all worksheps will be put into the initial setup phase.';
$string['saveandclose'] = 'Save and close';
$string['saveandcontinue'] = 'Save and continue editing';
$string['saveandpreview'] = 'Save and preview';
$string['saveandshownext'] = 'Save and show next';
$string['selfassessmentdisabled'] = 'Self-assessment disabled';
$string['showingperpage'] = 'Showing {$a} items per page';
$string['showingperpagechange'] = 'Change ...';
$string['someuserswosubmission'] = 'There is at least one author who has not yet submitted their work';
$string['sortasc'] = 'Ascending sort';
$string['sortdesc'] = 'Descending sort';
$string['strategy'] = 'Grading strategy';
$string['strategy_help'] = 'The grading strategy determines the assessment form used and the method of grading submissions. There are 4 options:

* Accumulative grading - Comments and a grade are given regarding specified aspects
* Comments - Comments are given regarding specified aspects but no grade can be given
* Number of errors - Comments and a yes/no assessment are given regarding specified assertions
* Rubric - A level assessment is given regarding specified criteria';
$string['strategyhaschanged'] = 'The Enhanced Workshop grading strategy has changed since the form was opened for editing.';
$string['submission'] = 'Submission';
$string['submissionattachment'] = 'Attachment';
$string['submissionby'] = 'Submission by {$a}';
$string['submissioncontent'] = 'Submission content';
$string['submissionend'] = 'Submissions deadline';
$string['submissionendbeforestart'] = 'Submissions deadline can not be specified before the open for submissions date';
$string['submissionendevent'] = '{$a} (submissions deadline)';
$string['submissionenddatetime'] = 'Submissions deadline: {$a->daydatetime} ({$a->distanceday})';
$string['submissionendswitch'] = 'Switch to the next phase after the submissions deadline';
$string['submissionendswitch_help'] = 'If the submissions deadline is specified and this box is checked, the Enhanced Workshop will automatically switch to the assessment phase after the submissions deadline.

If you enable this feature, it is recommended to set up the scheduled allocation method, too. If the submissions are not allocated, no assessment can be done even if the Enhanced Workshop itself is in the assessment phase.';
$string['submissiongrade'] = 'Grade for submission';
$string['submissiongrade_help'] = 'This setting specifies the maximum grade that may be obtained for submitted work.';
$string['submissiongradeof'] = 'Grade for submission (of {$a})';
$string['submissionsettings'] = 'Submission settings';
$string['submissionstart'] = 'Open for submissions from';
$string['submissionstartevent'] = '{$a} (opens for submissions)';
$string['submissionstartdatetime'] = 'Open for submissions from {$a->daydatetime} ({$a->distanceday})';
$string['submissiontitle'] = 'Title';
$string['subplugintype_workshepallocation'] = 'Submissions allocation method';
$string['subplugintype_workshepallocation_plural'] = 'Submissions allocation methods';
$string['subplugintype_workshepeval'] = 'Grading evaluation method';
$string['subplugintype_workshepeval_plural'] = 'Grading evaluation methods';
$string['subplugintype_workshepform'] = 'Grading strategy';
$string['subplugintype_workshepform_plural'] = 'Grading strategies';
$string['switchingphase'] = 'Switching phase';
$string['switchphase'] = 'Switch phase';
$string['switchphase10info'] = 'You are about to switch the Enhanced Workshop into the <strong>Setup phase</strong>. In this phase, users cannot modify their submissions or their assessments. Teachers may use this phase to change Enhanced Workshop settings, modify the grading strategy or tweak assessment forms.';
$string['switchphase20info'] = 'You are about to switch the Enhanced Workshop into the <strong>Submission phase</strong>. Students may submit their work during this phase (within the submission access control dates, if set). Teachers may allocate submissions for peer review.';
$string['switchphase30auto'] = 'Enhanced Workshop will automatically switch into the assessment phase after {$a->daydatetime} ({$a->distanceday})';
$string['switchphase30info'] = 'You are about to switch the Enhanced Workshop into the <strong>Assessment phase</strong>. In this phase, reviewers may assess the submissions they have been allocated (within the assessment access control dates, if set).';
$string['switchphase40info'] = 'You are about to switch the Enhanced Workshop into the <strong>Grading evaluation phase</strong>. In this phase, users cannot modify their submissions or their assessments. Teachers may use the grading evaluation tools to calculate final grades and provide feedback for reviewers.';
$string['switchphase50info'] = 'You are about to close the Workshop (UNSW). This will result in the calculated grades appearing in the gradebook. Students may view their submissions and their submission assessments.';
$string['taskassesspeers'] = 'Assess peers';
$string['taskassesspeersdetails'] = 'total: {$a->total}<br />pending: {$a->todo}';
$string['taskassessself'] = 'Assess yourself';
$string['taskconclusion'] = 'Provide a conclusion of the activity';
$string['taskinstructauthors'] = 'Provide instructions for submission';
$string['taskinstructreviewers'] = 'Provide instructions for assessment';
$string['taskintro'] = 'Set the Enhanced Workshop description';
$string['tasksubmit'] = 'Submit your work';
$string['toolbox'] = 'Enhanced Workshop toolbox';
$string['undersetup'] = 'The Enhanced Workshop is currently being set up. Please wait until it is switched to the next phase.';
$string['useexamples'] = 'Use examples';
$string['useexamples_desc'] = 'Example submissions are provided for practice in assessing';
$string['useexamples_help'] = 'If enabled, users can try assessing one or more example submissions and compare their assessment with a reference assessment.';
$string['usepeerassessment'] = 'Use peer assessment';
$string['usepeerassessment_desc'] = 'Students may assess the work of others';
$string['usepeerassessment_help'] = 'If enabled, a user may be allocated submissions from other users to assess and will receive a grade for assessment in addition to a grade for their own submission.';
$string['userdatecreated'] = 'submitted on <span>{$a}</span>';
$string['userdatemodified'] = 'modified on <span>{$a}</span>';
$string['userplan'] = 'Enhanced Workshop planner';
$string['userplan_help'] = 'The Enhanced Workshop planner displays all phases of the activity and lists the tasks for each phase. The current phase is highlighted and task completion is indicated with a tick.';
$string['useselfassessment'] = 'Use self-assessment';
$string['useselfassessment_help'] = 'If enabled, a user may be allocated their own submission to assess and will receive a grade for assessment in addition to a grade for their submission.';
$string['useselfassessment_desc'] = 'Students may assess their own work';
$string['weightinfo'] = 'Weight: {$a}';
$string['withoutsubmission'] = 'Reviewer without own submission';
$string['workshep:addinstance'] = 'Add a new Enhanced Workshop';
$string['workshep:allocate'] = 'Allocate submissions for review';
$string['workshep:editdimensions'] = 'Edit assessment forms';
$string['workshep:ignoredeadlines'] = 'Ignore time restrictions';
$string['workshep:manageexamples'] = 'Manage example submissions';
$string['workshepname'] = 'Enhanced Workshop name';
$string['workshep:overridegrades'] = 'Override calculated grades';
$string['workshep:peerassess'] = 'Peer assess';
$string['workshep:publishsubmissions'] = 'Publish submissions';
$string['workshep:submit'] = 'Submit';
$string['workshep:switchphase'] = 'Switch phase';
$string['workshep:view'] = 'View Enhanced Workshop';
$string['workshep:viewallassessments'] = 'View all assessments';
$string['workshep:viewallsubmissions'] = 'View all submissions';
$string['workshep:viewauthornames'] = 'View author names';
$string['workshep:viewauthorpublished'] = 'View authors of published submissions';
$string['workshep:viewpublishedsubmissions'] = 'View published submissions';
$string['workshep:viewreviewernames'] = 'View reviewer names';
$string['yourassessment'] = 'Your assessment';
$string['yourgrades'] = 'Your grades';
$string['yoursubmission'] = 'Your submission';

//Additions: Team Mode
$string['teammode'] = 'Team mode';
$string['teammode_desc'] = 'Allow students to submit work as a team.';
$string['teammode_help'] = <<<MDOWN
Allows students to submit work as a team.

Enabling team mode means that work is treated as being submitted by a whole group. When one student submits work, that submission counts for everyone in that student's team, and everyone in their team can edit that submission as if it were their own. Teams are the same as groups; when you make a Enhanced Workshop in Team Mode, you must ensure that every student belongs to exactly one group. The teams are scoped by the grouping you select for the Enhanced Workshop at the bottom of this page, so if your students belong to more than one group, make a grouping with groups such that they only belong to one.

If self-assessment is disabled, students are prevented from marking their own team's work.
MDOWN;
$string['teammode_disabled'] = 'Team mode is disabled because you have no groups in your course.';
$string['teammode_ungroupedwarning'] = 'Warning: If the Enhanced Workshop is in Team mode, then users MUST be part of at least one group to submit work.<br/>
	<br/>
These users are currently not in a group: {$a}';
$string['teammode_notingroupwarning'] = 'You are not in any groups. You cannot submit work for this assessment.';
$string['teammode_duplicategroupnameswarning'] = 'You have some groups with the same name, so you can\'t upload data. You need to change their names or allocate manually. (Duplicate names: {$a})';
$string['teammode_multiplegroupswarning'] = 'You have users in multiple groups ({$a}). Please select a grouping with unique groups.';
$string['teammode_nogroupswarning'] = 'There are no groups in your course, or in the selected grouping. <strong>Team mode has been disabled</strong>. If you wish to continue in team mode, create some groups, then edit the settings this Enhanced Workshop and re-enabled team mode.';

//Additions: Calibration
$string['examplescompare'] = 'Example comparison';
$string['examplescompare_desc'] = 'Allow comparison of example submissions with reference assessments';
$string['examplescompare_warn'] = 'Do not check both of these if you are using <strong>Calibrated</strong> grading.';
$string['examplesreassess'] = 'Example reassessment';
$string['examplesreassess_desc'] = 'Allow students to reassess example submissions';
$string['examplesrequired'] = 'You must select <strong>Use examples</strong> to set up <strong>Calibrated</strong> grading.';
$string['examplesmoderequired'] = 'Do not use <strong>voluntary</strong> example assessment when using <strong>Calibrated</strong> grading.';
$string['exampleassessmentsname'] = '{$a}\'s example assessments';
$string['explanation'] = 'Calibration score breakdown for {$a}';
$string['yourexplanation'] = 'Your calibration score breakdown';
$string['showexamples'] = 'Show {$a}\'s example assessments';
$string['showyourexamples'] = 'Show your example assessments';
$string['showsubmission'] = 'Show Submission';

//Additions: Random Examples
$string['numexamples'] = 'Number of examples';
$string['numexamples_help'] = <<<MDOWN
This allows you to provide more example submissions than are presented to your students.

If you set this, the students are presented examples pseudo-randomly; they will be shown a roughly even spread of poor to good submissions. This is useful to prevent cheating when using the Calibrated evaluation method.

If you leave this at zero, all of your example submissions will be shown to your students.
MDOWN;

$string['randomexamplesoverlapwarning'] = 'The {$a->prev} and {$a->next} brackets overlap. There might be little or no differentiation between these brackets.';
$string['randomexampleshelp'] = 'Random Examples: What does this mean?';
$string['randomexampleshelp_help'] = <<<MDOWN

When you choose to show your students more than one example assessment (and not all of them), Enhanced Workshop attempts to give them a good spread of examples, choosing poor, average and good assessments evenly. It also picks assessments semi-randomly, to prevent students cheating off each other. This is especially useful for the Calibration evaluation method.

In order to help you create better examples, we've got this handy tool. It gives you a quick and easy visual representation of your example assessments.

When Enhanced Workshop is picking example assessments for a student, it divides all the assessments into n even brackets, where n is the number of example assessments you chose for each student to do. You can see these brackets here, represented by the coloured bars. These represent the range of the lowest to the highest mark in that bracket.

The small bars are the individual example assessments, while the tall, thick bars are the average mark for that bracket.

You can use this tool to help you create an even spread of example assessments.
MDOWN;

//Additions: Download Marks

$string['downloadmarks'] = 'Download Marks';
$string['name'] = 'Name';
$string['idnumber'] = 'ID Number';
$string['overallmarks'] = 'Overall Marks';
$string['individualmarks'] = 'Individual Marks';
$string['markedsubmission'] = 'Marked Submission';
$string['overallmark'] = 'Overall Mark';
$string['scaledmark'] = 'Scaled Mark';
$string['referencemarker'] = 'Reference Marker';
$string['submittedby'] = 'Submitted by';
$string['submitteridnumber'] = 'Submitter ID Number';
$string['markeridnumber'] = 'Marker ID Number';
$string['markername'] = 'Marker Name';
$string['comments'] = 'Comments';
$string['feedback'] = 'Feedback';
$string['submissiondate'] = 'Submission date';


$string['wordcount'] = 'Word count: {$a}';

$string['downloadallocations'] = 'Download Allocations';

$string['submitterflagging'] = 'Submitter Assessment Flagging';
$string['flaggingon'] = 'Allow submitters to flag reviews as unfair';
$string['flagassessment'] = 'Flag this assessment as unfair';
$string['unflagassessment'] = 'Unflag this assessment';
$string['flagassessment_help'] = 'If you feel this assessment was unfair, you can flag it for review.';
$string['showflaggedassessments'] = 'Review assessments that users have flagged as unfair';
$string['resolutiontitle'] = 'Flagged as unfair';
$string['resolutionfair'] = 'This assessment is fair';
$string['resolutionunfair'] = 'This assessment is unfair and should be discounted';
$string['needsresolution'] = 'This assessment has been flagged by the submitter as unfair and needs review.';

// Calibrationphase

$string['calibration'] = 'Calibration';
$string['usecalibration'] = 'Use Calibration';
$string['usecalibration_desc'] = 'Calibrate students against the reference marker on the example assessments';
$string['calibrationphase'] = 'Place calibration phase...';
$string['beforesubmission'] = 'Before submission phase';
$string['beforeassessment'] = 'Before assessment phase';
$string['beforeevaluation'] = 'Before evaluation phase';
$string['usecalibration_help'] = 'Calibration enables users to have their competence as reviewers determined before reviewing their peers\' work.';
$string['calibrationphase_help'] = 'Calibration can be completed by your students either before they submit work, or before they assess their peers.';
$string['phasecalibration'] = 'Calibration phase';
$string['calculatecalibrationscores'] = 'Calculate calibration scores';
$string['switchphase25info'] = 'You are about to switch the workshep into the <strong>Calibration phase</strong>. In this phase, potential reviewers will complete example assessments, and their assessments will be compared to the reference assessments you provided. This score will be used to assess their competence when it comes to reviewing their peers\' work.';
$string['noexamples'] = 'This user has not been assigned any examples.';
$string['nocalibrationscore'] = '–';
$string['notcompleted'] = 'Not completed';
$string['exampleassessment'] = 'Example Assessment';
$string['score'] = 'Calibration Score';
$string['calculatescores'] = 'Calculate Calibration Scores';
$string['yourcalibration'] = 'Your calibration results';
$string['calibrationcompletion'] = '{$a->num} / {$a->den} users have completed example assessments.';



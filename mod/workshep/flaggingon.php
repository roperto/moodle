<?php
    
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');

$id         = required_param('id', PARAM_INT); // course_module ID
$activate   = required_param('activate', PARAM_BOOL);

$cm         = get_coursemodule_from_id('workshep', $id, 0, false, MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$workshep   = $DB->get_record('workshep', array('id' => $cm->instance), '*', MUST_EXIST);

print_r($cm);

require_login($course, true, $cm);
require_capability('mod/workshep:viewallassessments', $PAGE->context);

$DB->set_field('workshep', 'submitterflagging', $activate, array('id' => $workshep->id));
    
?>
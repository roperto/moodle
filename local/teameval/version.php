<?php
    
defined('MOODLE_INTERNAL') || die();

$plugin->version  = 2016070600;
$plugin->requires = 2015051100;  // Requires this Moodle version.
$plugin->component = 'local_teameval';
$plugin->dependencies = array(
    'local_searchable' => 2016052600
);

?>
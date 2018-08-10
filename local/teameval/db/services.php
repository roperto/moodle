<?php

$functions = [
    
    'local_teameval_turn_on' => [
        
        'classname'     => 'local_teameval\external',
        'methodname'    => 'turn_on',
        'type'          => 'write',
        
    ],

    'local_teameval_get_settings' => [
        
        'classname'     => 'local_teameval\external',
        'methodname'    => 'get_settings',
        'type'          => 'read'
        
    ],
    
    'local_teameval_update_settings' => [
        
        'classname'     => 'local_teameval\external',
        'methodname'    => 'update_settings',
        'type'          => 'write',
        
    ],

    'local_teameval_questionnaire_set_order' => [

        'classname'     => 'local_teameval\external',
        'methodname'    => 'questionnaire_set_order',
        'type'          => 'write',

    ],

    'local_teameval_report' => [
    
        'classname'     => 'local_teameval\external',
        'methodname'    => 'report',
        'type'          => 'read',

    ],

    'local_teameval_release' => [

        'classname'     => 'local_teameval\external',
        'methodname'    => 'release',
        'type'          => 'write',

    ],

    'local_teameval_get_release' => [

        'classname'     => 'local_teameval\external',
        'methodname'    => 'get_release',
        'type'          => 'read',

    ],

    'local_teameval_template_search' => [

        'classname'     => 'local_teameval\external',
        'methodname'    => 'template_search',
        'type'          => 'read'

    ],

    'local_teameval_add_from_template' => [

        'classname'     => 'local_teameval\external',
        'methodname'    => 'add_from_template',
        'type'          => 'write',
        'requiredcapability' => 'local/teameval:createquestionnaire',

    ],

    'local_teameval_upload_template' => [

        'classname'     => 'local_teameval\external',
        'methodname'    => 'upload_template',
        'type'          => 'write',
        'requiredcapability' => 'local/teameval:createquestionnaire',

    ]
    
]
    
?>
<?php

return [
    'verbose' => false,
    'to' => '',
    'providers' => [
        'bitbucket' => [
            'uid' => '',
            'psw' => '',
            'folder' => '',
        ],
        'github' => [
            'uid' => '',
            'token' => '', // Must be generated in the Github account section.
        ]
    ],
    'projects' => [
        [
            'name' => 'project-a',
            'root' => '/home/project-a',
            'git' => [
                'provider' => 'bitbucket',
                'branch' => 'master',
            ],
            'mysql' => [
                'dir' => '.rs/data',
                'name' => '',
                'uid' => '',
                'psw' => '',
                'date' => 'Wy', // One file per week.
            ]
        ]
    ]
]

?>
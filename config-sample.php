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
        'github' => [ //@TODO
            'uid' => '',
            'psw' => '',
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
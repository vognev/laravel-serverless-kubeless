<?php

return [
    'storage' => storage_path('serverless'),
    'image' => 'vognev/kubeless-php',
    'auth' => env('SERVERLESS_KUBELESS_REGISTRY_AUTH'),
    'php' => [
        'modules' => ['default', 'pcntl'],
        'presets' => [
            'default' => [
                'curl',
                'dom',
                'fileinfo',
                'filter',
                'ftp',
                'hash',
                'iconv',
                'intl',
                'json',
                'mbstring',
                'openssl',
                'opcache',
                'pdo_mysql',
                'readline',
                'session',
                'sockets',
                'tokenizer',
                'zip',
            ]
        ]
    ]
];

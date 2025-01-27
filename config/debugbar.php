<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Debugbar Settings
    |--------------------------------------------------------------------------
    */
    'enabled' => env('DEBUGBAR_ENABLED', null),

    /*
    |--------------------------------------------------------------------------
    | Storage settings
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'enabled' => true,
        'driver' => 'file', // redis, file, pdo, socket
        'path' => storage_path('debugbar'),
        'connection' => null,
        'provider' => '',
    ],

    /*
    |--------------------------------------------------------------------------
    | Vendors
    |--------------------------------------------------------------------------
    */
    'include_vendors' => true,

    /*
    |--------------------------------------------------------------------------
    | Capture Ajax Requests
    |--------------------------------------------------------------------------
    */
    'capture_ajax' => true,

    /*
    |--------------------------------------------------------------------------
    | Clockwork Integration
    |--------------------------------------------------------------------------
    */
    'clockwork' => false,

    /*
    |--------------------------------------------------------------------------
    | Data Collectors
    |--------------------------------------------------------------------------
    */
    'collectors' => [
        'phpinfo' => true, // Php version
        'messages' => true, // Messages
        'time' => true, // Time Datalogger
        'memory' => true, // Memory usage
        'exceptions' => true, // Exception displayer
        'log' => true, // Logs from Monolog (merged in messages if enabled)
        'db' => true, // Show database (PDO) queries and bindings
        'views' => true, // Views with their data
        'route' => true, // Current route information
        'auth' => true, // Display Laravel authentication status
        'gate' => true, // Display Gate checks
        'session' => true, // Display session data
        'symfony_request' => true, // Symfony Request
        'models' => true, // Display models
        'livewire' => true, // Display Livewire (when available)
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Collector Options
    |--------------------------------------------------------------------------
    */
    'options' => [
        'db' => [
            'with_params' => true, // Render SQL with the parameters
            'backtrace' => false, // Use a backtrace to find the origin of the query in your files.
            'timeline' => true, // Add the queries to the timeline
            'duration_background' => true, // Show shaded background on each query relative to how long it took to execute.
            'explain' => [ // Show EXPLAIN output on queries
                'enabled' => false,
                'types' => ['SELECT'], // ['SELECT', 'INSERT', 'UPDATE', 'DELETE']; for MySQL 5.6.3+
            ],
            'hints' => true, // Show hints for common mistakes
            'show_copy' => false, // Show copy button next to the query
            'soft_limit' => 1500, // Soft limit for the number of queries
            'hard_limit' => 4000, // Hard limit for the number of queries
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Editor settings
    |--------------------------------------------------------------------------
    */
    'editor' => env('DEBUGBAR_EDITOR', null),
    'remote_sites_path' => env('DEBUGBAR_REMOTE_SITES_PATH', ''),
    'local_sites_path' => env('DEBUGBAR_LOCAL_SITES_PATH', ''),

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */
    'allowed_ips' => env('DEBUGBAR_ALLOWED_IPS', null),
    'hide_ajax' => false,

    /*
    |--------------------------------------------------------------------------
    | Debugbar HTTP driver
    |--------------------------------------------------------------------------
    */
    'http_driver' => \Barryvdh\Debugbar\SymfonyHttpDriver::class,

    /*
    |--------------------------------------------------------------------------
    | Enable / Disable individual collectors
    |--------------------------------------------------------------------------
    */
    'collectors' => [
        'phpinfo' => true,
        'messages' => true,
        'time' => true,
        'memory' => true,
        'exceptions' => true,
        'log' => true,
        'db' => true,
        'views' => true,
        'route' => true,
        'auth' => true,
        'gate' => true,
        'session' => true,
        'symfony_request' => true,
        'models' => true,
        'livewire' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Route Prefix
    |--------------------------------------------------------------------------
    */
    'route_prefix' => '_debugbar',

    /*
    |--------------------------------------------------------------------------
    | Debugbar AJAX Requests Timeout
    |--------------------------------------------------------------------------
    */
    'ajax_handler_timeout' => 3000,
];

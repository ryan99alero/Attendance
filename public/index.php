<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// KoolReport Laravel 11 Compatibility Fix - Add Collection clone method
if (class_exists('\Illuminate\Support\Collection')) {
    \Illuminate\Support\Collection::macro('clone', function () {
        return new \Illuminate\Support\Collection($this->all());
    });
}

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());

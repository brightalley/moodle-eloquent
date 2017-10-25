<?php

// Require the Composer autoloader.
require_once(__DIR__.'/vendor/autoload.php');

use local_eloquent\eloquent\model;
use Illuminate\Container\Container;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Events\EventServiceProvider;

call_user_func(function () {
    $app = new Container();

    // Create service providers.
    $providers = [
        new EventServiceProvider($app),
        new DatabaseServiceProvider($app),
    ];

    // Register them.
    foreach ($providers as $provider) {
        $provider->register();

        // Wow...
        if (method_exists($provider, 'boot')) {
            return call_user_func([$provider, 'boot']);
        }
    }
});

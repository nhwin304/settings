<?php

// config for Nhwin304/Settings
return [

    /*
    |--------------------------------------------------------------------------
    | Table Name
    |--------------------------------------------------------------------------
    |
    | The name of the database table used to store the settings.
    |
    */
    'table_name' => 'settings',

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the cache behavior for the package. You can set a custom
    | prefix for all cache keys and define a Time To Live (TTL) in minutes.
    |
    | Setting 'ttl' to null or 0 will cache the settings forever.
    |
    */
    'cache' => [
        'prefix' => 'settings',
        'ttl' => null, // Cache forever by default
    ],

];
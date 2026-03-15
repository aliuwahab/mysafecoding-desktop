<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payload Size Limits
    |--------------------------------------------------------------------------
    |
    | Security scan results can be large (multiple scanner outputs in JSON).
    | Increase the default 1MB limit to 5MB to handle comprehensive scans.
    |
    */

    'payload' => [
        'max_size' => 5 * 1024 * 1024, // 5MB
    ],

];

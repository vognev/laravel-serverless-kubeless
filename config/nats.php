<?php

return [
    'driver'    => 'nats',
    'server'    => env('NATS_SERVER'),
    'queue'     => env('NATS_TOPIC'),
    'debug'     => env('NATS_DEBUG', false),
];

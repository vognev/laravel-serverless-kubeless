<?php

return [
    'driver'            => 'kafka',
    'queue'             => env('KAFKA_QUEUE', 'default'),
    'consumer_group_id' => env('KAFKA_CONSUMER_GROUP_ID', 'worker'),
    'brokers'           => env('KAFKA_BROKERS', 'broker.kubeless.svc:9092'),
    /*'sleep_on_error'    => env('KAFKA_ERROR_SLEEP', 5),
    'sleep_on_deadlock' => env('KAFKA_DEADLOCK_SLEEP', 2),*/
];

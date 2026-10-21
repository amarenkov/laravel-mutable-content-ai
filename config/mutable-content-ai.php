<?php

return [
    'user_model' => env('MUTABLE_CONTENT_AI_USER_MODEL'),

    'memory' => [
        'max_items' => 50,
        'max_length' => 500,
    ],

    'settings' => [
        'driver' => 'openai-compatible',
    ],

    'prices' => [],

    'history' => [
        'runs' => 10,
        'minutes' => 120,
    ],
];

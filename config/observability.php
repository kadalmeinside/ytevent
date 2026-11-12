<?php

return [
    'slow_queries' => [
        'enabled' => (bool) env('OBSERVABILITY_SLOW_QUERIES', false),
        'threshold_ms' => (int) env('OBSERVABILITY_SLOW_QUERY_THRESHOLD_MS', 500),
        'channel' => env('OBSERVABILITY_LOG_CHANNEL', 'stack'),
    ],
    'alerts' => [
        'enabled' => (bool) env('OBSERVABILITY_ALERTS', false),
        'email' => env('OBSERVABILITY_ALERT_EMAIL'),
    ],
];

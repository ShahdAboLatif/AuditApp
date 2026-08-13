<?php

$devMode = (int) env('DEV_MODE', 0) === 1;

$authSubject = $devMode
    ? 'auth.testing.v1.>'
    : 'auth.v1.>';

$hiringSubject = $devMode
    ? 'hiring.testing.v1.>'
    : 'hiring.v1.>';

$notificationsSubject = $devMode
    ? 'notifications.testing.v1.>'
    : 'notifications.v1.>';

return [
    'dev_mode' => $devMode,
    'host' => env('NATS_HOST', '127.0.0.1'),
    'port' => (int) env('NATS_PORT', 4222),

    'user' => env('NATS_USER'),
    'pass' => env('NATS_PASS'),
    'token' => env('NATS_TOKEN'),



    'publishers' => [
        [
            'name' => $devMode
                ? env('NATS_NOTIFICATIONS_STREAM', 'NOTIFICATIONS_TESTING_EVENTS')
                : env('NATS_NOTIFICATIONS_STREAM', 'NOTIFICATIONS_EVENTS'),
            'subjects' => [$notificationsSubject],
        ],
    ],
    /**
     * Add streams here as new projects appear.
     * Each stream gets its own durable pull consumer.
     */
    'streams' => [
        [
            'name' => $devMode ? env('NATS_AUTH_STREAM', 'AUTH_TESTING_EVENTS') : env('NATS_AUTH_STREAM', 'AUTH_EVENTS'),
            'durable' => $devMode ? env('NATS_AUTH_DURABLE', 'QA_AUTH_TESTING_CONSUMER') : env('NATS_AUTH_DURABLE', 'QA_AUTH_CONSUMER'),
            'filter_subject' => $authSubject, // match your stream subjects
        ],

        // EMPLOYEES from HiringPizza
        [
            'name' => $devMode ? env('NATS_HIRING_STREAM', 'HIRING_TESTING_EVENTS') : env('NATS_HIRING_STREAM', 'HIRING_EVENTS'),
            'durable' => $devMode ? env('NATS_HIRING_DURABLE', 'QA_HIRING_TESTING_CONSUMER') : env('NATS_HIRING_DURABLE', 'QA_HIRING_CONSUMER'),
            'filter_subject' => $hiringSubject,
        ],
    ],

    'pull' => [
        'batch' => (int) env('NATS_PULL_BATCH', 25),
        'timeout_ms' => (int) env('NATS_PULL_TIMEOUT_MS', 2000),
        'sleep_ms' => (int) env('NATS_PULL_SLEEP_MS', 250),
    ],

    /**
     * How long to wait for a real JetStream publish ack before giving up.
     * A subject with no backing stream never gets an ack, so this is also
     * the maximum time a missing-stream misconfiguration takes to surface
     * as an exception instead of failing silently.
     */
    'publish_ack_timeout' => (float) env('NATS_PUBLISH_ACK_TIMEOUT', 3),

    /**
     * Subjects NatsPublisher is allowed to send, independent of `publishers`
     * above (which only lists streams THIS app provisions via
     * nats:ensure-streams). qa.v1.> has a QA_EVENTS stream that already
     * exists on the NATS server — created before notifications.v1.> replaced
     * it here — so it's kept allowed even though this app no longer manages
     * that stream's lifecycle.
     */
    'allowed_publish_subjects' => [
        $notificationsSubject,
        $devMode ? 'qa.testing.v1.>' : 'qa.v1.>',
    ],
];

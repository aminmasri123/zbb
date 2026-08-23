<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'ba_jobsuche' => [
        'base_url' => env('BA_JOBSEARCH_BASE_URL', 'https://rest.arbeitsagentur.de/jobboerse/jobsuche-service'),
        'api_key' => env('BA_JOBSEARCH_API_KEY', 'jobboerse-jobsuche'),
        'timeout' => (int) env('BA_JOBSEARCH_TIMEOUT', 12),
    ],

    'zbb_ai_agent' => [
        // The production web server reaches the remote loopback listener through
        // a supervised SSH tunnel. Never point this setting at a LAN listener.
        'base_url' => env('ZBB_AI_AGENT_BASE_URL', 'http://127.0.0.1:18000'),
        'key_id' => env('ZBB_AI_AGENT_KEY_ID'),
        'secret' => env('ZBB_AI_AGENT_SECRET'),
        'connect_timeout' => (int) env('ZBB_AI_AGENT_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('ZBB_AI_AGENT_TIMEOUT', 130),
        'max_response_bytes' => (int) env('ZBB_AI_AGENT_MAX_RESPONSE_BYTES', 1000000),
    ],

    'zbb_ai_workspace' => [
        'pdfinfo' => env('ZBB_PDFINFO_BINARY', 'pdfinfo'),
        'pdftotext' => env('ZBB_PDFTOTEXT_BINARY', 'pdftotext'),
        'timeout' => (int) env('ZBB_AI_WORKSPACE_TIMEOUT', 300),
    ],

];

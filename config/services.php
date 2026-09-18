<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'freepbx' => [
        'host' => env('FREEPBX_SSH_HOST', '127.0.0.1'),
        'user' => env('FREEPBX_SSH_USER', 'root'),
        'pass' => env('FREEPBX_SSH_PASS'), // 🚀 Kosongkan parameter kedua agar password mentah tidak terekspos
        // Folder rekaman di server FreePBX (pola URL /monitor/YYYY/MM/DD/namafile)
        'monitor_path' => env('FREEPBX_MONITOR_PATH', '/var/spool/asterisk/monitor'),
    ],

    'pds' => [
        // agent_first = kaki agent didial dulu (perilaku lama, aman).
        // customer_first = kaki customer didial dulu via trunk, yang angkat
        // diteruskan ke agent oleh context [pds-connect] di extensions_custom.conf.
        // Ganti ke customer_first HANYA setelah dialplan + AGI + queue 9000 siap.
        'mode' => env('PDS_DIAL_MODE', 'agent_first'),
        'connect_context' => env('PDS_CONNECT_CONTEXT', 'pds-connect'),
        'queue' => env('PDS_QUEUE', '9000'),
        // CallerID yang disodorkan ke trunk untuk kaki customer.
        'callerid' => env('PDS_CALLERID', ''),
        // Token untuk AGI pds-bridge.php (header X-Pds-Token).
        'bridge_token' => env('PDS_BRIDGE_TOKEN', ''),
    ],

];

<?php

declare(strict_types=1);

return [

    'reservation' => [
        'daily_limit' => (int) env('GYM_RESERVATION_DAILY_LIMIT', 5),

        'cancellation_notice_days' => (int) env(
            'GYM_CANCELLATION_NOTICE_DAYS',
            3
        ),

        'reference_prefix' => env(
            'GYM_REFERENCE_PREFIX',
            'MCST-GYM'
        ),

        'opening_time' => env('GYM_OPENING_TIME', '06:00'),

        'closing_time' => env('GYM_CLOSING_TIME', '22:00'),
    ],

    'statuses' => [
        'new',
        'validated',
        'approved',
        'rejected',
        'cancelled',
        'completed',
        'waiting_list',
    ],

    'roles' => [
        'super_admin',
        'gym_admin',
        'requestor',
    ],

    'requestor_categories' => [
        'student',
        'faculty',
        'staff',
        'recognized_organization',
        'external_organization',
        'municipal_office',
    ],

    'documents' => [
        'disk' => 'local',

        'directory' => 'reservation-documents',

        'max_size_kb' => (int) env(
            'GYM_DOCUMENT_MAX_SIZE_KB',
            5120
        ),

        'allowed_extensions' => [
            'pdf',
            'jpg',
            'jpeg',
            'png',
        ],

        'allowed_mime_types' => [
            'application/pdf',
            'image/jpeg',
            'image/png',
        ],
    ],

    'equipment' => [
        'standalone_reservations_enabled' => false,
    ],

];

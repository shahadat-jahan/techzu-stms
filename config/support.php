<?php

return [
    'business_timezone' => env('SUPPORT_BUSINESS_TIMEZONE', 'Asia/Dhaka'),
    'default_sla_minutes' => env('SUPPORT_DEFAULT_SLA_MINUTES', 1440),
    'warning_percent' => env('SUPPORT_WARNING_PERCENT', 80),
    'uploads' => [
        'max_size' => env('SUPPORT_UPLOAD_MAX_SIZE', 5120),
        'max_files' => env('SUPPORT_UPLOAD_MAX_FILES', 5),
        'allowed_mimes' => explode(',', env('SUPPORT_UPLOAD_ALLOWED_MIMES', 'pdf,doc,docx,jpg,jpeg,png,gif')),
    ],
    'pagination' => [
        'per_page' => env('SUPPORT_PAGINATION_PER_PAGE', 10),
        'load_more' => env('SUPPORT_PAGINATION_LOAD_MORE', true),
    ],
];

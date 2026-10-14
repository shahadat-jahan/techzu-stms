<?php

return [
    'business_timezone' => env('SUPPORT_BUSINESS_TIMEZONE', 'Asia/Dhaka'),
    'uploads' => [
        'max_size' => env('SUPPORT_UPLOAD_MAX_SIZE', 5120),
        'max_files' => env('SUPPORT_UPLOAD_MAX_FILES', 5),
        'allowed_mimes' => env('SUPPORT_UPLOAD_ALLOWED_MIMES', 'pdf,doc,docx,jpg,jpeg,png,gif'),
    ],
    'pagination' => [
        'per_page' => env('SUPPORT_PAGINATION_PER_PAGE', 10),
        'load_more' => env('SUPPORT_PAGINATION_LOAD_MORE', true),
    ],
];

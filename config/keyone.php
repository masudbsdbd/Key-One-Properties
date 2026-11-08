<?php

return [

    // Path of the sign-in page. Set a non-obvious value in production.
    'login_path' => env('LOGIN_PATH', 'login'),

    // Proxy IPs whose X-Forwarded-For header is trusted for the client IP. Empty = trust none.
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    'login' => [
        'max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'decay_seconds' => (int) env('LOGIN_DECAY_SECONDS', 300),
    ],

    'uploads' => [
        'image_max_kb' => (int) env('UPLOAD_IMAGE_MAX_KB', 5120),
        'document_max_kb' => (int) env('UPLOAD_DOCUMENT_MAX_KB', 10240),
        'image_mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        'document_mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'],
    ],

    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
        'email' => env('SUPER_ADMIN_EMAIL'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],

    'super_admin_role' => 'Super Admin',

];

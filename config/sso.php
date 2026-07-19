<?php

return [
    'private_key' => env('SSO_PRIVATE_KEY', ''),

    'public_key' => env('SSO_PUBLIC_KEY', ''),

    'public_key_pem' => env('SSO_PUBLIC_KEY_PEM')
        ? str_replace('\n', "\n", env('SSO_PUBLIC_KEY_PEM'))
        : (file_exists(storage_path('keys/sso-public.pem'))
            ? file_get_contents(storage_path('keys/sso-public.pem'))
            : ''),

    'ttl' => (int) env('SSO_TOKEN_TTL', 900),

    'algorithm' => 'EdDSA',
];

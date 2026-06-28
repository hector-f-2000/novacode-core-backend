<?php

namespace App\Models\Sanctum;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'ip_address',
        'user_agent',
        'device_name',
        'location',
        'is_revoked',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'is_revoked' => 'boolean',
        ]);
    }
}

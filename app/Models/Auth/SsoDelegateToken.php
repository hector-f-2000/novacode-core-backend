<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

class SsoDelegateToken extends Model
{
    public $timestamps = false;

    protected $table = 'sso_delegate_tokens';

    protected $fillable = [
        'jti',
        'tenant_user_id',
        'tenant_id',
        'app_slug',
        'issued_at',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at'  => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}

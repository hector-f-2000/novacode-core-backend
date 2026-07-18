<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

class UserOtp extends Model
{
    protected $table = 'user_otps';

    protected $fillable = [
        'user_type',
        'user_id',
        'email',
        'code_hash',
        'session_token',
        'code_expires_at',
        'resend_allowed_at',
        'attempts_remaining',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'code_expires_at'   => 'datetime',
            'resend_allowed_at' => 'datetime',
            'verified_at'       => 'datetime',
        ];
    }
}

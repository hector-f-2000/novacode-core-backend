<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class AccountLocked
{
    use Dispatchable;

    public function __construct(
        public string $email,
        public string $ip,
        public int $backoffMinutes,
    ) {}
}

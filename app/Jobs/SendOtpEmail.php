<?php

namespace App\Jobs;

use App\Mail\Auth\OtpMail;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendOtpEmail
{
    use Dispatchable, Queueable;

    public function __construct(
        public string $email,
        public string $code,
    ) {}

    public function handle(): void
    {
        Mail::to($this->email)->send(new OtpMail($this->code));
    }
}

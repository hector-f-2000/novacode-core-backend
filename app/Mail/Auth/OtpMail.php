<?php

namespace App\Mail\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Código de verificación — NovaCode Labs',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family:sans-serif;padding:24px;">
  <h2>NovaCode Labs</h2>
  <p>Tu código de verificación es:</p>
  <p style="font-size:32px;letter-spacing:8px;font-weight:bold;text-align:center;padding:16px;background:#f5f5f5;border-radius:8px;">
    {$this->code}
  </p>
  <p>Este código expira en 5 minutos. No lo compartas con nadie.</p>
  <p style="color:#666;font-size:12px;">Si no solicitaste este código, ignora este correo.</p>
</body>
</html>
HTML
        );
    }
}

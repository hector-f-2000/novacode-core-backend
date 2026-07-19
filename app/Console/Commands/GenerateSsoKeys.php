<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateSsoKeys extends Command
{
    protected $signature = 'sso:generate-keys';
    protected $description = 'Genera el par de claves Ed25519 para firmar tokens SSO delegados';

    public function handle(): int
    {
        $keypair = sodium_crypto_sign_keypair();
        $secretKey = sodium_crypto_sign_secretkey($keypair);
        $publicKey = sodium_crypto_sign_publickey($keypair);

        $privateKeyB64 = base64_encode($secretKey);
        $publicKeyB64 = base64_encode($publicKey);

        $pemPublicKey = $this->sodiumKeyToPem($publicKey);

        $disk = Storage::build([
            'driver' => 'local',
            'root' => storage_path('keys'),
        ]);

        $disk->put('sso-private.key', $privateKeyB64);
        $disk->put('sso-public.key', $publicKeyB64);
        $disk->put('sso-public.pem', $pemPublicKey);

        $this->info('Par de claves Ed25519 generado en storage/keys/:');
        $this->line("  sso-private.key  (base64, 64 bytes secret key)");
        $this->line("  sso-public.key   (base64, 32 bytes public key)");
        $this->line("  sso-public.pem   (PEM, para distribuir a satélites)");

        $this->newLine();
        $this->warn('Agrega estas líneas a tu .env:');
        $this->line("SSO_PRIVATE_KEY={$privateKeyB64}");
        $this->line("SSO_PUBLIC_KEY={$publicKeyB64}");

        $pemSingle = str_replace("\n", '\\n', $pemPublicKey);
        $this->line("SSO_PUBLIC_KEY_PEM={$pemSingle}");

        return self::SUCCESS;
    }

    private function sodiumKeyToPem(string $publicKey): string
    {
        $base64 = base64_encode($publicKey);
        $chunks = chunk_split($base64, 64, "\n");

        return "-----BEGIN PUBLIC KEY-----\n{$chunks}-----END PUBLIC KEY-----\n";
    }
}

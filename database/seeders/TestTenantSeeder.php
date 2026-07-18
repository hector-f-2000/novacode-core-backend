<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestTenantSeeder extends Seeder
{
    public function run(): void
    {
        $planId = DB::table('plans')->where('name', 'Plan Básico')->value('id');

        if (!$planId) {
            $this->command->error('Ejecuta primero TestPlanSeeder para crear el "Plan Básico".');
            return;
        }

        $apiKey = 'nc_pub_test_' . Str::random(32);
        $plainSecret = Str::random(40);
        $hashedSecret = Hash::make($plainSecret);

        DB::table('tenants')->updateOrInsert(
            ['slug' => 'empresa-demo'],
            [
                'rut' => '76.123.456-7',
                'razon_social' => 'Empresa Demo Ltda.',
                'giro' => 'Servicios de prueba',
                'address' => 'Av. Siempre Viva 123',
                'api_key_client' => $apiKey,
                'api_secret_token' => $hashedSecret,
                'plan_id' => $planId,
                'status' => 'trial',
                'expires_at' => now()->addDays(30),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('Tenant de prueba "Empresa Demo Ltda." creado correctamente.');
        $this->command->warn("=== SECRETO EN TEXTO PLANO (una sola vez) para slug 'empresa-demo': {$plainSecret} ===");
    }
}

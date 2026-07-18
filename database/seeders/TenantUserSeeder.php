<?php

namespace Database\Seeders;

use App\Models\Tenant\TenantUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TenantUserSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = DB::table('tenants')->where('status', 'trial')->first();

        if (!$tenant) {
            $this->command->warn('No se encontró un tenant con status "active". Ejecuta primero TestTenantSeeder.');
            return;
        }

        $email = 'owner@empresa-demo.cl';
        $plainPassword = 'password123';

        TenantUser::updateOrCreate(
            ['email' => $email],
            [
                'tenant_id' => $tenant->id,
                'name'      => 'Dueño de Empresa Demo',
                'password'  => Hash::make($plainPassword),
                'role'      => 'tenant_owner',
                'status'    => true,
            ]
        );

        $this->command->info("Tenant owner de prueba creado para tenant '{$tenant->slug}' ({$tenant->razon_social}).");
        $this->command->warn("=== CREDENCIALES DE PRUEBA ===");
        $this->command->warn("  Email:    {$email}");
        $this->command->warn("  Password: {$plainPassword}");
    }
}

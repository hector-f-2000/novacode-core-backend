<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestTenantAppSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = DB::table('tenants')->where('slug', 'empresa-demo')->value('id');
        $appId = DB::table('apps')->where('slug', 'asist-go')->value('id');

        if (!$tenantId || !$appId) {
            $this->command->error('Ejecuta primero TestTenantSeeder y TestAppSeeder.');
            return;
        }

        DB::table('tenant_apps')->updateOrInsert(
            ['tenant_id' => $tenantId, 'app_id' => $appId],
            [
                'is_active' => true,
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('Vinculación tenant-app creada correctamente.');
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestPlanSeeder extends Seeder
{
    public function run(): void
    {
        $appId = DB::table('apps')->where('slug', 'asist-go')->value('id');

        if (!$appId) {
            $this->command->error('Ejecuta primero TestAppSeeder para crear la app "asist-go".');
            return;
        }

        DB::table('plans')->updateOrInsert(
            ['name' => 'Plan Básico', 'app_id' => $appId],
            [
                'app_id' => $appId,
                'description' => 'Plan básico para pequeñas empresas',
                'price' => 29990,
                'billing_period' => 'monthly',
                'max_users' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('Plan de prueba "Plan Básico" creado correctamente.');
    }
}

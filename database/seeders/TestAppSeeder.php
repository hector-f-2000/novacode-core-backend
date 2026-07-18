<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestAppSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('apps')->updateOrInsert(
            ['slug' => 'asist-go'],
            [
                'name' => 'Asist-GO',
                'description' => 'Sistema de control de asistencia laboral',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('App de prueba "Asist-GO" creada correctamente.');
    }
}

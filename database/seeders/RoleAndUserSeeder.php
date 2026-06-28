<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User\User;
use App\Models\User\UserProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class RoleAndUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Usamos una transacción para asegurar que ambos registros se creen correctamente
        DB::transaction(function () {
            
            // 1. Crear el usuario principal (Seguridad)
            $user = User::create([
                'username' => 'admin',
                'email'    => 'admin@novacode.cl',
                'password' => Hash::make('admin123'),
                'role_id'  => 1,
                'status'   => true, // Usuario activo por defecto
            ]);

            // 2. Crear el perfil asociado (Ficha personal)
            // Usamos la relación definida en el modelo para insertar los datos
            $user->profile()->create([
                'firstname' => 'User',
                'lastname'  => 'Admin',
                'phone'     => '+56912345678',
                'address'   => 'Luna',
                'avatar'    => null,
                'settings'  => [
                    'theme' => 'dark',
                    'language' => 'es'
                ],
            ]);
            
        });
    }
}
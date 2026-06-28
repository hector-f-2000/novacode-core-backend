<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // 1. Limpiar tablas en PostgreSQL
        // Deshabilitamos triggers para poder truncar tablas con relaciones
        DB::statement('TRUNCATE TABLE role_has_permissions RESTART IDENTITY CASCADE;');
        DB::statement('TRUNCATE TABLE roles RESTART IDENTITY CASCADE;');
        DB::statement('TRUNCATE TABLE permissions RESTART IDENTITY CASCADE;');

        $now = Carbon::now();

        // 2. Definir Permisos Estándar
        $permissions = [
            ['name' => 'user_view',     'guard_name' => 'api', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'user_create',   'guard_name' => 'api', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'user_edit',     'guard_name' => 'api', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'user_delete',   'guard_name' => 'api', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'role_view',     'guard_name' => 'api', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'role_manage',   'guard_name' => 'api', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'settings_view', 'guard_name' => 'api', 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('permissions')->insert($permissions);

        // 3. Definir Roles
        // En la sección de Roles de tu Seeder
        $roles = [
            [
                'name' => 'super_admin', 
                'display_name' => 'Súper Admin', 
                'guard_name' => 'api', 
                'created_at' => $now, 
                'updated_at' => $now
            ],
            [
                'name' => 'admin',       
                'display_name' => 'Administrador',       
                'guard_name' => 'api', 
                'created_at' => $now, 
                'updated_at' => $now
            ],
            [
                'name' => 'editor',      
                'display_name' => 'Editor', 
                'guard_name' => 'api', 
                'created_at' => $now, 
                'updated_at' => $now
            ],
            [
                'name' => 'viewer',      
                'display_name' => 'Solo Lectura',        
                'guard_name' => 'api', 
                'created_at' => $now, 
                'updated_at' => $now
            ],
        ];

        DB::table('roles')->insert($roles);

        // 4. Asignar Permisos a Roles
        $allPermissionIds = DB::table('permissions')->pluck('id')->toArray();
        $superAdminId = DB::table('roles')->where('name', 'super_admin')->value('id');
        $adminId      = DB::table('roles')->where('name', 'admin')->value('id');
        $viewerId     = DB::table('roles')->where('name', 'viewer')->value('id');

        // Relaciones (usando tus nombres: role_has_permissions y permission_id)
        
        // Super Admin: Todo
        foreach ($allPermissionIds as $pId) {
            DB::table('role_has_permissions')->insert([
                'permission_id' => $pId,
                'role_id'        => $superAdminId
            ]);
        }

        // Admin: Todo menos roles
        $adminPermissions = DB::table('permissions')
            ->where('name', 'not like', 'role_%')
            ->pluck('id');

        foreach ($adminPermissions as $pId) {
            DB::table('role_has_permissions')->insert([
                'permission_id' => $pId,
                'role_id'        => $adminId
            ]);
        }

        // Viewer: Solo lectura de usuarios
        $viewPermissionId = DB::table('permissions')->where('name', 'user_view')->value('id');
        if ($viewPermissionId) {
            DB::table('role_has_permissions')->insert([
                'permission_id' => $viewPermissionId,
                'role_id'        => $viewerId
            ]);
        }
    }
}
<?php

namespace App\Core\Tenants\Application\UseCases;

use App\Models\Tenant\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Exception;

class CreateTenantOwnerUseCase
{
    public function execute(int $tenantId, string $name, string $email): array
    {
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();

        if (!$tenant) {
            throw new Exception('El tenant especificado no existe.');
        }

        if ($tenant->status === 'cancelled') {
            throw new Exception('No se puede crear un owner para un tenant cancelado.');
        }

        $exists = TenantUser::where('email', $email)->exists();

        if ($exists) {
            throw new Exception('El correo ya está registrado como usuario de tenant.');
        }

        $plainPassword = Str::random(16);

        $tenantUser = TenantUser::create([
            'tenant_id' => $tenantId,
            'name'      => $name,
            'email'     => $email,
            'password'  => Hash::make($plainPassword),
            'role'      => 'tenant_owner',
            'status'    => true,
        ]);

        return [
            'id'             => $tenantUser->id,
            'name'           => $tenantUser->name,
            'email'          => $tenantUser->email,
            'role'           => $tenantUser->role,
            'tenant_id'      => $tenantUser->tenant_id,
            'tenant_slug'    => $tenant->slug,
            'tenant_razon_social' => $tenant->razon_social,
            'temp_password'  => $plainPassword,
        ];
    }
}

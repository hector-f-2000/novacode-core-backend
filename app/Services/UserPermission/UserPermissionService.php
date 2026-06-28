<?php

namespace App\Services\UserPermission;

use App\Models\User\User;
use App\Models\Permission\Permission;
use App\DTOs\UserPermission\SyncUserPermissionsDTO;
use Illuminate\Support\Facades\DB;

class UserPermissionService
{
    public function getUserPermissions(int $userId): array
    {
        $user = User::with('role.permissions')->findOrFail($userId);

        $allPermissions = Permission::orderBy('name')->get();
        $directPermIds = $user->getDirectPermissions()->pluck('id')->toArray();
        $rolePermIds = $user->role?->permissions->pluck('id')->toArray() ?? [];

        $permissions = $allPermissions->map(function ($perm) use ($directPermIds, $rolePermIds) {
            $inherited = in_array($perm->id, $rolePermIds);
            $direct    = in_array($perm->id, $directPermIds);
            return [
                'id'             => $perm->id,
                'name'           => $perm->name,
                'display_name'   => $perm->display_name,
                'guard_name'     => $perm->guard_name,
                'has_permission' => $inherited || $direct,
                'inherited'      => $inherited,
                'direct'         => $direct,
            ];
        });

        return [
            'user' => [
                'id'        => $user->id,
                'username'  => $user->username,
                'full_name' => $user->full_name,
                'role_name' => $user->role?->display_name ?? 'Sin Rol',
            ],
            'permissions' => $permissions,
        ];
    }

    public function syncPermissions(SyncUserPermissionsDTO $dto): User
    {
        $user = User::findOrFail($dto->userId);

        DB::beginTransaction();
        try {
            $user->permissions()->sync($dto->toArray());
            DB::commit();
            return $user->load('role');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

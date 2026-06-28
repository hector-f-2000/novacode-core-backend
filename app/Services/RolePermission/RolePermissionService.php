<?php

namespace App\Services\RolePermission;

use App\Models\Role\Role;
use App\DTOs\RolePermission\SyncRolePermissionsDTO;
use Illuminate\Database\Eloquent\Collection;

class RolePermissionService
{
    /**
     * Obtiene todos los roles junto con sus permisos asociados (Tabla Pivote).
     * Ideal para cargar la tercera pestaña de asignación con PrimeReact de forma eficiente.
     *
     * @return Collection
     */
    public function getRolesWithPermissions(): Collection
    {
        return Role::with('permissions:id,name')
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Sincroniza los permisos de un rol específico en la tabla pivote.
     * Reemplaza las asociaciones antiguas por las nuevas enviadas en el DTO.
     *
     * @param SyncRolePermissionsDTO $dto
     * @return Role
     */
    public function syncPermissions(SyncRolePermissionsDTO $dto): Role
    {
        // Buscamos el rol mediante Eloquent
        $role = Role::findOrFail($dto->roleId);

        // El método sync() de Laravel se encarga de insertar los nuevos registros,
        // mantener los que ya estaban y borrar los que no vengan en el arreglo.
        $role->permissions()->sync($dto->toArray());

        // Retornamos el rol refrescado con sus nuevos permisos cargados
        return $role->load('permissions:id,name');
    }
}
<?php

namespace App\Services\Permission;

use App\Models\Permission\Permission;
use App\DTOs\Permission\PermissionDTO;
use Illuminate\Database\Eloquent\Collection;

class PermissionService
{
    /**
     * Obtiene todos los permisos registrados en el sistema.
     *
     * @return Collection
     */
    public function getAll(): Collection
    {
        return Permission::orderBy('id', 'desc')->get();
    }

    /**
     * Obtiene un permiso específico por su ID.
     *
     * @param int $id
     * @return Permission
     */
    public function getById(int $id): Permission
    {
        return Permission::findOrFail($id);
    }

    /**
     * Crea un nuevo permiso en la base de datos a partir de su DTO.
     *
     * @param PermissionDTO $dto
     * @return Permission
     */
    public function create(PermissionDTO $dto): Permission
    {
        return Permission::create($dto->toArray());
    }

    /**
     * Actualiza un permiso existente a partir de su ID y el DTO con nuevos datos.
     *
     * @param int $id
     * @param PermissionDTO $dto
     * @return Permission
     */
    public function update(int $id, PermissionDTO $dto): Permission
    {
        $permission = $this->getById($id);
        $permission->update($dto->toArray());
        
        return $permission;
    }

    /**
     * Elimina un permiso del sistema.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $permission = $this->getById($id);
        return $permission->delete();
    }
}
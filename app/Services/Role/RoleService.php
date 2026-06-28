<?php

namespace App\Services\Role;

use App\Models\Role\Role;
use App\DTOs\Role\RoleDTO;
use Illuminate\Database\Eloquent\Collection;

class RoleService
{
    /**
     * Obtiene todos los roles registrados en el sistema.
     *
     * @return Collection
     */
    public function getAll(): Collection
    {
        return Role::orderBy('id', 'desc')->get();
    }

    /**
     * Obtiene un rol específico por su ID.
     *
     * @param int $id
     * @return Role
     */
    public function getById(int $id): Role
    {
        return Role::findOrFail($id);
    }

    /**
     * Crea un nuevo rol en la base de datos a partir de su DTO.
     *
     * @param RoleDTO $dto
     * @return Role
     */
    public function create(RoleDTO $dto): Role
    {
        return Role::create($dto->toArray());
    }

    /**
     * Actualiza un rol existente a partir de su ID y el DTO con nuevos datos.
     *
     * @param int $id
     * @param RoleDTO $dto
     * @return Role
     */
    public function update(int $id, RoleDTO $dto): Role
    {
        $role = $this->getById($id);
        $role->update($dto->toArray());
        
        return $role;
    }

    /**
     * Elimina un rol del sistema.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $role = $this->getById($id);
        return $role->delete();
    }
}
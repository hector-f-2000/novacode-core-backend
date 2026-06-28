<?php

namespace App\Http\Controllers\Api\Role;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\DTOs\Role\RoleDTO;
use App\Services\Role\RoleService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Exception;

class RoleController extends Controller
{
    /**
     * Inyección de dependencias del servicio de Roles.
     */
    public function __construct(
        protected RoleService $roleService
    ) {}

    /**
     * Listar todos los roles.
     * GET /api/roles
     */
    public function index(): JsonResponse
    {
        try {
            $roles = $this->roleService->getAll();
            
            return response()->json([
                'status' => true,
                'data'   => $roles
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error al obtener la lista de roles.',
                'error'   => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Crear un nuevo rol.
     * POST /api/roles
     */
    public function store(StoreRoleRequest $request): JsonResponse{
        try {
            $dto = RoleDTO::fromRequest($request->validated());
            $role = $this->roleService->create($dto);

            return response()->json([
                'status'  => true,
                'message' => 'Rol creado exitosamente.',
                'data'    => $role
            ], Response::HTTP_CREATED);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error al crear el rol.',
                'error'   => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Actualizar un rol existente.
     * PUT/PATCH /api/roles/{id}
     */
    public function update(StoreRoleRequest $request, int $id): JsonResponse
    {
        try {
            $dto = RoleDTO::fromRequest($request->validated());
            $role = $this->roleService->update($id, $dto);

            return response()->json([
                'status'  => true,
                'message' => 'Rol actualizado exitosamente.',
                'data'    => $role
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error al actualizar el rol.',
                'error'   => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Eliminar un rol.
     * DELETE /api/roles/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->roleService->delete($id);

            return response()->json([
                'status'  => true,
                'message' => 'Rol eliminado exitosamente.'
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error al eliminar el rol.',
                'error'   => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
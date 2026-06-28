<?php

namespace App\Http\Controllers\Api\Permission;

use App\Http\Controllers\Controller;
use App\Http\Requests\Permission\StorePermissionRequest;
use App\DTOs\Permission\PermissionDTO;
use App\Services\Permission\PermissionService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Exception;

class PermissionController extends Controller
{
    /**
     * Inyección de dependencias del servicio de Permisos.
     */
    public function __construct(
        protected PermissionService $permissionService
    ) {}

    /**
     * Listar todos los permisos.
     * GET /api/permissions
     */
    public function index(): JsonResponse
    {
        try {
            $permissions = $this->permissionService->getAll();
            return response()->json([
                'status' => true,
                'data'   => $permissions
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error al obtener la lista de permisos.',
                'error'   => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Crear un nuevo permiso.
     * POST /api/permissions
     */
    public function store(StorePermissionRequest $request): JsonResponse
    {
        try {
            $dto = PermissionDTO::fromRequest($request->validated());
            $permission = $this->permissionService->create($dto);

            return response()->json([
                'status'  => true,
                'message' => 'Permiso creado exitosamente.',
                'data'    => $permission
            ], Response::HTTP_CREATED);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error al crear el permiso.',
                'error'   => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Actualizar un permiso existente.
     * PUT/PATCH /api/permissions/{id}
     */
    public function update(StorePermissionRequest $request, int $id): JsonResponse
    {
        try {
            $dto = PermissionDTO::fromRequest($request->validated());
            $permission = $this->permissionService->update($id, $dto);

            return response()->json([
                'status'  => true,
                'message' => 'Permiso actualizado exitosamente.',
                'data'    => $permission
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error al actualizar el permiso.',
                'error'   => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Eliminar un permiso.
     * DELETE /api/permissions/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->permissionService->delete($id);

            return response()->json([
                'status'  => true,
                'message' => 'Permiso eliminado exitosamente.'
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error al eliminar el permiso.',
                'error'   => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
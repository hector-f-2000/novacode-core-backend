<?php

namespace App\Http\Controllers\Api\RolePermission;

use App\Http\Controllers\Controller;
use App\Http\Requests\RolePermission\SyncRolePermissionsRequest;
use App\DTOs\RolePermission\SyncRolePermissionsDTO;
use App\Services\RolePermission\RolePermissionService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Exception;

class RolePermissionController extends Controller
{
    /**
     * Inyección de dependencias del servicio de relaciones.
     */
    public function __construct(
        protected RolePermissionService $rolePermissionService
    ) {}

    /**
     * Listar los roles junto con sus permisos asociados.
     * GET /api/role-permissions
     */
    public function index(): JsonResponse
    {
        try {
            $rolePermissions = $this->rolePermissionService->getRolesWithPermissions();

            return response()->json([
                'status' => true,
                'data'   => $rolePermissions
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error al obtener la matriz de roles y permisos.',
                'error'   => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Sincronizar (guardar) los permisos asociados a un rol.
     * POST /api/role-permissions/sync
     */
    public function sync(SyncRolePermissionsRequest $request): JsonResponse
    {
        try {
            $dto = SyncRolePermissionsDTO::fromRequest($request->validated());
            $role = $this->rolePermissionService->syncPermissions($dto);

            return response()->json([
                'status'  => true,
                'message' => 'Permisos sincronizados correctamente con el rol.',
                'data'    => $role
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error al sincronizar los permisos.',
                'error'   => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
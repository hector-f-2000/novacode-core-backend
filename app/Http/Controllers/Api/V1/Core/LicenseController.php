<?php

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Core\Tenants\Application\UseCases\ValidateTenantLimitsUseCase;
use App\Core\Tenants\Infrastructure\DTOs\CheckLimitsDTO;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class LicenseController extends Controller
{
    public function __construct(
        private readonly ValidateTenantLimitsUseCase $validateLimitsUseCase
    ) {}

    public function checkLimits(Request $request): JsonResponse
    {
        try {
            $dto = CheckLimitsDTO::fromArray([
                'tenant_slug' => $request->header('X-Tenant-Slug'),
                'app_slug' => $request->header('X-App-Identifier'),
                'current_users_count' => $request->input('current_users_count', 0)
            ]);

            $result = $this->validateLimitsUseCase->execute($dto);

            return response()->json([
                'status' => true,
                'message' => 'Validación de cuotas procesada con éxito.',
                'data' => $result
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 400);
        }
    }
}

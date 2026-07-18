<?php

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Core\Tenants\Domain\Enums\TenantStatus;
use App\Core\Tenants\Domain\Enums\AppSubscriptionStatus;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    public function getStatuses(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Catálogo de estados del sistema recuperado con éxito.',
            'data' => [
                'tenant_statuses' => TenantStatus::getFormOptions(),
                'app_subscription_statuses' => AppSubscriptionStatus::getFormOptions()
            ]
        ], 200);
    }
}

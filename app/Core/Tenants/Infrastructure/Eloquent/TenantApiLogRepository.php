<?php

namespace App\Core\Tenants\Infrastructure\Eloquent;

use App\Core\Tenants\Infrastructure\DTOs\RegisterApiLogDTO;
use Illuminate\Support\Facades\DB;

class TenantApiLogRepository
{
    public function logExchange(RegisterApiLogDTO $dto): void
    {
        DB::table('tenant_api_logs')->insert([
            'tenant_id' => $dto->tenantId,
            'app_slug' => $dto->appSlug,
            'ip_address' => $dto->ipAddress,
            'user_agent' => $dto->userAgent,
            'endpoint' => $dto->endpoint,
            'is_success' => $dto->is_success,
            'fail_reason' => $dto->failReason,
            'created_at' => now(),
        ]);
    }
}

# INSTRUCCIONES DE DESARROLLO: REGISTRO DE AUDITORÍA Y LOGS DE API (CORE)

## 🎯 Objetivo

Implementar la tabla de persistencia, DTO y repositorio para capturar de manera asíncrona o directa los logs de peticiones inter-app (Handshakes) que procesa el Core, sirviendo como base para el monitoreo de seguridad.

---

## 🗄️ 1. Migración de Base de Datos

El agente debe generar y ejecutar la siguiente migración:
`php artisan make:migration create_tenant_api_logs_table`

### Código de la Migración:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->onDelete('set null');
            $table->string('app_slug')->nullable(); // Identificador de la app (ej: 'asist-go')
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('endpoint'); // Endpoint consultado (ej: 'api/v1/core/license/check-limits')
            $table->boolean('is_success')->default(true);
            $table->string('fail_reason')->nullable(); // Ej: 'TOKEN_INVALID', 'EXPIRED'
            $table->timestamp('created_at')->useCurrent(); // Registro rápido de solo lectura
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_api_logs');
    }
};
📁 2. Estructura de Código (Clean Architecture)
El agente debe crear los siguientes archivos dentro del módulo de Tenants/Auditoría:

Plaintext
app/Core/Tenants/
└── Infrastructure/
    ├── DTOs/
    │   └── RegisterApiLogDTO.php
    └── Eloquent/
        └── TenantApiLogRepository.php
Paso A: DTO de Registro de Log
Ruta: app/Core/Tenants/Infrastructure/DTOs/RegisterApiLogDTO.php

PHP
<?php

namespace App\Core\Tenants\Infrastructure\DTOs;

class RegisterApiLogDTO
{
    public ?int $tenantId;
    public ?string $appSlug;
    public ?string $ipAddress;
    public ?string $userAgent;
    public string $endpoint;
    public bool $is_success;
    public ?string $failReason;

    public function __construct(array $data)
    {
        $this->tenantId = $data['tenant_id'] ?? null;
        $this->appSlug = $data['app_slug'] ?? null;
        $this->ipAddress = $data['ip_address'] ?? null;
        $this->userAgent = $data['user_agent'] ?? null;
        $this->endpoint = $data['endpoint'];
        $this->is_success = (bool)$data['is_success'];
        $this->failReason = $data['fail_reason'] ?? null;
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
Paso B: Repositorio Concreto de Logs
Ruta: app/Core/Tenants/Infrastructure/Eloquent/TenantApiLogRepository.php

PHP
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
            'created_at' => now()
        ]);
    }
}
```

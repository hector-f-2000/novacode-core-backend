<?php

namespace App\Console\Commands\Tenants;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Core\Tenants\Domain\Enums\TenantStatus;

class ExpireSubscriptionsCommand extends Command
{
    protected $signature = 'app:expire-subscriptions';

    protected $description = 'Busca las suscripciones de aplicaciones de los Tenants que ya vencieron y cambia su estado a expired.';

    public function handle(): int
    {
        $this->info('Iniciando verificación perimetral de expiración de Tenants...');

        // 1. Estados de control basados en tu Enum de Dominio para el Tenant
        $statusActive = TenantStatus::ACTIVE->value;
        $statusTrial = 'trial'; // Estado por defecto en tu DDL nativo
        $statusCancelled = TenantStatus::CANCELLED->value; // Usado aquí como expirado/bloqueado

        // 2. Modificar directamente sobre la tabla tenants según el DDL real
        $affectedRows = DB::table('tenants')
            ->whereIn('status', [$statusActive, $statusTrial])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => $statusCancelled,
                'updated_at' => now()
            ]);

        if ($affectedRows > 0) {
            $this->warn("Se ha cambiado el estado a inactivo de {$affectedRows} empresas (Tenants) por expiración temporal.");
        } else {
            $this->info('No se encontraron cuentas de empresas vencidas en este ciclo.');
        }

        $this->info('Proceso de expiración finalizado.');
        return Command::SUCCESS;
    }
}

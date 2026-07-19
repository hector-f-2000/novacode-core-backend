# INSTRUCCIONES DE DESARROLLO: COMANDO DE EXPIRACIÓN AUTOMÁTICA DE SUSCRIPCIONES

## 🎯 Objetivo

Crear un comando de consola personalizado en Laravel (`Artisan Command`) que recorra diariamente la tabla `tenant_apps` para identificar suscripciones vencidas (`expires_at < now`) que sigan en estado `active` o `trial`, y cambiar su estado automáticamente a `expired` utilizando el Enum `AppSubscriptionStatus`.

---

## 🛠️ 1. Creación del Comando Artisan

El agente debe crear el siguiente archivo para la tarea programada:

- **Ruta:** `app/Console/Commands/ExpireSubscriptionsCommand.php`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Core\Tenants\Domain\Enums\AppSubscriptionStatus;

class ExpireSubscriptionsCommand extends Command
{
    /**
     * El nombre y firma del comando en consola.
     */
    protected $signature = 'app:expire-subscriptions';

    /**
     * La descripción del comando.
     */
    protected $description = 'Busca las suscripciones de aplicaciones de los Tenants que ya vencieron y cambia su estado a expired.';

    /**
     * Ejecuta el comando lógico.
     */
    public function handle(): int
    {
        $this->info('Iniciando el proceso de verificación de expiración de licencias...');

        // 1. Capturar estados válidos desde el Enum
        $statusActive = AppSubscriptionStatus::ACTIVE->value;
        $statusTrial = AppSubscriptionStatus::TRIAL->value;
        $statusExpired = AppSubscriptionStatus::EXPIRED->value;

        // 2. Buscar y actualizar las filas cuya fecha de expiración sea menor o igual a este instante
        $affectedRows = DB::table('tenant_apps')
            ->whereIn('status', [$statusActive, $statusTrial])
            ->where('expires_at', '<=', now())
            ->update([
                'status' => $statusExpired,
                'updated_at' => now()
            ]);

        if ($affectedRows > 0) {
            $this->warn("Se han expirado de forma automática {$affectedRows} licencias de clientes.");
        } else {
            $this->info('No se encontraron suscripciones vencidas en este ciclo.');
        }

        $this->info('Proceso de expiración finalizado con éxito.');
        return Command::SUCCESS;
    }
}
📅 2. Registro en el Scheduler de Laravel
Dependiendo de la versión exacta de tu instalación del Core, el agente debe registrar este comando para que corra diariamente. En las versiones más recientes de Laravel (11, 12, 13), esto se gestiona en el archivo de configuración de la consola.

Ruta: routes/console.php

El agente debe añadir la programación del comando para que se ejecute todas las noches:

PHP
<?php

use Illuminate\Support\Facades\Schedule;

// Registrar el comando para ejecutarse automáticamente todos los días a la medianoche
Schedule::command('app:expire-subscriptions')->daily();
```

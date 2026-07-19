# NovaCode Labs - Ficha de Parametrización y Reglas de Oro de IA

## Proyecto: Core Master - Panel de Control Centralizado

## 🗺️ 1. Visión General del Ecosistema

Este proyecto es el **Core Master (Cerebro Central)** de NovaCode Labs. Su propósito es actuar como el software de administración global (SaaS) que gobierna el negocio. Está completamente desacoplado en dos repositorios (Backend API y Frontend SPA).

### Componentes del Ecosistema:

1. **Core Master Backend (Este Repositorio):** API RESTful pura e inmutable construida en Laravel 13. Es el juez supremo de licencias, planes, control DTE y handshake de seguridad. No renderiza vistas (Blade/Inertia), solo procesa y retorna JSON estrictos.
2. **Core Master Frontend:** SPA construida en React + Vite + PrimeReact (Tema Dark/Cian Neón) de uso exclusivo para los administradores de NovaCode Labs (Héctor y Antonio).
3. **Aplicaciones Satélites (Clientes):** Proyectos independientes (como el backend de cada cliente o la App móvil _asist-GO_ en Flutter) que consumen y dependen de las validaciones de este Core.

---

## 🏗️ 2. Reglas de Oro de Arquitectura (Clean Architecture - Senior Standard)

Para los módulos centrales del negocio (como `Tenants`, `Apps`, `Plans`), el agente **NO** debe usar la arquitectura por capas tradicional (`app/DTOs`, `app/Services`). DEBE respetar estrictamente el patrón de **Clean Architecture** organizado por contextos, aplicando tipado fuerte y características modernas de **PHP 8.3**:

### Flujo Estricto de Datos:

👉 `FormRequest (Validación)` ➔ `DTO (Sanitización)` ➔ `Use Case (Lógica Pura)` ➔ `Repository Interface (Dominio)` ➔ `Eloquent Repository (Infraestructura)` ➔ `Base de Datos`

### Ubicación de Carpetas por Módulo (Mapeo PSR-4):

Toda la lógica de los módulos maestros vive dentro de `app/Core/{Modulo}/`. Ejemplo para el módulo de Clientes:

- `app/Core/Tenants/Infrastructure/DTOs/`: Objetos inmutables (`readonly classes`) que capturan, tipan y sanitizan los datos de entrada usando promoción de propiedades en constructor.
- `app/Core/Tenants/Domain/Contracts/`: Interfaces puras de repositorios (Contratos).
- `app/Core/Tenants/Infrastructure/Eloquent/`: Implementación concreta de los repositorios usando DB o Eloquent.
- `app/Core/Tenants/Application/UseCases/`: Lógica de negocio pura y agnóstica de la infraestructura.

---

## 🗄️ 3. Estructura de Base de Datos y Reglas del Modelo

El agente debe ceñirse al mapa relacional de 5 tablas clave del Core:

1. **`users`:** Solo para administradores internos de NovaCode Labs (Héctor y Antonio). Campos de seguridad y autenticación. Incluye `softDeletes()`. Toda la información biográfica extendida reside en `user_profiles` (Relación 1:1).
2. **`apps`:** Catálogo de productos de software comercializables (ID 1: _asist-GO_).
3. **`plans`:** Niveles de precios amarrados a una App. Controla el límite crítico de usuarios operativos mediante el campo `max_users`.
4. **`tenants`:** Empresas clientes legalmente constituidas. Contiene datos DTE (RUT, Razón Social, Giro, Dirección) y las credenciales globales de Handshake Inter-App (`api_key_client` y `api_secret_token`).
5. **`tenant_apps`:** Matriz pivot. Controla qué aplicaciones específicas tiene permitidas cada empresa y su estado de activación individual (`is_active`).

### Reglas Críticas de Base de Datos:

- **Transacciones:** Toda operación de escritura que afecte a múltiples tablas o modelos simultáneamente (ej: `User` + `UserProfile` o `Tenant` + `TenantApp`) DEBE envolverse estrictamente en bloques `DB::transaction()` o `DB::beginTransaction() / DB::commit()`.
- **Integridad:** El borrado de registros maestros debe usar `softDeletes()` de forma obligatoria para preservar históricos comerciales y auditoría (`created_by`, `updated_by`).
- **Limpieza de RUTs:** Al persistir RUTs chilenos en la base de datos, deben limpiarse de puntos y guiones, guardándose en mayúsculas y formato puro (ej: `76123456K`).

---

## 🔐 4. Protocolo de Seguridad e Integración (Handshake Inter-App)

El Core es el encargado de validar la vigencia de las licencias de las aplicaciones externas.

- Cada petición de validación que reciba el Core debe evaluar las cabeceras `X-Tenant-Identifier` (Slug) y `X-App-Token` (`api_secret_token`).
- La generación de credenciales se realiza exclusivamente en el Caso de Uso de creación usando:
    - `api_key_client`: Prefijo `nc_pub_` seguido de un string aleatorio seguro de 32 caracteres (`Str::random(32)`).
    - `api_secret_token`: Hash criptográfico HMAC-SHA256 firmado con la clave de la aplicación (`config('app.key')`).

---

## 🛠️ 5. Configuración del Entorno y Estándares de Código

- **Stack Técnico:** PHP 8.3.31 / Laravel 13 Backend API / Autenticación Sanctum (Stateless).
- **Zona Horaria:** Chile (`America/Santiago`) obligatoria. Formato de fecha para UI: `d/m/Y H:i`.
- **Locale:** `es_CL`.
- **Estructura de Respuestas HTTP:** Toda respuesta de la API debe ser homogénea, estandarizada y retornar un JSON con la estructura de respuesta unificada:
    ```json
    {
        "status": true,
        "message": "Mensaje claro de la operación",
        "data": {}
    }
    ```

## 🌐 Estándar de Enrutamiento API (Laravel 13)

El agente debe estructurar las rutas en `routes/api.php` utilizando estrictamente **Grupos de Rutas Versionados** y legibles. Queda PROHIBIDO declarar rutas sueltas en la raíz del archivo o mezclar namespaces.

### Reglas de Oro para Rutas:

1. **Inyección del Namespace:** Importar siempre el Controlador al inicio del archivo mediante `use` para mantener las rutas limpias.
2. **Versionamiento Obligatorio:** Todas las rutas del Core deben envolverse en un grupo con el prefijo `v1`.
3. **Agrupación por Contexto:** Cada módulo de Clean Architecture (`Tenants`, `Apps`, `Plans`) debe tener su propio subgrupo con prefijo, facilitando la lectura y la posterior asignación de Middlewares de protección (como `auth:sanctum`).

### Estructura Estricta de Referencia en `routes/api.php`:

```php
use App\Http\Controllers\Core\Tenants\TenantController;
use Illuminate\Support\Facades\Route;

// Ecosistema Global Core Master - NovaCode Labs
Route::prefix('v1')->group(function () {

    // 🏢 Submódulo: Control de Clientes (Tenants)
    Route::prefix('core/tenants')->group(function () {
        Route::post('/', [TenantController::class, 'store']); // POST /api/v1/core/tenants
    });

    // 📋 Submódulo: Catálogo de Aplicaciones (Roadmap)
    // Route::prefix('core/apps')->group(function () { ... });

});
```

## 🛑 Consultas Críticas de Infraestructura Inmutables

El método `verifyCredentials` dentro de `TenantEloquentRepository` es el corazón de la seguridad Inter-App del Core. El agente TIENE PROHIBIDO alterar su estructura de JOINs o simplificar la query. Debe mantenerse estrictamente mapeada a PostgreSQL de la siguiente forma:

````php
public function verifyCredentials(string $slug, string $secretToken, string $appSlug): ?array
{
    return \Illuminate\Support\Facades\DB::table('tenants')
        ->join('tenant_apps', 'tenants.id', '=', 'tenant_apps.tenant_id')
        ->join('apps', 'apps.id', '=', 'tenant_apps.app_id')
        ->where('tenants.slug', $slug)
        ->where('tenants.api_secret_token', $secretToken)
        ->where('apps.slug', $appSlug)
        ->select(
            'tenants.id as tenant_id',
            'tenants.razon_social',
            'tenants.status as tenant_status',
            'tenants.expires_at',
            'tenant_apps.is_active as app_active'
        )
        ->first() ? (array)$result : null; // Garantiza consistencia de tipos en PHP 8.3
}
```***
````

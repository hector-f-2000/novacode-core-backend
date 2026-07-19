# 📋 PLAN DE IMPLEMENTACIÓN - MÓDULO DE SEGURIDAD Y SESIONES

**Proyecto:** NovaCode Labs - Plataforma Centralizada  
**Módulo:** Auditoría de Conexiones Activas + Invalidación de Tokens + Historial de Eventos  
**Fecha de Inicio:** 2026-06-06  
**Estado:** 🔵 En Planificación

---

## 📋 ESPECIFICACIONES TÉCNICAS CONFIRMADAS

### Requisitos Funcionales

1. ✅ **Auditoría de Conexiones Activas:** Mostrar dispositivos conectados (SO/Navegador, IP, ubicación, última actividad)
2. ✅ **Invalidación Forzada de Tokens:** Revocar sesión individual o cerrar todas las demás sesiones
3. ✅ **Historial de Auditoría:** Log inmutable de: logins exitosos, logins fallidos, cierres forzados
4. ✅ **Permisos:**
    - Administradores: ven todas las sesiones de todos los usuarios
    - Usuarios normales: ven solo sus propias sesiones
5. ✅ **Notificaciones:** Toast en pantalla (Email + Jobs de colas → Roadmap futuro)

### Tecnologías Confirmadas

- **Geolocalización:** Servicio gratuito (geoip-lite o similar)
- **User-Agent Parser:** ✅ Ya instalado `jenssegers/agent`
- **Database:** PostgreSQL
- **ORM:** Laravel Eloquent
- **Autenticación:** Laravel Sanctum

---

# 🔧 FASE 1: BACKEND (Laravel 13)

## ETAPA 1.1: Extensión de Base de Datos

### ✅ TAREA 1.1.1 - Ampliar Migración de personal_access_tokens

**Archivo:** `database/migrations/2024_05_20_000000_add_metadata_to_personal_access_tokens_table.php`

**Estado:** ⚠️ PARCIAL (ya tiene ip_address, user_agent)

**Campos a AGREGAR:**

```sql
- device_name: string (e.g., "Windows 11 - Chrome")
- location: string (e.g., "Concepción, CL")
- is_revoked: boolean (default: false)
```

**Cambios en migración:**

- Actualizar método `up()` para agregar estos 3 campos faltantes
- Actualizar método `down()` para dropear estos campos

---

### ✅ TAREA 1.1.2 - Crear Tabla security_audit_logs

**Archivo:** `database/migrations/XXXX_XX_XX_create_security_audit_logs_table.php` (nueva)

**Estructura:**

```php
Schema::create('security_audit_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
    $table->enum('event_type', ['login_success', 'login_failed', 'session_revoked', 'sessions_revoked_all']);
    $table->string('ip_address', 45)->nullable();
    $table->text('user_agent')->nullable();
    $table->string('device_name')->nullable(); // "Windows 11 - Chrome"
    $table->string('location')->nullable(); // "Concepción, CL"
    $table->integer('attempt_count')->default(0); // Para login_failed
    $table->text('description')->nullable();
    $table->timestamps();

    $table->index('user_id');
    $table->index('event_type');
    $table->index('created_at');
});
```

---

## ETAPA 1.2: Modelos

### ✅ TAREA 1.2.1 - Crear Modelo SecurityAuditLog

**Ubicación:** `app/Models/Security/SecurityAuditLog.php` (nueva carpeta)

**Responsabilidades:**

- Relación `belongsTo(User::class)`
- Cast `event_type` como enum o string validado
- Scope `forUser($userId)` - filtrar por usuario
- Scope `byEventType($type)` - filtrar por tipo evento
- Scope `recent($days = 30)` - eventos recientes

**Estructura:**

```php
namespace App\Models\Security;

class SecurityAuditLog extends Model
{
    protected $table = 'security_audit_logs';
    protected $fillable = [
        'user_id', 'event_type', 'ip_address', 'user_agent',
        'device_name', 'location', 'attempt_count', 'description'
    ];

    // Relaciones, scopes, casts...
}
```

---

### ✅ TAREA 1.2.2 - Extender Modelo User (relación a sesiones)

**Ubicación:** `app/Models/User/User.php` (ya existe)

**Agregar:**

- Relación hasMany a `PersonalAccessToken` (Sanctum)
- Método helper `getActiveSessions()` que retorna tokens no revocados
- Relación hasMany a `SecurityAuditLog`

---

## ETAPA 1.3: Utilities (Parsers)

### ✅ TAREA 1.3.1 - Crear DeviceParser

**Ubicación:** `app/Utilities/DeviceParser.php` (nueva)

**Responsabilidades:**

- Usar `jenssegers/agent` para parsear User-Agent
- Retornar estructura:

```php
[
    'os' => 'Windows 11',
    'browser' => 'Chrome 125',
    'device_name' => 'Windows 11 - Chrome', // legible para UI
    'device_type' => 'desktop|mobile|tablet'
]
```

**Método principal:**

```php
public static function parse($userAgent): array
```

---

### ✅ TAREA 1.3.2 - Crear GeoIPParser

**Ubicación:** `app/Utilities/GeoIPParser.php` (nueva)

**Responsabilidades:**

- Usar servicio gratuito (geoip-lite, MaxMind free tier, o similar)
- Implementar caché en Redis/File para IPs ya consultadas
- Retornar estructura:

```php
[
    'city' => 'Concepción',
    'country_code' => 'CL',
    'country' => 'Chile',
    'latitude' => -36.8201,
    'longitude' => -73.0445
]
```

**Método principal:**

```php
public static function getLocation($ipAddress): array
```

---

## ETAPA 1.4: Data Transfer Objects (DTOs)

### ✅ TAREA 1.4.1 - Crear SessionDTO

**Ubicación:** `app/DTOs/Security/SessionDTO.php` (nueva)

**Estructura:**

```php
class SessionDTO
{
    public function __construct(
        public int $token_id,
        public string $device_name,
        public string $ip_address,
        public string $location,
        public string $last_used_at,
        public bool $is_current = false
    ) {}
}
```

---

### ✅ TAREA 1.4.2 - Crear AuditLogDTO

**Ubicación:** `app/DTOs/Security/AuditLogDTO.php` (nueva)

**Estructura:**

```php
class AuditLogDTO
{
    public function __construct(
        public int $user_id,
        public string $event_type,
        public string $ip_address,
        public string $user_agent,
        public string $device_name,
        public string $location,
        public int $attempt_count = 0,
        public ?string $description = null
    ) {}
}
```

---

## ETAPA 1.5: Servicios de Lógica de Negocio

### ✅ TAREA 1.5.1 - Crear SessionService

**Ubicación:** `app/Services/Security/SessionService.php` (nueva carpeta)

**Responsabilidades:**

#### Método 1: `recordLoginSuccess(User $user, string $ip, string $userAgent): string`

- Parsear User-Agent con DeviceParser
- Obtener ubicación con GeoIPParser
- Crear token en `personal_access_tokens` con todos los metadatos
- Registrar evento en `security_audit_logs` (type='login_success')
- Retornar el token generado

#### Método 2: `recordLoginFailed(string $email, string $ip, string $userAgent, int $attemptCount): void`

- Parsear dispositivo y ubicación
- Registrar evento en `security_audit_logs` (type='login_failed')
- Incrementar contador de intentos para bloqueos futuros
- Lógica simple: solo registrar (bloqueo temporal → roadmap)

#### Método 3: `getUserActiveSessions(int $userId): Collection`

- Retorna array de `SessionDTO` de sesiones activas (is_revoked=false)
- Incluye información de identificar cuál es la sesión actual
- Ordenado por `last_used_at` DESC

#### Método 4: `revokeToken(int $tokenId, int $userId): bool`

- Validar que el token pertenece al usuario o user es admin
- Marcar token como `is_revoked=true`
- Registrar evento en `security_audit_logs` (type='session_revoked')
- Retornar true/false

#### Método 5: `revokeAllOtherSessions(int $userId, int $currentTokenId): int`

- Revocar TODOS los tokens del usuario EXCEPTO `$currentTokenId`
- Registrar UN SOLO evento (type='sessions_revoked_all') con count de revocadas
- Retornar cantidad de sesiones revocadas

#### Método 6: `getAuditLogs(?int $userId = null, int $limit = 50, int $offset = 0): array`

- Si `$userId` null → retorna todo (para admins)
- Si `$userId` set → retorna solo ese usuario
- Paginación con limit/offset
- Ordenado por `created_at` DESC
- Retorna array de `AuditLogDTO`

---

### ✅ TAREA 1.5.2 - Crear SecurityService (auxiliar)

**Ubicación:** `app/Services/Security/SecurityService.php` (nueva)

**Responsabilidades (si es necesario):**

- Métodos estáticos para validaciones de seguridad
- Métodos auxiliares que no pertenecen a SessionService
- (Por ahora puede estar vacío y crearse solo si se necesita)

---

## ETAPA 1.6: Form Requests (Validaciones)

### ✅ TAREA 1.6.1 - Crear FormRequests para Security

**Ubicación:** `app/Http/Requests/Security/` (nueva carpeta)

**Si es necesario:**

- `GetSessionsRequest.php` - validar query params (limit, offset)
- `RevokeSessionRequest.php` - validar tokenId
- Pueden dejarse vacíos si no hay lógica de validación específica

---

## ETAPA 1.7: Controladores

### ✅ TAREA 1.7.1 - Crear SecurityController

**Ubicación:** `app/Http/Controllers/Api/Security/SecurityController.php` (nueva carpeta)

**Endpoints (RESTful):**

#### 1️⃣ `GET /api/security/sessions` → `index()`

- Auth required
- Si user es admin → retorna sesiones de TODOS los usuarios
- Si user normal → retorna solo sus sesiones
- Query params: `user_id` (si admin), `limit`, `offset`
- Respuesta: array de `SessionDTO` enriquecido con marcador `is_current`

#### 2️⃣ `DELETE /api/security/sessions/{tokenId}` → `destroy($tokenId)`

- Auth required
- Validar que user sea admin O sea dueño del token
- Llamar `SessionService::revokeToken()`
- Respuesta: `{status: true, message: "Sesión revocada", data: {}}`
- Error 403 si no tiene permiso
- Error 404 si token no existe

#### 3️⃣ `POST /api/security/sessions/revoke-all-others` → `revokeAllOtherSessions()`

- Auth required
- Obtener token actual del request (via Guard)
- Llamar `SessionService::revokeAllOtherSessions(auth()->id(), current_token_id)`
- Respuesta: `{status: true, message: "...", data: {revoked_count: N}}`

#### 4️⃣ `GET /api/security/audit-logs` → `getAuditLogs()`

- Auth required
- Si admin → retorna todos los logs (con filtro opcional por user_id)
- Si user normal → retorna solo sus logs
- Query params: `user_id` (si admin), `event_type`, `limit`, `offset`, `start_date`, `end_date`
- Respuesta: paginated array de `AuditLogDTO`

---

## ETAPA 1.8: Rutas API

### ✅ TAREA 1.8.1 - Registrar Rutas

**Ubicación:** `routes/api.php`

**Agregar dentro del grupo autenticado:**

```php
Route::middleware('auth:sanctum')->group(function () {
    // Rutas de Security
    Route::prefix('security')->group(function () {
        Route::get('/sessions', [SecurityController::class, 'index']);
        Route::delete('/sessions/{tokenId}', [SecurityController::class, 'destroy']);
        Route::post('/sessions/revoke-all-others', [SecurityController::class, 'revokeAllOtherSessions']);
        Route::get('/audit-logs', [SecurityController::class, 'getAuditLogs']);
    });
});
```

---

## ETAPA 1.9: Integración con Autenticación Existente

### ✅ TAREA 1.9.1 - Modificar AuthController

**Ubicación:** `app/Http/Controllers/Api/Auth/AuthController.php` (ya existe)

**En método `login()`:**

- Después de validar credenciales y status del user
- Capturar: `$ip = $request->ip()`
- Capturar: `$userAgent = $request->header('User-Agent')`
- Llamar: `$sessionService->recordLoginSuccess($user, $ip, $userAgent)`
- Retornar el token en la respuesta junto con los datos del usuario

**En método `login()` - si login falla:**

- Capturar email del intento
- Llamar: `$sessionService->recordLoginFailed($email, $ip, $userAgent, $attemptCount)`

---

### ✅ TAREA 1.9.2 - Middleware de Validación de Token Revocado

**Ubicación:** `app/Http/Middleware/ValidateNotRevokedToken.php` (nueva)

**Responsabilidades:**

- En cada request autenticado, validar que el token NO tenga `is_revoked=true`
- Si token está revocado → lanzar `TokenRevokedException` con respuesta `401 Unauthorized`
- Registrar en auditoría (optional) que token fue usado post-revocación

**Registrar en `app/Http/Kernel.php`:**

```php
protected $routeMiddleware = [
    // ...
    'validate.token' => \App\Http\Middleware\ValidateNotRevokedToken::class,
];
```

**Aplicar a rutas autenticadas en `routes/api.php`:**

```php
Route::middleware(['auth:sanctum', 'validate.token'])->group(function () {
    // rutas aquí
});
```

---

## ETAPA 1.10: Seeders / Fixtures (Testing)

### ✅ TAREA 1.10.1 - Crear Seeder para SecurityAuditLogs

**Ubicación:** `database/seeders/SecurityAuditLogsSeeder.php` (opcional)

- Crear registros de auditoría simulados para testing
- Diferentes tipos de eventos
- Múltiples usuarios y IPs

---

# 🎨 FASE 2: FRONTEND (React + Vite)

## ETAPA 2.1: Servicio API

### ✅ TAREA 2.1.1 - Crear SecurityService

**Ubicación:** `src/services/SecurityService.js` (nueva)

**Métodos:**

```javascript
- getSessions(userId = null)
  → GET /api/security/sessions

- revokeSession(tokenId)
  → DELETE /api/security/sessions/{tokenId}

- revokeAllOtherSessions()
  → POST /api/security/sessions/revoke-all-others

- getAuditLogs(params)
  → GET /api/security/audit-logs?...
```

---

## ETAPA 2.2: Hook Personalizado

### ✅ TAREA 2.2.1 - Crear useSecuritySessions Hook

**Ubicación:** `src/hooks/useSecuritySessions.js` (nueva)

**State:**

- `sessions` - array de sesiones activas
- `auditLogs` - array de eventos de auditoría
- `loading` - boolean
- `error` - error message
- `pagination` - {limit, offset, total}

**Methods:**

- `fetchSessions(userId = null)`
- `fetchAuditLogs(params)`
- `revokeSession(tokenId)`
- `revokeAllOtherSessions()`
- `markCurrentSession()` - identifica sesión actual

---

## ETAPA 2.3: Componentes

### ✅ TAREA 2.3.1 - Crear SecuritySessionsIndex

**Ubicación:** `src/pages/Security/Sessions/SecuritySessionsIndex.jsx` (nueva carpeta)

**Estructura:**

- Dentro de `<PageContainer />`
- TabView con 2 pestañas: "Sesiones Activas" | "Historial de Auditoría"

**TAB 1: Sesiones Activas**

- DataTable (PrimeReact) con columnas:
    - Device Name (e.g., "Windows 11 - Chrome")
    - IP Address
    - Location
    - Última Actividad (formato 24h es_CL: "25/03/2026 14:30")
    - Acción: Botón con ícono papelera (revoke individual)
- Badge "Sesión Actual" en la fila correspondiente (color cian)
- Botón global superior: "Cerrar Todas las Demás Sesiones"
- Si user es admin: dropdown/selector para filtrar por usuario
- Si user normal: solo ve sus sesiones

**TAB 2: Historial / Auditoría**

- DataTable con columnas:
    - Fecha/Hora (24h es_CL: "25/03/2026 14:30")
    - Tipo de Evento (badge: login_success=green, login_failed=red, session_revoked=orange, etc.)
    - Device Name
    - IP Address
    - Location
    - Descripción
- Filtro por tipo de evento (dropdown)
- Paginación (limit, offset)
- Si user admin: filtro por usuario

---

### ✅ TAREA 2.3.2 - Integración en Menú/Sidebar

**Ubicación:** `src/components/layout/Sidebar.jsx` (ya existe - MODIFICAR)

**Agregar:**

- Enlace a "Seguridad > Sesiones Activas"
- Visible para: Admins SIEMPRE + Usuarios en su perfil personal (opcional)

---

## ETAPA 2.4: Estilos y UX

### ✅ TAREA 2.4.1 - CSS y Variables NovaCode

- Variables: `--nc-cyan-neon`, `--nc-gray-metallic`, `--nc-black`
- Botones: `border-radius: 0px` (recto puro)
- Inputs flotantes: PrimeReact `<FloatLabel>`
- DataTable: tema dark, bordes sutiles
- Badge "Sesión Actual": fondo cian, texto oscuro

### ✅ TAREA 2.4.2 - Notificaciones (Toast)

- Al revocar sesión: Toast verde "Sesión revocada"
- Al revocar todas: Toast verde "N sesiones cerradas"
- Errores: Toast rojo con descripción
- Posición: `top-center`
- Duración: 3s

---

## ETAPA 2.5: Testing

### ✅ TAREA 2.5.1 - Test E2E Básico

Casos a validar:

- [ ] Admin ve sesiones de todos los usuarios
- [ ] Usuario normal solo ve sus sesiones
- [ ] Revocar sesión individual → cierra esa sesión (401 en siguiente request)
- [ ] "Cerrar todas las demás" mantiene sesión actual activa
- [ ] Historial registra eventos correctamente
- [ ] Toast notifica acciones

---

# 📊 RESUMEN DE ARCHIVOS A CREAR/MODIFICAR

## Backend (Laravel)

**CREAR:**

- `database/migrations/XXXX_XX_XX_create_security_audit_logs_table.php`
- `app/Models/Security/SecurityAuditLog.php`
- `app/Utilities/DeviceParser.php`
- `app/Utilities/GeoIPParser.php`
- `app/DTOs/Security/SessionDTO.php`
- `app/DTOs/Security/AuditLogDTO.php`
- `app/Services/Security/SessionService.php`
- `app/Services/Security/SecurityService.php`
- `app/Http/Requests/Security/` (si es necesario)
- `app/Http/Controllers/Api/Security/SecurityController.php`
- `app/Http/Middleware/ValidateNotRevokedToken.php`
- `database/seeders/SecurityAuditLogsSeeder.php` (optional)

**MODIFICAR:**

- `database/migrations/2024_05_20_000000_add_metadata_to_personal_access_tokens_table.php` (agregar campos faltantes)
- `app/Models/User/User.php` (agregar relaciones)
- `app/Http/Controllers/Api/Auth/AuthController.php` (integrar recordLoginSuccess/Failed)
- `routes/api.php` (registrar rutas de Security)
- `app/Http/Kernel.php` (registrar middleware)

---

## Frontend (React)

**CREAR:**

- `src/services/SecurityService.js`
- `src/hooks/useSecuritySessions.js`
- `src/pages/Security/Sessions/SecuritySessionsIndex.jsx`
- `src/pages/Security/Sessions/components/` (si componentes son necesarios)

**MODIFICAR:**

- `src/components/layout/Sidebar.jsx` (agregar enlace)
- Rutas en `AppRoutes.jsx` o similar (si es necesario)

---

# 🎯 CHECKLIST DE ETAPAS

## ✅ FASE 1: BACKEND

- [ ] **ETAPA 1.1:** Extensión de DB (personal_access_tokens + security_audit_logs)
- [ ] **ETAPA 1.2:** Modelos (SecurityAuditLog + extensión User)
- [ ] **ETAPA 1.3:** Utilities (DeviceParser + GeoIPParser)
- [ ] **ETAPA 1.4:** DTOs (SessionDTO + AuditLogDTO)
- [ ] **ETAPA 1.5:** Services (SessionService + SecurityService)
- [ ] **ETAPA 1.6:** Form Requests (si es necesario)
- [ ] **ETAPA 1.7:** Controlador (SecurityController)
- [ ] **ETAPA 1.8:** Rutas API
- [ ] **ETAPA 1.9:** Integración con Auth (AuthController + Middleware)
- [ ] **ETAPA 1.10:** Seeders/Testing

## ✅ FASE 2: FRONTEND

- [ ] **ETAPA 2.1:** Servicio API (SecurityService)
- [ ] **ETAPA 2.2:** Hook (useSecuritySessions)
- [ ] **ETAPA 2.3:** Componentes (SecuritySessionsIndex + integración)
- [ ] **ETAPA 2.4:** Estilos y UX
- [ ] **ETAPA 2.5:** Testing E2E

---

**Última Actualización:** 2026-06-06  
**Próximo Paso:** Iniciar ETAPA 1.1 del Backend

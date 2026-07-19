# DISEÑO FINAL: Bloqueo de Cuenta (Lockout) + 2FA por Email

**Proyecto:** Core NovaCode Labs
**Estado:** Implementado y verificado (tareas 2.1 a 2.5)
**Documentos relacionados:** `14_plan_lockout_2fa.md`, `16_pendientes_generales.md`

---

## 1. Lockout de Cuenta

### 1.1 Componente
`app/Services/Security/LockoutService.php`

### 1.2 Contadores duales

| Contador | Scope | Backoff | Cache key |
|----------|-------|---------|-----------|
| **Usuario** | Protege la cuenta específica | 0,0,0,0, 3min, 5min, 15min, 30min (techo) | `lockout:user:{md5(email)}` |
| **IP** | Protege contra ataques multi-cuenta | 10 intentos libres, luego 1min, 5min, 15min, 30min (techo) | `lockout:ip:{ip}` |

### 1.3 Estado
Vive en **caché** (driver `database` actual, compatible con Redis/File), no en columnas de `users`/`tenant_users`.

Cada entrada guarda:
```php
['count' => int, 'created_at' => timestamp]
```
TTL de 24h para autolimpieza.

### 1.4 Flujo
1. Antes de verificar credenciales: `LockoutService::check()` → si bloqueado, rechazar.
2. Credenciales incorrectas: `LockoutService::increment()` → incrementa contadores, si supera umbral dispatch `AccountLocked` event.
3. Credenciales correctas: `LockoutService::reset()` → limpia contadores de usuario e IP.
4. Reset ocurre **antes** de bifurcar a 2FA (línea 45 LoginUseCase, línea 52 TenantLoginUseCase).

### 1.5 Mensajes de error
- Usuario bloqueado: `"Demasiados intentos fallidos. Intenta de nuevo en {N} minutos."` — no revela si la cuenta existe.
- Credenciales incorrectas sin bloqueo: `"Las credenciales proporcionadas son incorrectas."`

### 1.6 Evento
`AccountLocked` dispatchado al superar umbral. Listener es no-op hasta que se configure mailer real.

---

## 2. 2FA por Email (OTP de 6 dígitos)

### 2.1 Componentes
- `app/Services/Auth/TwoFactorService.php` — lógica core
- `app/Http/Controllers/Api/Auth/OtpController.php` — endpoints
- `app/Jobs/SendOtpEmail.php` — envío de email (sync temporal)
- `app/Mail/Auth/OtpMail.php` — plantilla del correo
- `app/Models/Auth/UserOtp.php` — modelo de datos

### 2.2 Tabla `user_otps`

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | bigint PK | Auto-increment |
| `user_type` | string | `'staff'` o `'tenant'` |
| `user_id` | bigint | FK polimórfica |
| `email` | string | Destino del OTP |
| `code_hash` | string | bcrypt del código de 6 dígitos |
| `session_token` | string(64) | Token único, unique index, hex aleatorio |
| `code_expires_at` | datetime | TTL del código (5 minutos) |
| `verified_at` | datetime null | Cuándo se usó correctamente |
| `resend_allowed_at` | datetime | Cooldown para reenvío (+60s desde creación) |
| `attempts_remaining` | smallint | 3 intentos, se agota → invalida sesión |

### 2.3 Tres niveles de 2FA

| Nivel | Gatillo | Dónde se evalúa |
|-------|---------|-----------------|
| **Obligatorio** | Staff con rol Spatie `super_admin` | `TwoFactorService::isRequiredForStaff()` |
| **Obligatorio** | Tenant con `role = 'tenant_owner'` | `TwoFactorService::isRequiredForTenant()` |
| **Configurable** | Tenant con rol en `INTERMEDIATE_TENANT_ROLES` + flag `two_factor_enabled_for_intermediate_roles = true` | `TwoFactorService::isRequiredForTenant()` |

`INTERMEDIATE_TENANT_ROLES` está vacío (`[]`) hasta que se creen roles intermedios de tenant. El flag `two_factor_enabled_for_intermediate_roles` en tabla `tenants` ya existe con endpoint `PUT .../{tenant}/2fa-flag`.

### 2.4 Flujo de login con 2FA

```
POST /auth/login
  → LockoutService::check()
  → Buscar user + Hash::check(password)
  → LockoutService::reset() (si password correcto)
  → TwoFactorService::isRequiredForStaff()?
     ├── No  → emitir token Sanctum directo
     └── Sí  → TwoFactorService::generateOtp()
                → Invalida OTP previo del mismo usuario
                → Crea nuevo UserOtp con session_token
                → Dispatch SendOtpEmail (sync temporal)
                → Responde { requires_2fa: true, session_token }
```

### 2.5 Endpoints OTP

| Endpoint | Throttle | Descripción |
|----------|----------|-------------|
| `POST auth/verify-otp` | 10/min | Verifica código + session_token. Si OK emite token Sanctum |
| `POST auth/resend-otp` | 5/min | Reenvía código (cooldown 60s, resetea attempts_remaining) |
| `POST tenant/auth/verify-otp` | 10/min | Ídem para tenant_users |
| `POST tenant/auth/resend-otp` | 5/min | Ídem para tenant_users |

Cada throttle usa prefijo único (`staff-verify-otp`, `staff-resend-otp`, `tenant-verify-otp`, `tenant-resend-otp`) para evitar colisión de keys en caché.

### 2.6 Validaciones en verify-otp

1. `session_token` existe, `verified_at IS NULL`, `attempts_remaining > 0`, `code_expires_at > NOW()`?
   - No → `"Sesión de verificación expirada o inválida. Inicia sesión nuevamente."` (401)
2. `Hash::check(code, code_hash)`?
   - No → decrementa `attempts_remaining`
     - Si llega a 0 → `code_expires_at = NOW()` + `"Código agotado. Debes iniciar sesión nuevamente."` (401)
     - Si quedan intentos → `"Código incorrecto."` (401)
3. Sí → `verified_at = NOW()`, crea token Sanctum (staff 8h, tenant 24h), responde 200 con token.

### 2.7 Manejo de sesiones OTP huérfanas

`generateOtp()` invalida explícitamente cualquier OTP previo del mismo usuario que esté pendiente:

```php
$this->userOtp
    ->where('user_type', $userType)
    ->where('user_id', $user->id)
    ->whereNull('verified_at')
    ->where('code_expires_at', '>', now())
    ->update(['code_expires_at' => now()]);
```

Esto garantiza que solo una sesión 2FA esté activa por usuario a la vez.

---

## 3. Integración con el flujo de login existente

### 3.1 Login sin 2FA
Respuesta existente (sin cambios):
```json
{ "token": "sanctum_token...", "user": { ... } }
```

### 3.2 Login con 2FA
```json
{ "requires_2fa": true, "session_token": "64_hex_chars...", "user": { ... } }
```

### 3.3 Orden de validaciones
1. Lockout check (IP + usuario)
2. Buscar usuario + verificar password
3. Verificar estado del usuario (activo/desactivado)
4. Verificar estado del tenant (solo tenant)
5. Reset lockout
6. Evaluar si requiere 2FA → bifurca

### 3.4 Tokens Sanctum emitidos

| Contexto | Token Name | Expiración | Cómo se emite |
|----------|-----------|------------|---------------|
| Staff sin 2FA | `core_master_session` | 8h | LoginUseCase línea 60-64 |
| Staff con 2FA | `core_master_session` | 8h | OtpController verifyOtp línea 63-67 |
| Tenant sin 2FA | `tenant_session` | 24h | TenantLoginUseCase línea 69-73 |
| Tenant con 2FA | `tenant_session` | 24h | OtpController verifyOtp línea 68-72 |

---

## 4. Seguridad: Revocación de tokens en cambios de política

### 4.1 Cambio de rol de staff
En `UserController@update`, después de persistir el cambio:
1. Sincroniza Spatie (`$user->syncRoles([$role])`)
2. Revoca tokens Sanctum:
   - Auto-modificación: conserva token actual, revoca los demás
   - Modificación a otro usuario: revoca todos

### 4.2 Cambio de flag 2FA de tenant
Nuevo endpoint `PUT /api/v1/core/tenants/{tenant}/2fa-flag` (protegido con `auth:sanctum` + permiso `role_manage`):
1. Actualiza `two_factor_enabled_for_intermediate_roles`
2. Revoca tokens de `TenantUser` con roles en `INTERMEDIATE_TENANT_ROLES` (actualmente vacío, lógica lista)

### 4.3 Creación de usuario staff
En `UserController@store`, después de crear el usuario:
1. Asigna rol Spatie (`$user->assignRole($role)`)
2. No revoca tokens (usuario nuevo, no tiene)

---

## 5. Deuda técnica conocida

| # | Deuda | Referencia |
|---|-------|------------|
| 1 | `SendOtpEmail` corre en modo `sync` (temporal para pruebas Mailtrap). Antes de producción: revertir a `ShouldQueue` + worker supervisado. | `16_pendientes_generales.md` punto 10 |
| 2 | Dos fuentes de verdad para rol de staff: `users.role_id` + Spatie `model_has_roles`. Sincronizadas manualmente en store/update. Recomendación: migrar a Spatie exclusivo y eliminar `role_id`. | `16_pendientes_generales.md` punto 12 |
| 3 | `INTERMEDIATE_TENANT_ROLES` vacío. El flag de tenant y el endpoint 2fa-flag existen pero no tienen efecto hasta que se definan roles intermedios. | `16_pendientes_generales.md` punto 6 |
| 4 | `AccountLocked` event sin listener real (espera mailer configurado). | `16_pendientes_generales.md` punto 8 |
| 5 | No existe renovación de sesión (refresh) — token expirado = 401 sin aviso. | `16_pendientes_generales.md` punto 4 |

---

## 6. Archivos modificados/creados

### Nuevos
- `app/Services/Security/LockoutService.php`
- `app/Services/Auth/TwoFactorService.php`
- `app/Http/Controllers/Api/Auth/OtpController.php`
- `app/Events/AccountLocked.php`
- `app/Jobs/SendOtpEmail.php`
- `app/Mail/Auth/OtpMail.php`
- `app/Models/Auth/UserOtp.php`
- `database/migrations/2026_07_18_000002_create_user_otps_table.php`
- `database/migrations/2026_07_18_000003_add_2fa_flag_to_tenants_table.php`

### Modificados
- `app/Core/Auth/Application/UseCases/LoginUseCase.php` — lockout + 2FA bifurcación
- `app/Core/Auth/Application/UseCases/TenantLoginUseCase.php` — lockout + 2FA bifurcación
- `app/Http/Controllers/Api/Usuarios/UserController.php` — store/update con syncRoles + revocación de tokens
- `app/Http/Controllers/Api/Tenants/TenantController.php` — update2faFlag endpoint
- `routes/api.php` — rutas OTP + throttle + ruta 2fa-flag
- `database/migrations/2026_07_18_000001_remove_event_type_check.php` — CHECK constraint removido

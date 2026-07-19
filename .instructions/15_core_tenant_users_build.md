# INSTRUCCIONES DE CONSTRUCCIÓN: Base de Identidad Federada (Core Backend)

**Alcance:** Solo el backend del Core. No tocar, referenciar ni asumir nada sobre proyectos satélite (asist-GO u otros) en este documento.
**Relacionado con:** `.instructions/13_identidad_federada_tenants.md` (plan general) — este documento cubre la implementación de las decisiones ya tomadas en las Etapas 0, 1 y 2 de ese plan.
**Fuera de alcance de este documento** (no implementar todavía):
- Token delegado / JWT federado (Etapa 3 del plan general) — requiere más diseño antes de codificar.
- Frontend del tenant_owner — proyecto separado, no se toca aquí.
- Roles adicionales de tenant, autoservicio de registro, lockout/2FA (`.instructions/14_plan_lockout_2fa.md`, sigue pausado).

---

## Regla de trabajo para el agente

Aplica las secciones **en orden (A → E)**. Al terminar cada sección, detente y confirma qué archivos creaste o modificaste antes de seguir con la siguiente, para poder revisarlo. No avances a la Sección B sin confirmar que la A quedó aplicada.

---

## Sección A — Corrección de seguridad: credenciales del handshake perimetral

**Problema actual:** `api_secret_token` se guarda como un HMAC-SHA256, pero ese HMAC se trata como si fuera la credencial misma (se compara tal cual, incluso por SQL). Si la tabla `tenants` se filtra, el atacante obtiene un valor directamente utilizable — el hash no está cumpliendo ninguna función protectora real.

**Corrección a aplicar:**

1. **Generación** (en `RegisterTenantUseCase` y `TestTenantSeeder`): seguir generando un secreto aleatorio en texto plano (`Str::random(40)`) — este es el valor que se le entrega al cliente/se documenta en el `.env` del satélite. En la base de datos, guardar en `api_secret_token` el resultado de `Hash::make($secretoPlano)` (bcrypt, vía el helper `Hash` de Laravel), no un HMAC.
2. **Validación** (`TenantEloquentRepository::verifyCredentials`): dejar de comparar el secreto dentro del `WHERE` de SQL. El flujo correcto es:
   - Buscar el tenant únicamente por `slug` (y `app_slug` si corresponde) — el slug no es secreto, puede ir en el `WHERE`.
   - Si se encuentra el tenant, comparar el token entrante contra el hash guardado usando `Hash::check($tokenEntrante, $tenant->api_secret_token)` en PHP.
   - Si `Hash::check` falla, tratar como credencial inválida (mismo comportamiento de error que hoy).
3. **Regenerar los secretos de los tenants de prueba existentes** (no hay clientes reales todavía, así que no se necesita plan de migración en caliente) — actualizar el seeder o crear un comando de artisan puntual para regenerar y mostrar el nuevo secreto en texto plano una sola vez.
4. Confirmar que ningún log (`Log::info`, `Log::error`, etc.) imprima el secreto en texto plano en ningún punto del flujo.

---

## Sección B — Modelo de datos: `tenant_users`

Crear la tabla y el modelo Eloquent para los usuarios del lado cliente, separados de `users` (staff interno).

**Migración `tenant_users`:**
- `id`
- `tenant_id` — FK a `tenants`, `onDelete('cascade')`
- `name`
- `email` — unique
- `password`
- `role` — string, por ahora un solo valor posible: `tenant_owner` (no crear tabla de roles con spatie para este lado todavía; es un solo rol y agregar el paquete completo sería sobre-ingeniería en este punto — se puede migrar a un sistema de roles más completo cuando exista una segunda variante de rol de cliente).
- `status` — boolean, default `true`
- `email_verified_at` — nullable
- `last_login_at`, `last_login_ip` — nullable
- `remember_token`
- `timestamps`
- `deleted_at` (soft deletes)

**Modelo `TenantUser`:**
- Implementa `Authenticatable` (extiende de la clase base de Laravel, igual que `User`).
- Usa el trait `HasApiTokens` de Sanctum.
- Relación `belongsTo(Tenant::class)`.
- Nota: como el proyecto usa el patrón repositorio + `DB::table()` en el resto del código, este modelo Eloquent es una excepción necesaria — Sanctum requiere un `Authenticatable` de Eloquent para emitir y validar tokens, no es opcional.

---

## Sección C — Guard de Sanctum separado para tenant_users

En `config/auth.php`:
- Agregar un guard nuevo, `tenant`, con `driver: sanctum` y su propio `provider`.
- Agregar el provider correspondiente apuntando al modelo `TenantUser` (driver `eloquent`).
- El guard `web` existente sigue siendo exclusivo para el modelo `User` (staff interno) — no debe cambiar.

**Endpoint de login de tenant_owner:**
- Nuevo controlador, ej. `Core\Auth\TenantAuthController`, con su propio caso de uso (siguiendo el mismo patrón Clean Architecture que ya usa `Core\Auth\AuthController`).
- Ruta: `POST /api/v1/tenant/auth/login`.
- Validaciones antes de emitir el token:
  1. Credenciales correctas (`Hash::check` contra `tenant_users.password`).
  2. `tenant_users.status = true`.
  3. `tenant.status` en (`trial`, `active`) — si está `suspended` o `cancelled`, rechazar el login aunque las credenciales sean correctas. **No validar `app_status` acá** — eso se resuelve a nivel de qué apps se muestran dentro del panel, no como bloqueo de acceso al Core.
- Respuesta: seguir el estándar ya establecido en el proyecto — `{status, message, data: {...}}`.
- Agregar un `throttle` básico a esta ruta (ej. `throttle:10,1`) como protección mínima mientras se diseña el lockout formal en `.instructions/14_plan_lockout_2fa.md` — esto no reemplaza ese diseño, es solo una barrera de bajo costo mientras tanto.

---

## Sección D — Alta manual de `tenant_owner`

- Nuevo endpoint protegido con el guard `web` (staff interno), ej. `POST /api/v1/core/tenants/{tenant}/owner`, que crea el registro en `tenant_users` con `role = tenant_owner` para el tenant indicado.
- No implementar autoservicio de registro público en este documento.
- Restringir este endpoint a roles de staff con permiso adecuado (ej. `super_admin`, `admin`) usando el sistema de permisos de spatie ya existente para `users`.

---

## Sección E — Directorio de instancias por tenant

Agregar a la tabla existente `tenant_apps` (no crear tabla nueva):
- `instance_type` — string/enum, valores `shared` | `dedicated`, default `shared`.
- `instance_endpoint` — string, nullable (URL base de la instancia del satélite para ese tenant + app; nulo mientras sea `shared` y no se necesite diferenciar).

No implementar lógica que llame a estos endpoints todavía — es solo el modelo de datos, listo para cuando se diseñe la Etapa 3 (token delegado) del plan general.

---

## Plan de pruebas en Postman (una vez aplicadas las secciones A–E)

1. **Handshake perimetral sigue funcionando** tras el fix del secreto — repetir la prueba de marcación/handshake que ya tenían funcionando, con el secreto regenerado.
2. **Alta de tenant_owner:** login como staff (guard `web`) → `POST /api/v1/core/tenants/{id}/owner` → verificar fila creada en `tenant_users`.
3. **Login de tenant_owner exitoso:** `POST /api/v1/tenant/auth/login` con las credenciales creadas → debe devolver token, y ese token debe corresponder al guard `tenant`, no al `web`.
4. **Login con tenant suspendido:** cambiar `tenants.status` a `suspended` → repetir login del tenant_owner → debe rechazar aunque la contraseña sea correcta.
5. **Login con credenciales incorrectas:** verificar mensaje de error genérico (sin revelar si el email existe o no).
6. **Verificar que el token de un tenant_owner no puede acceder a rutas protegidas por el guard `web`** (y viceversa) — esta es la prueba más importante de aislamiento.

---

## Recordatorio de seguridad transversal

Este proyecto se quiere caracterizar por ser seguro. Para todo lo que se construya en este documento:
- Nunca comparar secretos con `===` o dentro de un `WHERE` de SQL — siempre `Hash::check()` o `hash_equals()` según corresponda.
- Nunca loguear contraseñas, tokens, ni secretos en texto plano.
- Cualquier endpoint nuevo que exponga si un email/usuario existe o no (mensajes de error específicos) debe reportarse como hallazgo antes de continuar, no corregirse silenciosamente ni ignorarse.

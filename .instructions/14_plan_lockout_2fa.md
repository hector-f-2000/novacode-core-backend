# PLAN DE TRABAJO POR ETAPAS: Bloqueo de Cuenta (Lockout) + 2FA por Email

**Proyecto:** Core de NovaCode Labs
**Relacionado con:** `.instructions/00_roadmap_asist_go.md` (Sección Handshake / Seguridad)
**Modo de trabajo:** Propuesta → Revisión humana → Aprobación → Implementación

---

## 🎯 Objetivo general

Diseñar e implementar dos capas de seguridad nuevas en el Core:

1. **Bloqueo de cuenta por intentos fallidos de login** (Account Lockout Policy).
2. **Autenticación de doble factor (2FA) vía email con OTP**, aplicada en **tres niveles**:
    - **Obligatorio siempre** → roles críticos (Súper Admin, Dueño de Tenant).
    - **Configurable por tenant** → roles administrativos intermedios.
    - **No aplica** → trabajadores que solo marcan asistencia (u operaciones equivalentes en otros satélites).

---

## 📜 Reglas generales para el agente (aplican a TODAS las etapas)

- **No escribir código en ninguna etapa hasta que Héctor lo apruebe explícitamente.** Cada etapa termina con una propuesta en markdown, no con implementación.
- **No avanzar de etapa sin aprobación.** Si terminas la Etapa 1, detente y espera confirmación antes de tocar la Etapa 2.
- **Usa la terminología ya establecida en el proyecto**: Core, satélite, tenant, handshake perimetral, `X-Tenant-Slug`, `X-Tenant-Token`, `X-App-Identifier`.
- **Antes de proponer, consulta el código real** (vía codebase-memory-mcp): modelos existentes, tabla `users`, `user_profiles`, roles con `spatie/laravel-permission`, configuración de tenants, `config/services.php`, y cualquier controlador de autenticación ya existente.
- **Marca explícitamente cada punto donde exista más de un camino válido** con la etiqueta:

    > ⚠️ **Decisión pendiente:** [descripción de las opciones]

    Esto es obligatorio — no elijas por tu cuenta en puntos de diseño ambiguos, solo preséntalos.

- **Formato de cada propuesta de etapa:** máximo 1 página, en markdown, sin bloques de código (pseudocódigo conceptual está bien si ayuda a explicar un flujo, pero no código PHP/JS real).
- Si en cualquier etapa detectas que algo ya definido en una etapa anterior no calza con el código real, repórtalo como ⚠️ Decisión pendiente en vez de improvisar una solución.

---

## 🧭 Etapa 0 — Reconocimiento del código base (solo lectura)

**Objetivo:** que el agente entienda qué existe hoy en el Core antes de proponer nada nuevo.

El agente debe revisar y resumir (sin proponer cambios todavía):

- Estructura actual de la tabla `users` y `user_profiles`.
- Cómo están definidos los roles y permisos (`spatie/laravel-permission`) — específicamente cuáles roles existen hoy y cuáles podrían calificar como "críticos".
- Cómo está modelada la relación tenant ↔ usuario ↔ rol.
- Flujo de login actual: controlador, middleware, cómo se emite el token de Sanctum hoy.
- Qué mecanismo de colas/queues está configurado (para saber si ya hay infraestructura de envío de correo asíncrono o hay que definirla).
- Qué guarda hoy el Core en caché/Redis (si existe) vs. en base de datos.

**Entregable:** un resumen breve (no una propuesta de diseño todavía) + lista de preguntas abiertas que el agente necesita para poder diseñar las etapas siguientes.

---

## 🔒 Etapa 1 — Diseño del Bloqueo de Cuenta (Lockout)

**Objetivo:** proponer el modelo de datos y la lógica conceptual, sin código.

Puntos que la propuesta debe cubrir obligatoriamente:

1. **Doble contador:**
    - Contador por `username` (protege la cuenta específica sin importar el origen del ataque).
    - Contador más laxo por `IP` (protege contra un solo origen atacando muchas cuentas).
2. **Umbral y backoff incremental:** cuántos intentos fallidos gatillan el bloqueo y cómo escala el tiempo de espera (ej. 1 min → 5 min → 15 min).
3. **Tope máximo de espera:** definir un techo razonable para no dejar a alguien bloqueado indefinidamente sin vía de desbloqueo manual.
4. **Reset del contador:** confirmar que se reinicia tras un login exitoso.
5. **Dónde vive el estado:** tabla en base de datos vs. caché (Redis/Memcached) — con pros/contras para este proyecto específico.
6. **Mensaje genérico de error:** cómo se estructura la respuesta para no revelar si el usuario existe o no, y cómo se diferencia (sin filtrar información) del mensaje de "cuenta bloqueada" (que sí puede ser explícito sobre el tiempo de espera).
7. **Notificación al dueño de la cuenta:** correo automático al superar el umbral de intentos fallidos, usando la misma infraestructura de colas que se usará para el 2FA.
8. **Alcance:** ¿el lockout es global por usuario o puede variar por tenant/rol?

**Entregable:** propuesta de diseño de datos (tablas o estructura de caché) + flujo conceptual, con las ⚠️ Decisiones pendientes marcadas.

---

## 🛡️ Etapa 2 — Diseño del Modelo de 2FA (tres niveles)

**Objetivo:** proponer el modelo de datos y flujo para el 2FA, sin código.

Puntos que la propuesta debe cubrir obligatoriamente:

1. **Modelo de tres niveles:**
    - Flag a nivel de **rol** (`requires_2fa_always`) para roles críticos — no configurable por el tenant.
    - Flag a nivel de **tenant** (`2fa_enabled_for_intermediate_roles` o similar) para roles administrativos intermedios.
    - Ningún flag para roles operativos (ej. trabajadores que solo marcan asistencia).
2. **Almacenamiento del OTP:**
    - ⚠️ Evaluar si el código se guarda hasheado (recomendado) en vez de texto plano, igual que se hace con las contraseñas.
    - Tabla o caché, y su relación con el usuario y el tenant.
3. **TTL vs. cooldown de reenvío:** separar claramente el tiempo de vida del código (ej. 2–5 min) del tiempo mínimo antes de permitir un reenvío (ej. 30–60s), para no confundir ambos conceptos.
4. **Límite de intentos de ingreso del código:** cuántos intentos de escribir el OTP se permiten antes de invalidarlo y obligar a reiniciar el login (protección contra fuerza bruta sobre el OTP de 6 dígitos).
5. **Token de sesión temporal (estado intermedio entre login y 2FA):** cómo se genera (debe ser aleatorio, no predecible), su TTL, y qué pasa si expira o se agotan los intentos.
6. **Envío asíncrono:** confirmar uso de colas/queues para el envío del correo, sin bloquear la respuesta HTTP del login.
7. **Invalidación de un solo uso:** el código se invalida apenas se usa correctamente, para que no pueda reutilizarse.

**Entregable:** propuesta de diseño de datos + flujo de las 4 fases (validación básica → estado intermedio → despacho → handshake final), con ⚠️ Decisiones pendientes marcadas.

---

## 🔗 Etapa 3 — Integración con el flujo de login existente

**Objetivo:** proponer cómo se conecta todo esto con el endpoint de login actual, sin código.

Puntos a cubrir:

1. Cómo cambia la respuesta del endpoint de login (ahora puede tener un estado intermedio "pendiente de 2FA" en vez de devolver el token directo).
2. Qué le llega a las apps satélite (como asist-GO) cuando un usuario con 2FA obligatorio intenta loguearse — cómo deben manejar ese estado intermedio.
3. Si el lockout y el 2FA interactúan entre sí (ej. ¿los intentos fallidos de OTP cuentan para el lockout general de la cuenta, o son un contador aparte?).
4. Qué pasa con sesiones/tokens ya emitidos si se cambia la configuración de 2FA de un tenant mientras un usuario tiene sesión activa.

**Entregable:** descripción de la secuencia completa del login (con y sin 2FA), con ⚠️ Decisiones pendientes marcadas.

---

## ✅ Etapa 4 — Plan de pruebas y casos borde

**Objetivo:** antes de implementar, tener claro qué se va a probar.

**Entregable:** lista de casos de prueba en markdown (sin código de test todavía).

---

### A. Casos verificados manualmente (Etapas 2.1 / 2.2 / 2.3)

#### A.1 Lockout — Contadores duales (usuario + IP)

| # | Caso | Procedimiento | Resultado esperado | Verificado |
|---|------|---------------|-------------------|------------|
| 1 | Lockout por usuario (4 intentos) | Login con email correcto + password incorrecto 4 veces seguidas | 4° intento responde: `"Demasiados intentos fallidos. Intenta de nuevo en 3 minutos."`. Cache guarda `lockout:user:{md5(email)}` con `count=4`. | ✅ |
| 2 | Backoff incremental (usuario) | Continuar intentando hasta 7+ fallos | Secuencia: fallo 4 → 3 min, fallo 5 → 5 min, fallo 6 → 15 min, fallo 7+ → 30 min (techo) | ✅ |
| 3 | Backoff incremental (IP) | 10+ intentos fallidos desde misma IP (contra cuentas distintas) | Fallo 10 → 0 min, fallo 11 → 1 min, fallo 12 → 5 min, fallo 13 → 15 min, fallo 14+ → 30 min (techo) | ✅ |
| 4 | Reset de contadores tras login exitoso | Lockear usuario, luego loguear correctamente | LockoutService::reset() borra entries de cache `lockout:user:` y `lockout:ip:`; usuario puede fallar de nuevo desde 0 | ✅ |
| 5 | Evento AccountLocked dispatchado | Provocar lockout (fallo 4+) | `AccountLocked::dispatch()` se ejecuta en `increment()` cuando `backoff > 0` | ✅ |
| 6 | Auditoría security_audit_logs.event_type | Login fallido | `event_type='login_failed'` (staff) o `'tenant_login_failed'` (tenant). Columna es `varchar` sin CHECK constraint — no rechaza valores nuevos | ✅ |

#### A.2 2FA — Generación, verificación y reenvío de OTP

| # | Caso | Procedimiento | Resultado esperado | Verificado |
|---|------|---------------|-------------------|------------|
| 7 | Login de super_admin requiere 2FA | POST `/auth/login` con usuario super_admin | Respuesta con `requires_2fa: true` + `session_token` de 64 caracteres hex. No se emite token Sanctum todavía | ✅ |
| 8 | OTP correcto → token Sanctum emitido | POST `/auth/verify-otp` con session_token vigente + código correcto de 6 dígitos | `verified_at` se setea. Token Sanctum emitido con expiración 8h (staff) o 24h (tenant) | ✅ |
| 9 | OTP incorrecto → decrementa attempts_remaining | POST `/auth/verify-otp` con código incorrecto | `attempts_remaining` baja de 3 → 2. Responde `"Código incorrecto."` (401) | ✅ |
| 10 | OTP agotado (3 intentos fallidos) → invalida sesión | Fallar verify-otp 3 veces | Al 3er fallo `attempts_remaining` llega a 0, `code_expires_at = NOW()`. Responde `"Código agotado. Debes iniciar sesión nuevamente."` (401) | ✅ |
| 11 | Reutilización de OTP verificado | Usar el mismo código después de verify exitoso | Sesión no existe más (verified_at no es null), responde `"Sesión de verificación expirada o inválida"` | ✅ |
| 12 | Cooldown de reenvío (60s) | Login, esperar 10s, hacer resend-otp | Responde `"Espera N segundos antes de reenviar el código."` (429) donde N ≈ 50 | ✅ |
| 13 | Resend exitoso (cooldown cumplido) | Login, esperar 60s+, hacer resend-otp | Nuevo código generado, enviado por email. `attempts_remaining` se reset a 3. Responde 200 | ✅ |
| 14 | El código OTP se guarda como bcrypt hash | Inspeccionar fila user_otps.code_hash | Hash de bcrypt, no texto plano | ✅ |
| 15 | SendOtpEmail en modo sincrónico (temporal para pruebas) | Login de super_admin | `SendOtpEmail` se ejecuta en modo `sync` sin pasar por la tabla `jobs`. El email se envía inmediatamente por SMTP (Mailtrap). **Temporal:** antes de producción debe revertirse a `ShouldQueue` con worker supervisado. Ver nota en `16_pendientes_generales.md` punto 10. | ✅ |

#### A.3 Edge cases de integración (Task 2.3)

| # | Caso | Procedimiento | Resultado esperado | Verificado |
|---|------|---------------|-------------------|------------|
| 16 | Sesión 2FA expirada (code_expires_at vencido) | Insertar OTP con `code_expires_at` en el pasado, intentar verify con código correcto | `$otp` no se encuentra (null). Responde `"Sesión de verificación expirada o inválida. Inicia sesión nuevamente."` (401) — **distinto de código incorrecto** | ✅ |
| 17 | Re-login mientras hay 2FA pendiente | Login (OTP A), login de nuevo (OTP B) sin verificar OTP A | `generateOtp()` invalida OTP A con `code_expires_at = NOW()`. OTP B es el único activo. El session_token de A responde `"Sesión de verificación expirada..."` (401), no `"Código incorrecto."` | ✅ |
| 18 | Lockout concurrente durante 2FA pendiente | Login (sesión 2FA creada), luego 4 login-fallidos con password equivocado | LockoutService se activa independientemente de las sesiones 2FA abiertas. 4° fallo da `"Demasiados intentos fallidos..."` (mensaje lockout). Lockout y 2FA son mecanismos independientes | ✅ |
| 19 | Throttle en verify-otp (10 intentos/min) | 11 requests a verify-otp en menos de 1 minuto | Requests 1-10: pasan (respuesta 401 según código). Request 11: HTTP 429 `"Too Many Attempts"`. Throttle con prefijo único `staff-verify-otp` / `tenant-verify-otp` | ✅ |
| 20 | Throttle en resend-otp (5 intentos/min) | 6 requests a resend-otp en menos de 1 minuto (con tokens distintos) | Requests 1-5: pasan. Request 6: HTTP 429. Throttle independiente del cooldown de 60s de la aplicación | ✅ |
| 21 | Errores en resend-otp retornan HTTP correcto | Session inválida vs. cooldown activo | Session inválida → HTTP 401 con `"Sesión de verificación expirada..."`. Cooldown activo → HTTP 429 con `"Espera N segundos..."` | ✅ |

---

### B. Casos borde documentados (comportamiento actual, no necesariamente requieren cambio)

#### B.1 Cambio de rol mientras hay sesión activa — corregido

**Problema original:**
El check `isRequiredForStaff()` / `isRequiredForTenant()` se ejecuta **solo al momento del login** (`LoginUseCase::execute` línea 47, `TenantLoginUseCase::execute` línea 54). Al cambiar el rol de un usuario vía `UserController@update`, el token Sanctum existente seguía siendo válido sin exigir 2FA.

**Solución implementada:**
En `UserController@update` (después de persistir el cambio):
1. **Sincronizar rol de Spatie** (`$user->syncRoles([$role->name])`) para mantener consistencia entre `users.role_id` y `model_has_roles`.
2. **Revocar tokens Sanctum** del usuario modificado. Si el usuario se modifica a sí mismo, se conserva el token de la petición actual; si se modifica a otro usuario, se revocan todos.

```php
$role = Role::find($validated['role_id']);
$user->syncRoles([$role->name]);

if ((int) $request->user()->id === (int) $user->id) {
    $currentTokenId = $request->user()->currentAccessToken()->id;
    $user->tokens()->where('id', '!=', $currentTokenId)->delete();
} else {
    $user->tokens()->delete();
}
```

**Resultado:** Al cambiar el rol de un usuario, se revocan inmediatamente sus tokens activos, forzándolo a re-login donde se evaluará el requisito de 2FA según su nuevo rol. ✅

#### B.2 Lockout vs. resend-otp — independencia de mecanismos

**Comportamiento actual:**
`LockoutService` solo se invoca desde `LoginUseCase` y `TenantLoginUseCase`. No se usa en `OtpController` ni en `TwoFactorService`. Las rutas `auth/resend-otp` y `auth/verify-otp` son completamente independientes del lockout de login.

**Implicación:**
- Un usuario que está en lockout (por fallar el password) **no puede** intentar login de nuevo, pero **sí puede** hacer resend-otp si ya tiene una sesión 2FA pendiente creada antes del lockout.
- Esto es correcto por diseño: los contadores de lockout se resetean apenas el password es correcto (línea 45 LoginUseCase, línea 52 TenantLoginUseCase), antes de bifurcar a 2FA. Por lo tanto, cuando un usuario llega al estado 2FA, el lockout ya se limpió.
- Los intentos fallidos de OTP (`attempts_remaining` en `user_otps`) **no** incrementan los contadores de lockout de usuario/IP. Son contadores totalmente independientes.

**Confirmación:** Lockout y flujo OTP son ortogonales. No hay camino donde un usuario pueda estar simultáneamente en lockout y en medio de 2FA, porque lockout se resetea antes de bifurcar a 2FA. Diseño correcto.

#### B.3 Múltiples dispositivos / pestañas — invalidación mutua de OTP

**Comportamiento actual:**
`TwoFactorService::generateOtp()` (líneas 44-49) invalida cualquier OTP previo del mismo usuario que esté pendiente (`verified_at IS NULL` y `code_expires_at > NOW()`) antes de crear uno nuevo:

```php
$this->userOtp
    ->where('user_type', $userType)
    ->where('user_id', $user->id)
    ->whereNull('verified_at')
    ->where('code_expires_at', '>', now())
    ->update(['code_expires_at' => now()]);
```

**Escenario:**
1. Usuario inicia login desde el dispositivo A (recibe OTP A, session_token A).
2. Sin verificar, inicia login desde el dispositivo B (OTP A se invalida, recibe OTP B, session_token B).
3. El dispositivo A intenta usar session_token A → recibe `"Sesión de verificación expirada o inválida"`.
4. El dispositivo B puede verificar OTP B con normalidad.

**Impacto:** Esto puede ser confuso para un usuario legítimo que tenga el mismo login abierto en dos dispositivos. Sin embargo, es la opción más segura: evita que dos sesiones 2FA pendientes compartan el mismo presupuesto de 3 intentos y elimina ambigüedad sobre cuál es la sesión activa.

**Comportamiento conocido.** No se requiere cambio. Si en el futuro se quiere permitir múltiples sesiones 2FA simultáneas, se necesitaría:
- No invalidar sesiones previas en `generateOtp()`.
- Asegurar que cada OTP tenga su propio `attempts_remaining` independiente (ya es así).
- Documentar que un usuario puede tener N OTPs pendientes, uno por dispositivo.

#### B.4 Cambio de configuración 2FA de un tenant — corregido

**Problema original:**
El flag `two_factor_enabled_for_intermediate_roles` en la tabla `tenants` no tenía un endpoint API para actualizarlo, y los cambios en este flag no afectaban sesiones ya emitidas.

**Solución implementada:**
1. **Nuevo endpoint** `PUT /api/v1/core/tenants/{tenant}/2fa-flag` en `TenantController@update2faFlag`, protegido con `auth:sanctum`.
2. Al cambiar el flag, se revocan los tokens de todos los `TenantUser` con roles en `INTERMEDIATE_TENANT_ROLES` para ese tenant.
3. La constante `INTERMEDIATE_TENANT_ROLES` se cambió a `public` para ser accesible desde el controlador.

> Nota: Actualmente `INTERMEDIATE_TENANT_ROLES` está vacío (`[]`), por lo que esta revocación no tendrá efecto hasta que se definan roles intermedios. El endpoint y la lógica están listos para cuando se requieran. ✅

#### B.5 Orden de validaciones en el flujo de login

El orden exacto en ambos UseCases es:

1. **Check lockout** (L25-26 LoginUseCase, L23-24 TenantLoginUseCase) — Si el usuario o IP están bloqueados, se rechaza inmediatamente sin revelar nada.
2. **Buscar usuario + verificar password** (L28-38 / L28-37) — Si credenciales incorrectas, incrementa lockout y registra auditoría.
3. **Verificar estado del usuario** (L40-43 / L40-42) — Usuario desactivado → rechazar.
4. **Verificar estado del tenant** (solo tenant, L45-50) — Tenant suspendido → rechazar.
5. **Reset lockout** (L45 / L52) — Solo si password fue correcto.
6. **Check 2FA** (L47-58 / L54-67) — Si aplica, bifurca a flujo OTP. Si no, emite token Sanctum directo.

Esto asegura que:
- Un usuario bloqueado **nunca** llega a la verificación de password ni a 2FA. ✔️
- El lockout se resetea **antes** de bifurcar a 2FA, así los intentos fallidos de OTP no se confunden con intentos de login. ✔️
- El orden lockout → password → 2FA es correcto y no permite bypass. ✔️

---

## 📁 Etapa 5 — Documentación final

**Objetivo:** una vez aprobadas todas las etapas anteriores, documentar el diseño final.

- Actualizar `.instructions/00_roadmap_asist_go.md` en la sección de seguridad, igual como se hizo con el estándar de respuesta del Core (`data.allowed`, `data.reason`, etc.).
- Crear un archivo nuevo `.instructions/18_security_lockout_2fa.md` con el diseño final aprobado, para que cualquier agente futuro lo lea antes de tocar este módulo.

Recién **después** de esta etapa se pasa a la implementación real (código), en una fase separada y explícitamente autorizada por Héctor.

---

## 🚦 Cómo usar este documento con el agente

1. Copia este archivo en `.instructions/14_plan_lockout_2fa.md` de tu proyecto Core.
2. Dile al agente: _"Lee este plan completo. Empieza solo por la Etapa 0. No avances de etapa ni escribas código sin mi aprobación explícita."_
3. Cuando el agente entregue la propuesta de una etapa, cópiala y muéstrasela a Claude para revisarla en conjunto.
4. Con las correcciones hechas, pásale al agente la versión corregida y dile que la incorpore como definición final de esa etapa antes de pasar a la siguiente.

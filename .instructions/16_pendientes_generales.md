# PENDIENTES Y NOTAS TÉCNICAS — Core NovaCode Labs

**Última actualización:** a partir de la sesión de construcción de `15_core_tenant_users_build.md`
**Propósito de este archivo:** consolidar todo lo que quedó anotado pero no resuelto, para no perderlo entre conversaciones. Revisar este archivo antes de retomar cualquier trabajo en el Core.

---

## 📍 Estado general de los módulos

| Módulo | Estado |
|---|---|
| Handshake perimetral (Core ↔ satélite) | ✅ Funcionando, secreto corregido (Sección A de `15_core_tenant_users_build.md`) |
| `tenant_users` + guard `tenant` | ✅ Construido y aislamiento verificado con pruebas cruzadas |
| Alta de `tenant_owner` (endpoint staff) | ✅ Construido, con permisos de Spatie correctos |
| Sistema de permisos en `RoleController`/`UserController`/`PermissionController`/`RolePermissionController` | ✅ Corregido (no verificaban nada, ahora sí) |
| Directorio de instancias (`instance_type`/`instance_endpoint` en `tenant_apps`) | ✅ Columnas creadas, sin lógica todavía (depende de Etapa 3) |
| Expiración de tokens Sanctum (staff 8h, tenant 24h) | ✅ Aplicado y verificado con timestamps reales |
| Token delegado (SSO Core → satélite) | ⏳ Diseño pendiente — Etapa 3 de `13_identidad_federada_tenants.md` |
| Frontend del tenant_owner | ⏳ No iniciado — proyecto React separado, usando como base el frontend de staff |
| Lockout de cuentas + 2FA de tres niveles | ⏸️ Pausado — `14_plan_lockout_2fa.md`, listo para retomar ahora que existe `tenant_users` |

---

## 🔧 Deuda técnica y notas pendientes (no bloqueantes, pero anotadas)

### 1. `security_audit_logs.event_type` sigue siendo un CHECK constraint, no `string`
Cada evento nuevo (`tenant_login_failed` ya se agregó así) requiere una migración que altere el constraint. Se sugirió convertir la columna a `string` para evitar repetir esto cada vez. **Recomendado resolver antes o durante la retomada de `14_plan_lockout_2fa.md`**, ya que ese plan va a necesitar varios `event_type` nuevos (`lockout_triggered`, `otp_requested`, `otp_verified`, `otp_failed`, etc.).

### 2. `role_manage` es un permiso demasiado amplio
Cubre crear, editar y eliminar roles/permisos con el mismo permiso — no hay forma de dar acceso a editar sin también poder eliminar. No es un error, pero es una limitación a tener en cuenta si en el futuro se necesita ese nivel de granularidad.

### 3. Posible timing side-channel en `verifyCredentials()` del handshake
Cuando el `slug` no existe, la función retorna rápido; cuando existe pero el secreto es incorrecto, corre `Hash::check()` (bcrypt, más lento). En teoría permite inferir si un `slug` existe por diferencia de tiempos de respuesta. Riesgo bajo porque el `slug` no es secreto en sí (viaja en texto plano en cada request legítimo). No es urgente.

### 4. No existe renovación de sesión (refresh) tras la expiración de tokens
Con la expiración ya activa (8h staff / 24h tenant), un usuario con la sesión abierta más tiempo del TTL empezará a recibir 401 en todo, sin aviso ni renovación silenciosa. Es un tema de experiencia de usuario a resolver cuando se construyan los frontends (especialmente el de tenant_owner, que aún no existe).

### 5. `tenant_users.email` es único a nivel global, no por tenant
Hoy una misma persona no puede ser `tenant_owner` de dos empresas distintas con el mismo correo. Aceptable si el modelo de negocio es "una persona = una cuenta = un tenant". Revisar si esto cambia en el futuro.

### 6. Roles adicionales de tenant (más allá de `tenant_owner`)
El documento de identidad federada dejó anotada la posibilidad de roles intermedios (ej. `tenant_admin`, sub-usuarios invitados por el owner) como extensión futura, sin diseñarlos en detalle. **Ahora también depende de esto:** `TwoFactorService::INTERMEDIATE_TENANT_ROLES` está vacío a propósito, y la columna `tenants.two_factor_enabled_for_intermediate_roles` no tiene ningún efecto hasta que se cree al menos un rol intermedio y se agregue a esa constante. Cuando se diseñen esos roles, recordar poblar esa lista.

### 7. Throttle básico en login de tenant (`throttle:10,1`) — SUPERADO
Se agregó como protección mínima mientras se diseñaba el lockout formal. El lockout ya está implementado y verificado (tarea 2.1), por lo que este throttle quedó como una capa adicional, no como el único mecanismo. No requiere acción, solo queda anotado el contexto.

### 8. Evento `AccountLocked` con listener no-op
Se creó como parte del lockout (tarea 2.1) para notificar al dueño de la cuenta cuando se alcanza el umbral de bloqueo, pero el listener no hace nada todavía porque no hay mailer real configurado (`MAIL_MAILER=log`). Cuando se configure un mailer real, conectar un listener que envíe la notificación.

### 9. `attempt_count` en `security_audit_logs` — ya corregido, dejar de usar `1` fijo
Nota histórica: en la primera versión del `LockoutService`, `attempt_count` quedaba hardcodeado en `1` en vez de reflejar el conteo real. Ya se corrigió y se verificó con evidencia (filas 1,2,3,4 en la auditoría). Se deja anotado solo como referencia de que el campo ahora sí es confiable para análisis futuros.

### 10. Mailer configurado en modo sandbox — CUIDADO: verificar que `ShouldQueue` esté realmente removido
`SendOtpEmail` debía quedar sin `ShouldQueue` (modo `sync`) para las pruebas con Mailtrap sin depender de un worker. **Se documentó como hecho, pero en la práctica el código seguía con `implements ShouldQueue`**, causando que los correos quedaran encolados sin enviarse silenciosamente (sin error en logs, porque el job nunca llegó a ejecutarse). Corregido nuevamente — verificar con una prueba real de envío después de cualquier cambio futuro a este archivo, no confiar solo en el comentario `// TODO` o en el resumen del agente. Antes de producción: volver a `ShouldQueue` + worker supervisado, y reemplazar Mailtrap por un mailer real.

### 11. Diseño visual del correo de OTP — pendiente
El `OtpMail` quedó simplificado a texto plano/HTML mínimo para la prueba de envío. El diseño corporativo (branding, colores, logo) se definirá cuando se resuelvan temas pendientes de identidad visual — no bloquea el funcionamiento del 2FA, solo la presentación.

---

## 🧭 Decisiones de diseño aún abiertas

### Etapa 3 de `13_identidad_federada_tenants.md` — Token delegado (SSO Core → satélite)
La pieza más sensible del diseño completo. Definiciones ya tomadas como punto de partida:
- Algoritmo **asimétrico** (RS256/EdDSA), no reutilizar el HMAC simétrico del handshake perimetral.
- Debe emitirse solo después de que el 2FA del `tenant_owner` (cuando exista) esté completado.

Falta definir en detalle: estructura de claims, vida útil exacta del token, mecanismo de revocación cuando se suspende un plan a mitad de sesión, y dónde se almacenan las claves (privada en el Core, pública distribuida a cada satélite — incluyendo los de servidor dedicado).

### `14_plan_lockout_2fa.md` — Decisiones que ya quedaron resueltas y listas para aplicar
Cuando se retome este plan, estas definiciones ya no están abiertas, solo falta implementarlas:
- Lockout: doble contador (`username` + `IP` por separado), backoff incremental con techo máximo, reset tras login exitoso, estado en caché (no columnas en `users`/`tenant_users`).
- 2FA de tres niveles: obligatorio siempre para roles críticos (`super_admin` interno y `tenant_owner`), configurable por tenant para roles intermedios (aún no existen), no aplica a roles puramente operativos.
- OTP: separar TTL del código del cooldown de reenvío; límite de intentos de ingreso del código: separado del contador de lockout general.
- Frontend del tenant_owner: proyecto React nuevo, usando como base el frontend de staff, pero repo/deploy separado.

---

### 13. Mensajes de validación: dos mecanismos coexistiendo (traducción vs. hardcodeado)
Se descubrió que el proyecto no tenía `lang/es/validation.php` — los mensajes de validación automática salían sin traducir (ej. `"validation.digits"`) en cualquier endpoint que usara `$request->validate()` directo. Ya corregido con `php artisan lang:publish` + archivo de validación en español completo, incluyendo mapeo de `attributes`. Pendiente de bajo riesgo: `LoginRequest` (y posiblemente otros `FormRequest` existentes) usan mensajes hardcodeados inline en vez de depender de la traducción automática — no es un bug, pero es una segunda forma de generar el mismo tipo de mensaje. Evaluar a futuro si conviene unificar todo hacia el sistema de traducción, ahora que ya existe y funciona.

### 14. Lección de proceso: aislar variables al probar (tarea 3.2, check-revocation)
Durante la verificación de `checkRevocation()`, un primer resultado pareció indicar que `valid` dependía del estado del tenant (`valid: false` al suspender), lo cual habría sido un bug — pero el código nunca relacionó ambos campos. Al repetir la prueba con un `jti` limpio (sin `revoked_at` previo de una prueba anterior), se confirmó el comportamiento correcto: `valid` y `tenant_active` son independientes. Causa del resultado engañoso: el `jti` reusado en la primera prueba ya venía revocado de un test anterior. Lección para futuras pruebas: siempre generar un `jti`/token nuevo y confirmar su estado inicial (`revoked_at IS NULL`) antes de cambiar la variable que se quiere aislar, en vez de reusar estado de una prueba previa.

### 15. Archivos de `.instructions/` con información desactualizada (ex-rescatados del .gitignore)

1. **`02_clean_architecture_tenants.md`** — referencia PHP 7.4; el proyecto usa `^8.3`.
2. **`03_http_routes_and_controllers.md`** — está vacío.
3. **`09_refactor_middleware_and_usecase.md`** — referencia `VerifyCorporateHandshake.php`; el archivo real es `TenantHandshakeMiddleware.php`.
4. **`11_integrate_audit_middleware.md`** — misma referencia obsoleta a `VerifyCorporateHandshake.php`.

Son archivos que quedaron sin migrar tras refactors y cambios de nombre. No bloquean nada hoy, pero actualizar o archivar cuando se retome trabajo relacionado a esas áreas.

---

## ✅ Próximos pasos recomendados (en orden sugerido)

1. Resolver el punto de deuda técnica #1 (`event_type` a `string`) — es más barato hacerlo antes de que el plan de lockout/2FA agregue más eventos sobre el constraint actual.
2. Retomar `14_plan_lockout_2fa.md` desde la Etapa 1 (ya tiene `tenant_users` disponible para aterrizar el diseño con datos reales).
3. En paralelo o después: diseñar en detalle la Etapa 3 de `13_identidad_federada_tenants.md` (token delegado) — es la pieza que bloquea tanto el frontend del tenant_owner como la experiencia de "un solo dashboard" que se definió como objetivo de producto.
4. Iniciar el proyecto de frontend del tenant_owner una vez que el token delegado esté al menos diseñado (no necesariamente implementado del todo, pero sí con la forma de la respuesta de login definida).

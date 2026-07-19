# PLAN DE TRABAJO POR ETAPAS: Identidad Federada Core ↔ Satélites (Tenants)

**Proyecto:** Core de NovaCode Labs
**Relacionado con:** `.instructions/00_roadmap_asist_go.md` (Handshake perimetral) y `.instructions/14_plan_lockout_2fa.md` (**pausado**, se retoma una vez cerrado este plan, porque el 2FA del `tenant_owner` depende de este diseño)
**Modo de trabajo:** Propuesta → Revisión humana → Aprobación → Implementación

---

## 🎯 Objetivo general

Definir cómo un **cliente (tenant)** administra, desde una sola identidad y un solo login en el Core, tanto su cuenta/plan/facturación como las apps satélite que tiene contratadas (asist-GO y las que vengan después), sin tener que loguearse por separado en cada satélite, y sin mezclar usuarios operativos (trabajadores de cada app) con la base de datos del Core.

### Principios ya acordados (no rediscutir, son punto de partida)

1. **El Core NO almacena usuarios operativos de las apps.** Los trabajadores/usuarios finales de cada satélite viven y se gestionan exclusivamente en la base de datos de ese satélite (compartida o dedicada según el plan del cliente).
2. **El Core separa usuarios internos (staff NovaCode) de usuarios de cliente (`tenant_users`) en tablas distintas** (Opción B ya decidida) — nunca en la misma tabla `users`.
3. **El rol `tenant_owner`** es el dueño de la cuenta del cliente dentro del Core: administra plan, licencias, facturación, y accede a las apps contratadas.
4. **Un solo dashboard/shell para el tenant_owner**, con un panel "casa" (plan, licencias, apps contratadas) y navegación hacia los módulos propios de cada app contratada — pero los datos de cada módulo se resuelven contra la base de datos del satélite correspondiente, no contra el Core.
5. **Un cliente puede tener su(s) app(s) en la instancia compartida de NovaCode o en un servidor dedicado propio** — el Core debe saber, por tenant, a qué servidor/endpoint apunta cada app contratada.

---

## 📜 Reglas generales para el agente (aplican a TODAS las etapas)

- **No escribir código en ninguna etapa hasta que Héctor lo apruebe explícitamente.**
- **No avanzar de etapa sin aprobación.**
- **Usa la terminología ya establecida:** Core, satélite, tenant, tenant_owner, handshake perimetral, `X-Tenant-Slug`, `X-Tenant-Token`, `X-App-Identifier`.
- **Antes de proponer, consulta el código real** vía codebase-memory-mcp: tabla `tenants` actual, cómo se genera hoy `api_key_client`/`api_secret_token`, estructura de roles/permisos con spatie, y cómo está armado el frontend actual del Core (si hay algo de dashboard ya construido).
- **Marca cada punto con más de un camino válido** con:
  > ⚠️ **Decisión pendiente:** [descripción de las opciones]
- **Formato de cada propuesta:** markdown, sin bloques de código real (PHP/JS), pseudocódigo conceptual permitido solo si ayuda a explicar un flujo.
- Nombra los archivos de instrucciones **sin punto delante del nombre del archivo** (la carpeta `.instructions/` ya está oculta; el archivo va como `13_identidad_federada_tenants.md`, no `.13_identidad_federada_tenants.md`).

---

## 🧭 Etapa 0 — Reconocimiento del código base (solo lectura)

El agente debe revisar y resumir (sin proponer diseño todavía):

- Estructura actual de la tabla `tenants`: campos existentes, cómo se relaciona hoy con `plan_id`, `status`, `api_key_client`, `api_secret_token`.
- Cómo se genera y valida hoy el handshake perimetral (`NovaCodeCoreClient`, middleware del lado Core que recibe `X-Tenant-Slug`/`X-Tenant-Token`).
- Si existe alguna tabla o relación que registre qué apps satélite tiene contratada cada tenant (probablemente no existe aún — confirmarlo).
- Estado actual del frontend del Core (React): si hay algo de dashboard, login, o estructura de navegación ya construida, o si se parte de cero.
- Cómo maneja Sanctum hoy los distintos "guards" o tipos de token (uno para staff interno del Core vs. lo que se necesitaría para `tenant_users`).

**Entregable:** resumen breve + lista de preguntas abiertas para las etapas siguientes.

---

## 🗂️ Etapa 1 — Modelo de datos: `tenant_users`

**Objetivo:** proponer la tabla y relaciones para los usuarios de cliente, separada de `users` (staff interno).

Puntos a cubrir:

1. Campos de `tenant_users`: relación con `tenants`, credenciales, email de contacto, estado.
2. Roles del lado cliente: confirmar `tenant_owner` como primer rol; proponer (sin implementar) si se necesitan otros roles a futuro (ej. `tenant_admin` para sub-usuarios que el owner invite) — dejar solo anotado como posible extensión, no diseñarlo en detalle todavía si no es necesario para el alcance actual.
3. Guard de Sanctum separado para `tenant_users` (distinto al guard usado por `users` interno), para reforzar el aislamiento ya decidido en el plan de lockout/2FA.
4. Cómo se crea el primer `tenant_owner` cuando se da de alta un tenant nuevo (¿flujo manual desde el Core por el equipo de NovaCode, o autoservicio del cliente?).

**Entregable:** propuesta de modelo de datos + flujo conceptual de alta de un tenant_owner, con ⚠️ Decisiones pendientes marcadas.

---

## 🗺️ Etapa 2 — Directorio de instancias por tenant

**Objetivo:** definir cómo el Core sabe qué apps tiene contratadas cada tenant y a qué servidor/base de datos apunta cada una.

Puntos a cubrir:

1. Nueva tabla/relación (ej. `tenant_app_instances` o similar) que registre: qué app satélite, en qué instancia (compartida vs. dedicada), y el endpoint/URL base de esa instancia.
2. Cómo se diferencia una instancia **compartida** (varios tenants en el mismo servidor/DB del satélite, filtrados por `tenant_slug`) de una **dedicada** (servidor/DB exclusivo del cliente).
3. Qué pasa si un tenant contrata una segunda app: ¿se agrega una fila nueva en esta tabla, reutilizando el mismo `tenant_slug`/`tenant_token` del handshake perimetral, o cada app tiene sus propias credenciales de handshake?
4. Cómo se refleja esto en el panel "casa" del dashboard (qué apps ve el tenant_owner, y hacia dónde apunta cada una al hacer clic).

**Entregable:** propuesta de modelo de datos + explicación de cómo el Core resuelve "a qué servidor mando a este cliente cuando entra a administrar la App X", con ⚠️ Decisiones pendientes marcadas.

---

## 🔐 Etapa 3 — Identidad federada (SSO delegado Core → Satélite)

**Objetivo:** definir cómo el `tenant_owner`, ya autenticado en el Core, accede a la administración de un satélite sin loguearse una segunda vez.

Puntos a cubrir:

1. Mecanismo de credencial delegada: token firmado de corta duración (tipo JWT) que el Core emite al navegar hacia un satélite, conteniendo al menos: identidad del tenant, identidad del tenant_owner (o su rol), y con qué permisos entra.
2. Cómo el satélite **verifica** ese token sin necesidad de una segunda tabla de usuarios ni llamar de vuelta al Core en cada request (ej. verificación de firma con una clave pública/compartida, similar en espíritu al handshake perimetral ya existente pero a nivel de persona, no de servidor).
3. Vida útil del token delegado: cuánto dura, qué pasa si expira mientras el tenant_owner sigue navegando dentro del módulo del satélite (¿renovación silenciosa, o debe volver al Core?).
4. Revocación: si el Core suspende el plan del tenant o le quita acceso, ¿cómo se corta el acceso ya delegado a un satélite (especialmente relevante en servidores dedicados donde la comunicación en tiempo real con el Core podría ser más lenta o intermitente)?
5. Relación con el 2FA: este es el punto donde se conecta con el plan pausado (`14_plan_lockout_2fa.md`) — el 2FA obligatorio del `tenant_owner` debe completarse en el Core **antes** de que se emita cualquier token delegado hacia un satélite.

**Entregable:** propuesta de flujo de emisión y verificación del token delegado, con ⚠️ Decisiones pendientes marcadas. Este es el punto más sensible de todo el plan — el agente debe ser especialmente explícito en marcar alternativas aquí, no asumir una sola solución.

### ✅ Etapa 3.4 — Pruebas de expiración, revocación y suspensión (simuladas desde el Core)

**Herramienta:** `app/Console/Commands/Dev/SimulateSatelliteVerification.php` — simula la verificación satélite localmente. No es parte del diseño real de un satélite; la integración en un proyecto satélite real (middleware `sso.verify` / `sso.verify-live`) queda pendiente hasta que exista uno.

**Escenarios probados y resultados (18-jul-2026, Core en localhost:8001, tenant `empresa-demo`, app `asist-go`):**

| # | Escenario | Acción | Decisión | Detalle |
|---|-----------|--------|----------|---------|
| 1 | Token recién emitido | read | **PERMITIDO** | Firma OK, claims OK, exp vigente |
| 2 | Token recién emitido | critical | **PERMITIDO** | JWT OK + check-revocation responde `valid:true, tenant_active:true, app_active:true` |
| 3 | Token con exp pasado (TTL vencido) | read | **DENEGADO** | `Firebase\JWT\ExpiredException` — ni siquiera llama a check-revocation |
| 4a | Token válido, `revoked_at` seteado en BD | read | **PERMITIDO** | La lectura no verifica revocación (JWT autocontenido, TTL es el único control) |
| 4b | Token válido, `revoked_at` seteado en BD | critical | **DENEGADO** | check-revocation responde `valid:false, tenant_active:true, app_active:true` |
| 5a | Token válido, tenant en status `suspended` | read | **PERMITIDO** | La lectura no consulta estado del tenant |
| 5b | Token válido, tenant en status `suspended` | critical | **DENEGADO** | check-revocation responde `valid:true, tenant_active:false, app_active:true` |
| 6 | Token válido, Core caído (puerto 9999) | critical | **DENEGADO (fallo cerrado)** | `ConnectionException` atrapada, mensaje: "FALLO CERRADO — No se pudo contactar check-revocation" |

**Conclusión:** El modelo bidireccional (JWT autocontenido para lecturas, verificación en vivo para críticas) funciona según lo diseñado. La renovación silenciosa no se probó aquí porque requiere un frontend real; el mecanismo queda descrito en la Etapa 3 — el satélite responde `401 + X-SSO-Renewal-Required`, el frontend pide un nuevo delegado al Core.

---

## 🖥️ Etapa 4 — Estructura conceptual del dashboard (shell + módulos)

**Objetivo:** describir a nivel conceptual (no de UI ni código) cómo se organiza la experiencia de un tenant_owner con una o varias apps contratadas.

Puntos a cubrir:

1. Panel "casa": qué información y acciones vive ahí (plan, licencias, facturación, lista de apps contratadas).
2. Navegación hacia el módulo de una app específica: qué se le muestra al tenant, y qué llamada(s) se disparan hacia el satélite correspondiente usando el token delegado de la Etapa 3.
3. Caso de un tenant con múltiples apps: cómo cambia de una app a otra sin perder la sesión "casa" del Core.
4. Qué pasa visualmente/funcionalmente si una app está en servidor dedicado y ese servidor está caído (mensaje de error, no debe tumbar el resto del panel).

**Entregable:** descripción conceptual del flujo de navegación, sin mockups de código, con ⚠️ Decisiones pendientes marcadas.

---

## ✅ Etapa 5 — Plan de pruebas y casos borde

Escenarios mínimos a proponer:

- Tenant con una sola app en instancia compartida.
- Tenant con una app en servidor dedicado.
- Tenant con dos apps, una compartida y otra dedicada.
- Expiración del token delegado a mitad de sesión en un satélite.
- Suspensión del plan del tenant mientras el tenant_owner tiene una sesión activa en un satélite.
- Alta de una segunda app para un tenant ya existente (no debe requerir que vuelva a loguearse ni reconfigurar su cuenta desde cero).

**Entregable:** lista de casos de prueba en markdown.

---

## 📁 Etapa 6 — Documentación final

- Actualizar `.instructions/00_roadmap_asist_go.md` con el estándar de token delegado, para que asist-GO (y futuros satélites) sepan cómo verificarlo.
- Crear `.instructions/13_identidad_federada_tenants.md` (este mismo archivo, actualizado con el diseño final aprobado) como referencia definitiva.
- Retomar `.instructions/14_plan_lockout_2fa.md` con el modelo de `tenant_users` y el flujo de token delegado ya resueltos, para completar el diseño de 2FA de tres niveles con datos reales en vez de supuestos.

---

## 🚦 Cómo usar este documento con el agente

1. Copia este archivo en `.instructions/13_identidad_federada_tenants.md` de tu proyecto Core.
2. Dile al agente: *"Lee este plan completo. Empieza solo por la Etapa 0. No avances de etapa ni escribas código sin mi aprobación explícita. No uses punto delante del nombre de los archivos dentro de `.instructions/`."*
3. Cuando entregue la propuesta de una etapa, cópiala y muéstrasela a Claude para revisarla en conjunto.
4. Con las correcciones hechas, pásale al agente la versión corregida para que la incorpore como definición final de esa etapa antes de pasar a la siguiente.

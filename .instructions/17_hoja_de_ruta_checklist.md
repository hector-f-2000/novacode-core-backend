# HOJA DE RUTA — Core NovaCode Labs

**Orden:** de lo más simple a lo más complejo, según lo acordado.
**Cómo usar este archivo:** marcar `[x]` cada tarea a medida que se complete y se verifique con evidencia real (no solo con el resumen del agente). Actualizar `16_pendientes_generales.md` si aparecen notas nuevas en el camino.

---

## 1️⃣ Deuda técnica simple

- [x] **1.1 — `security_audit_logs.event_type`: convertir de CHECK constraint a `string`**
      Elimina la necesidad de una migración nueva cada vez que se agregue un tipo de evento de seguridad. Bloqueante de baja fricción para el punto 2.

---

## 2️⃣ Lockout de cuenta + 2FA de tres niveles (`14_plan_lockout_2fa.md`)

- [x] **2.1 — Etapa 1: Diseño e implementación del lockout**
      Doble contador (`username` + `IP`), backoff incremental con techo, reset tras éxito, estado en caché (no en columnas de `users`/`tenant_users`), mensaje genérico, notificación al dueño de la cuenta.
- [x] **2.2 — Etapa 2: Diseño e implementación del 2FA de tres niveles**
      OTP por email, TTL separado del cooldown de reenvío, límite de intentos de ingreso del código, flag obligatorio en roles críticos (`super_admin` interno, `tenant_owner`) y configurable por tenant para roles intermedios.
- [x] **2.3 — Etapa 3: Integración con el flujo de login existente**
      Cómo cambia la respuesta de login para reflejar el estado intermedio "pendiente de 2FA", en ambos guards (`sanctum` y `tenant`).
- [x] **2.4 — Etapa 4: Plan de pruebas y casos borde**
      Documentado en `14_plan_lockout_2fa.md` sección Etapa 4. 21 casos verificados manualmente + 5 casos borde cubiertos (revocación de tokens, OTP multi-dispositivo, lockout vs resend-otp, orden de validaciones).
- [x] **2.5 — Etapa 5: Documentación final**
      `15_security_lockout_2fa.md` creado con el diseño final aprobado. Checklist actualizado.

---

## 3️⃣ Identidad federada — Token delegado (`13_identidad_federada_tenants.md`, Etapa 3)

- [x] **3.1 — Diseño detallado del token delegado**
      Estructura de claims, algoritmo (RS256/EdDSA ya decidido), vida útil, mecanismo de revocación, almacenamiento de claves (privada en el Core, pública distribuida a satélites).
- [x] **3.2 — Implementación de la emisión del token** (lado Core)
- [ ] **3.3 — Implementación de la verificación del token** (lado satélite — cuando corresponda, fuera del alcance del Core puro)
- [ ] **3.4 — Pruebas de expiración, revocación y suspensión de plan a mitad de sesión**

---

## 4️⃣ Frontend del tenant_owner

- [ ] **4.1 — Inicializar proyecto React nuevo**, usando como base el frontend de staff (repo separado)
- [ ] **4.2 — Panel "casa"**: plan, licencias, facturación, lista de apps contratadas
- [ ] **4.3 — Navegación federada hacia módulos de cada app satélite** (depende de 3.2/3.3)

---

## Notas de seguimiento

Cualquier hallazgo nuevo que aparezca mientras se trabaja en estas etapas va a `16_pendientes_generales.md`, no a este archivo — este archivo es solo la ruta y su avance.

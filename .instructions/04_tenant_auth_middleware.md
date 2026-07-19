# INSTRUCCIONES DE DESARROLLO: MIDDLEWARE DE AUTENTICACIÓN Y VALIDACIÓN SAAS

## 🎯 Objetivo

Implementar un Middleware en Laravel 13 utilizando sintaxis moderna de PHP 8.3 para validar las credenciales de integración (`Handshake`) de las empresas clientes (Tenants) en los endpoints protegidos del Core.

---

## 🛠️ Especificación de Archivos a Modificar y Crear

### Paso A: Extender el Contrato del Repositorio (Capa de Dominio)

- **Ruta:** `app/Core/Tenants/Domain/Contracts/TenantRepositoryInterface.php`
- **Acción:** Añadir el método de búsqueda avanzada para validar las credenciales unificadas junto con el estado de su aplicación.

```php
interface TenantRepositoryInterface
{
    // ... métodos anteriores ...

    /**
     * Busca un tenant activo y verifica si tiene acceso a una app específica.
     */
    public function verifyCredentials(string $slug, string $secretToken, string $appSlug): ?array;
}
```

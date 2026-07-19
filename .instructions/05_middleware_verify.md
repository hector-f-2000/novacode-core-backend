🤖 **AGENTE: CONFIGURACIÓN DE ENDPOINT DE PRUEBA PARA EL MIDDLEWARE**

Necesitamos verificar que el Middleware `tenant.handshake` funciona correctamente. Crea una ruta de prueba protegida dentro de `routes/api.php` que use el alias e inyecte el parámetro de la App ('asist-go').

1. Agrega esta ruta dentro de tu grupo de rutas `v1/core`:

```php
Route::prefix('v1/core')->group(function () {

    // ... tu ruta actual de POST /tenants ...

    // Endpoint de prueba protegido para simular la verificación de conexión de asist-GO
    Route::get('/asist-go/check-connection', function (\Illuminate\Http\Request $request) {
        // Recuperar el tenant que el Middleware inyectó en la petición
        $tenant = $request->attributes->get('current_tenant');

        return response()->json([
            'status'  => true,
            'message' => 'Handshake exitoso. Conexión establecida con el Core Master.',
            'data'    => [
                'empresa' => $tenant['razon_social'],
                'estado_licencia' => $tenant['tenant_status']
            ]
        ]);
    })->middleware('tenant.handshake:asist-go'); // 👈 Pasamos 'asist-go' como el parámetro $appSlug

});
```

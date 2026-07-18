<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\Usuarios\UserController;
use App\Http\Controllers\Api\Parametros\SystemParameterController;

use App\Http\Controllers\Api\Role\RoleController;
use App\Http\Controllers\Api\Permission\PermissionController;
use App\Http\Controllers\Api\RolePermission\RolePermissionController;
use App\Http\Controllers\Api\Security\SecurityController;

use App\Http\Controllers\Api\Tenants\TenantController;
use App\Http\Controllers\Core\Auth\AuthController;
use App\Http\Controllers\Api\V1\Core\LicenseController;
use App\Http\Controllers\Api\V1\Core\CatalogController;
use App\Http\Controllers\Core\Auth\TenantAuthController;
use App\Http\Controllers\Api\Auth\OtpController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// --- RUTAS PÚBLICAS ---
// Cualquiera puede acceder a estas rutas (no requieren token)

// Endpoint para el inicio de sesión
Route::post('/login', [AuthController::class, 'login']);

/**
 * NovaCode Labs - Core Master API v1
 * Todos los endpoints de este archivo tienen el prefijo automático de Laravel '/api'
 */
Route::prefix('v1')->group(function () {

    // 🔑 Autenticación de Administradores del Core
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/verify-otp', [OtpController::class, 'verifyOtp'])
        ->middleware('throttle:10,1,staff-verify-otp');
    Route::post('auth/resend-otp', [OtpController::class, 'resendOtp'])
        ->middleware('throttle:5,1,staff-resend-otp');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
    });

    // 🔑 Autenticación de Tenant Owners
    Route::post('tenant/auth/login', [TenantAuthController::class, 'login'])
        ->middleware('throttle:10,1');
    Route::post('tenant/auth/verify-otp', [OtpController::class, 'verifyOtp'])
        ->middleware('throttle:10,1,tenant-verify-otp');
    Route::post('tenant/auth/resend-otp', [OtpController::class, 'resendOtp'])
        ->middleware('throttle:5,1,tenant-resend-otp');

    Route::middleware('auth:tenant')->group(function () {
        Route::post('tenant/auth/logout', [TenantAuthController::class, 'logout']);
        Route::get('tenant/auth/me', [TenantAuthController::class, 'me']);
    });

    // Contexto: Core Business Management
    Route::prefix('core')->group(function () {

        // 📋 Catálogo de estados para el frontend (React PrimeReact)
        Route::get('catalogs/statuses', [CatalogController::class, 'getStatuses']);

        // 🏢 Submódulo: Empresas Clientes (Tenants)
        Route::prefix('tenants')->group(function () {
            Route::post('/', [TenantController::class, 'register']); // POST /api/v1/core/tenants

            Route::middleware(['auth:sanctum'])->group(function () {
                Route::post('{tenant}/owner', [TenantController::class, 'storeOwner']);
                Route::put('{tenant}/2fa-flag', [TenantController::class, 'update2faFlag']);
            });
        });

        // 🔐 Grupo protegido por Handshake Inter-App corporativo
        Route::middleware('tenant.handshake:asist-go')->group(function () {
            // 🔹 Validación de límites de usuarios para apps satélites
            Route::post('license/check-limits', [LicenseController::class, 'checkLimits']);
        });
        
    });

    // Endpoint de prueba protegido para simular la verificación de conexión de asist-GO
    Route::get('core/asist-go/check-connection', function (Request $request) {
        // Recuperar el tenant que el Middleware inyectó en la petición
        $tenant = $request->attributes->get('current_tenant');
        
        return response()->json([
            'status'  => true,
            'message' => 'Handshake exitoso. Conexión establecida con los servidores de NovaCode Labs.',
            'data'    => [
                'empresa' => $tenant['razon_social'],
                'estado_licencia' => $tenant['tenant_status']
            ]
        ]);
    })->middleware('tenant.handshake:asist-go'); // 👈 Pasamos 'asist-go' como el parámetro $appSlug

});


// --- RUTAS PROTEGIDAS ---
// Solo usuarios con un token válido generado por Sanctum pueden entrar aquí
Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | API Routes - Módulo de Seguridad (Roles y Permisos)
    |--------------------------------------------------------------------------
    */

        
    // --- Pestaña 1: Gestión de Roles ---
    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:role_view');
    Route::middleware('permission:role_manage')->group(function () {
        Route::post('/roles', [RoleController::class, 'store']);
        Route::put('/roles/{id}', [RoleController::class, 'update']);
        Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
    });

    // --- Pestaña 2: Gestión de Permisos Atómicos ---
    Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:role_view');
    Route::middleware('permission:role_manage')->group(function () {
        Route::post('/permissions', [PermissionController::class, 'store']);
        Route::put('/permissions/{id}', [PermissionController::class, 'update']);
        Route::delete('/permissions/{id}', [PermissionController::class, 'destroy']);
    });

    // --- Pestaña 3: Asignación y Tabla Pivote ---
    Route::get('/role-permissions', [RolePermissionController::class, 'index'])->middleware('permission:role_view');
    Route::post('/role-permissions/sync', [RolePermissionController::class, 'sync'])->middleware('permission:role_manage');

    Route::get('/me', [AuthController::class, 'me']);
    
    // Endpoint para cerrar sesión (borra el token)
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Ruta de prueba para verificar que el usuario está logueado y qué rol tiene
    Route::get('/user-check', function () {
        return response()->json((object)[
            'status' => 'success',
            'user' => request()->user(),
            // Traemos el nombre del rol asignado gracias al paquete de Spatie
            'role' => request()->user()->getRoleNames()->first() 
        ]);
    });

    Route::get('/user', function (Request $request) {
        return $request->user()->load(['profile', 'role']);
    });

    // UserController — permisos inline porque las rutas no se agrupan limpiamente
    Route::get('/user-list', [UserController::class, 'index']);
    Route::post('/user-store', [UserController::class, 'store']);
    Route::put('/user-update/{id}', [UserController::class, 'update']);
    Route::put('/user-status/{id}', [UserController::class, 'toggleStatus']);
    Route::get('/roles-lookup', [UserController::class, 'getRolesQuery']);
    Route::get('/users/{id}/permissions', [UserController::class, 'getUserPermissions']);
    Route::post('/users/{id}/permissions', [UserController::class, 'syncUserPermissions']);

    Route::get('/system-parameters', [SystemParameterController::class, 'index']);
    Route::put('/system-parameters/update-bulk', [SystemParameterController::class, 'updateBulk']);

    /*
    |--------------------------------------------------------------------------
    | API Routes - Módulo de Seguridad y Sesiones
    |--------------------------------------------------------------------------
    */
    Route::prefix('security')->group(function () {
        Route::get('/sessions', [SecurityController::class, 'index']);
        Route::delete('/sessions/{tokenId}', [SecurityController::class, 'destroy']);
        Route::post('/sessions/revoke-all-others', [SecurityController::class, 'revokeAllOtherSessions']);
        Route::get('/audit-logs', [SecurityController::class, 'getAuditLogs']);
    });
});
<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Usuarios\UserController;
use App\Http\Controllers\Api\Parametros\SystemParameterController;

use App\Http\Controllers\Api\Role\RoleController;
use App\Http\Controllers\Api\Permission\PermissionController;
use App\Http\Controllers\Api\RolePermission\RolePermissionController;
use App\Http\Controllers\Api\Security\SecurityController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// --- RUTAS PÚBLICAS ---
// Cualquiera puede acceder a estas rutas (no requieren token)

// Endpoint para el inicio de sesión
Route::post('/login', [AuthController::class, 'login']);


// --- RUTAS PROTEGIDAS ---
// Solo usuarios con un token válido generado por Sanctum pueden entrar aquí
Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | API Routes - Módulo de Seguridad (Roles y Permisos)
    |--------------------------------------------------------------------------
    */

        
    // --- Pestaña 1: Gestión de Roles ---
    Route::apiResource('/roles', RoleController::class)->except(['show']);

    // --- Pestaña 2: Gestión de Permisos Atómicos ---
    Route::apiResource('/permissions', PermissionController::class)->except(['show']);

    // --- Pestaña 3: Asignación y Tabla Pivote ---
    Route::get('/role-permissions', [RolePermissionController::class, 'index']);
    Route::post('role-permissions/sync', [RolePermissionController::class, 'sync']);
        

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

    // Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    //     return $request->user();
    // });
    Route::get('/user', function (Request $request) {
        // Cargamos tanto el perfil como el rol antes de devolver el JSON
        return $request->user()->load(['profile', 'role']);
    });

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
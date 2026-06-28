<?php

namespace App\Http\Controllers\Api\Usuarios;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User\User;
use Spatie\Permission\Models\Role;
// use App\Models\User\UserProfile;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserPermission\SyncUserPermissionsRequest;
use App\DTOs\UserPermission\SyncUserPermissionsDTO;
use App\Services\UserPermission\UserPermissionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function __construct(
        protected UserPermissionService $userPermissionService
    ) {}

    /**
     * Obtiene la lista de usuarios con sus perfiles cargados.
     */
    public function index(Request $request): JsonResponse
    {
        // Cargamos la relación 'profile' de antemano para optimizar la base de datos
        $users = User::with(['role', 'profile'])->get();
        $data = $users->map(function ($user) {
            return [
                'id'            => $user->id,
                'username'      => $user->username,
                'full_name'     => $user->full_name, // Usamos el Accessor que definimos
                'first_name'    => $user->profile->firstname, // Usamos el Accessor que definimos
                'last_name'     => $user->profile->lastname, // Usamos el Accessor que definimos
                'email'         => $user->email,
                'phone'         => $user->profile?->phone ?? 'N/A',
                'address'       => $user->profile?->address ?? 'N/A',
                'status'        => $user->status, // Enviamos el booleano puro (true/false)
                'status_label'  => $user->status ? 'Activo' : 'Inactivo', // Etiqueta para la UI
                'created_at'    => $user->created_at_formatted,
                'role_name'     => $user->role?->display_name ?? 'Sin Rol',
                'role_slug'     => $user->role?->name ?? 'none', // Útil para colores/lógica
                'role_id'       => $user->role_id,
            ];
        });

        return response()->json($data);
    }


    /**
     * Crea un nuevo usuario y su perfil asociado.
     */
    public function store(Request $request)
    {
        // 1. Validación Elite: validando existencia real de la FK en la tabla 'roles'
        $validated = $request->validate([
            'username'  => 'required|string|max:50|unique:users,username',
            'email'     => 'required|email|unique:users,email',
            'role_id'   => 'required|integer|exists:roles,id', // Valida que el ID exista en la tabla roles
            'password'  => 'required|string|min:6|confirmed',
            'firstname' => 'required|string|max:100',
            'lastname'  => 'required|string|max:100',
            'phone'     => 'nullable|string|max:20',
            'address'   => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();

        try {
            // 2. Crear el Usuario base mapeando la llave foránea role_id
            $user = User::create([
                'username' => $validated['username'],
                'email'    => $validated['email'],
                'role_id'  => $validated['role_id'], // Inserción del ID numérico del rol
                'password' => Hash::make($validated['password']),
                'status'   => true,
            ]);

            // 3. Crear el Perfil vinculado
            $user->profile()->create([
                'firstname' => $validated['firstname'],
                'lastname'  => $validated['lastname'],
                'phone'     => $validated['phone'],
                'address'   => $validated['address'],
            ]);

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Usuario creado exitosamente',
                'id'      => $user->id
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error creando usuario: " . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'No se pudo crear el usuario',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        // 2. Validación Senior conservando unicidad del ID actual y foránea del rol
        $validated = $request->validate([
            'username'  => 'required|string|max:50|unique:users,username,' . $id,
            'email'     => 'required|email|unique:users,email,' . $id,
            'role_id'   => 'required|integer|exists:roles,id', // Asegura la consistencia relacional
            'password'  => 'nullable|string|min:6|confirmed',
            'firstname' => 'required|string|max:100',
            'lastname'  => 'required|string|max:100',
            'phone'     => 'nullable|string|max:20',
            'address'   => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();

        try {
            // 3. Actualizar datos del Usuario base e inyectar el nuevo id del rol
            $user->username = $validated['username'];
            $user->email    = $validated['email'];
            $user->role_id  = $validated['role_id']; // Actualización de la FK
            
            if (!empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
            }
            
            $user->save();

            // 4. Actualizar o Crear el Perfil asociado
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'firstname' => $validated['firstname'],
                    'lastname'  => $validated['lastname'],
                    'phone'     => $validated['phone'],
                    'address'   => $validated['address'],
                ]
            );

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Usuario actualizado correctamente',
                'data'    => $user->load('profile')
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error actualizando usuario ID {$id}: " . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'No se pudo actualizar el usuario',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function toggleStatus(int $id){
        try {
            $user = User::findOrFail($id);
            $user->status = !$user->status; // Invierte el estado actual
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Estado actualizado correctamente',
                'new_status' => $user->status
            ]);
        } catch (\Exception $e) {
            Log::error("Error cambiando estado del usuario ID {$id}: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getRolesQuery(){
        try {
            // Traemos solo los campos necesarios de forma eficiente
            $roles = DB::table('roles')
                ->select('id', 'display_name as label')
                ->get();

            return response()->json($roles, 200);
        } catch (\Exception $e) {
            Log::error("Error obteniendo roles para formulario: " . $e->getMessage());
            return response()->json(['error' => 'No se pudo cargar la lista de roles'], 500);
        }
    }

    public function getUserPermissions(int $id): JsonResponse
    {
        try {
            $data = $this->userPermissionService->getUserPermissions($id);

            return response()->json([
                'status' => true,
                'data'   => $data,
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error obteniendo permisos del usuario ID {$id}: " . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'Error al obtener los permisos del usuario.',
            ], 500);
        }
    }

    public function syncUserPermissions(SyncUserPermissionsRequest $request, int $id): JsonResponse
    {
        try {
            $dto = SyncUserPermissionsDTO::fromRequest($request->validated(), $id);
            $user = $this->userPermissionService->syncPermissions($dto);

            return response()->json([
                'status'  => true,
                'message' => 'Permisos asignados correctamente al usuario.',
                'data'    => $user,
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error sincronizando permisos del usuario ID {$id}: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json([
                'status'  => false,
                'message' => 'Error al sincronizar los permisos del usuario.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
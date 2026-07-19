1. Estructura de Carpetas a Asegurar
   El agente debe verificar o crear la siguiente estructura de directorios dentro de app/Core/:

Plaintext
app/Core/
└── Auth/
├── Domain/
│ └── Contracts/ # AuthRepositoryInterface
├── Infrastructure/
│ ├── Eloquent/ # AuthEloquentRepository
│ └── DTOs/ # LoginDTO
└── Application/
└── UseCases/ # LoginUseCase, LogoutUseCase
🛠️ 2. Especificación de Archivos a Crear
Paso A: El Objeto de Transferencia de Datos (DTO)
Ruta: app/Core/Auth/Infrastructure/DTOs/LoginDTO.php

Propósito: Sanitizar y tipar las credenciales de acceso de forma inmutable.

PHP

<?php

namespace App\Core\Auth\Infrastructure\DTOs;

readonly class LoginDTO
{
    public function __construct(
        public string $email,
        public string $password
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            email: strtolower(trim($data['email'])),
            password: $data['password']
        );
    }
}
Paso B: El Contrato del Repositorio (Capa de Dominio)
Ruta: app/Core/Auth/Domain/Contracts/AuthRepositoryInterface.php

PHP
<?php

namespace App\Core\Auth\Domain\Contracts;

use App\Models\User\User;

interface AuthRepositoryInterface
{
    public function findByEmail(string $email): ?User;
    
    public function createToken(User $user, string $tokenName): string;
    
    public function revokeCurrentToken(User $user): bool;
}
Paso C: Implementación del Repositorio (Capa de Infraestructura)
Ruta: app/Core/Auth/Infrastructure/Eloquent/AuthEloquentRepository.php

PHP
<?php

namespace App\Core\Auth\Infrastructure\Eloquent;

use App\Core\Auth\Domain\Contracts\AuthRepositoryInterface;
use App\Models\User\User;

class AuthEloquentRepository implements AuthRepositoryInterface
{
    public function findByEmail(string $email): ?User
    {
        // Regla de Oro: Siempre usar Eager Loading con el perfil biográfico
        return User::with('profile')->where('email', $email)->first();
    }

    public function createToken(User $user, string $tokenName): string
    {
        return $user->createToken($tokenName)->plainTextToken;
    }

    public function revokeCurrentToken(User $user): bool
    {
        return $user->currentAccessToken()?->delete() ?? false;
    }
}
Paso D: El Caso de Uso de Login (Capa de Aplicación)
Ruta: app/Core/Auth/Application/UseCases/LoginUseCase.php

Propósito: Validar credenciales, verificar que el usuario esté activo (status === true) antes de emitir tokens, y arrojar excepciones limpias.

PHP
<?php

namespace App\Core\Auth\Application\UseCases;

use App\Core\Auth\Domain\Contracts\AuthRepositoryInterface;
use App\Core\Auth\Infrastructure\DTOs\LoginDTO;
use Illuminate\Support\Facades\Hash;
use Exception;

class LoginUseCase
{
    public function __construct(
        private readonly AuthRepositoryInterface $authRepository
    ) {}

    public function execute(LoginDTO $dto): array
    {
        $user = $this->authRepository->findByEmail($dto->email);

        // 1. Validar existencia y contraseña
        if (!$user || !Hash::check($dto->password, $user->password)) {
            throw new Exception('Las credenciales proporcionadas son incorrectas.');
        }

        // 2. Regla de Oro: Validar obligatoriamente el estado antes de emitir token
        if (!$user->status) {
            throw new Exception('El acceso a este administrador se encuentra desactivado.');
        }

        // 3. Generar token de Sanctum
        $token = $this->authRepository->createToken($user, 'core_master_session');

        return [
            'token' => $token,
            'user'  => [
                'id'        => $user->id,
                'email'     => $user->email,
                'full_name' => $user->full_name, // Accessor del modelo
                'profile'   => $user->profile
            ]
        ];
    }
}
Paso E: El FormRequest y el Controlador HTTP
Ruta: app/Http/Requests/Auth/LoginRequest.php

Ruta: app/Http/Controllers/Core/Auth/AuthController.php

El controlador inyectará el caso de uso y expondrá los métodos login, logout y me retornando el formato de respuesta unificado de NovaCode Labs.

PHP
<?php

namespace App\Http\Controllers\Core\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Core\Auth\Application\UseCases\LoginUseCase;
use App\Core\Auth\Domain\Contracts\AuthRepositoryInterface;
use App\Core\Auth\Infrastructure\DTOs\LoginDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class AuthController extends Controller
{
    public function __construct(
        private readonly LoginUseCase $loginUseCase,
        private readonly AuthRepositoryInterface $authRepository
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $dto = LoginDTO::fromArray($request->validated());
            $result = $this->loginUseCase->execute($dto);

            return response()->json([
                'status'  => true,
                'message' => 'Autenticación exitosa. Sesión iniciada.',
                'data'    => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
                'data'    => null
            ], 401);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authRepository->revokeCurrentToken($request->user());

        return response()->json([
            'status'  => true,
            'message' => 'Sesión cerrada exitosamente.',
            'data'    => null
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('profile');
        
        return response()->json([
            'status'  => true,
            'message' => 'Datos del administrador autenticado obtenidos.',
            'data'    => [
                'id'        => $user->id,
                'email'     => $user->email,
                'full_name' => $user->full_name,
                'profile'   => $user->profile
            ]
        ]);
    }
}
🌐 3. Registro de Rutas y Binding (Laravel 13)
En routes/api.php:
Añadir las rutas de autenticación dentro del prefijo v1. Nota que logout y me quedan estrictamente protegidos por el middleware nativo auth:sanctum.

PHP
use App\Http\Controllers\Core\Auth\AuthController;

Route::prefix('v1')->group(function () {

    // 🔑 Rutas Públicas de Auth
    Route::post('auth/login', [AuthController::class, 'login']);

    // 🔒 Rutas Protegidas de Auth (Solo Administradores del Core activos)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
    });

});
En app/Providers/AppServiceProvider.php:
PHP
$this->app->bind(
    \App\Core\Auth\Domain\Contracts\AuthRepositoryInterface::class,
    \App\Core\Auth\Infrastructure\Eloquent\AuthEloquentRepository.php
);

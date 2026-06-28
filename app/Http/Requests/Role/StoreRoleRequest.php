<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class StoreRoleRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta petición.
     */
    public function authorize(): bool
    {
        return true; // Controlado previamente por middleware de Sanctum/Permisos
    }

    /**
     * Preparar los datos para la validación (Sanitización PRO).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                // Convierte "Administrador Sistema" o "test Slug" en "administrador_sistema" o "test_slug"
                'name' => Str::slug($this->input('name'), '_')
            ]);
        }
    }

    /**
     * Obtiene las reglas de validación que se aplican a la petición.
     */
    public function rules(): array
    {
        // 1. Extraemos el parámetro de la ruta de forma segura.
        // Laravel puede inyectar el modelo directamente u obtener el ID numérico plano.
        $roleParam = $this->route('role');
        $roleId = is_object($roleParam) ? $roleParam->id : $roleParam;

        return [
            'name'         => [
                'required', 
                'string', 
                'max:255', 
                // 2. Usamos Rule::unique de forma reactiva. Si $roleId es null (Store), 
                // genera una query limpia. Si tiene valor (Update), aplica el "ignore" seguro para PostgreSQL.
                Rule::unique('roles', 'name')->ignore($roleId)
            ],
            'display_name' => [
                'required', 
                'string', 
                'max:255'
            ],
            'guard_name'   => [
                'nullable', 
                'string', 
                'max:255'
            ],
        ];
    }

    /**
     * Mensajes personalizados de error para la API.
     */
    public function messages(): array
    {
        return [
            'name.required'         => 'El nombre técnico del rol es obligatorio.',
            'name.unique'           => 'Este nombre técnico de rol ya está registrado en el sistema.',
            'display_name.required' => 'El nombre visible (etiqueta) del rol es obligatorio.',
        ];
    }

    /**
     * Falla de validación en formato JSON limpio para PrimeReact.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'status'  => false,
                'message' => 'Errores de validación en el formulario.',
                'errors'  => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY)
        );
    }
}
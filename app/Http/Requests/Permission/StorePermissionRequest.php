<?php

namespace App\Http\Requests\Permission;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class StorePermissionRequest extends FormRequest
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
        // Interceptamos el ID de la ruta en caso de actualización (PUT/PATCH)
        $permissionParam = $this->route('permission');
        $permissionId = is_object($permissionParam) ? $permissionParam->id : $permissionParam;

        return [
            'name'         => [
                'required', 
                'string', 
                'max:255', 
                Rule::unique('permissions', 'name')->ignore($permissionId)
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
            'name.required'         => 'El nombre técnico del permiso es obligatorio.',
            'name.unique'           => 'Este nombre técnico de permiso ya está registrado en el sistema.',
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
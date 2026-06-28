<?php

namespace App\Http\Requests\RolePermission;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Symfony\Component\HttpFoundation\Response;

class SyncRolePermissionsRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta petición.
     */
    public function authorize(): bool
    {
        return true; // Controlado previamente por middleware
    }

    /**
     * Obtiene las reglas de validación que se aplican a la petición de sincronización.
     */
    public function rules(): array
    {
        return [
            'role_id'          => [
                'required',
                'integer',
                'exists:roles,id' // Garantiza que el rol exista antes de tocar la pivote
            ],
            'permissions_ids'  => [
                'present',
                'array',
            ],
            'permissions_ids.*'=> [
                'integer',
                'exists:permissions,id' // Garantiza que cada ID de permiso enviado exista en la BD
            ]
        ];
    }

    /**
     * Mensajes personalizados de error para el flujo de la pivote.
     */
    public function messages(): array
    {
        return [
            'role_id.required'    => 'El rol a asignar es obligatorio.',
            'role_id.exists'      => 'El rol seleccionado no es válido o no existe.',
            'permissions_ids.array' => 'Los permisos deben ser enviados en formato de lista (arreglo).',
            'permissions_ids.*.exists' => 'Uno o más de los permisos seleccionados no existen en el sistema.',
        ];
    }

    /**
     * Falla de validación en formato JSON unificado.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'status'  => false,
                'message' => 'Errores en la asignación de permisos.',
                'errors'  => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY)
        );
    }
}
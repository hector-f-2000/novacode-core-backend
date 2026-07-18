<?php

namespace App\Http\Requests\Tenants;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Symfony\Component\HttpFoundation\Response;

class RegisterTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rut'         => ['required', 'string', 'max:20', 'unique:tenants,rut'],
            'razon_social' => ['required', 'string', 'max:255'],
            'giro'        => ['required', 'string', 'max:255'],
            'address'     => ['required', 'string', 'max:255'],
            'slug'        => ['required', 'string', 'max:100', 'unique:tenants,slug', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'plan_id'     => ['required', 'integer', 'exists:plans,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'rut.required'      => 'El RUT de la empresa es obligatorio.',
            'rut.unique'        => 'Este RUT ya se encuentra registrado.',
            'razon_social.required' => 'La razón social es obligatoria.',
            'slug.required'     => 'El slug es obligatorio.',
            'slug.unique'       => 'Este slug ya está en uso.',
            'slug.regex'        => 'El slug solo puede contener letras minúsculas, números y guiones medios.',
            'plan_id.required'  => 'El plan es obligatorio.',
            'plan_id.exists'    => 'El plan seleccionado no es válido.',
        ];
    }

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

<?php

namespace App\Http\Requests\Parametros;

use Illuminate\Foundation\Http\FormRequest;

class UpdateParametrosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
{
    return [
        'company_legal_name'         => 'required|string|max:255',
        'company_rut'                => ['required', 'string', 'regex:/^\d{7,8}-[0-9Kk]$/'],
        'company_business_activity'  => 'required|string',
        'company_address'            => 'required|string|max:255',
        'company_email'              => 'required|email|max:255',
        'company_contact_name'       => 'required|string|max:255',
        'company_legal_representative'=> 'required|string|max:255',
        'company_rep_rut'            => ['required', 'string', 'regex:/^\d{7,8}-[0-9Kk]$/'],
        'company_website'            => 'nullable|string|max:255',
        'company_slogan'             => 'nullable|string|max:255',
        
        // Aumentamos el rango de aceptación a 15MB de forma segura
        'company_logo_path'          => 'nullable|image|mimes:jpeg,png,jpg,webp|max:15360',
    ];
}
}
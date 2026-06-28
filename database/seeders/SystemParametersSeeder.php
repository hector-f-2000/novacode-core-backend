<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemParametersSeeder extends Seeder
{
    public function run(): void
    {
        $parameters = [
            [
                'key' => 'company_legal_name',
                'label' => 'Razón Social',
                'type' => 'text',
                'group' => 'identidad',
                'value' => null,
            ],
            [
                'key' => 'company_business_activity',
                'label' => 'Giro Comercial',
                'type' => 'text',
                'group' => 'identidad',
                'value' => null,
            ],
            [
                'key' => 'company_rut',
                'label' => 'RUT Empresa',
                'type' => 'text', // Usamos text para poder aplicar máscaras en el front si es necesario
                'group' => 'identidad',
                'value' => null,
            ],
            [
                'key' => 'company_address',
                'label' => 'Dirección Casa Matriz',
                'type' => 'text',
                'group' => 'identidad',
                'value' => null,
            ],
            [
                'key' => 'company_website',
                'label' => 'Sitio Web',
                'type' => 'url',
                'group' => 'identidad',
                'value' => null,
            ],
            [
                'key' => 'company_email',
                'label' => 'Correo Electrónico Corporativo',
                'type' => 'email',
                'group' => 'identidad',
                'value' => null,
            ],
            [
                'key' => 'company_contact_name',
                'label' => 'Nombre de Contacto Administrativo',
                'type' => 'text',
                'group' => 'identidad',
                'value' => null,
            ],
            [
                'key' => 'company_legal_representative',
                'label' => 'Representante Legal',
                'type' => 'text',
                'group' => 'identidad',
                'value' => null,
            ],
            [
                'key' => 'company_rep_rut',
                'label' => 'RUT Representante Legal',
                'type' => 'text',
                'group' => 'identidad',
                'value' => null,
            ],
            [
                'key' => 'company_slogan',
                'label' => 'Eslogan Institucional',
                'type' => 'text',
                'group' => 'identidad',
                'value' => null,
            ],
            [
                'key' => 'company_logo_path',
                'label' => 'Logo Corporativo',
                'type' => 'file', // Clave para activar el cargador de imágenes en React
                'group' => 'identidad',
                'value' => null,
            ],
        ];

        foreach ($parameters as $param) {
            DB::table('system_parameters')->updateOrInsert(
                ['key' => $param['key']], // Condición para no duplicar si se vuelve a correr
                [
                    'label' => $param['label'],
                    'type' => $param['type'],
                    'group' => $param['group'],
                    'value' => $param['value'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
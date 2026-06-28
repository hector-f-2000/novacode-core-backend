<?php

namespace App\Services\Parametros;

use App\DTOs\Parametros\ParametrosDTO;
use App\Models\Parametros\SystemParameter; // Tu modelo de parámetros clave->valor
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SystemParameterService
{
    public function updateBulkParameters(ParametrosDTO $dto): void
    {
        DB::transaction(function () use ($dto) {
            // 1. Obtener el array mapeado de valores de texto
            $parameters = $dto->toArray();

            // 2. Procesar la carga del logotipo si viene en el DTO
            if ($dto->logoFile) {
                // Obtener logo antiguo para no acumular basura en el disco
                $oldLogo = SystemParameter::where('key', 'company_logo_path')->value('value');
                if ($oldLogo) {
                    Storage::disk('public')->delete($oldLogo);
                }

                // Guardar el nuevo logotipo en la carpeta public/branding
                $path = $dto->logoFile->store('branding', 'public');
                $parameters['company_logo_path'] = $path;
            }

            // 3. Upsert o actualización masiva llave-valor en la base de datos
            foreach ($parameters as $key => $value) {
                SystemParameter::updateOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                );
            }
        });
    }

    /**
     * Recupera todos los parámetros del sistema mapeados en un arreglo asociativo continuo.
     */
    public function getAllParameters(): array
    {
        // Pluck extrae un mapa directo ['key' => 'value'] de la base de datos
        return \App\Models\Parametros\SystemParameter::pluck('value', 'key')->toArray();
    }
}
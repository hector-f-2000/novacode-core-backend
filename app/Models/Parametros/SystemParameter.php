<?php

namespace App\Models\Parametros;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemParameter extends Model
{
    use HasFactory;

    // Nombre explícito de la tabla
    protected $table = 'system_parameters';

    // Campos habilitados para asignación masiva
    protected $fillable = [
        'key',
        'value',
        'label',
        'type',
        'group',
    ];

    /**
     * Opcional: Si en el futuro agregas arreglos o configuraciones en formato JSON,
     * este cast te ayudará a manejarlo automáticamente como array de PHP.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

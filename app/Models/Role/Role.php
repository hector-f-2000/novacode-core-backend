<?php

namespace App\Models\Role;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Permission\Permission; // Asumiendo que Permission seguirá este mismo orden en su subcarpeta

class Role extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'roles';

    /**
     * Atributos asignables de forma masiva (Mass Assignment).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'guard_name',
        'display_name',
    ];

    /**
     * Relación Muchos a Muchos con los Permisos (Tabla Pivote: role_has_permissions).
     *
     * @return BelongsToMany
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'role_has_permissions',
            'role_id',
            'permission_id'
        );
    }
}
<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'user_id',
    'firstname',
    'lastname',
    'phone',
    'address',
    'avatar',
    'settings'
])]
class UserProfile extends Model
{
    use HasFactory;

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     */
    protected function casts(): array
    {
      return [
        'settings' => 'json', // Permite manipular el campo metadata como un array de PHP
      ];
    }

    /**
     * Relación inversa: Un perfil pertenece a un usuario.
     */
    public function user()
    {
      return $this->belongsTo(User::class);
    }
}
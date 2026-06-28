<?php

namespace App\Models\User;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes; // No olvides importar esto
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Permission\Models\Role;
use App\Models\Security\SecurityAuditLog;
use App\Models\Sanctum\PersonalAccessToken;

#[Fillable([
    'role_id',
    'username',
    'email',
    'password',
    'status',
    'last_login_at',
    'last_login_ip',
    'fcm_token',
    'device_id',
    'device_type'
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens, SoftDeletes; // Habilitar borrado lógico

    protected $appends = ['full_name', 'created_at_formatted'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean', // Asegura que llegue como true/false a React
        ];
    }

    /**
     * Relación uno a uno con el perfil del usuario.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * Relación con el Rol
     * Un usuario pertenece a un solo rol (convención Spatie o personalizada)
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Relación polimórfica: Un usuario tiene muchos tokens de acceso personal.
     * Usa MorphMany para que Sanctum inserte automáticamente tokenable_type.
     */
    public function tokens(): MorphMany
    {
        return $this->morphMany(PersonalAccessToken::class, 'tokenable');
    }

    /**
     * Relación: Un usuario tiene muchos logs de auditoría.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(SecurityAuditLog::class);
    }

    /**
     * Método helper: Obtener sesiones activas (tokens no revocados).
     */
    public function getActiveSessions()
    {
        return $this->tokens()
            ->where('is_revoked', false)
            ->orderByDesc('last_used_at')
            ->get();
    }

    /**
     * Accessor para obtener el nombre completo del usuario.
     * Combina el nombre y apellido desde la tabla de perfiles.
     */
    public function getFullNameAttribute()
    {
        // Si el usuario tiene perfil, combinamos nombres; si no, devolvemos el username
        if ($this->profile) {
            return "{$this->profile->firstname} {$this->profile->lastname}";
        }

        return $this->username;
    }

    /**
     * Accessor para la fecha de creación formateada.
     */
    public function getCreatedAtFormattedAttribute(){
        // Formato: 05/05/2026 21:15
        return $this->created_at->format('d/m/Y H:i');
    }
}

<?php

namespace App\Core\Tenants\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Tenant\TenantUser;

class Tenant extends Model
{
    use SoftDeletes;

    protected $table = 'tenants';

    protected $fillable = [];

    public function tenantUsers(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }
}

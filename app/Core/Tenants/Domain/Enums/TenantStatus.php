<?php

namespace App\Core\Tenants\Domain\Enums;

enum TenantStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case CANCELLED = 'cancelled';

    public static function getFormOptions(): array
    {
        return array_map(fn($status) => [
            'label' => match($status) {
                self::ACTIVE => 'Activo',
                self::SUSPENDED => 'Suspendido Global',
                self::CANCELLED => 'Cancelado / Inactivo',
            },
            'value' => $status->value
        ], self::cases());
    }
}

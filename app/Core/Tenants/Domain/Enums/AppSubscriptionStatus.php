<?php

namespace App\Core\Tenants\Domain\Enums;

enum AppSubscriptionStatus: string
{
    case TRIAL = 'trial';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case EXPIRED = 'expired';

    public static function getFormOptions(): array
    {
        return array_map(fn($status) => [
            'label' => match($status) {
                self::TRIAL => 'Período de Prueba',
                self::ACTIVE => 'Suscripción Activa',
                self::SUSPENDED => 'Suscripción Suspendida',
                self::EXPIRED => 'Suscripción Vencida',
            },
            'value' => $status->value
        ], self::cases());
    }
}

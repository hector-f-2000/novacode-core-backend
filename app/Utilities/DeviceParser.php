<?php

namespace App\Utilities;

use Jenssegers\Agent\Agent;

class DeviceParser
{
    /**
     * Parsea el User-Agent y retorna información del dispositivo.
     */
    public static function parse(?string $userAgent): array
    {
        if (!$userAgent) {
            return [
                'os' => 'Unknown',
                'browser' => 'Unknown',
                'device_name' => 'Unknown Device',
                'device_type' => 'unknown',
            ];
        }

        $agent = new Agent();
        $agent->setUserAgent($userAgent);

        $os = $agent->platform() ?: 'Unknown OS';
        $browser = $agent->browser() ?: 'Unknown Browser';

        // Obtener versión del SO si está disponible
        $osVersion = $agent->version($agent->platform());
        if ($osVersion) {
            $os = "{$os} {$osVersion}";
        }

        // Obtener versión del navegador si está disponible
        $browserVersion = $agent->version($agent->browser());
        if ($browserVersion) {
            $browser = "{$browser} {$browserVersion}";
        }

        // Nombre amigable del dispositivo: "Windows 11 - Chrome"
        $deviceName = "{$os} - {$browser}";

        // Tipo de dispositivo
        $deviceType = 'desktop';
        if ($agent->isPhone()) {
            $deviceType = 'mobile';
        } elseif ($agent->isTablet()) {
            $deviceType = 'tablet';
        }

        return [
            'os' => trim($os),
            'browser' => trim($browser),
            'device_name' => trim($deviceName),
            'device_type' => $deviceType,
        ];
    }
}

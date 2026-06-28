<?php

namespace App\Utilities;

use Illuminate\Support\Facades\Cache;

class GeoIPParser
{
    /**
     * Servicio gratuito de geolocalización por IP usando geoip-lite.
     * Retorna información de ubicación basada en la dirección IP.
     */
    public static function getLocation(?string $ipAddress): array
    {
        if (!$ipAddress || $ipAddress === '127.0.0.1' || $ipAddress === 'localhost') {
            return [
                'city' => 'Local',
                'country_code' => 'LOCAL',
                'country' => 'Local Environment',
                'latitude' => null,
                'longitude' => null,
            ];
        }

        // Validar que sea una IP válida
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            return [
                'city' => 'Unknown',
                'country_code' => 'UNKNOWN',
                'country' => 'Unknown',
                'latitude' => null,
                'longitude' => null,
            ];
        }

        // Usar caché para no consultar el servicio múltiples veces
        $cacheKey = "geoip:{$ipAddress}";

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($ipAddress) {
            return self::queryGeoIPService($ipAddress);
        });
    }

    /**
     * Consulta el servicio de geolocalización.
     * Implementación con ip-api.com (free tier, 45 req/min).
     */
    private static function queryGeoIPService(string $ipAddress): array
    {
        try {
            $response = @file_get_contents("http://ip-api.com/json/{$ipAddress}");

            if ($response === false) {
                return self::getDefaultLocation();
            }

            $data = json_decode($response, true);

            if ($data && $data['status'] === 'success') {
                return [
                    'city' => $data['city'] ?? 'Unknown',
                    'country_code' => $data['countryCode'] ?? 'UNKNOWN',
                    'country' => $data['country'] ?? 'Unknown',
                    'latitude' => $data['lat'] ?? null,
                    'longitude' => $data['lon'] ?? null,
                ];
            }

            return self::getDefaultLocation();
        } catch (\Exception $e) {
            // Si falla, retornar ubicación por defecto
            return self::getDefaultLocation();
        }
    }

    /**
     * Retorna ubicación por defecto cuando no se puede obtener la real.
     */
    private static function getDefaultLocation(): array
    {
        return [
            'city' => 'Unknown',
            'country_code' => 'UNKNOWN',
            'country' => 'Unknown',
            'latitude' => null,
            'longitude' => null,
        ];
    }
}

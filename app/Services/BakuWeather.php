<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Bakının indiki havası (Open-Meteo, açar tələb etmir) — Stəkan qülləsində hava effektləri üçün.
 * Nəticə keşdə saxlanılır ki, hər səhifə açılışında xidmətə sorğu getməsin.
 * Xidmət cavab verməsə null qaytarılır və oyun effektsiz açılır.
 */
class BakuWeather
{
    /** Neçə km/saat küləkdən sonra "küləkli" sayılır */
    public const WINDY_FROM = 25;

    /** Test və baxış üçün ?hava=... ilə seçilə bilən növlər */
    public const KINDS = ['clear', 'clouds', 'rain', 'storm', 'snow', 'fog'];

    private const LABELS = [
        'clear' => 'Günəşli',
        'clouds' => 'Buludlu',
        'rain' => 'Yağışlı',
        'storm' => 'İldırımlı',
        'snow' => 'Qarlı',
        'fog' => 'Dumanlı',
    ];

    public function current(): ?array
    {
        if (!config('games.weather.enabled')) {
            return null;
        }

        $key = 'baku_weather';
        if (Cache::has($key)) {
            return Cache::get($key) ?: null;
        }

        $weather = $this->fetch();
        // uğursuz cavabı da qısa müddətə yadda saxlayırıq ki, xidmət işləməyəndə səhifə ləngiməsin
        Cache::put($key, $weather ?? false, now()->addMinutes($weather ? config('games.weather.cache_minutes', 20) : 2));

        return $weather;
    }

    private function fetch(): ?array
    {
        try {
            // xidmət bəzən 3-4 saniyəyə cavab verir; IPv4 ilə bağlanmaq əlaqəni sürətləndirir
            $r = Http::connectTimeout(5)->timeout(10)->withOptions(['force_ip_resolve' => 'v4'])
                ->get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => config('games.weather.lat'),
                'longitude' => config('games.weather.lon'),
                'current' => 'temperature_2m,weather_code,wind_speed_10m,wind_direction_10m,is_day',
                'timezone' => 'Asia/Baku',
            ]);
            $c = $r->successful() ? $r->json('current') : null;
            if (!is_array($c) || !isset($c['weather_code'])) {
                return null;
            }

            return self::make(
                self::kindFromCode((int) $c['weather_code']),
                (float) ($c['wind_speed_10m'] ?? 0),
                (int) ($c['wind_direction_10m'] ?? 0),
                isset($c['temperature_2m']) ? (float) $c['temperature_2m'] : null,
                (bool) ($c['is_day'] ?? true),
            );
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** WMO hava kodunu oyundakı effekt növünə çevirir */
    public static function kindFromCode(int $code): string
    {
        return match (true) {
            $code <= 1 => 'clear',
            $code <= 3 => 'clouds',
            $code === 45 || $code === 48 => 'fog',
            ($code >= 71 && $code <= 77) || $code === 85 || $code === 86 => 'snow',
            $code >= 95 => 'storm',
            ($code >= 51 && $code <= 67) || ($code >= 80 && $code <= 82) => 'rain',
            default => 'clouds',
        };
    }

    public static function make(string $kind, float $wind, int $windDir, ?float $temp, bool $day): array
    {
        $windy = $wind >= self::WINDY_FROM;
        $label = self::LABELS[$kind] ?? 'Buludlu';
        if ($windy) {
            $label .= ', küləkli';
        }

        return [
            'kind' => $kind,
            'wind' => round($wind, 1),
            'windDir' => $windDir,
            'windy' => $windy,
            'temp' => $temp === null ? null : (int) round($temp),
            'day' => $day,
            'label' => $label,
        ];
    }

    /** ?hava=rain, ?hava=clear-wind kimi baxış rejimi (canlı havanı gözləmədən effekti görmək üçün) */
    public static function preview(?string $value): ?array
    {
        if (!$value) {
            return null;
        }
        [$kind, $extra] = array_pad(explode('-', strtolower($value), 2), 2, null);
        if ($kind === 'wind') {
            [$kind, $extra] = ['clouds', 'wind'];
        }
        if (!in_array($kind, self::KINDS, true)) {
            return null;
        }
        $night = $extra === 'night';

        return self::make($kind, $extra === 'wind' ? 45 : 8, 0, 15, !$night);
    }
}

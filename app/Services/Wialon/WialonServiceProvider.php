<?php

namespace App\Services\Wialon;

use Illuminate\Support\ServiceProvider;

class WialonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WialonService::class, function ($app): WialonService {
            /** @var array<string, mixed> $cfg */
            $cfg = $app['config']->get('services.wialon', []);

            return new WialonService(
                baseUrl: (string) ($cfg['base_url'] ?? 'https://hst-api.wialon.com'),
                token: $cfg['token'] ?? null,
                recurso: (string) ($cfg['resource'] ?? 'TECAVI'),
                sidTtlMinutes: (int) ($cfg['sid_ttl'] ?? 5),
            );
        });
    }
}

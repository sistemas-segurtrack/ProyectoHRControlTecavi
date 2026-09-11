<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureUrlGeneration();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Fuerza `route()`/`url()`/redirects a usar APP_URL tal cual, con su
     * eventual subpath (p. ej. `/hrcontrol`).
     *
     * Sin esto, Laravel calcula la raíz de las URLs a partir del host de la
     * petición entrante, que ya llega SIN el prefijo (Apache lo recorta antes
     * de reenviar al contenedor) — cualquier `redirect()->route(...)` o
     * `route('login')` generado durante una petición real perdía el
     * "/hrcontrol" y el navegador terminaba pidiendo `/login` a Apache, que
     * no tiene ese path y devuelve su propio 404 ("Not Found").
     */
    protected function configureUrlGeneration(): void
    {
        $appUrl = config('app.url');

        if (! is_string($appUrl) || $appUrl === '') {
            return;
        }

        URL::forceRootUrl($appUrl);

        $scheme = parse_url($appUrl, PHP_URL_SCHEME);
        if (is_string($scheme)) {
            URL::forceScheme($scheme);
        }
    }
}

<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

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
        $this->configureInertiaUrl();
        $this->configureGuestRedirect();
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

    /**
     * Antepone el subpath de `APP_URL` (p. ej. `/hrcontrol`) a la `url` que
     * Inertia comparte en cada página.
     *
     * `Inertia\Response::getUrl()` la arma con `$request->fullUrl()`, que lee
     * directo de la petición ya recortada por el `ProxyPass` de Apache — no
     * pasa por `route()`/`url()`, así que `forceRootUrl()` no lo alcanza. Sin
     * esto, el cliente de Inertia hace `history.replaceState(..., page.url)`
     * al hidratar con `page.url = "/"`, y la barra de direcciones "pierde" el
     * `/hrcontrol` apenas carga la página.
     */
    /**
     * A dónde manda el middleware `guest` (p. ej. una visita a /login ya
     * autenticada) cuando no se indica nada más.
     *
     * Por defecto, `RedirectIfAuthenticated` busca una ruta nombrada
     * "dashboard" o, si no existe, "home" — acá "home" es
     * `Route::redirect('/', '/login')`, así que sin este override un usuario
     * ya logueado en /login quedaría rebotando /login → / → /login sin fin
     * ahora que la ruta "dashboard" ya no existe.
     */
    protected function configureGuestRedirect(): void
    {
        RedirectIfAuthenticated::redirectUsing(
            fn () => config('fortify.home'),
        );
    }

    protected function configureInertiaUrl(): void
    {
        // El prefijo se relee en cada petición (no se fija una vez al boot)
        // para que un cambio de `app.url` en tiempo de ejecución (tests,
        // `config()->set()`) también se refleje sin tener que reiniciar la
        // app.
        Inertia::resolveUrlUsing(function (Request $request): string {
            $appUrl = config('app.url');
            $prefix = is_string($appUrl)
                ? rtrim((string) parse_url($appUrl, PHP_URL_PATH), '/')
                : '';

            $url = Str::start(Str::after($request->fullUrl(), $request->getSchemeAndHttpHost()), '/');

            return $prefix === '' ? $url : $prefix.$url;
        });
    }
}

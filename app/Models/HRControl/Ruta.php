<?php

namespace App\Models\HRControl;

use Closure;
use Database\Factories\HRControl\RutaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property string $idruta
 * @property string|null $placa
 * @property string|null $piloto
 * @property string|null $copiloto
 * @property string|null $precintos
 * @property string|null $carreta
 * @property string|null $estado
 */
class Ruta extends Model
{
    /** @use HasFactory<RutaFactory> */
    use HasFactory;

    /**
     * Hoja de ruta abierta (en curso).
     */
    public const ACTIVA = 'A';

    /**
     * Hoja de ruta cerrada: un documento con `condicionaFin = 1` la finalizó.
     */
    public const FINALIZADA = 'F';

    protected $table = 'ruta';

    protected $primaryKey = 'idruta';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'idruta',
        'placa',
        'piloto',
        'copiloto',
        'precintos',
        'carreta',
        'estado',
    ];

    /**
     * @return HasMany<DetalleRuta, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleRuta::class, 'ruta_idruta', 'idruta');
    }

    /**
     * Kilometraje de la última parada registrada en la hoja, sin importar el
     * tramo (el odómetro es uno solo). `null` si no hay paradas o la última
     * no tiene kilometraje (registros anteriores a que fuera obligatorio).
     */
    public function kilometrajeUltimaParada(): ?int
    {
        $kilometraje = $this->detalles()->orderByDesc('orden')->value('kilometraje');

        return is_numeric($kilometraje) ? (int) $kilometraje : null;
    }

    /**
     * Siguiente código correlativo para una hoja de ruta: T000001, T000002, …
     */
    public static function siguienteCodigo(): string
    {
        $ultimo = static::query()
            ->where('idruta', 'like', 'T%')
            ->orderByRaw('LENGTH(idruta) DESC')
            ->orderByDesc('idruta')
            ->value('idruta');

        $n = is_string($ultimo) && ctype_digit(substr($ultimo, 1))
            ? ((int) substr($ultimo, 1)) + 1
            : 1;

        return 'T'.str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }

    /**
     * ID de la hoja de ruta EN RUTA de verdad (tramo abierto, sin cerrar)
     * para esta placa ahora mismo, sin importar qué conductor la inició, o
     * `null` si la unidad está libre para empezar una hoja desde cero —
     * aunque tenga otra hoja ACTIVA con todos sus tramos ya cerrados,
     * esperando el documento que la finalice (ver `conTramoAbiertoSinCerrar()`).
     */
    public static function idEnRutaPorPlaca(string $placa): ?string
    {
        return static::query()
            ->where('placa', $placa)
            ->whereHas('detalles', self::conTramoAbiertoSinCerrar())
            ->value('idruta');
    }

    /**
     * Placa => idruta de cada hoja EN RUTA de verdad ahora mismo (mismo
     * criterio que `idEnRutaPorPlaca()`) — el catálogo que la PWA cachea
     * para avisar al elegir la unidad en "Nueva Ruta" (`ResuelveCatalogos`),
     * antes de tocar el servidor.
     *
     * @return Collection<string, string>
     */
    public static function unidadesEnRuta(): Collection
    {
        return static::query()
            ->whereNotNull('placa')
            ->whereHas('detalles', self::conTramoAbiertoSinCerrar())
            ->pluck('idruta', 'placa');
    }

    /**
     * Hoja ACTIVA más reciente de esta placa que ya no tiene ningún tramo
     * abierto (todas sus paradas cerradas, esperando el documento que la
     * finalice), o `null`. Una hoja nueva sobre esa unidad hereda su
     * copiloto, precintos y carreta.
     */
    public static function activaSinTramoAbiertoPorPlaca(string $placa): ?self
    {
        return self::activasSinTramoAbierto()->where('placa', $placa)->first();
    }

    /**
     * Placa => datos que hereda una hoja nueva de esa unidad (ver
     * `activaSinTramoAbiertoPorPlaca()`) — el catálogo que la PWA cachea para
     * completarlos y bloquearlos al instante en "Nueva Ruta", aun sin señal.
     *
     * @return array<string, array{idruta: string, copiloto: ?string, precintos: ?string, carreta: ?string}>
     */
    public static function datosHeredablesPorPlaca(): array
    {
        $datos = [];

        $rutas = self::activasSinTramoAbierto()
            ->whereNotNull('placa')
            ->get(['idruta', 'placa', 'copiloto', 'precintos', 'carreta']);

        foreach ($rutas as $ruta) {
            // Vienen de la más reciente a la más antigua: queda la primera por placa.
            $datos[(string) $ruta->placa] ??= [
                'idruta' => $ruta->idruta,
                'copiloto' => $ruta->copiloto,
                'precintos' => $ruta->precintos,
                'carreta' => $ruta->carreta,
            ];
        }

        return $datos;
    }

    /**
     * @return Builder<static>
     */
    private static function activasSinTramoAbierto(): Builder
    {
        return static::query()
            ->where('estado', self::ACTIVA)
            ->whereHas('detalles')
            ->whereDoesntHave('detalles', self::conTramoAbiertoSinCerrar())
            ->orderByRaw('LENGTH(idruta) DESC')
            ->orderByDesc('idruta');
    }

    /**
     * "En ruta de verdad" = la parada de MAYOR orden de la hoja (la última
     * registrada) sigue `EN_RUTA` a nivel crudo Y además es IMPAR (abre un
     * tramo). Una parada PAR con ese mismo estado crudo solo sigue así
     * porque nadie registró todavía la parada siguiente — el observer
     * (`DetalleRutaObserver`) recién la pasa a `FINALIZADO` en ese momento —
     * pero ya cerró su propio tramo, así que NO cuenta como "en curso". Es
     * el mismo criterio que ya corrige el encabezado del listado admin
     * (`ConsultaHojasRuta::transformarRuta()`) y el badge de cada parada en
     * el PWA (`ContinuarPage.vue::estadoAvance()`).
     *
     * @return Closure(Builder<DetalleRuta>): Builder<DetalleRuta>
     */
    private static function conTramoAbiertoSinCerrar(): Closure
    {
        return fn (Builder $q): Builder => $q
            ->where('estado', DetalleRuta::EN_RUTA)
            ->whereRaw('detalleruta.orden % 2 = 1')
            ->whereRaw('detalleruta.orden = (select max(d2.orden) from detalleruta d2 where d2.ruta_idruta = detalleruta.ruta_idruta)');
    }
}

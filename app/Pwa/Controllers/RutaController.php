<?php

namespace App\Pwa\Controllers;

use App\Events\HojaRutaCreada;
use App\Events\HojaRutaFinalizada;
use App\Events\TramoRutaCerrado;
use App\Http\Controllers\Controller;
use App\Jobs\Wialon\ActualizarContadorKilometrajeJob;
use App\Models\HRControl\DetalleRuta;
use App\Models\HRControl\Ruta;
use App\Models\HRControl\TipoDocumento;
use App\Models\WialonSTK\WialonConductor;
use App\Models\WialonSTK\WialonUnidad;
use App\Pwa\Controllers\Concerns\GuardaDocumentoRuta;
use App\Pwa\Controllers\Concerns\ResuelveCatalogos;
use App\Pwa\Models\PwaRuta;
use App\Pwa\Requests\CrearRutaRequest;
use App\Pwa\Requests\RegistrarOrdenRequest;
use App\Pwa\Resources\RutaResource;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RutaController extends Controller
{
    use GuardaDocumentoRuta;
    use ResuelveCatalogos;

    /**
     * Intentos para crear una hoja cuando otro envío simultáneo tomó el mismo
     * código T###### (ver `store()`).
     */
    private const INTENTOS_CODIGO = 3;

    /**
     * Hoja de ruta en curso del conductor (o `null`).
     */
    public function activa(Request $request): JsonResponse
    {
        /** @var WialonConductor $conductor */
        $conductor = $request->user();

        $ruta = $this->rutaActiva($conductor);

        if ($ruta === null) {
            return response()->json(['data' => null]);
        }

        return (new RutaResource($ruta))->response();
    }

    /**
     * "Nueva Ruta": crea la hoja de ruta + su primer orden (EN RUTA), con un
     * documento adjunto opcional que puede finalizarla de inmediato. Si la
     * unidad ya tiene una hoja ACTIVA sin tramos abiertos, en vez de crear
     * otra se sigue esa (ver `seguirHoja()`).
     */
    public function store(CrearRutaRequest $request): JsonResponse
    {
        /** @var WialonConductor $conductor */
        $conductor = $request->user();
        $datos = $request->validated();
        $key = trim((string) $request->header('Idempotency-Key', '')) ?: null;

        if ($key !== null && $previa = PwaRuta::where('idempotency_key', $key)->first()) {
            return $this->respuesta($previa->ruta_idruta, 200);
        }

        if ($this->rutaActiva($conductor) !== null) {
            return response()->json(['message' => 'Ya tienes una hoja de ruta en curso.'], 409);
        }

        $tipo = $this->tipoDocumentoAdjunto($request);
        $finaliza = $this->documentoFinaliza($tipo);

        $hoja = Ruta::activaSinTramoAbiertoPorPlaca($datos['placa']);
        if ($hoja !== null) {
            return $this->seguirHoja($request, $conductor, $hoja, $datos, $key, $tipo, $finaliza);
        }

        $crear = function () use ($conductor, $datos, $key, $request, $tipo, $finaliza): Ruta {
            $ruta = Ruta::create([
                'idruta' => Ruta::siguienteCodigo(),
                'placa' => $datos['placa'],
                'piloto' => $conductor->nombre,
                'copiloto' => $datos['copiloto'] ?? null,
                'precintos' => $datos['precintos'] ?? null,
                'carreta' => $datos['carreta'] ?? null,
                'estado' => $finaliza ? Ruta::FINALIZADA : Ruta::ACTIVA,
            ]);

            $this->registrarParada($ruta, $datos, $request, $tipo, $finaliza);

            PwaRuta::create([
                'ruta_idruta' => $ruta->idruta,
                'wialon_conductor_id' => $conductor->getKey(),
                'idempotency_key' => $key,
            ]);

            return $ruta;
        };

        // El código T###### lo arma el servidor (nunca el celular: sin señal la
        // hoja vive como LOCAL-xxxx hasta sincronizar) leyendo el último y
        // sumando uno. Si dos envíos llegan a la vez (p. ej. dos celulares que
        // recuperan señal juntos) ambos pueden leer el mismo último código: el
        // que inserta segundo choca con la clave única y se reintenta con el
        // siguiente libre, en vez de devolver un 500 que dejaría ese envío
        // marcado con error en la cola offline.
        $intentos = 0;
        while (true) {
            try {
                $ruta = DB::transaction($crear);
                break;
            } catch (UniqueConstraintViolationException $e) {
                // O fue este mismo envío repetido (misma Idempotency-Key) y el
                // otro ya lo registró: se devuelve ese.
                if ($key !== null && $previa = PwaRuta::where('idempotency_key', $key)->first()) {
                    return $this->respuesta($previa->ruta_idruta, 200);
                }

                if (++$intentos >= self::INTENTOS_CODIGO) {
                    throw $e;
                }
            }
        }

        // Notificaciones (no bloquean la respuesta: van a la cola).
        HojaRutaCreada::dispatch($ruta->idruta);
        if ($finaliza) {
            HojaRutaFinalizada::dispatch($ruta->idruta);
        }
        $this->empujarContadorSiCorresponde($ruta->placa, $datos['kilometraje'] ?? null);

        return $this->respuesta($ruta->idruta, 201);
    }

    /**
     * "Continuar": registra la siguiente orden de la hoja de ruta del conductor.
     */
    public function orden(RegistrarOrdenRequest $request, string $ruta): JsonResponse
    {
        /** @var WialonConductor $conductor */
        $conductor = $request->user();
        $datos = $request->validated();

        PwaRuta::query()
            ->where('wialon_conductor_id', $conductor->getKey())
            ->where('ruta_idruta', $ruta)
            ->firstOrFail();

        /** @var Ruta $modelo */
        $modelo = Ruta::query()->findOrFail($ruta);

        $tipo = $this->tipoDocumentoAdjunto($request);
        $finaliza = $this->documentoFinaliza($tipo);

        $numeroOrden = DB::transaction(function () use ($modelo, $datos, $request, $tipo, $finaliza): int {
            $this->bloquearHoja($modelo);
            $request->asegurarKilometrajeMayorQue($modelo->kilometrajeUltimaParada());

            return $this->registrarParada($modelo, $datos, $request, $tipo, $finaliza);
        });

        $this->avisarParada($ruta, $numeroOrden, $finaliza);
        $this->empujarContadorSiCorresponde($modelo->placa, $datos['kilometraje'] ?? null);

        return $this->respuesta($ruta, 201);
    }

    /**
     * "Nueva Ruta" sobre una unidad con una hoja ACTIVA sin tramos abiertos:
     * no se crea otra hoja, el conductor la sigue. Registra la parada que abre
     * el tramo siguiente (su kilometraje debe superar al de la última parada)
     * y pasa a ser el piloto; copiloto, precintos y carreta quedan los de la
     * hoja. El enlace de la PWA pasa a este conductor: la hoja deja de
     * figurar "en curso" para el anterior.
     *
     * @param  array<string, mixed>  $datos
     */
    private function seguirHoja(
        CrearRutaRequest $request,
        WialonConductor $conductor,
        Ruta $hoja,
        array $datos,
        ?string $key,
        ?TipoDocumento $tipo,
        bool $finaliza,
    ): JsonResponse {
        $numeroOrden = DB::transaction(function () use ($request, $conductor, $hoja, $datos, $key, $tipo, $finaliza): int {
            $this->bloquearHoja($hoja);

            // Entre la elección de la unidad y el bloqueo pudo abrirse un tramo
            // o finalizarse la hoja.
            if (Ruta::activaSinTramoAbiertoPorPlaca((string) $hoja->placa)?->idruta !== $hoja->idruta) {
                throw ValidationException::withMessages([
                    'placa' => "La hoja de ruta {$hoja->idruta} de esta unidad cambió mientras se registraba. Vuelve a intentarlo.",
                ]);
            }
            $request->asegurarKilometrajeMayorQue($hoja->kilometrajeUltimaParada());

            $numero = $this->registrarParada($hoja, $datos, $request, $tipo, $finaliza);

            $hoja->update(['piloto' => $conductor->nombre]);
            PwaRuta::query()->where('ruta_idruta', $hoja->idruta)->delete();
            PwaRuta::create([
                'ruta_idruta' => $hoja->idruta,
                'wialon_conductor_id' => $conductor->getKey(),
                'idempotency_key' => $key,
            ]);

            return $numero;
        });

        $this->avisarParada($hoja->idruta, $numeroOrden, $finaliza);
        $this->empujarContadorSiCorresponde($hoja->placa, $datos['kilometraje'] ?? null);

        return $this->respuesta($hoja->idruta, 201);
    }

    /**
     * Bloquea la hoja hasta terminar la transacción: dos paradas simultáneas
     * de la misma hoja (dos celulares sincronizando a la vez) se registran una
     * detrás de la otra, sin repetir el número de orden.
     */
    private function bloquearHoja(Ruta $hoja): void
    {
        Ruta::query()->whereKey($hoja->getKey())->lockForUpdate()->first();
    }

    /**
     * Registra la siguiente parada de la hoja (con su documento, si lo trae) y
     * devuelve su número de orden. Un documento con condicionaFin la finaliza.
     *
     * @param  array<string, mixed>  $datos
     */
    private function registrarParada(
        Ruta $hoja,
        array $datos,
        CrearRutaRequest|RegistrarOrdenRequest $request,
        ?TipoDocumento $tipo,
        bool $finaliza,
    ): int {
        $siguienteOrden = (int) $hoja->detalles()->max('orden') + 1;

        /** @var DetalleRuta $parada */
        $parada = $hoja->detalles()->create([
            'contacto_idcontacto' => $datos['contacto_idcontacto'] ?? null,
            'geocerca' => $datos['geocerca'] ?? null,
            'coordenada' => $datos['coordenada'] ?? null,
            'kilometraje' => $datos['kilometraje'] ?? null,
            'observacion' => $datos['observacion'] ?? null,
            'orden' => $siguienteOrden,
            'fhRegistro' => $datos['fhRegistro'] ?? now(),
            'fhIndicado' => now(),
            // Un documento con condicionaFin cierra la hoja: la parada nace FINALIZADA.
            'estado' => $finaliza ? DetalleRuta::FINALIZADO : null,
        ]);

        if ($tipo !== null) {
            $this->guardarDocumento($parada, $tipo, $request);
        }

        if ($finaliza) {
            $hoja->update(['estado' => Ruta::FINALIZADA]);
        }

        return $siguienteOrden;
    }

    /**
     * Avisos (van a la cola): una parada par cierra un tramo; si además
     * finaliza la hoja, solo sale el aviso final.
     */
    private function avisarParada(string $idruta, int $numeroOrden, bool $finaliza): void
    {
        if ($finaliza) {
            HojaRutaFinalizada::dispatch($idruta);
        } elseif ($numeroOrden % 2 === 0) {
            TramoRutaCerrado::dispatch($idruta);
        }
    }

    /**
     * Si el kilometraje que acaba de registrar el conductor superó al
     * contador que Wialon tenía para esa unidad, lo empuja de vuelta
     * (`unit/update_mileage_counter`) — en cola, no bloquea la respuesta.
     */
    private function empujarContadorSiCorresponde(?string $placa, mixed $kilometraje): void
    {
        if ($placa === null || $kilometraje === null || ! is_numeric($kilometraje)) {
            return;
        }

        $km = (int) $kilometraje;

        $unidad = WialonUnidad::query()->where('placa', $placa)->first();
        if ($unidad === null) {
            return;
        }

        if ($unidad->contador_kilometraje_km !== null && $km <= $unidad->contador_kilometraje_km) {
            return;
        }

        ActualizarContadorKilometrajeJob::dispatch($unidad->wialon_unidad_id, $km);
    }

    private function respuesta(string $idruta, int $status): JsonResponse
    {
        $ruta = Ruta::query()
            ->with([
                'detalles' => fn ($q) => $q->orderBy('orden')->orderBy('iddetalleRuta'),
                'detalles.documentos.tipoDocumento',
            ])
            ->findOrFail($idruta);

        return (new RutaResource($ruta))->response()->setStatusCode($status);
    }
}

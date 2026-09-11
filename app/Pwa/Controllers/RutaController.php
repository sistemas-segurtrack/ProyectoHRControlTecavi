<?php

namespace App\Pwa\Controllers;

use App\Events\HojaRutaCreada;
use App\Events\HojaRutaFinalizada;
use App\Http\Controllers\Controller;
use App\Models\HRControl\DetalleRuta;
use App\Models\HRControl\Ruta;
use App\Models\WialonSTK\WialonConductor;
use App\Pwa\Controllers\Concerns\GuardaDocumentoRuta;
use App\Pwa\Controllers\Concerns\ResuelveCatalogos;
use App\Pwa\Models\PwaRuta;
use App\Pwa\Requests\CrearRutaRequest;
use App\Pwa\Requests\RegistrarOrdenRequest;
use App\Pwa\Resources\RutaResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RutaController extends Controller
{
    use GuardaDocumentoRuta;
    use ResuelveCatalogos;

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
     * documento adjunto opcional que puede finalizarla de inmediato.
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

        $ruta = DB::transaction(function () use ($conductor, $datos, $key, $request, $tipo, $finaliza): Ruta {
            $ruta = Ruta::create([
                'idruta' => Ruta::siguienteCodigo(),
                'placa' => $datos['placa'],
                'piloto' => $conductor->nombre,
                'copiloto' => $datos['copiloto'] ?? null,
                'precintos' => $datos['precintos'] ?? null,
                'carreta' => $datos['carreta'] ?? null,
                'estado' => $finaliza ? Ruta::FINALIZADA : Ruta::ACTIVA,
            ]);

            /** @var DetalleRuta $orden */
            $orden = $ruta->detalles()->create([
                'contacto_idcontacto' => null,
                'geocerca' => $datos['geocerca'] ?? null,
                'coordenada' => $datos['coordenada'] ?? null,
                'kilometraje' => $datos['kilometraje'] ?? null,
                'observacion' => $datos['observacion'] ?? null,
                'orden' => 1,
                'fhRegistro' => $datos['fhRegistro'] ?? now(),
                'fhIndicado' => now(),
                'estado' => $finaliza ? DetalleRuta::FINALIZADO : null,
            ]);

            if ($tipo !== null) {
                $this->guardarDocumento($orden, $tipo, $request);
            }

            PwaRuta::create([
                'ruta_idruta' => $ruta->idruta,
                'wialon_conductor_id' => $conductor->getKey(),
                'idempotency_key' => $key,
            ]);

            return $ruta;
        });

        // Notificaciones por correo (no bloquean la respuesta: van a la cola).
        HojaRutaCreada::dispatch($ruta->idruta);
        if ($finaliza) {
            HojaRutaFinalizada::dispatch($ruta->idruta);
        }

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

        DB::transaction(function () use ($modelo, $datos, $request, $tipo, $finaliza): void {
            $siguienteOrden = (int) $modelo->detalles()->max('orden') + 1;

            /** @var DetalleRuta $orden */
            $orden = $modelo->detalles()->create([
                'contacto_idcontacto' => $datos['contacto_idcontacto'] ?? null,
                'geocerca' => $datos['geocerca'] ?? null,
                'coordenada' => $datos['coordenada'] ?? null,
                'kilometraje' => $datos['kilometraje'] ?? null,
                'observacion' => $datos['observacion'] ?? null,
                'orden' => $siguienteOrden,
                'fhRegistro' => $datos['fhRegistro'] ?? now(),
                'fhIndicado' => now(),
                // Un documento con condicionaFin cierra la hoja: el orden nace FINALIZADO.
                'estado' => $finaliza ? DetalleRuta::FINALIZADO : null,
            ]);

            if ($tipo !== null) {
                $this->guardarDocumento($orden, $tipo, $request);
            }

            if ($finaliza) {
                $modelo->update(['estado' => Ruta::FINALIZADA]);
            }
        });

        if ($finaliza) {
            HojaRutaFinalizada::dispatch($ruta);
        }

        return $this->respuesta($ruta, 201);
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

<?php

namespace App\Http\Controllers\Backend\Web\Modulos\Rutas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modulos\DetalleRutaRequest;
use App\Http\Requests\Modulos\RutaRequest;
use App\Models\HRControl\Contacto;
use App\Models\HRControl\DetalleRuta;
use App\Models\HRControl\Ruta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD de apoyo para crear/probar hojas de ruta y sus órdenes (detalleruta),
 * y verificar la transición de estado EN RUTA → FINALIZADO.
 */
class RutasCrudController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const ESTADOS = [
        DetalleRuta::EN_RUTA => 'EN RUTA',
        DetalleRuta::FINALIZADO => 'FINALIZADO',
    ];

    public function gestion(Request $request): Response
    {
        $buscar = trim((string) $request->query('buscar', ''));

        $rutas = Ruta::query()
            ->with(['detalles' => fn ($q) => $q->orderBy('orden')->orderBy('iddetalleRuta')])
            ->when($buscar !== '', function ($q) use ($buscar) {
                $q->where(function ($sub) use ($buscar) {
                    $sub->where('idruta', 'like', "%{$buscar}%")
                        ->orWhere('placa', 'like', "%{$buscar}%")
                        ->orWhere('piloto', 'like', "%{$buscar}%")
                        ->orWhere('copiloto', 'like', "%{$buscar}%");
                });
            })
            ->orderByDesc('idruta')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Ruta $ruta): array => [
                'idruta' => $ruta->idruta,
                'placa' => $ruta->placa,
                'piloto' => $ruta->piloto,
                'copiloto' => $ruta->copiloto,
                'precintos' => $ruta->precintos,
                'carreta' => $ruta->carreta,
                'estado' => $ruta->estado,
                'detalles' => $ruta->detalles->map(fn (DetalleRuta $d): array => [
                    'id' => $d->iddetalleRuta,
                    'orden' => $d->orden,
                    'contacto_idcontacto' => $d->contacto_idcontacto,
                    'geocerca' => $d->geocerca,
                    'coordenada' => $d->coordenada,
                    'kilometraje' => $d->kilometraje,
                    'fhRegistro' => $d->fhRegistro?->format('d/m/Y H:i'),
                    'fhIndicado' => $d->fhIndicado?->format('d/m/Y H:i'),
                    'observacion' => $d->observacion,
                    'estado' => $d->estado,
                    'estado_label' => self::ESTADOS[$d->estado] ?? (string) $d->estado,
                ])->all(),
            ]);

        return Inertia::render('Frontend/Modulos/Rutas/Gestion', [
            'rutas' => $rutas,
            'filtros' => ['buscar' => $buscar],
            'contactos' => Contacto::query()->orderBy('nombre')->get(['idcontacto', 'nombre', 'geocerca']),
            'proximoCodigo' => Ruta::siguienteCodigo(),
        ]);
    }

    public function store(RutaRequest $request): RedirectResponse
    {
        Ruta::create([
            'idruta' => Ruta::siguienteCodigo(),
            ...$request->validated(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Hoja de ruta creada.']);

        return back();
    }

    public function update(RutaRequest $request, Ruta $ruta): RedirectResponse
    {
        $ruta->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Hoja de ruta actualizada.']);

        return back();
    }

    public function destroy(Ruta $ruta): RedirectResponse
    {
        $ruta->detalles()->delete();
        $ruta->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Hoja de ruta eliminada.']);

        return back();
    }

    /**
     * Registra un nuevo orden. El estado lo fija el observer:
     * el nuevo orden nace "EN RUTA" y los anteriores pasan a "FINALIZADO".
     */
    public function guardarDetalle(DetalleRutaRequest $request, Ruta $ruta): RedirectResponse
    {
        $siguienteOrden = (int) $ruta->detalles()->max('orden') + 1;

        $ruta->detalles()->create([
            ...$request->validated(),
            'orden' => $siguienteOrden,
            'fhRegistro' => $request->date('fhRegistro') ?? now(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Orden {$siguienteOrden} registrado.",
        ]);

        return back();
    }

    public function eliminarDetalle(DetalleRuta $detalle): RedirectResponse
    {
        $detalle->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Orden eliminado.']);

        return back();
    }
}

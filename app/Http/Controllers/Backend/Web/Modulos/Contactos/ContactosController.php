<?php

namespace App\Http\Controllers\Backend\Web\Modulos\Contactos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modulos\ContactoRequest;
use App\Models\HRControl\Contacto;
use App\Models\WialonSTK\WialonGeocerca;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactosController extends Controller
{
    /**
     * Opciones válidas para "filas por página" — mismas que el listado de Rutas.
     *
     * @var list<int>
     */
    private const POR_PAGINA = [10, 25, 50, 100];

    /**
     * Listado de contactos (CRUD).
     */
    public function index(Request $request): Response
    {
        $buscar = trim((string) $request->query('buscar', ''));

        $porPagina = (int) $request->query('porPagina', 15);
        if (! in_array($porPagina, self::POR_PAGINA, true)) {
            $porPagina = 15;
        }

        $paginador = Contacto::query()
            ->when($buscar !== '', function ($q) use ($buscar) {
                $q->where(function ($sub) use ($buscar) {
                    $sub->where('nombre', 'like', "%{$buscar}%")
                        ->orWhere('geocerca', 'like', "%{$buscar}%")
                        ->orWhere('correo', 'like', "%{$buscar}%")
                        ->orWhere('telefonos', 'like', "%{$buscar}%");
                });
            })
            ->orderBy('nombre')
            ->paginate($porPagina)
            ->withQueryString();

        $contactos = collect($paginador->items())
            ->map(fn (Contacto $contacto): array => [
                'id' => $contacto->idcontacto,
                'geocerca' => $contacto->geocerca,
                'nombre' => $contacto->nombre,
                'correo' => $contacto->correo,
                'telefonos' => $contacto->telefonos,
            ])
            ->values();

        return Inertia::render('Frontend/Modulos/Contactos/Index', [
            'contactos' => $contactos,
            'paginaMeta' => [
                'actual' => $paginador->currentPage(),
                'total' => $paginador->lastPage(),
                'porPagina' => $paginador->perPage(),
                'totalRegistros' => $paginador->total(),
            ],
            'filtros' => ['buscar' => $buscar],
            'opciones' => [
                'porPagina' => self::POR_PAGINA,
                // Geocercas reales de Tecavi (mismo catálogo que usa el filtro de
                // Rutas), para que el combo del formulario no dependa de que el
                // usuario escriba el nombre exacto a mano.
                'geocercas' => WialonGeocerca::query()
                    ->whereNotNull('nombre')->where('nombre', '!=', '')
                    ->distinct()->orderBy('nombre')->pluck('nombre'),
            ],
        ]);
    }

    public function store(ContactoRequest $request): RedirectResponse
    {
        Contacto::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Contacto creado.']);

        return back();
    }

    public function update(ContactoRequest $request, Contacto $contacto): RedirectResponse
    {
        $contacto->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Contacto actualizado.']);

        return back();
    }

    public function destroy(Contacto $contacto): RedirectResponse
    {
        $contacto->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Contacto eliminado.']);

        return back();
    }
}

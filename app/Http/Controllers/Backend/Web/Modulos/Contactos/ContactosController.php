<?php

namespace App\Http\Controllers\Backend\Web\Modulos\Contactos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modulos\ContactoRequest;
use App\Models\HRControl\Contacto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactosController extends Controller
{
    /**
     * Listado de contactos (CRUD).
     */
    public function index(Request $request): Response
    {
        $buscar = trim((string) $request->query('buscar', ''));

        $contactos = Contacto::query()
            ->when($buscar !== '', function ($q) use ($buscar) {
                $q->where(function ($sub) use ($buscar) {
                    $sub->where('nombre', 'like', "%{$buscar}%")
                        ->orWhere('geocerca', 'like', "%{$buscar}%")
                        ->orWhere('correo', 'like', "%{$buscar}%")
                        ->orWhere('telefonos', 'like', "%{$buscar}%");
                });
            })
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Contacto $contacto): array => [
                'id' => $contacto->idcontacto,
                'geocerca' => $contacto->geocerca,
                'nombre' => $contacto->nombre,
                'correo' => $contacto->correo,
                'telefonos' => $contacto->telefonos,
            ]);

        return Inertia::render('Frontend/Modulos/Contactos/Index', [
            'contactos' => $contactos,
            'filtros' => ['buscar' => $buscar],
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

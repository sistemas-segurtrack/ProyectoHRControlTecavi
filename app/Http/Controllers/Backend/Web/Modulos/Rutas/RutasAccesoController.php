<?php

namespace App\Http\Controllers\Backend\Web\Modulos\Rutas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modulos\AutenticarRutasRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class RutasAccesoController extends Controller
{
    /**
     * Acceso a /modulos/rutas para la cuenta compartida `tecavi@segurtrack.com`
     * (rol "usuario"): el formulario solo pide la contraseña, el correo va
     * fijo — no es un login normal, es el "portón" de un reporte, igual que
     * https://mavitours.segurtrack.com/clientes/reporte/rutas.
     */
    public function store(AutenticarRutasRequest $request): RedirectResponse
    {
        $ok = Auth::attempt([
            'email' => config('services.tecavi.email'),
            'password' => $request->validated('password'),
        ]);

        if (! $ok) {
            throw ValidationException::withMessages([
                'password' => 'La contraseña no es correcta.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->route('modulos.rutas.index');
    }
}

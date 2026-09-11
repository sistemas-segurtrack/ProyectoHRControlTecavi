<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Muestra la información de la cuenta. Solo lectura: los usuarios de
     * este panel no se autogestionan (los administra el sistema), así que
     * no hay edición de perfil ni borrado de cuenta.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/Profile');
    }
}

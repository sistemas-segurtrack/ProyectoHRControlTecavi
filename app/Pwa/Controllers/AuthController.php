<?php

namespace App\Pwa\Controllers;

use App\Http\Controllers\Controller;
use App\Models\WialonSTK\WialonConductor;
use App\Pwa\Controllers\Concerns\ResuelveCatalogos;
use App\Pwa\Requests\LoginRequest;
use App\Pwa\Resources\ConductorResource;
use App\Pwa\Resources\RutaResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ResuelveCatalogos;

    public function login(LoginRequest $request): JsonResponse
    {
        $conductor = WialonConductor::query()
            ->where('codigo', $request->string('codigo')->trim()->value())
            ->where('activo', true)
            ->first();

        if (! $conductor?->pwd_hash || ! Hash::check($request->string('password')->value(), $conductor->pwd_hash)) {
            throw ValidationException::withMessages([
                'codigo' => ['Código o contraseña incorrectos.'],
            ]);
        }

        $token = $conductor->createToken('pwa')->plainTextToken;

        $activa = $this->rutaActiva($conductor);

        return response()->json([
            'token' => $token,
            'conductor' => new ConductorResource($conductor),
            'catalogos' => $this->catalogos($conductor),
            'ruta_activa' => $activa ? new RutaResource($activa) : null,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var WialonConductor $conductor */
        $conductor = $request->user();

        $activa = $this->rutaActiva($conductor);

        return response()->json([
            'conductor' => new ConductorResource($conductor),
            'ruta_activa' => $activa ? new RutaResource($activa) : null,
        ]);
    }

    public function catalogosRefresh(Request $request): JsonResponse
    {
        /** @var WialonConductor $conductor */
        $conductor = $request->user();

        return response()->json($this->catalogos($conductor));
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var WialonConductor $conductor */
        $conductor = $request->user();

        $conductor->tokens()
            ->where('id', $conductor->currentAccessToken()->getKey())
            ->delete();

        return response()->json(['ok' => true]);
    }
}

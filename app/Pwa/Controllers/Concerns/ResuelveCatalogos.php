<?php

namespace App\Pwa\Controllers\Concerns;

use App\Models\HRControl\DetalleRuta;
use App\Models\HRControl\Ruta;
use App\Models\HRControl\TipoDocumento;
use App\Models\WialonSTK\WialonCarreta;
use App\Models\WialonSTK\WialonConductor;
use App\Models\WialonSTK\WialonGeocerca;
use App\Models\WialonSTK\WialonUnidad;
use App\Pwa\Models\PwaRuta;

trait ResuelveCatalogos
{
    /**
     * Catálogos que la PWA cachea para trabajar offline.
     *
     * @return array<string, mixed>
     */
    protected function catalogos(WialonConductor $conductor): array
    {
        return [
            'placas' => WialonUnidad::query()
                ->whereNotNull('placa')->where('placa', '!=', '')
                ->orderBy('placa')->pluck('placa')->values(),
            // Unidades EN RUTA de verdad ahora mismo (tramo abierto, sin
            // cerrar), sin importar qué conductor lo inició: "Nueva Ruta" la
            // usa para avisar al instante, al elegir la placa, que esa
            // unidad ya está en ruta (hay que continuarla/finalizarla) en
            // vez de dejar crear una hoja nueva desde cero encima. Una
            // unidad con una hoja ACTIVA pero ya sin ningún tramo abierto
            // (esperando el documento que la finalice) SÍ se puede elegir
            // — ver `Ruta::unidadesEnRuta()`.
            'unidades_en_ruta' => Ruta::unidadesEnRuta(),
            // Unidades con una hoja ACTIVA sin tramos abiertos: "Nueva Ruta"
            // completa y bloquea copiloto, precintos y carreta con los de esa
            // hoja — ver `Ruta::datosHeredablesPorPlaca()`.
            'unidades_activas' => Ruta::datosHeredablesPorPlaca(),
            'carretas' => WialonCarreta::query()->orderBy('nombre')->pluck('nombre')->unique()->values(),
            'geocercas' => WialonGeocerca::query()->orderBy('nombre')->pluck('nombre')->unique()->values(),
            'copilotos' => WialonConductor::query()
                ->where('activo', true)
                ->whereKeyNot($conductor->getKey())
                ->orderBy('nombre')
                ->pluck('nombre')
                ->values(),
            'tipos_documento' => TipoDocumento::query()
                ->orderBy('nombre')
                ->get(['idtipoDocumento', 'nombre', 'condicionaFin'])
                ->map(fn (TipoDocumento $t): array => [
                    'id' => $t->idtipoDocumento,
                    'nombre' => $t->nombre,
                    'condiciona_fin' => (string) $t->condicionaFin === '1',
                ])
                ->values(),
        ];
    }

    /**
     * Hoja de ruta en curso del conductor: la más reciente cuya última orden sigue "EN RUTA".
     */
    protected function rutaActiva(WialonConductor $conductor): ?Ruta
    {
        $idruta = PwaRuta::query()
            ->where('wialon_conductor_id', $conductor->getKey())
            ->latest()
            ->value('ruta_idruta');

        if ($idruta === null) {
            return null;
        }

        /** @var Ruta|null $ruta */
        $ruta = Ruta::query()
            ->with(['detalles' => fn ($q) => $q->orderBy('orden')->orderBy('iddetalleRuta'), 'detalles.documentos.tipoDocumento'])
            ->find($idruta);

        if ($ruta === null) {
            return null;
        }

        $tieneEnRuta = $ruta->detalles->contains(
            fn (DetalleRuta $d) => $d->estado === DetalleRuta::EN_RUTA,
        );

        return $tieneEnRuta ? $ruta : null;
    }
}

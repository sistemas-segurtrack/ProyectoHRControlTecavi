<?php

namespace App\Support\HojasRuta;

use App\Models\HRControl\DetalleRuta;
use App\Models\HRControl\DocRuta;
use App\Models\HRControl\Ruta;

/**
 * Arma el {@see ResumenHojaRuta} que consumen los correos de "creada" y
 * "finalizada" a partir del estado actual de la ruta en base de datos.
 */
class ConstruirResumenHojaRuta
{
    /**
     * @var array<string, string>
     */
    private const ESTADOS = [
        Ruta::ACTIVA => 'ACTIVA',
        Ruta::FINALIZADA => 'FINALIZADA',
    ];

    /**
     * Al crearse la hoja de ruta: usa el primer orden (geocerca de partida), sin documentos.
     */
    public function paraCreacion(string $idruta): ?ResumenHojaRuta
    {
        $ruta = Ruta::query()
            ->with(['detalles' => fn ($q) => $q->orderBy('orden')])
            ->find($idruta);

        if ($ruta === null) {
            return null;
        }

        return $this->armar($ruta, $ruta->detalles->first(), []);
    }

    /**
     * Al finalizarse: usa el último orden (el que la cerró), con sus documentos.
     */
    public function paraFinalizacion(string $idruta): ?ResumenHojaRuta
    {
        return $this->conUltimoOrden($idruta);
    }

    /**
     * Al cerrarse un tramo: usa el último orden (el que cerró el tramo), con sus documentos.
     */
    public function paraTramoCerrado(string $idruta): ?ResumenHojaRuta
    {
        return $this->conUltimoOrden($idruta);
    }

    private function conUltimoOrden(string $idruta): ?ResumenHojaRuta
    {
        $ruta = Ruta::query()
            ->with(['detalles' => fn ($q) => $q->orderBy('orden'), 'detalles.documentos.tipoDocumento'])
            ->find($idruta);

        if ($ruta === null) {
            return null;
        }

        /** @var DetalleRuta|null $ultimo */
        $ultimo = $ruta->detalles->last();

        /** @var list<array{tipo: ?string, documento: ?string, producto: ?string, cantidad: ?string, envase: ?string, pesoNeto: ?string, pesoBruto: ?string}> $documentos */
        $documentos = $ultimo?->documentos
            ->map(fn (DocRuta $doc): array => [
                'tipo' => $doc->tipoDocumento?->nombre,
                'documento' => $doc->documento,
                'producto' => $doc->producto,
                'cantidad' => $doc->cantidad,
                'envase' => $doc->envase,
                'pesoNeto' => $doc->pesoNeto,
                'pesoBruto' => $doc->pesoBruto,
            ])
            ->values()
            ->all() ?? [];

        return $this->armar($ruta, $ultimo, $documentos);
    }

    /**
     * @param  list<array{tipo: ?string, documento: ?string, producto: ?string, cantidad: ?string, envase: ?string, pesoNeto: ?string, pesoBruto: ?string}>  $documentos
     */
    private function armar(Ruta $ruta, ?DetalleRuta $detalle, array $documentos): ResumenHojaRuta
    {
        return new ResumenHojaRuta(
            idruta: $ruta->idruta,
            placa: $ruta->placa,
            piloto: $ruta->piloto,
            copiloto: $ruta->copiloto,
            carreta: $ruta->carreta,
            precintos: $ruta->precintos,
            geocerca: $detalle?->geocerca,
            fecha: $detalle?->fhRegistro,
            estadoLabel: self::ESTADOS[$ruta->estado] ?? (string) $ruta->estado,
            documentos: $documentos,
            horaSistema: $detalle?->fhIndicado,
        );
    }
}

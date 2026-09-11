<?php

namespace App\Support\HojasRuta;

use Carbon\CarbonInterface;

/**
 * Datos de una hoja de ruta para el correo de "creada" o "finalizada".
 *
 * @property-read list<array{tipo: ?string, documento: ?string, producto: ?string, cantidad: ?string, envase: ?string, pesoNeto: ?string, pesoBruto: ?string}> $documentos
 */
final readonly class ResumenHojaRuta
{
    /**
     * @param  list<array{tipo: ?string, documento: ?string, producto: ?string, cantidad: ?string, envase: ?string, pesoNeto: ?string, pesoBruto: ?string}>  $documentos
     */
    public function __construct(
        public string $idruta,
        public ?string $placa,
        public ?string $piloto,
        public ?string $copiloto,
        public ?string $carreta,
        public ?string $precintos,
        public ?string $geocerca,
        public ?CarbonInterface $fecha,
        public string $estadoLabel,
        public array $documentos = [],
    ) {}
}

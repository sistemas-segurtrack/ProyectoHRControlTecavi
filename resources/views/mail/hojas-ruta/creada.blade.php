<x-mail::message>
# Hoja de ruta {{ $r->idruta }} creada

<x-mail::table>
| Campo | Valor |
| :--- | :--- |
| Placa | {{ $r->placa ?? '—' }} |
| Piloto | {{ $r->piloto ?? '—' }} |
| Copiloto | {{ $r->copiloto ?? '—' }} |
| Carreta | {{ $r->carreta ?? '—' }} |
| Precintos | {{ $r->precintos ?? '—' }} |
| Geocerca | {{ $r->geocerca ?? '—' }} |
| Fecha / hora | {{ $r->fecha?->format('d/m/Y H:i') ?? '—' }} |
| Estado | {{ $r->estadoLabel }} |
</x-mail::table>

Segurtrack · HRControl
</x-mail::message>

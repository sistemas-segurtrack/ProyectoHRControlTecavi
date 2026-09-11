<x-mail::message>
# Hoja de ruta {{ $r->idruta }} finalizada

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

@if (count($r->documentos))
## Documentos adjuntos

<x-mail::table>
| Tipo | Documento | Producto | Cantidad | Envase | P. Neto | P. Bruto |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
@foreach ($r->documentos as $d)
| {{ $d['tipo'] ?? '—' }} | {{ $d['documento'] ?? '—' }} | {{ $d['producto'] ?? '—' }} | {{ $d['cantidad'] ?? '—' }} | {{ $d['envase'] ?? '—' }} | {{ $d['pesoNeto'] ?? '—' }} | {{ $d['pesoBruto'] ?? '—' }} |
@endforeach
</x-mail::table>
@endif

Segurtrack · HRControl
</x-mail::message>

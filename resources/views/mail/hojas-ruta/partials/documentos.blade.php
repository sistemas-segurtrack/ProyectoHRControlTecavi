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

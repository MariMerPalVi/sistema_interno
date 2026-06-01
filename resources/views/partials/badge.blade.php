@php
    $class = match ($status) {
        'validado', 'sin_novedad', 'completado' => 'ok',
        'cargado', 'en_revision', 'no_aplica' => 'info',
        'rechazado', 'con_observacion', 'observado' => 'bad',
        default => 'wait',
    };
@endphp
<span class="badge {{ $class }}">{{ str_replace('_', ' ', $status) }}</span>

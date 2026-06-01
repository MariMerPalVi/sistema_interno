@extends('layouts.app')

@section('content')
    <section class="page-head">
        <div>
            <p class="eyebrow">Procesos internos</p>
            <h1>Seleccione el proceso a realizar</h1>
        </div>
    </section>

    <section class="process-grid">
        @foreach ($processes as $process)
            <article class="process-card {{ $process->is_enabled ? 'enabled' : 'disabled' }}">
                <div class="process-icon">{{ strtoupper(substr($process->name, 0, 1)) }}</div>
                <div>
                    <h2>{{ $process->name }}</h2>
                    <p>{{ $process->description }}</p>
                </div>
                @if ($process->is_enabled && $process->route_name)
                    <a class="button primary" href="{{ route($process->route_name) }}">Iniciar</a>
                @else
                    <button class="button ghost" disabled>Proximamente</button>
                @endif
            </article>
        @endforeach
    </section>
@endsection

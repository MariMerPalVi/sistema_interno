@extends('layouts.app')

@section('content')
    <section class="page-head">
        <div>
            <p class="eyebrow">Salud del sistema</p>
            <h1>Verificación técnica</h1>
            <p>Estado rápido de servicios básicos, almacenamiento y configuración sensible.</p>
        </div>
        <a class="button secondary" href="{{ route('processes.index') }}">Volver</a>
    </section>

    <section class="panel">
        <div class="health-grid">
            @foreach ($checks as $check)
                <article class="health-card {{ $check['status'] ? 'ok' : 'warn' }}">
                    <span>{{ $check['status'] ? 'Correcto' : 'Revisar' }}</span>
                    <h2>{{ $check['name'] }}</h2>
                    <p>{{ $check['detail'] }}</p>
                </article>
            @endforeach
        </div>
    </section>
@endsection

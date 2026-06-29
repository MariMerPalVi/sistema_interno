@extends('layouts.app')

@section('content')
    <section class="page-head">
        <div>
            <p class="eyebrow">Procesos internos</p>
            <h1>{{ auth()->user()->canReviewConsents() ? 'Control de consentimientos' : 'Seleccione el proceso a realizar' }}</h1>
        </div>
    </section>

    <section class="process-grid {{ auth()->user()->canReviewConsents() ? 'process-grid-single' : '' }}">
        @if (auth()->user()->canReviewConsents())
            <article class="process-card process-card-featured enabled">
                <div class="process-icon">C</div>
                <div>
                    <h2>Control de consentimientos</h2>
                    <p>Revise y filtre los consentimientos de datos personales cargados desde todas las agencias.</p>
                </div>
                <a class="button secondary" href="{{ route('consents.index') }}">Revisar</a>
            </article>
        @else
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
                        <button class="button ghost" disabled>Próximamente</button>
                    @endif
                </article>
            @endforeach
        @endif
    </section>
@endsection

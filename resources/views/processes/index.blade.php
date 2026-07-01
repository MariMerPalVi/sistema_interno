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
            @if (auth()->user()->isAdministrator())
                <article class="process-card process-card-featured enabled">
                    <div class="process-icon">R</div>
                    <div>
                        <h2>Reportes operativos</h2>
                        <p>Consulta global de expedientes, agencias, documentos y tiempos del proceso.</p>
                    </div>
                    <a class="button secondary" href="{{ route('reports.index') }}">Ver reportes</a>
                </article>
                <article class="process-card process-card-featured enabled">
                    <div class="process-icon">S</div>
                    <div>
                        <h2>Salud del sistema</h2>
                        <p>Revise conexión, almacenamiento privado, formatos y configuración sensible.</p>
                    </div>
                    <a class="button secondary" href="{{ route('system-health.index') }}">Revisar</a>
                </article>
            @endif

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

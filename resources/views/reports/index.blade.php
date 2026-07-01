@extends('layouts.app')

@section('content')
    <section class="page-head">
        <div>
            <p class="eyebrow">Reportes</p>
            <h1>Control operativo</h1>
            <p>Consulta global de expedientes, documentos y consentimientos por agencia.</p>
        </div>
        <a class="button secondary" href="{{ route('processes.index') }}">Volver</a>
    </section>

    <form class="report-filters" method="get">
        <label>
            Desde
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}">
        </label>
        <label>
            Hasta
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}">
        </label>
        <label>
            Agencia
            <select name="agency">
                <option value="">Todas</option>
                @foreach ($agencies as $key => $name)
                    <option value="{{ $key }}" @selected(($filters['agency'] ?? '') === $key)>{{ $name }}</option>
                @endforeach
            </select>
        </label>
        <label>
            Tipo de cuenta
            <select name="account_type_id">
                <option value="">Todos</option>
                @foreach ($accountTypes as $type)
                    <option value="{{ $type->id }}" @selected((string) ($filters['account_type_id'] ?? '') === (string) $type->id)>{{ $type->name }}</option>
                @endforeach
            </select>
        </label>
        <label>
            Usuario
            <select name="user_id">
                <option value="">Todos</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </label>
        <label>
            Estado
            <select name="status">
                <option value="">Todos</option>
                @foreach (['borrador', 'en revision', 'observado', 'aprobado', 'rechazado', 'finalizado'] as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </label>
        <button class="button primary" type="submit">Filtrar</button>
    </form>

    <section class="metrics-grid">
        <article class="metric-card">
            <span>Total expedientes</span>
            <strong>{{ $total }}</strong>
        </article>
        <article class="metric-card">
            <span>Consentimientos pendientes</span>
            <strong>{{ $pendingConsents }}</strong>
        </article>
        <article class="metric-card">
            <span>Documentos observados</span>
            <strong>{{ $documentAlerts['observados'] }}</strong>
        </article>
        <article class="metric-card">
            <span>Revisión manual OCR</span>
            <strong>{{ $documentAlerts['revision_manual'] }}</strong>
        </article>
        <article class="metric-card">
            <span>Promedio a revisión</span>
            <strong>{{ $averageHoursToSubmit === null ? '-' : $averageHoursToSubmit.' h' }}</strong>
        </article>
    </section>

    <section class="panel">
        <div class="panel-title">
            <h2>Expedientes recientes</h2>
            <span>{{ $openings->total() }} registros</span>
        </div>
        <div class="table-wrap">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Expediente</th>
                        <th>Agencia</th>
                        <th>Tipo</th>
                        <th>Usuario</th>
                        <th>Estado</th>
                        <th>Creado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($openings as $opening)
                        <tr>
                            <td><a href="{{ route('accounts.show', $opening) }}">{{ $opening->public_code }}</a></td>
                            <td>{{ $agencies[$opening->agency] ?? $opening->agency }}</td>
                            <td>{{ $opening->accountType?->name }}</td>
                            <td>{{ $opening->creator?->name ?? 'Sin usuario' }}</td>
                            <td><span class="status-pill">{{ $opening->status }}</span></td>
                            <td>{{ $opening->created_at?->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">No hay expedientes con los filtros seleccionados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $openings->links() }}
    </section>

    <section class="panel">
        <div class="panel-title">
            <h2>Estados</h2>
        </div>
        <div class="metrics-grid compact">
            @forelse ($byStatus as $status => $count)
                <article class="metric-card">
                    <span>{{ ucfirst($status) }}</span>
                    <strong>{{ $count }}</strong>
                </article>
            @empty
                <p>No hay datos para graficar.</p>
            @endforelse
        </div>
    </section>
@endsection

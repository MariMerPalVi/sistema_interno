@extends('layouts.app')

@php
    $statusLabels = [
        'pendiente' => 'Pendiente',
        'cargado' => 'Cargado',
        'validado' => 'Validado',
        'rechazado' => 'Rechazado',
    ];
@endphp

@section('content')
    <section class="page-head">
        <div>
            <p class="eyebrow">Control legal</p>
            <h1>Consentimientos de datos personales</h1>
            <p class="muted">Registro central de consentimientos cargados para revisión y verificación.</p>
        </div>
        <a class="button ghost" href="{{ route('processes.index') }}">
            <i data-lucide="arrow-left"></i>
            Volver
        </a>
    </section>

    <form class="filter-panel" method="get">
        <label>
            <span>Buscar</span>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Expediente, socio o cédula">
        </label>
        <label>
            <span>Estado</span>
            <select name="status">
                <option value="">Todos</option>
                @foreach ($statusLabels as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>Agencia</span>
            <select name="agency">
                <option value="">Todas</option>
                @foreach ($agencies as $agency)
                    <option value="{{ $agency['key'] }}" @selected(($filters['agency'] ?? '') === $agency['key'])>
                        {{ $agency['name'] }}
                    </option>
                @endforeach
            </select>
        </label>
        <label>
            <span>Desde</span>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}">
        </label>
        <label>
            <span>Hasta</span>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}">
        </label>
        <button class="button primary" type="submit">
            <i data-lucide="search"></i>
            Filtrar
        </button>
        <a class="button ghost" href="{{ route('consents.index') }}">
            <i data-lucide="refresh-cw"></i>
            Limpiar
        </a>
    </form>

    <section class="panel consent-registry">
        <div class="section-title">
            <h2>Registro de consentimientos</h2>
            <span>{{ $consents->total() }} registros</span>
        </div>

        <div class="table-wrap">
            <table class="document-check-table consent-table">
                <thead>
                    <tr>
                        <th>Expediente</th>
                        <th>Socio</th>
                        <th>Cuenta</th>
                        <th>Agencia</th>
                        <th>Estado</th>
                        <th>Firma</th>
                        <th>Carga</th>
                        <th>Validación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($consents as $consent)
                        @php
                            $opening = $consent->opening;
                            $memberName = trim(($opening->member_last_names ?? '').' '.($opening->member_first_names ?? ''));
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $opening->file_name ?: $opening->public_code }}</strong>
                                <small>{{ $opening->public_code }}</small>
                            </td>
                            <td>
                                <strong>{{ $memberName ?: 'Pendiente' }}</strong>
                                <small>{{ $opening->member_identification ?: 'Sin cédula registrada' }}</small>
                            </td>
                            <td>{{ $opening->accountType?->name ?? 'No definido' }}</td>
                            <td>{{ config("opening.agencies.{$opening->agency}.name", $opening->agency) }}</td>
                            <td>
                                <span class="badge {{ $consent->status === 'validado' ? 'ok' : ($consent->status === 'rechazado' ? 'bad' : 'wait') }}">
                                    {{ $statusLabels[$consent->status] ?? ucfirst($consent->status) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $consent->manual_signature_confirmed ? 'ok' : 'wait' }}">
                                    {{ $consent->manual_signature_confirmed ? 'Revisada' : 'Pendiente' }}
                                </span>
                            </td>
                            <td>
                                <strong>{{ $consent->created_at?->format('d/m/Y') ?? 'Sin fecha' }}</strong>
                                <small>{{ $consent->created_at?->format('H:i') ?? '' }}</small>
                            </td>
                            <td>
                                <strong>{{ $consent->validator?->name ?? 'Sin validador' }}</strong>
                                <small>{{ $consent->validated_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}</small>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a class="icon-button" href="{{ route('accounts.show', [$opening, 'paso' => 'requisitos']) }}" title="Abrir expediente" aria-label="Abrir expediente">
                                        <i data-lucide="folder-open"></i>
                                    </a>
                                    @if ($consent->signed_file_path)
                                        <a class="icon-button" href="{{ route('accounts.consent.preview', $opening) }}" target="_blank" title="Ver consentimiento" aria-label="Ver consentimiento">
                                            <i data-lucide="file-search"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">No hay consentimientos con los filtros aplicados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="consent-pager">
            <span>
                Mostrando {{ $consents->firstItem() ?? 0 }} - {{ $consents->lastItem() ?? 0 }}
                de {{ $consents->total() }}
            </span>
            <div>
                @if ($consents->previousPageUrl())
                    <a class="icon-button" href="{{ $consents->previousPageUrl() }}" title="Página anterior" aria-label="Página anterior">
                        <i data-lucide="chevron-left"></i>
                    </a>
                @else
                    <span class="icon-button disabled" aria-hidden="true">
                        <i data-lucide="chevron-left"></i>
                    </span>
                @endif

                <strong>Página {{ $consents->currentPage() }} de {{ $consents->lastPage() }}</strong>

                @if ($consents->nextPageUrl())
                    <a class="icon-button" href="{{ $consents->nextPageUrl() }}" title="Página siguiente" aria-label="Página siguiente">
                        <i data-lucide="chevron-right"></i>
                    </a>
                @else
                    <span class="icon-button disabled" aria-hidden="true">
                        <i data-lucide="chevron-right"></i>
                    </span>
                @endif
            </div>
        </div>
    </section>
@endsection

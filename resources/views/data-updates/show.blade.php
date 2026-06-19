@extends('layouts.app')

@php
    $documentsByKey = $update->documents->keyBy('document_key');
    $currentData = $update->current_data ?? [];
    $newData = $update->new_data ?? [];
    $fields = [
        'direccion' => 'Dirección domiciliaria',
        'telefono' => 'Teléfono',
        'correo' => 'Correo electrónico',
        'estado_civil' => 'Estado civil',
        'actividad_economica' => 'Actividad económica',
        'ingresos' => 'Ingresos aproximados',
        'residencia_fiscal' => 'Residencia fiscal',
    ];
@endphp

@section('content')
    <section class="page-head">
        <div>
            <p class="eyebrow">{{ $update->public_code }}</p>
            <h1>Actualización de datos</h1>
            <div class="opening-meta">
                <span class="hint">{{ $update->file_name }}</span>
                <span class="agency-label"><i data-lucide="building-2"></i> {{ $agencyName }}</span>
            </div>
        </div>
        <span class="status">{{ str_replace('_', ' ', $update->status) }}</span>
    </section>

    <div class="progress"><span style="width: {{ $progress }}%"></span></div>

    <nav class="steps">
        @foreach ($steps as $stepKey => $stepLabel)
            <a class="{{ $activeStep === $stepKey ? 'current' : '' }}" href="{{ route('data-updates.show', [$update, 'paso' => $stepKey]) }}">{{ $stepLabel }}</a>
        @endforeach
    </nav>

    @if ($activeStep === 'datos')
        <section class="panel">
            <div class="panel-head">
                <h2>Datos del socio</h2>
                <span class="hint">Compare lo registrado actualmente con la información nueva.</span>
            </div>
            <form method="post" action="{{ route('data-updates.data', $update) }}" class="compare-form">
                @csrf
                <label class="span-2">Nombre del socio
                    <input name="member_name" value="{{ old('member_name', $update->member_name) }}" placeholder="Nombre completo">
                </label>
                <div class="compare-head">Dato</div>
                <div class="compare-head">Actual</div>
                <div class="compare-head">Nuevo</div>
                @foreach ($fields as $field => $label)
                    <div class="compare-label">{{ $label }}</div>
                    <input name="current_data[{{ $field }}]" value="{{ old("current_data.{$field}", $currentData[$field] ?? '') }}" placeholder="Actual">
                    <input name="new_data[{{ $field }}]" value="{{ old("new_data.{$field}", $newData[$field] ?? '') }}" placeholder="Nuevo">
                @endforeach
                <label class="span-3">Observaciones
                    <input name="observations" value="{{ old('observations', $update->observations) }}" placeholder="Observación si aplica">
                </label>
                <button class="button primary" type="submit"><i data-lucide="save"></i> Guardar datos</button>
            </form>
        </section>
    @endif

    @if ($activeStep === 'documentos')
        <section class="panel">
            <div class="panel-head">
                <h2>Documentos de respaldo</h2>
                <span class="hint">PDF, JPG o PNG. Máximo 5 MB por archivo.</span>
            </div>
            <div class="step-guidance">
                <i data-lucide="clipboard-check"></i>
                <span>Cargue los documentos que respalden los datos modificados. El trámite no podrá finalizar si falta algún documento obligatorio.</span>
            </div>
            <div class="check-list">
                @foreach ($requiredDocuments as $document)
                    @php $loaded = $documentsByKey->get($document['key']); @endphp
                    <article class="check-item">
                        <div>
                            <h3>{{ $document['name'] }}</h3>
                            <p>Se guardará como: {{ str_replace('{expediente}', $update->file_name, $document['file_name']) }}</p>
                            @include('partials.badge', ['status' => $loaded?->status ?? 'pendiente'])
                            @if ($loaded)
                                <p class="hint">{{ basename($loaded->file_path) }}</p>
                            @endif
                        </div>
                        <form method="post" enctype="multipart/form-data" action="{{ route('data-updates.documents', $update) }}">
                            @csrf
                            <input type="hidden" name="document_key" value="{{ $document['key'] }}">
                            <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required>
                            <select name="status">
                                <option value="cargado">Cargado</option>
                                <option value="validado">Validado</option>
                                <option value="rechazado">Rechazado</option>
                            </select>
                            <input name="observations" placeholder="Observación">
                            <button class="button secondary" type="submit">{{ $loaded ? 'Reemplazar' : 'Subir' }}</button>
                        </form>
                    </article>
                @endforeach
            </div>
            @if ($documentsComplete)
                <a class="button primary" href="{{ route('data-updates.show', [$update, 'paso' => 'resumen']) }}">Continuar <i data-lucide="arrow-right"></i></a>
            @endif
        </section>
    @endif

    @if ($activeStep === 'resumen')
        <section class="panel">
            <div class="panel-head">
                <h2>Check List de actualización</h2>
                <span class="hint">{{ $progress }}% completado</span>
            </div>
            <div class="summary-grid">
                <div><strong>Socio</strong><span>{{ $update->member_identification }}</span></div>
                <div><strong>Nombre</strong><span>{{ $update->member_name ?: 'Pendiente' }}</span></div>
                <div><strong>Documentos</strong><span>{{ $update->documents->count() }} cargados</span></div>
                <div><strong>Estado</strong><span>{{ str_replace('_', ' ', $update->status) }}</span></div>
            </div>
            <table class="mini-checklist">
                <thead>
                    <tr><th>N°</th><th>Documento requerido</th><th>Estado</th><th>Archivo</th></tr>
                </thead>
                <tbody>
                    @foreach ($requiredDocuments as $index => $document)
                        @php $loaded = $documentsByKey->get($document['key']); @endphp
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $document['name'] }}</td>
                            <td>{{ $loaded ? strtoupper($loaded->status) : 'PENDIENTE' }}</td>
                            <td>{{ $loaded ? basename($loaded->file_path) : '' }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td colspan="4"><strong>Ruta:</strong> {{ $storagePath }}</td>
                    </tr>
                </tbody>
            </table>
            @if ($update->status !== 'finalizado')
                <form method="post" action="{{ route('data-updates.submit', $update) }}">
                    @csrf
                    <button class="button primary" type="submit"><i data-lucide="check-circle-2"></i> Finalizar actualización</button>
                </form>
            @endif
        </section>
    @endif
@endsection

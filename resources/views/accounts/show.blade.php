@extends('layouts.app')

@php
    $requirementDocs = $opening->documents->where('document_scope', 'requisito')->keyBy('account_type_requirement_id');
    $internalDocs = $opening->documents->where('document_scope', 'interno')->keyBy('internal_document_template_id');
    $serviceDocs = $opening->documents->where('document_scope', 'servicio')->keyBy('internal_document_template_id');
    $externalDocs = $opening->externalEvidences->keyBy('external_check_item_id');
    $selectedServices = $opening->services->pluck('additional_service_id')->all();
    $consentComplete = $opening->consent?->signed_file_path && $opening->consent?->manual_signature_confirmed;
    $spouseRequirementIds = $opening->accountType->requirements->filter(fn ($requirement) => $requirement->type->slug === 'documentos-conyuge')->pluck('id');
    $requiredRequirementIds = $opening->accountType->requirements
        ->where('is_required', true)
        ->pluck('id')
        ->merge($opening->requires_spouse_documents ? $spouseRequirementIds : collect())
        ->unique();
    $loadedRequirementIds = $opening->documents->where('document_scope', 'requisito')->whereIn('status', ['cargado', 'validado'])->pluck('account_type_requirement_id');
    $requirementsComplete = $requiredRequirementIds->diff($loadedRequirementIds)->isEmpty();
    $requiredExternalIds = $externalChecks->where('is_required', true)->pluck('id');
    $loadedExternalIds = $opening->externalEvidences->whereNotNull('screenshot_path')->pluck('external_check_item_id');
    $externalComplete = $requiredExternalIds->diff($loadedExternalIds)->isEmpty();
    $requiredInternalIds = $internalTemplates->where('is_required', true)->pluck('id');
    $loadedInternalIds = $opening->documents->where('document_scope', 'interno')->whereIn('status', ['cargado', 'validado'])->pluck('internal_document_template_id');
    $internalComplete = $requiredInternalIds->diff($loadedInternalIds)->isEmpty();
    $servicesComplete = $opening->histories->contains('action', 'seleccionar_servicios');
    $serviceTemplatesBySlug = $serviceTemplates->keyBy('slug');
    $selectedServiceModels = $services->whereIn('id', $selectedServices);
    $selectedServiceTemplateIds = $selectedServiceModels
        ->map(fn ($service) => $serviceTemplatesBySlug->get($serviceDocumentMap[$service->slug] ?? null)?->id)
        ->filter();
    $loadedServiceIds = $opening->documents->where('document_scope', 'servicio')->whereIn('status', ['cargado', 'validado'])->pluck('internal_document_template_id');
    $serviceDocsComplete = $servicesComplete && $selectedServiceTemplateIds->diff($loadedServiceIds)->isEmpty();
    $steps = [
        'consentimiento' => '1. Consentimiento',
        'requisitos' => '2. Requisitos',
        'externas' => '3. Consultas',
        'internos' => '4. Internos',
        'servicios' => '5. Servicios',
        'resumen' => '6. Check List',
    ];
@endphp

@section('content')
    <section class="page-head">
        <div>
            <p class="eyebrow">{{ $opening->public_code }}</p>
            <h1>{{ $opening->accountType->name }}</h1>
            <span class="hint">{{ $opening->file_name }}</span>
        </div>
        <span class="status">{{ str_replace('_', ' ', $opening->status) }}</span>
    </section>

    <div class="progress">
        <span style="width: {{ $progress }}%"></span>
    </div>

    <nav class="steps">
        @foreach ($steps as $stepKey => $stepLabel)
            @php
                $stepClass = $activeStep === $stepKey ? 'current' : ($workflow['complete'][$stepKey] ? 'done' : '');
                $stepClass = !$workflow['unlocked'][$stepKey] ? 'locked' : $stepClass;
            @endphp
            @if ($workflow['unlocked'][$stepKey])
                <a class="{{ $stepClass }}" href="{{ route('accounts.show', [$opening, 'paso' => $stepKey]) }}">{{ $stepLabel }}</a>
            @else
                <span class="step-pill locked">{{ $stepLabel }}</span>
            @endif
        @endforeach
    </nav>

    @if ($activeStep === 'consentimiento')
    <section id="consentimiento" class="panel">
        <div class="panel-head">
            <h2>Consentimiento de datos personales</h2>
            @include('partials.badge', ['status' => optional($opening->consent)->status ?? 'pendiente'])
        </div>
        <p>Descargue el formato, imprimalo, obtenga la firma del socio y cargue el documento firmado.</p>
        <div class="toolbar">
            <a class="button secondary" href="{{ asset('formatos/CONSENTIMIENTO_DE_DATOS_PERSONALES_LAS_NAVES.pdf') }}" target="_blank">Descargar formato</a>
            @if ($consentComplete)
                <a class="button ghost" href="{{ route('accounts.consent.preview', $opening) }}" target="_blank">Previsualizar documento cargado</a>
                <a class="button primary" href="{{ route('accounts.show', [$opening, 'paso' => 'requisitos']) }}">Continuar</a>
            @endif
        </div>
        @if ($consentComplete)
            <div class="preview-box">
                <iframe src="{{ route('accounts.consent.preview', $opening) }}" title="Vista previa del consentimiento firmado"></iframe>
            </div>
        @endif
        <form class="upload-row" method="post" enctype="multipart/form-data" action="{{ route('accounts.consent.upload', $opening) }}">
            @csrf
            <input type="file" name="signed_file" accept=".pdf,.jpg,.jpeg,.png" required>
            <label class="check"><input type="checkbox" name="manual_signature_confirmed" value="1" required> Firma verificada manualmente</label>
            <input name="observations" placeholder="Observacion opcional">
            <button class="button primary" type="submit">Subir y validar</button>
        </form>
    </section>
    @endif

    @if ($activeStep === 'requisitos')
    <section id="requisitos" class="panel">
        <div class="panel-head">
            <h2>Requisitos del tipo de cuenta</h2>
            <span class="hint">PDF, JPG o PNG. Maximo 5 MB por archivo.</span>
        </div>
        @if ($spouseRequirementIds->isNotEmpty())
            <form class="inline-choice" method="post" action="{{ route('accounts.spouse.update', $opening) }}">
                @csrf
                <label class="check">
                    <input type="checkbox" name="requires_spouse_documents" value="1" @checked($opening->requires_spouse_documents)>
                    El socio es casado o mantiene union de hecho y requiere documentos del conyuge
                </label>
                <button class="button secondary" type="submit">Guardar condicion</button>
            </form>
        @endif
        <div class="checklist">
            @foreach ($opening->accountType->requirements as $requirement)
                @php $doc = $requirementDocs->get($requirement->id); @endphp
                @if ($requirement->type->slug === 'documentos-conyuge' && !$opening->requires_spouse_documents)
                    @continue
                @endif
                <article class="check-item">
                    <div>
                        <h3>{{ $requirement->label }}</h3>
                        <p>{{ $requirement->type->validation_rules }}</p>
                        @if ($requirement->file_name_pattern)
                            <p class="hint">Se guardara como: {{ str_replace('{expediente}', $opening->file_name, $requirement->file_name_pattern) }}</p>
                        @endif
                        @include('partials.badge', ['status' => $doc->status ?? 'pendiente'])
                        @if ($doc?->extracted_data)
                            @php
                                $data = $doc->extracted_data;
                                $name = $data['nombres_apellidos'] ?? trim(($data['nombres'] ?? '').' '.($data['apellidos'] ?? ''));
                                $fields = match ($requirement->type->slug) {
                                    'cedula-papeleta', 'cedula' => [
                                        'Numero de cedula' => $data['cedula'] ?? null,
                                        'Nombre y apellido' => $name ?: null,
                                    ],
                                    'planilla-servicios' => [
                                        'Direccion' => $data['direccion'] ?? null,
                                    ],
                                    'ruc' => [
                                        'RUC' => $data['ruc'] ?? null,
                                        'Razon social' => $data['razon_social'] ?? null,
                                    ],
                                    default => [],
                                };
                                $fields = array_filter($fields, fn ($value) => filled($value));
                            @endphp
                            @if ($fields)
                                <dl class="extracted-fields">
                                    @foreach ($fields as $label => $value)
                                        <div>
                                            <dt>{{ $label }}</dt>
                                            <dd>{{ $value }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif
                        @endif
                    </div>
                    <form method="post" enctype="multipart/form-data" action="{{ route('accounts.requirements.upload', $opening) }}">
                        @csrf
                        <input type="hidden" name="account_type_requirement_id" value="{{ $requirement->id }}">
                        <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required>
                        <select name="status">
                            <option value="cargado">Cargado</option>
                            <option value="validado">Validado</option>
                            <option value="rechazado">Rechazado</option>
                        </select>
                        <input name="observations" placeholder="Observacion si aplica">
                        <button class="button secondary" type="submit">{{ $doc ? 'Reemplazar' : 'Subir' }}</button>
                    </form>
                </article>
            @endforeach
        </div>
        @if ($requirementsComplete)
            <div class="actions">
                <a class="button primary" href="{{ route('accounts.show', [$opening, 'paso' => 'externas']) }}">Continuar</a>
            </div>
        @endif
    </section>
    @endif

    @if ($activeStep === 'externas')
    <section id="externas" class="panel">
        <div class="panel-head">
            <h2>Lista de control externa</h2>
            <span class="hint">Pegue una evidencia por consulta. El sistema guardara un solo PDF consolidado.</span>
        </div>
        <form class="external-evidence-form" method="post" action="{{ route('accounts.external.upload', $opening) }}">
            @csrf
            <div class="external-grid">
                @foreach ($externalChecks as $item)
                    @php $evidence = $externalDocs->get($item->id); @endphp
                    <div>
                        <h3>{{ $item->name }}</h3>
                        <a href="{{ $item->url }}" target="_blank" rel="noopener">Abrir enlace oficial</a>
                        @include('partials.badge', ['status' => $evidence?->screenshot_path ? $evidence->result : 'pendiente'])
                        <select name="results[{{ $item->id }}]">
                            <option value="sin_novedad" @selected(($evidence?->result ?? null) === 'sin_novedad')>Sin novedad</option>
                            <option value="con_observacion" @selected(($evidence?->result ?? null) === 'con_observacion')>Con observacion</option>
                            <option value="no_aplica" @selected(($evidence?->result ?? null) === 'no_aplica')>No aplica</option>
                            <option value="pendiente" @selected(($evidence?->result ?? 'pendiente') === 'pendiente')>Pendiente</option>
                        </select>
                        <input name="observations[{{ $item->id }}]" value="{{ $evidence?->advisor_observation }}" placeholder="Observacion del asesor">
                        <input type="hidden" name="evidence_images[{{ $item->id }}]" class="pasted-evidence-input" @required(!$evidence?->screenshot_path)>
                        <div class="paste-capture compact-paste" tabindex="0">
                            <span>{{ $evidence?->screenshot_path ? 'Pegar nueva evidencia para reemplazar' : 'Pegar evidencia con Ctrl + V' }}</span>
                            <img alt="Vista previa de la evidencia pegada" hidden>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="actions">
                <button class="button secondary" type="submit">{{ $externalComplete ? 'Actualizar PDF consolidado' : 'Guardar PDF consolidado' }}</button>
            </div>
        </form>
        @if ($externalComplete)
            <div class="actions">
                <a class="button primary" href="{{ route('accounts.show', [$opening, 'paso' => 'internos']) }}">Continuar</a>
            </div>
        @endif
    </section>
    @endif

    @if ($activeStep === 'internos')
    <section id="internos" class="panel">
        <div class="panel-head">
            <h2>Documentos internos</h2>
            <span class="hint">Documentos manuales y documentos generados desde Econx.</span>
        </div>
        <div class="checklist">
            @foreach ($internalTemplates as $template)
                @php $doc = $internalDocs->get($template->id); @endphp
                <article class="check-item">
                    <div>
                        <h3>{{ $template->name }}</h3>
                        <span class="status">{{ $template->source === 'econx' ? 'ECONX' : 'MANUAL' }}</span>
                        @if ($template->template_path)
                            <button class="link-button" type="button" data-dialog-target="internal-template-{{ $template->id }}">Preparar descarga</button>
                        @else
                            <span class="hint">Formato pendiente de adjuntar</span>
                        @endif
                        @if ($template->file_name_pattern)
                            <p class="hint">Se guardara como: {{ str_replace('{expediente}', $opening->file_name, $template->file_name_pattern) }}</p>
                        @endif
                        @include('partials.badge', ['status' => $doc->status ?? 'pendiente'])
                    </div>
                    <form method="post" enctype="multipart/form-data" action="{{ route('accounts.internal.upload', $opening) }}">
                        @csrf
                        <input type="hidden" name="internal_document_template_id" value="{{ $template->id }}">
                        <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required>
                        <label class="check"><input type="checkbox" name="manual_signature_confirmed" value="1" required> Firma validada</label>
                        <select name="status">
                            <option value="cargado">Cargado</option>
                            <option value="validado">Validado</option>
                            <option value="rechazado">Rechazado</option>
                        </select>
                        <button class="button secondary" type="submit">{{ $doc ? 'Reemplazar' : 'Subir' }}</button>
                    </form>
                </article>
                @if ($template->template_path)
                    @php
                        $isSolicitud = str_contains($template->slug, 'solicitud-de-ingreso');
                        $isRegistro = str_contains($template->slug, 'registro-de-firmas');
                        $isBdh = str_contains($template->slug, 'bdh') || str_contains($template->slug, 'acreditacion') || str_contains($template->slug, 'reapertura') || str_contains($template->slug, 'cierre');
                    @endphp
                    <dialog class="data-dialog" id="internal-template-{{ $template->id }}">
                        <form method="post" action="{{ route('accounts.internal.generate', [$opening, $template]) }}">
                            @csrf
                            <div class="dialog-head">
                                <div>
                                    <span class="status">DATOS REQUERIDOS</span>
                                    <h3>{{ $template->name }}</h3>
                                </div>
                                <button class="icon-button" type="button" data-dialog-close aria-label="Cerrar">x</button>
                            </div>
                            <p class="hint">Revise la informacion extraida de la cedula. Corrija lo necesario antes de generar el PDF para firma.</p>
                            <div class="form-grid">
                                <label>
                                    Apellidos y nombres
                                    <input name="apellidos_nombres" value="{{ old('apellidos_nombres', $documentDefaults['apellidos_nombres']) }}" required>
                                </label>
                                <label>
                                    Cedula de identidad
                                    <input name="cedula_identidad" value="{{ old('cedula_identidad', $documentDefaults['cedula_identidad']) }}" required>
                                </label>
                                <label>
                                    Cuenta numero
                                    <input name="cuenta_numero" value="{{ old('cuenta_numero', $documentDefaults['cuenta_numero']) }}" @required($isRegistro || $isBdh)>
                                </label>
                                <label>
                                    Codigo de socio
                                    <input name="codigo_socio" value="{{ old('codigo_socio', $documentDefaults['codigo_socio']) }}" @required($isRegistro)>
                                </label>
                                <label>
                                    Tipo de cuenta
                                    <input name="tipo_cuenta" value="{{ old('tipo_cuenta', $documentDefaults['tipo_cuenta']) }}" @required($isRegistro)>
                                </label>
                                <label>
                                    Ciudad
                                    <input name="ciudad" value="{{ old('ciudad', $documentDefaults['ciudad']) }}" @required($isSolicitud)>
                                </label>
                                <label>
                                    Dia
                                    <input name="dia" value="{{ old('dia', $documentDefaults['dia']) }}" maxlength="2" @required($isSolicitud || $isBdh)>
                                </label>
                                <label>
                                    Mes
                                    <input name="mes" value="{{ old('mes', $documentDefaults['mes']) }}" @required($isSolicitud || $isBdh)>
                                </label>
                                <label>
                                    Anio
                                    <input name="anio" value="{{ old('anio', $documentDefaults['anio']) }}" maxlength="4" @required($isSolicitud || $isBdh)>
                                </label>
                                @if ($isBdh)
                                    <label class="span-2">
                                        Direccion
                                        <input name="direccion" value="{{ old('direccion', $documentDefaults['direccion']) }}" required>
                                    </label>
                                @else
                                    <input type="hidden" name="direccion" value="{{ $documentDefaults['direccion'] }}">
                                @endif
                                @if ($isSolicitud)
                                    <label>
                                        Calidad de ingreso
                                        <select name="tipo_solicitante" required>
                                            <option value="socio" @selected($documentDefaults['tipo_solicitante'] === 'socio')>Socio</option>
                                            <option value="cliente" @selected($documentDefaults['tipo_solicitante'] === 'cliente')>Cliente</option>
                                        </select>
                                    </label>
                                    <label>
                                        Fondo mortuorio
                                        <select name="fondo_mortuorio" required>
                                            <option value="no" @selected($documentDefaults['fondo_mortuorio'] === 'no')>No solicita</option>
                                            <option value="si" @selected($documentDefaults['fondo_mortuorio'] === 'si')>Si solicita</option>
                                        </select>
                                    </label>
                                @else
                                    <input type="hidden" name="tipo_solicitante" value="{{ $documentDefaults['tipo_solicitante'] }}">
                                    <input type="hidden" name="fondo_mortuorio" value="{{ $documentDefaults['fondo_mortuorio'] }}">
                                @endif
                            </div>
                            <div class="actions">
                                <button class="button primary" type="submit">Generar PDF lleno</button>
                                <button class="button ghost" type="button" data-dialog-close>Cancelar</button>
                            </div>
                        </form>
                    </dialog>
                @endif
            @endforeach
        </div>
        @if ($internalComplete)
            <div class="actions">
                <a class="button primary" href="{{ route('accounts.show', [$opening, 'paso' => 'servicios']) }}">Continuar</a>
            </div>
        @endif
    </section>
    @endif

    @if ($activeStep === 'servicios')
    <section id="servicios" class="panel">
        <div class="panel-head">
            <h2>Servicios adicionales</h2>
            <span class="hint">La seleccion queda registrada en el expediente.</span>
        </div>
        <form method="post" action="{{ route('accounts.services.save', $opening) }}">
            @csrf
            <div class="service-grid">
                @foreach ($services as $service)
                    <label class="service-option">
                        <input type="checkbox" name="services[]" value="{{ $service->id }}" @checked(in_array($service->id, $selectedServices))>
                        <span>{{ $service->name }}</span>
                    </label>
                @endforeach
            </div>
            <div class="actions">
                <button class="button primary" type="submit">Guardar servicios</button>
            </div>
        </form>
        @if ($servicesComplete && $selectedServiceModels->isNotEmpty())
            <h3>Documentos de servicios seleccionados</h3>
            <div class="checklist">
                @foreach ($selectedServiceModels as $service)
                    @php
                        $template = $serviceTemplatesBySlug->get($serviceDocumentMap[$service->slug] ?? null);
                        $doc = $template ? $serviceDocs->get($template->id) : null;
                    @endphp
                    @if ($template)
                        <article class="check-item">
                            <div>
                                <h3>{{ $template->name }}</h3>
                                @if ($template->template_path)
                                    <a href="{{ asset($template->template_path) }}" target="_blank">Descargar formato</a>
                                @else
                                    <span class="hint">Documento generado por el proceso del servicio</span>
                                @endif
                                <p class="hint">Se guardara como: {{ str_replace('{expediente}', $opening->file_name, $template->file_name_pattern) }}</p>
                                @include('partials.badge', ['status' => $doc->status ?? 'pendiente'])
                            </div>
                            <form method="post" enctype="multipart/form-data" action="{{ route('accounts.services.documents.upload', $opening) }}">
                                @csrf
                                <input type="hidden" name="internal_document_template_id" value="{{ $template->id }}">
                                <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required>
                                <label class="check"><input type="checkbox" name="manual_signature_confirmed" value="1" required> Firma validada</label>
                                <select name="status">
                                    <option value="cargado">Cargado</option>
                                    <option value="validado">Validado</option>
                                    <option value="rechazado">Rechazado</option>
                                </select>
                                <button class="button secondary" type="submit">{{ $doc ? 'Reemplazar' : 'Subir' }}</button>
                            </form>
                        </article>
                    @endif
                @endforeach
            </div>
        @endif
        @if ($serviceDocsComplete)
            <div class="actions">
                <a class="button primary" href="{{ route('accounts.show', [$opening, 'paso' => 'resumen']) }}">Continuar</a>
            </div>
        @endif
    </section>
    @endif

    @if ($activeStep === 'resumen')
    <section id="resumen" class="panel">
        <div class="panel-head">
            <h2>Check List del expediente</h2>
            <span class="hint">{{ $progress }}% completado</span>
        </div>
        <div class="summary-grid">
            <div><strong>Tipo de cuenta</strong><span>{{ $opening->accountType->name }}</span></div>
            <div><strong>Socio</strong><span>{{ trim($opening->member_first_names.' '.$opening->member_last_names) ?: 'Pendiente' }}</span></div>
            <div><strong>Documentos cargados</strong><span>{{ $opening->documents->count() }}</span></div>
            <div><strong>Evidencia de consultas</strong><span>{{ $opening->externalEvidences->whereNotNull('screenshot_path')->count() ? 'Cargada' : 'Pendiente' }}</span></div>
            <div><strong>Servicios</strong><span>{{ count($selectedServices) }}</span></div>
            <div><strong>Estado</strong><span>{{ str_replace('_', ' ', $opening->status) }}</span></div>
            <div><strong>Revision digital</strong><span>{{ $opening->ai_review_status ? str_replace('_', ' ', $opening->ai_review_status).' ('.$opening->ai_review_score.'%)' : 'Pendiente' }}</span></div>
        </div>
        @if ($opening->ai_review_result)
            <h3>Resultado de revision digital</h3>
            <div class="ai-review">
                <strong>{{ $opening->ai_review_result['summary'] ?? 'Revision digital ejecutada.' }}</strong>
                <span>Fecha: {{ $opening->ai_reviewed_at?->format('d/m/Y H:i') }}</span>
                @if (!empty($opening->ai_review_result['findings']))
                    <div class="checklist">
                        @foreach ($opening->ai_review_result['findings'] as $finding)
                            <article class="check-item compact">
                                <div>
                                    <h3>{{ $finding['subject'] ?? 'Hallazgo' }}</h3>
                                    <p>{{ $finding['message'] ?? '' }}</p>
                                    @include('partials.badge', ['status' => ($finding['severity'] ?? 'warning') === 'error' ? 'observado' : 'con_observacion'])
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
        <h3>Documentos adjuntados</h3>
        <div class="checklist">
            @foreach ($opening->documents->sortBy(['document_scope', 'display_name']) as $document)
                <article class="check-item compact">
                    <div>
                        <h3>{{ $document->display_name }}</h3>
                        <p>{{ ucfirst($document->document_scope) }} - {{ basename($document->file_path) }}</p>
                        @include('partials.badge', ['status' => $document->status])
                    </div>
                </article>
            @endforeach
            @if ($opening->consent?->signed_file_path)
                <article class="check-item compact">
                    <div>
                        <h3>Consentimiento para el Tratamiento de Datos Personales</h3>
                        <p>{{ basename($opening->consent->signed_file_path) }}</p>
                        @include('partials.badge', ['status' => $opening->consent->status])
                    </div>
                </article>
            @endif
            @if ($opening->externalEvidences->whereNotNull('screenshot_path')->first())
                <article class="check-item compact">
                    <div>
                        <h3>Revision listas de control</h3>
                        <p>{{ basename($opening->externalEvidences->whereNotNull('screenshot_path')->first()->screenshot_path) }}</p>
                        @include('partials.badge', ['status' => 'cargado'])
                    </div>
                </article>
            @endif
        </div>
        <form method="post" action="{{ route('accounts.submit', $opening) }}">
            @csrf
            <button class="button primary" type="submit">{{ $opening->ai_review_status ? 'Ejecutar revision digital nuevamente' : 'Ejecutar revision digital' }}</button>
        </form>
        <h3>Historial de acciones</h3>
        <ol class="timeline">
            @foreach ($opening->histories->sortByDesc('created_at') as $history)
                <li>
                    <strong>{{ $history->action }}</strong>
                    <span>{{ $history->description }}</span>
                    <small>{{ $history->created_at?->format('d/m/Y H:i') }}</small>
                </li>
            @endforeach
        </ol>
    </section>
    @endif
@endsection

@extends('layouts.app')

@php
    $requirementDocs = $opening->documents->where('document_scope', 'requisito')->keyBy('account_type_requirement_id');
    $internalDocs = $opening->documents->where('document_scope', 'interno')->keyBy('internal_document_template_id');
    $serviceDocs = $opening->documents->where('document_scope', 'servicio')->keyBy('internal_document_template_id');
    $externalDocs = $opening->externalEvidences->keyBy(fn ($evidence) => $evidence->subject_key.'_'.$evidence->external_check_item_id);
    $selectedServices = $opening->services->pluck('additional_service_id')->all();
    $consentComplete = optional($opening->consent)->status === 'validado';
    $spouseRequirementIds = $opening->accountType->requirements->filter(fn ($requirement) => $requirement->type->slug === 'documentos-conyuge')->pluck('id');
    $optionalRequirements = $opening->accountType->requirements->where('is_required', false)->reject(fn ($requirement) => $requirement->type->slug === 'documentos-conyuge');
    $optionalSelectionHistory = $opening->histories->where('action', 'seleccionar_requisitos_opcionales')->sortByDesc('id')->first();
    $selectedOptionalRequirementIds = collect(data_get($optionalSelectionHistory?->metadata, 'requirement_ids', []));
    if (!$optionalSelectionHistory) {
        $selectedOptionalRequirementIds = $selectedOptionalRequirementIds->merge(
            $opening->documents->where('document_scope', 'requisito')->pluck('account_type_requirement_id')
        );
    }
    $selectedOptionalRequirementIds = $selectedOptionalRequirementIds
        ->map(fn ($id) => (int) $id)
        ->intersect($optionalRequirements->pluck('id'))
        ->unique();
    $requiredRequirementIds = $opening->accountType->requirements
        ->where('is_required', true)
        ->pluck('id')
        ->merge($opening->requires_spouse_documents ? $spouseRequirementIds : collect())
        ->merge($selectedOptionalRequirementIds)
        ->unique();
    $loadedRequirementIds = $opening->documents->where('document_scope', 'requisito')->whereIn('status', ['cargado', 'validado'])->pluck('account_type_requirement_id');
    $requirementsDocumentsComplete = $requiredRequirementIds->diff($loadedRequirementIds)->isEmpty();
    $requirementsComplete = $requirementsDocumentsComplete && $consentComplete;
    $requiredExternalIds = $externalChecks->where('is_required', true)->pluck('id');
    $externalComplete = collect(array_keys($externalSubjects))->every(function ($subjectKey) use ($opening, $requiredExternalIds) {
        $loadedExternalIds = $opening->externalEvidences
            ->where('subject_key', $subjectKey)
            ->whereNotNull('screenshot_path')
            ->pluck('external_check_item_id');

        return $requiredExternalIds->diff($loadedExternalIds)->isEmpty();
    });
    $requiredInternalIds = $internalTemplates->where('is_required', true)->pluck('id');
    $loadedInternalIds = $opening->documents->where('document_scope', 'interno')->whereIn('status', ['cargado', 'validado'])->pluck('internal_document_template_id');
    $internalComplete = $requiredInternalIds->diff($loadedInternalIds)->isEmpty();
    $servicesSelectionSaved = $opening->histories->contains('action', 'seleccionar_servicios');
    $serviceTemplatesBySlug = $serviceTemplates->keyBy('slug');
    $scannerServiceUrl = config('opening.scanner_service_url');
    $fondoDecision = data_get(
        $opening->histories->where('action', 'seleccionar_servicios')->sortByDesc('id')->first()?->metadata,
        'fondo_mortuorio'
    );
    $membershipDecision = data_get(
        $opening->histories->where('action', 'seleccionar_servicios')->sortByDesc('id')->first()?->metadata,
        'tipo_vinculacion'
    );
    $contributionCertificateApplies = in_array($opening->accountType->slug, ['cuenta-ahorro-programado', 'cuenta-juridica'], true);
    $servicesComplete = $servicesSelectionSaved
        && (!$contributionCertificateApplies || in_array($membershipDecision, ['socio', 'cliente'], true));
    $selectedServiceTemplateSlugs = collect();
    if ($fondoDecision === 'si') {
        $selectedServiceTemplateSlugs->push($serviceTemplatesBySlug->get('formulario-servicio-fondo-mortuorio')?->id);
    } elseif ($fondoDecision === 'no') {
        $selectedServiceTemplateSlugs->push($serviceTemplatesBySlug->get('sin-fondo-mortuorio')?->id);
    }
    if ($contributionCertificateApplies && $membershipDecision === 'socio') {
        $selectedServiceTemplateSlugs->push($serviceTemplatesBySlug->get('certificado-de-aportacion')?->id);
    }
    $selectedServiceTemplateIds = $selectedServiceTemplateSlugs->filter()->unique()->values();
    $selectedServiceDocumentTemplates = $serviceTemplates->whereIn('id', $selectedServiceTemplateIds);
    $availableRequiredServiceTemplateIds = $selectedServiceDocumentTemplates->pluck('id');
    $loadedServiceIds = $opening->documents->where('document_scope', 'servicio')->whereIn('status', ['cargado', 'validado'])->pluck('internal_document_template_id');
    $serviceDocsComplete = $servicesComplete && $availableRequiredServiceTemplateIds->diff($loadedServiceIds)->isEmpty();
    $steps = [
        'requisitos' => '1. Requisitos',
        'externas' => '2. Lista de control',
        'expediente' => '3. Expediente',
        'internos' => '4. Internos',
        'servicios' => '5. Otros',
        'resumen' => '6. Check List',
    ];
    $storageRoot = storage_path('app/private');
    $expedientStoragePath = $storageRoot.DIRECTORY_SEPARATOR.'aperturas'.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $opening->storage_folder);
    $agencyName = config("opening.agencies.{$opening->agency}.name", $opening->agency ?: 'Agencia no registrada');
    $documentStoragePath = fn (?string $path) => $path
        ? $storageRoot.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path)
        : null;
    $checkLoadedMark = fn ($path) => $path ? 'X' : '';
@endphp

@section('content')
    @unless ($activeStep === 'resumen')
    <section class="page-head">
        <div>
            <p class="eyebrow">{{ $opening->public_code }}</p>
            <h1>{{ $opening->accountType->name }}</h1>
            <div class="opening-meta">
                <span class="hint">{{ $opening->file_name_confirmed ? $opening->file_name : 'Nombre pendiente' }}</span>
                <span class="agency-label"><i data-lucide="building-2"></i> {{ $agencyName }}</span>
            </div>
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
    @endunless

    @if ($activeStep === 'requisitos')
    <section id="requisitos" class="panel">
        <div class="panel-head">
            <h2>Requisitos del tipo de cuenta</h2>
            <span class="hint">PDF, JPG o PNG. Máximo 5 MB por archivo.</span>
        </div>
        @if ($spouseRequirementIds->isNotEmpty())
            <form class="inline-choice" method="post" action="{{ route('accounts.spouse.update', $opening) }}">
                @csrf
                <label class="check">
                    <input type="checkbox" name="requires_spouse_documents" value="1" @checked($opening->requires_spouse_documents)>
                    El socio es casado o mantiene unión de hecho y requiere documentos del cónyuge
                </label>
                <button class="button secondary" type="submit"><i data-lucide="save"></i> Guardar condición</button>
            </form>
        @endif
        @if ($optionalRequirements->isNotEmpty())
            <form class="optional-requirements-choice" method="post" action="{{ route('accounts.optional-requirements.update', $opening) }}">
                @csrf
                <span>Documentos que aplican:</span>
                @foreach ($optionalRequirements as $optionalRequirement)
                    <label>
                        <input type="checkbox" name="optional_requirements[]" value="{{ $optionalRequirement->id }}" @checked($selectedOptionalRequirementIds->contains($optionalRequirement->id))>
                        {{ $optionalRequirement->label }}
                    </label>
                @endforeach
                <button class="button secondary" type="submit"><i data-lucide="save"></i> Guardar selección</button>
            </form>
        @endif
        <div class="checklist">
            @foreach ($opening->accountType->requirements as $requirement)
                @php $doc = $requirementDocs->get($requirement->id); @endphp
                @if ($requirement->type->slug === 'documentos-conyuge' && !$opening->requires_spouse_documents)
                    @continue
                @endif
                @if (!$requirement->is_required && $requirement->type->slug !== 'documentos-conyuge' && !$selectedOptionalRequirementIds->contains($requirement->id))
                    @continue
                @endif
                <article class="check-item">
                    <div>
                        <h3>{{ $requirement->label }}</h3>
                        <p>{{ $requirement->type->validation_rules }}</p>
                        @if ($requirement->file_name_pattern)
                            <p class="hint">Se guardará como: {{ str_replace('{expediente}', $opening->file_name, $requirement->file_name_pattern) }}</p>
                        @endif
                        @include('partials.badge', ['status' => $doc->status ?? 'pendiente'])
                        @if ($doc?->extracted_data)
                            @php
                                $data = $doc->extracted_data;
                                $fullName = trim((string) ($data['nombres_apellidos'] ?? ''));
                                $nameParts = preg_split('/\s+/', $fullName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                                $lastNameCount = count($nameParts) >= 3 ? 2 : 1;
                                $fallbackLastNames = $fullName ? implode(' ', array_slice($nameParts, 0, $lastNameCount)) : null;
                                $fallbackFirstNames = $fullName ? implode(' ', array_slice($nameParts, $lastNameCount)) : null;
                                $fields = match ($requirement->type->slug) {
                                    'cedula-papeleta', 'cedula' => [
                                        'Número de cédula' => $data['cedula'] ?? null,
                                        'Nombres' => $data['nombres'] ?? $fallbackFirstNames,
                                        'Apellidos' => $data['apellidos'] ?? $fallbackLastNames,
                                    ],
                                    'planilla-servicios' => [
                                        'Dirección' => $data['direccion'] ?? null,
                                    ],
                                    'ruc' => [
                                        'RUC' => $data['ruc'] ?? null,
                                        'Razón social' => $data['razon_social'] ?? null,
                                    ],
                                    default => [],
                                };
                                $fields = array_filter($fields, fn ($value) => filled($value));
                            @endphp
                            @if ($fields)
                                <dl class="extracted-fields">
                                    @foreach ($fields as $label => $value)
                                        @php $copyId = 'extracted-'.$doc->id.'-'.$loop->index; @endphp
                                        <div>
                                            <dt>{{ $label }}</dt>
                                            <dd class="extracted-value">
                                                <input id="{{ $copyId }}" value="{{ $value }}" readonly aria-label="{{ $label }} extraído">
                                                <button class="copy-extracted" type="button" data-copy-target="{{ $copyId }}" aria-label="Copiar {{ strtolower($label) }}" title="Copiar {{ strtolower($label) }}">
                                                    <i data-lucide="copy"></i>
                                                </button>
                                            </dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif
                        @endif
                        @if ($doc && in_array($requirement->type->slug, ['cedula', 'cedula-papeleta', 'planilla-servicios'], true))
                            <form class="extract-action" method="post" action="{{ route('accounts.requirements.extract', [$opening, $doc]) }}">
                                @csrf
                                <button class="doc-action" type="submit" aria-label="Extraer datos del documento" data-tooltip="Extraer nombres o dirección">
                                    <i data-lucide="sparkles"></i>
                                </button>
                            </form>
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
                        <input name="observations" placeholder="Observación si aplica">
                        <div class="upload-actions">
                            <button
                                class="button scanner-trigger"
                                type="button"
                                data-scan-requirement
                                data-requirement-id="{{ $requirement->id }}"
                                data-requirement-label="{{ $requirement->label }}"
                                data-requirement-slug="{{ $requirement->type->slug }}"
                                data-scan-url="{{ route('accounts.requirements.scan', $opening) }}"
                                data-scanner-url="{{ $scannerServiceUrl }}"
                            >
                                <i data-lucide="file-search"></i> Escanear
                            </button>
                            <button class="button secondary" type="submit"><i data-lucide="{{ $doc ? 'refresh-cw' : 'upload' }}"></i> {{ $doc ? 'Reemplazar' : 'Subir' }}</button>
                        </div>
                    </form>
                </article>
            @endforeach
        </div>

        <section class="embedded-consent" aria-labelledby="embedded-consent-title">
            <div class="embedded-consent-main">
                <div>
                    <h3 id="embedded-consent-title"><i data-lucide="shield-check"></i> Consentimiento de datos personales</h3>
                    <p>Complete el formato editable con los datos extraídos, imprima, firme y cargue el documento firmado.</p>
                </div>
                @include('partials.badge', ['status' => optional($opening->consent)->status ?? 'pendiente'])
            </div>

            <div class="doc-actions consent-inline-actions">
                <button class="doc-action" type="button" data-open-dialog="consent-data-dialog" aria-label="Abrir consentimiento editable" data-tooltip="Abrir editable">
                    <i data-lucide="file-pen-line"></i>
                </button>
                <a class="doc-action" href="{{ asset('formatos/CONSENTIMIENTO_DE_DATOS_PERSONALES_LAS_NAVES.pdf') }}" target="_blank" aria-label="Ver formato original" data-tooltip="Ver original">
                    <i data-lucide="file-search"></i>
                </a>
                @if ($consentComplete)
                    <a class="doc-action" href="{{ route('accounts.consent.preview', $opening) }}" target="_blank" aria-label="Previsualizar consentimiento cargado" data-tooltip="Previsualizar cargado">
                        <i data-lucide="eye"></i>
                    </a>
                @endif
            </div>

            <form class="upload-row consent-upload-row" method="post" enctype="multipart/form-data" action="{{ route('accounts.consent.upload', $opening) }}" data-requires-signature="1" data-signature-label="consentimiento de datos personales">
                @csrf
                <input type="file" name="signed_file" accept=".pdf,.jpg,.jpeg,.png" required>
                <label class="check consent-signature-check" title="Confirme que revisó visualmente la firma">
                    <input type="checkbox" name="manual_signature_confirmed" value="1">
                    Firma revisada
                </label>
                <input name="observations" placeholder="Observación">
                <div class="upload-actions">
                    <button
                        class="button scanner-trigger"
                        type="button"
                        data-scan-document
                        data-scan-url="{{ route('accounts.consent.scan', $opening) }}"
                        data-scanner-url="{{ $scannerServiceUrl }}"
                        data-document-label="Consentimiento de datos personales"
                        data-requires-signature="1"
                    >
                        <i data-lucide="file-search"></i> Escanear
                    </button>
                    <button class="button primary" type="submit"><i data-lucide="upload"></i> Subir y validar</button>
                </div>
            </form>
        </section>

        <dialog class="app-dialog" id="consent-data-dialog">
            <form class="dialog-card consent-data-form" method="get" action="{{ route('accounts.consent.edit', $opening) }}" target="_blank">
                <div class="dialog-head">
                    <div>
                        <span class="eyebrow">Datos del consentimiento</span>
                        <h3>Revise y complete</h3>
                    </div>
                    <button class="doc-action" type="button" data-close-dialog aria-label="Cerrar" data-tooltip="Cerrar">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <div class="dialog-fields">
                    <fieldset class="dialog-choice" data-person-type-choice>
                        <legend>Tipo de titular</legend>
                        <label>
                            <input type="radio" name="tipo_persona" value="natural" @checked(($consentDefaults['tipo_persona'] ?? 'natural') === 'natural')>
                            Persona natural
                        </label>
                        <label>
                            <input type="radio" name="tipo_persona" value="juridica" @checked(($consentDefaults['tipo_persona'] ?? 'natural') === 'juridica')>
                            Persona jurídica
                        </label>
                    </fieldset>

                    <div class="dialog-field-panel" data-person-type-panel="natural">
                        <label>Apellidos y nombres
                            <input name="apellidos_nombres" value="{{ $consentDefaults['apellidos_nombres'] ?? '' }}" autocomplete="off">
                        </label>
                        <label>Cédula de identidad
                            <input name="cedula_identidad" value="{{ $consentDefaults['cedula_identidad'] ?? '' }}" autocomplete="off">
                        </label>
                        <label>Correo electrónico
                            <input type="email" name="correo" value="{{ $consentDefaults['correo'] ?? '' }}" placeholder="correo@ejemplo.com">
                        </label>
                        <label>Número de celular
                            <input name="celular" value="{{ $consentDefaults['celular'] ?? '' }}" placeholder="09xxxxxxxx">
                        </label>
                        <label>Dirección
                            <input name="direccion" value="{{ $consentDefaults['direccion'] ?? '' }}" autocomplete="off">
                        </label>
                    </div>

                    <div class="dialog-field-panel" data-person-type-panel="juridica">
                        <label>Razón social
                            <input name="razon_social" value="{{ $consentDefaults['razon_social'] ?? '' }}" autocomplete="off">
                        </label>
                        <label>RUC
                            <input name="ruc" value="{{ $consentDefaults['ruc'] ?? '' }}" autocomplete="off">
                        </label>
                        <label>Representante legal
                            <input name="representante_legal" value="{{ $consentDefaults['representante_legal'] ?? '' }}" autocomplete="off">
                        </label>
                        <label>Cédula del representante
                            <input name="cedula_representante" value="{{ $consentDefaults['cedula_representante'] ?? '' }}" autocomplete="off">
                        </label>
                        <label>Correo electrónico
                            <input type="email" name="correo_juridico" value="{{ $consentDefaults['correo_juridico'] ?? '' }}" placeholder="correo@ejemplo.com">
                        </label>
                        <label>Número de celular
                            <input name="celular_juridico" value="{{ $consentDefaults['celular_juridico'] ?? '' }}" placeholder="09xxxxxxxx">
                        </label>
                    </div>

                    <div class="dialog-field-panel dialog-common-fields">
                        <label>Ciudad
                            <input name="ciudad" value="{{ $consentDefaults['ciudad'] ?? '' }}">
                        </label>
                        <label>Día
                            <input name="dia" value="{{ $consentDefaults['dia'] ?? now()->format('d') }}">
                        </label>
                        <label>Mes
                            <input name="mes" value="{{ $consentDefaults['mes'] ?? now()->locale('es')->translatedFormat('F') }}">
                        </label>
                        <label>Año
                            <input name="anio" value="{{ $consentDefaults['anio'] ?? now()->format('Y') }}">
                        </label>
                    </div>
                    <input type="hidden" name="tipo_cuenta" value="{{ $consentDefaults['tipo_cuenta'] ?? $opening->accountType->name }}">
                </div>
                <div class="dialog-actions">
                    <button class="button secondary" type="button" data-close-dialog>Cancelar</button>
                    <button class="button primary" type="submit"><i data-lucide="file-pen-line"></i> Abrir editable</button>
                </div>
            </form>
        </dialog>

        @if ($requirementsDocumentsComplete && !$consentComplete)
            <p class="step-note">Para continuar, cargue y valide el consentimiento firmado.</p>
        @endif
        @if ($requirementsComplete)
            <div class="actions">
                <a class="button primary" href="{{ route('accounts.show', [$opening, 'paso' => 'externas']) }}">Continuar <i data-lucide="arrow-right"></i></a>
            </div>
        @endif
    </section>
    @endif

    @if ($activeStep === 'externas')
    <section id="externas" class="panel">
        <div class="panel-head">
            <h2 class="panel-title"><i data-lucide="shield-check"></i> Lista de control externa</h2>
            <span class="hint">Pegue una evidencia por consulta. El sistema guardará un PDF consolidado por cada persona o entidad revisada.</span>
        </div>
        <form class="external-evidence-form" method="post" action="{{ route('accounts.external.upload', $opening) }}">
            @csrf
            @if ($opening->accountType->slug === 'cuenta-juridica')
                <label class="external-company-choice">
                    <input type="checkbox" name="company_check_applicable" value="1" @checked($companyExternalCheckApplicable)>
                    Realizar también la revisión de la empresa cuando aplique
                </label>
            @endif

            @php
                $displaySubjects = $externalSubjects;
                if ($opening->accountType->slug === 'cuenta-juridica') {
                    $displaySubjects['empresa'] = 'Empresa';
                }
            @endphp

            @foreach ($displaySubjects as $subjectKey => $subjectLabel)
                <section class="external-subject" data-external-subject="{{ $subjectKey }}" @hidden($subjectKey === 'empresa' && !$companyExternalCheckApplicable)>
                    <div class="external-subject-head">
                        <h3 class="subject-title"><i data-lucide="{{ $subjectKey === 'empresa' ? 'building-2' : 'user-round-check' }}"></i> Revisión: {{ $subjectLabel }}</h3>
                        <span>Las cuatro evidencias se guardarán en un PDF consolidado.</span>
                    </div>
                    <div class="external-grid">
                        @foreach ($externalChecks as $item)
                            @php $evidence = $externalDocs->get($subjectKey.'_'.$item->id); @endphp
                            <div>
                                <h3>{{ $item->name }}</h3>
                                <a class="doc-action external-link-action" href="{{ $item->url }}" target="_blank" rel="noopener" aria-label="Abrir enlace oficial" data-tooltip="Abrir enlace oficial">
                                    <i data-lucide="external-link"></i>
                                </a>
                                @include('partials.badge', ['status' => $evidence?->screenshot_path ? $evidence->result : 'pendiente'])
                                <select name="results[{{ $subjectKey }}][{{ $item->id }}]" @disabled($subjectKey === 'empresa' && !$companyExternalCheckApplicable)>
                                    <option value="sin_novedad" @selected(($evidence?->result ?? null) === 'sin_novedad')>Sin novedad</option>
                                    <option value="con_observacion" @selected(($evidence?->result ?? null) === 'con_observacion')>Con observación</option>
                                    <option value="no_aplica" @selected(($evidence?->result ?? null) === 'no_aplica')>No aplica</option>
                                    <option value="pendiente" @selected(($evidence?->result ?? 'pendiente') === 'pendiente')>Pendiente</option>
                                </select>
                                <input name="observations[{{ $subjectKey }}][{{ $item->id }}]" value="{{ $evidence?->advisor_observation }}" placeholder="Observación del asesor" @disabled($subjectKey === 'empresa' && !$companyExternalCheckApplicable)>
                                <input type="hidden" name="evidence_images[{{ $subjectKey }}][{{ $item->id }}]" class="pasted-evidence-input" data-has-evidence="{{ $evidence?->screenshot_path ? '1' : '0' }}" @required(!$evidence?->screenshot_path) @disabled($subjectKey === 'empresa' && !$companyExternalCheckApplicable)>
                                <div class="paste-capture compact-paste" tabindex="0">
                                    <i data-lucide="clipboard-paste" aria-hidden="true"></i>
                                    <span>{{ $evidence?->screenshot_path ? 'Pegar evidencia para reemplazar' : 'Pegar evidencia con Ctrl + V' }}</span>
                                    <img alt="Vista previa de la evidencia pegada" hidden>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
            <div class="actions">
                <button class="button secondary" type="submit"><i data-lucide="{{ $externalComplete ? 'refresh-cw' : 'clipboard-check' }}"></i> {{ $externalComplete ? 'Actualizar evidencias' : 'Guardar evidencias' }}</button>
            </div>
        </form>
        @if ($externalComplete)
            <div class="actions">
                <a class="button primary" href="{{ route('accounts.show', [$opening, 'paso' => 'expediente']) }}">Continuar <i data-lucide="arrow-right"></i></a>
            </div>
        @endif
    </section>
    @endif

    @if ($activeStep === 'expediente')
    <section id="expediente" class="panel file-name-step">
        <div class="panel-head">
            <h2 class="panel-title"><i data-lucide="folder-plus"></i> Nombre definitivo del expediente</h2>
            @include('partials.badge', ['status' => $opening->file_name_confirmed ? 'validado' : 'pendiente'])
        </div>
        <div class="step-guidance">
            <i data-lucide="clipboard-check"></i>
            <span>Ingrese el número de cuenta o nombre definitivo con el que se guardará el expediente. El sistema renombrará automáticamente la carpeta y los documentos cargados hasta este momento.</span>
        </div>
        @if ($opening->file_name_confirmed)
            <div class="confirmed-file-name">
                <span>Expediente</span>
                <strong>{{ $opening->file_name }}</strong>
            </div>
            <a class="button primary" href="{{ route('accounts.show', [$opening, 'paso' => 'internos']) }}">Continuar <i data-lucide="arrow-right"></i></a>
        @else
            <form class="file-name-form" method="post" action="{{ route('accounts.file-name.update', $opening) }}">
                @csrf
                <label>Número o nombre del expediente
                    <input name="file_name" value="{{ old('file_name') }}" placeholder="Ej. 14115" maxlength="120" required autofocus>
                </label>
                <small class="hint">Debe ser único en toda la cooperativa. Después de guardarlo no podrá modificarse desde este proceso.</small>
                <button class="button primary" type="submit"><i data-lucide="save"></i> Guardar nombre y renombrar archivos</button>
            </form>
        @endif
    </section>
    @endif

    @if ($activeStep === 'internos')
    <section id="internos" class="panel">
        <div class="panel-head">
            <h2>Documentos internos</h2>
            <span class="hint">Documentos manuales y documentos generados desde el sistema.</span>
        </div>
        <div class="doc-action-legend" aria-label="Acciones disponibles">
            <span><i data-lucide="sparkles"></i> Detectar datos</span>
            <span><i data-lucide="file-pen-line"></i> Vacío editable</span>
            <span><i data-lucide="file-search"></i> Original</span>
        </div>
        <div class="checklist">
            @foreach ($internalTemplates as $template)
                @php $doc = $internalDocs->get($template->id); @endphp
                <article class="check-item">
                    <div>
                        <h3>{{ $template->name }}</h3>
                        <span class="status">{{ match ($template->source) {
                            'manual' => 'MANUAL / EDITABLE',
                            'sistema' => 'SISTEMA',
                            default => 'SISTEMA',
                        } }}</span>
                        @if ($template->source === 'manual' && $template->template_path)
                            <div class="doc-actions">
                                <a class="doc-action" href="{{ route('accounts.internal.generate', [$opening, $template]) }}" target="_blank" aria-label="Detectar datos y abrir" data-tooltip="Detectar datos y abrir">
                                    <i data-lucide="sparkles"></i>
                                </a>
                                <a class="doc-action" href="{{ route('accounts.internal.generate', [$opening, $template, 'modo' => 'vacio']) }}" target="_blank" aria-label="Abrir documento vacío" data-tooltip="Abrir documento vacío">
                                    <i data-lucide="file-pen-line"></i>
                                </a>
                                <a class="doc-action" href="{{ route('accounts.internal.original', [$opening, $template]) }}" target="_blank" aria-label="Ver documento original" data-tooltip="Ver documento original">
                                    <i data-lucide="file-search"></i>
                                </a>
                            </div>
                        @elseif ($template->source === 'manual')
                            <span class="hint">Formato manual editable pendiente de adjuntar</span>
                        @elseif ($template->template_path)
                            <div class="doc-actions">
                                <a class="doc-action" href="{{ route('accounts.internal.original', [$opening, $template]) }}" target="_blank" aria-label="Ver documento original" data-tooltip="Ver documento original">
                                    <i data-lucide="file-search"></i>
                                </a>
                            </div>
                        @else
                            <span class="hint">Documento generado por el sistema</span>
                        @endif
                        @if ($template->file_name_pattern)
                            <p class="hint">Se guardará como: {{ str_replace('{expediente}', $opening->file_name, $template->file_name_pattern) }}</p>
                        @endif
                        @include('partials.badge', ['status' => $doc->status ?? 'pendiente'])
                        @if ($template->requires_signature && $doc && !$doc->manual_signature_confirmed)
                            <p class="hint"><strong>Firma pendiente:</strong> reemplace el archivo y confirme la revisión.</p>
                        @endif
                    </div>
                    <form method="post" enctype="multipart/form-data" action="{{ route('accounts.internal.upload', $opening) }}" @if($template->requires_signature) data-requires-signature="1" data-signature-label="{{ $template->name }}" @endif>
                        @csrf
                        <input type="hidden" name="internal_document_template_id" value="{{ $template->id }}">
                        <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required>
                        @if ($template->requires_signature)
                            <label class="check"><input type="checkbox" name="manual_signature_confirmed" value="1"> Firma validada</label>
                        @endif
                        <select name="status">
                            <option value="cargado">Cargado</option>
                            <option value="validado">Validado</option>
                            <option value="rechazado">Rechazado</option>
                        </select>
                        <div class="upload-actions">
                            <button
                                class="button scanner-trigger"
                                type="button"
                                data-scan-document
                                data-scan-url="{{ route('accounts.internal.scan', $opening) }}"
                                data-scanner-url="{{ $scannerServiceUrl }}"
                                data-template-id="{{ $template->id }}"
                                data-document-label="{{ $template->name }}"
                                data-requires-signature="{{ $template->requires_signature ? '1' : '0' }}"
                                data-scan-mode="multi-page"
                                data-max-pages="6"
                            >
                                <i data-lucide="file-search"></i> Escanear
                            </button>
                            <button class="button secondary" type="submit"><i data-lucide="{{ $doc ? 'refresh-cw' : 'upload' }}"></i> {{ $doc ? 'Reemplazar' : 'Subir' }}</button>
                        </div>
                    </form>
                </article>
            @endforeach
        </div>
        @if ($internalComplete)
            <div class="actions">
                <a class="button primary" href="{{ route('accounts.show', [$opening, 'paso' => 'servicios']) }}">Continuar <i data-lucide="arrow-right"></i></a>
            </div>
        @endif
    </section>
    @endif

    @if ($activeStep === 'servicios')
    <section id="servicios" class="panel">
        <div class="panel-head">
            <h2>Fondo mortuorio / Certificado de aportación</h2>
            <span class="hint">La selección queda registrada en el expediente.</span>
        </div>
        <form method="post" action="{{ route('accounts.services.save', $opening) }}">
            @csrf
            <div class="service-grid">
                <fieldset class="service-option service-decision">
                    <legend>Fondo mortuorio</legend>
                    <label><input type="radio" name="fondo_mortuorio" value="si" @checked($fondoDecision === 'si') required> Sí</label>
                    <label><input type="radio" name="fondo_mortuorio" value="no" @checked($fondoDecision === 'no') required> No</label>
                </fieldset>
                @if ($contributionCertificateApplies)
                    <fieldset class="service-option service-decision">
                        <legend>Tipo de vinculación (Certificado de aportación)</legend>
                        <label><input type="radio" name="tipo_vinculacion" value="socio" @checked($membershipDecision === 'socio') required> Socio</label>
                        <label><input type="radio" name="tipo_vinculacion" value="cliente" @checked($membershipDecision === 'cliente') required> Cliente</label>
                    </fieldset>
                @endif
            </div>
            <div class="actions">
                <button class="button primary" type="submit"><i data-lucide="save"></i> Guardar servicios</button>
            </div>
        </form>
        @if ($servicesComplete && $selectedServiceDocumentTemplates->isNotEmpty())
            <h3>Documentos según servicios seleccionados</h3>
            <div class="checklist">
                @foreach ($selectedServiceDocumentTemplates as $template)
                    @php
                        $doc = $serviceDocs->get($template->id);
                        $certificateFormatPending = $template->slug === 'certificado-de-aportacion'
                            && blank(config("opening.agencies.{$opening->agency}.contribution_certificate.original_path"));
                    @endphp
                    <article class="check-item">
                        <div>
                            <h3>{{ $template->name }}</h3>
                            @if ($certificateFormatPending)
                                <span class="hint">El formato del certificado de aportación para {{ $agencyName }} está pendiente de incorporar.</span>
                            @elseif (!$template->template_path)
                                <span class="hint">Documento pendiente de definir. Debe cargarse cuando sea generado desde el sistema.</span>
                            @elseif ($template->template_path)
                                <div class="doc-actions">
                                    <a class="doc-action" href="{{ route('accounts.services.documents.generate', [$opening, $template]) }}" target="_blank" aria-label="Detectar datos y abrir" data-tooltip="Detectar datos y abrir">
                                        <i data-lucide="sparkles"></i>
                                    </a>
                                    <a class="doc-action" href="{{ route('accounts.services.documents.generate', [$opening, $template, 'modo' => 'vacio']) }}" target="_blank" aria-label="Abrir documento vacío" data-tooltip="Abrir documento vacío">
                                        <i data-lucide="file-pen-line"></i>
                                    </a>
                                    <a class="doc-action" href="{{ route('accounts.services.documents.original', [$opening, $template]) }}" target="_blank" aria-label="Ver documento original" data-tooltip="Ver documento original">
                                        <i data-lucide="file-search"></i>
                                    </a>
                                </div>
                            @else
                                <span class="hint">Documento generado por el proceso del servicio</span>
                            @endif
                            <p class="hint">Se guardará como: {{ str_replace('{expediente}', $opening->file_name, $template->file_name_pattern) }}</p>
                            @include('partials.badge', ['status' => $doc->status ?? 'pendiente'])
                            @if ($template->requires_signature && $doc && !$doc->manual_signature_confirmed)
                                <p class="hint"><strong>Firma pendiente:</strong> reemplace el archivo y confirme la revisión.</p>
                            @endif
                        </div>
                        <form method="post" enctype="multipart/form-data" action="{{ route('accounts.services.documents.upload', $opening) }}" @if($template->requires_signature) data-requires-signature="1" data-signature-label="{{ $template->name }}" @endif>
                            @csrf
                            <input type="hidden" name="internal_document_template_id" value="{{ $template->id }}">
                            <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required>
                            @if ($template->requires_signature)
                                <label class="check"><input type="checkbox" name="manual_signature_confirmed" value="1"> Firma validada</label>
                            @endif
                            <select name="status">
                                <option value="cargado">Cargado</option>
                                <option value="validado">Validado</option>
                                <option value="rechazado">Rechazado</option>
                            </select>
                            <div class="upload-actions">
                                <button
                                    class="button scanner-trigger"
                                    type="button"
                                    data-scan-document
                                    data-scan-url="{{ route('accounts.services.documents.scan', $opening) }}"
                                    data-scanner-url="{{ $scannerServiceUrl }}"
                                    data-template-id="{{ $template->id }}"
                                    data-document-label="{{ $template->name }}"
                                    data-requires-signature="{{ $template->requires_signature ? '1' : '0' }}"
                                    data-scan-mode="multi-page"
                                    data-max-pages="6"
                                >
                                    <i data-lucide="file-search"></i> Escanear
                                </button>
                                <button class="button secondary" type="submit"><i data-lucide="{{ $doc ? 'refresh-cw' : 'upload' }}"></i> {{ $doc ? 'Reemplazar' : 'Subir' }}</button>
                            </div>
                        </form>
                    </article>
                @endforeach
            </div>
        @endif
        @if ($serviceDocsComplete)
            <div class="actions">
                <a class="button primary" href="{{ route('accounts.show', [$opening, 'paso' => 'resumen']) }}">Continuar <i data-lucide="arrow-right"></i></a>
            </div>
        @endif
    </section>
    @endif

    @if ($activeStep === 'resumen')
    <section id="resumen" class="checklist-only">
        <div class="checklist-actions">
            <a class="button ghost" href="{{ route('accounts.show', [$opening, 'paso' => 'servicios']) }}">Regresar</a>
            <a class="button secondary" href="{{ route('processes.index') }}">Cerrar</a>
            <button class="button primary" type="button" onclick="window.print()"><i data-lucide="save"></i> Guardar expediente</button>
        </div>
        <div class="official-check-wrap">
            <table class="official-checklist">
                <tbody>
                    <tr class="official-head">
                        <td colspan="4">
                            <strong>CHECK LIST DE DOCUMENTOS DE LA APERTURA DE CUENTA</strong>
                            <span>ARCHIVO FISICO</span>
                        </td>
                        <td colspan="3" class="official-logo"><img src="{{ asset('images/logo-las-naves.png') }}" alt="Las Naves"></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="socio-label">SOCIO N°</td>
                        <td colspan="3" class="socio-number">{{ $opening->file_name }}</td>
                    </tr>
                    <tr>
                        <td colspan="4" class="socio-label">AGENCIA</td>
                        <td colspan="3" class="socio-number">{{ $agencyName }}</td>
                    </tr>
                    <tr class="section-row">
                        <td colspan="6">{{ str_contains(strtolower($opening->accountType->name), 'jurid') ? 'PERSONAS JURIDICAS' : 'PERSONAS NATURALES' }}</td>
                        <td>Asis.<br>Op</td>
                    </tr>

                    <tr class="section-row">
                        <td colspan="7">DOCUMENTOS DEL EXPEDIENTE EN ORDEN</td>
                    </tr>

                    @foreach ($checklistRows as $index => $row)
                        <tr>
                            <td class="num">{{ $index + 1 }}</td>
                            <td colspan="5">
                                <strong>{{ $row['name'] }}</strong>
                                @if ($row['path'] && $row['path'] !== 'check-list-generado')
                                    <small class="checklist-file-name">{{ basename($row['path']) }}</small>
                                @endif
                            </td>
                            <td>{{ $row['loaded'] ? 'X' : '' }}</td>
                        </tr>
                    @endforeach

                    <tr class="path-row">
                        <td colspan="7"><strong>Ruta del expediente:</strong> {{ $expedientStoragePath }}</td>
                    </tr>
                    <tr class="signature-row">
                        <td colspan="4">Observaciones:</td>
                        <td colspan="3">Firma:</td>
                    </tr>
                    <tr><td colspan="4">Asistente Operativo</td><td colspan="3">Fecha</td></tr>
                    <tr class="signature-row">
                        <td colspan="4">Observaciones:</td>
                        <td colspan="3">Firma:</td>
                    </tr>
                    <tr><td colspan="4">Jefe de Captaciones</td><td colspan="3">Fecha</td></tr>
                    <tr class="signature-row">
                        <td colspan="4">Observaciones:</td>
                        <td colspan="3">Firma:</td>
                    </tr>
                    <tr><td colspan="4">Oficial de Cumplimiento</td><td colspan="3">Fecha</td></tr>
                    <tr class="signature-row">
                        <td colspan="4">Observaciones:</td>
                        <td colspan="3">Firma:</td>
                    </tr>
                    <tr><td colspan="4">Administrador de Riesgos</td><td colspan="3">Fecha</td></tr>
                    <tr class="signature-row">
                        <td colspan="4">Observaciones:</td>
                        <td colspan="3">Firma:</td>
                    </tr>
                    <tr><td colspan="4">Consejo de Vigilancia</td><td colspan="3">Fecha</td></tr>
                    <tr class="signature-row">
                        <td colspan="4">Observaciones:</td>
                        <td colspan="3">Firma:</td>
                    </tr>
                    <tr><td colspan="4">Auditoría Externa/Interna</td><td colspan="3">Fecha</td></tr>
                </tbody>
            </table>
        </div>
    </section>
    @endif

    <dialog class="app-dialog scanner-dialog" id="scanner-dialog">
        <form class="dialog-card scanner-card" method="dialog">
            <div class="dialog-head">
                <div>
                    <span class="eyebrow">Escaneo directo</span>
                    <h3 id="scanner-dialog-title">Escanear documento</h3>
                </div>
                <button class="doc-action" type="button" data-scanner-close aria-label="Cerrar" data-tooltip="Cerrar">
                    <i data-lucide="x"></i>
                </button>
            </div>

            <div class="scanner-progress" aria-live="polite">
                <span data-scanner-step-counter>Paso 1 de 1</span>
                <strong data-scanner-status>Coloque el documento en el escáner.</strong>
            </div>

            <p class="scanner-instruction" data-scanner-instruction>Coloque el documento en el escáner y presione Escanear.</p>
            <p class="scanner-error" data-scanner-error hidden></p>

            <div class="scanner-preview-grid" data-scanner-previews></div>

            <div class="dialog-actions scanner-actions">
                <button class="button secondary" type="button" data-scanner-cancel>Cancelar</button>
                <button class="button secondary" type="button" data-scanner-repeat hidden><i data-lucide="refresh-cw"></i> Repetir captura</button>
                <button class="button primary" type="button" data-scanner-finish hidden><i data-lucide="file-text"></i> Generar PDF</button>
                <button class="button primary" type="button" data-scanner-next><i data-lucide="file-search"></i> Escanear</button>
            </div>
        </form>
    </dialog>
@endsection

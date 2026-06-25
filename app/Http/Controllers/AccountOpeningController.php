<?php

namespace App\Http\Controllers;

use App\Models\AccountOpening;
use App\Models\AccountType;
use App\Models\AccountTypeRequirement;
use App\Models\ActionHistory;
use App\Models\AdditionalService;
use App\Models\ExternalCheckEvidence;
use App\Models\ExternalCheckItem;
use App\Models\InternalDocumentTemplate;
use App\Models\OperationalCheckItem;
use App\Models\OperationalCheckRecord;
use App\Models\PersonalDataConsent;
use App\Models\SelectedAdditionalService;
use App\Models\UploadedDocument;
use App\Services\AutomatedReviewService;
use App\Services\DocumentExtractionService;
use App\Services\SignatureValidationService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccountOpeningController extends Controller
{
    private const MAX_FILE_KB = 5120;

    public function create(?AccountType $accountType = null)
    {
        return view('accounts.create', [
            'accountTypes' => AccountType::where('active', true)->with('requirements.type')->get(),
            'selectedAccountType' => $accountType,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'account_type_id' => ['required', 'exists:account_types,id'],
        ]);

        $opening = DB::transaction(function () use ($data) {
            $publicCode = $this->makePublicCode();
            $agency = auth()->user()->agency;
            $agencyFolder = config("opening.agencies.{$agency}.folder", $agency);

            $opening = AccountOpening::create([
                'public_code' => $publicCode,
                'file_name' => $publicCode,
                'file_name_confirmed' => false,
                'storage_folder' => $this->safeFileNamePart($agencyFolder).'/Temporales/'.$publicCode,
                'agency' => $agency,
                'account_type_id' => $data['account_type_id'],
                'created_by' => auth()->id(),
                'status' => 'borrador',
            ]);

            PersonalDataConsent::create([
                'account_opening_id' => $opening->id,
                'template_path' => 'formatos/CONSENTIMIENTO_DE_DATOS_PERSONALES_LAS_NAVES.pdf',
            ]);

            $this->audit($opening, 'crear_expediente', 'Expediente temporal de apertura creado.', [
                'agency' => $agency,
                'storage_folder' => $opening->storage_folder,
            ]);

            return $opening;
        });

        return redirect()->route('accounts.show', $opening)->with('success', 'Proceso iniciado. Cargue primero los requisitos.');
    }

    public function show(Request $request, AccountOpening $opening)
    {
        $opening->load(['accountType.requirements.type', 'consent', 'documents', 'externalEvidences', 'services', 'histories', 'operationalRecords']);
        $workflow = $this->workflowState($opening);
        $requestedStep = $request->query('paso', $workflow['current']);
        $activeStep = in_array($requestedStep, array_keys($workflow['unlocked']), true) && $workflow['unlocked'][$requestedStep]
            ? $requestedStep
            : $workflow['current'];

        return view('accounts.show', [
            'opening' => $opening,
            'externalChecks' => ExternalCheckItem::where('active', true)->orderBy('sort_order')->get(),
            'internalTemplates' => $this->internalTemplatesForOpening($opening)->get(),
            'serviceTemplates' => $this->serviceDocumentTemplates()->get(),
            'services' => AdditionalService::where('active', true)->orderBy('name')->get(),
            'serviceDocumentMap' => $this->serviceDocumentMap(),
            'externalSubjects' => $this->externalCheckSubjects($opening),
            'companyExternalCheckApplicable' => $this->companyExternalCheckApplicable($opening),
            'consentDefaults' => $this->consentDocumentDefaults($opening),
            'documentDefaults' => $this->documentDownloadDefaults($opening),
            'checklistRows' => $this->orderedChecklistRows($opening),
            'progress' => $this->calculateProgress($opening),
            'workflow' => $workflow,
            'activeStep' => $activeStep,
        ]);
    }

    public function updateMember(Request $request, AccountOpening $opening)
    {
        $data = $request->validate([
            'member_identification' => ['nullable', 'digits:10'],
            'member_first_names' => ['nullable', 'string', 'max:120'],
            'member_last_names' => ['nullable', 'string', 'max:120'],
            'member_nationality' => ['nullable', 'string', 'max:80'],
            'member_address' => ['nullable', 'string', 'max:200'],
        ]);

        $opening->update($data);
        $this->audit($opening, 'actualizar_datos_socio', 'Datos principales del socio actualizados.');

        return back()->with('success', 'Datos del socio guardados.');
    }

    public function editConsentDocument(Request $request, AccountOpening $opening)
    {
        $opening->load(['accountType']);

        return view('accounts.generated-documents.consent', [
            'opening' => $opening,
            'fields' => array_merge(
                $this->consentDocumentDefaults($opening),
                $request->only([
                    'tipo_persona',
                    'apellidos_nombres',
                    'cedula_identidad',
                    'razon_social',
                    'ruc',
                    'representante_legal',
                    'cedula_representante',
                    'ciudad',
                    'dia',
                    'mes',
                    'anio',
                    'tipo_cuenta',
                    'correo',
                    'celular',
                    'correo_juridico',
                    'celular_juridico',
                    'direccion',
                ])
            ),
        ]);
    }

    public function updateSpouseRequirement(Request $request, AccountOpening $opening)
    {
        $opening->update([
            'requires_spouse_documents' => $request->boolean('requires_spouse_documents'),
        ]);

        $this->audit(
            $opening,
            'actualizar_condicion_conyuge',
            $opening->requires_spouse_documents
                ? 'Se marcó que aplica documentación de cónyuge.'
                : 'Se marcó que no aplica documentación de cónyuge.'
        );

        return redirect()
            ->route('accounts.show', [$opening, 'paso' => 'requisitos'])
            ->with('success', 'Condición de cónyuge actualizada.');
    }

    public function updateOptionalRequirements(Request $request, AccountOpening $opening)
    {
        $allowedIds = $opening->accountType->requirements()
            ->where('is_required', false)
            ->whereHas('type', fn ($query) => $query->where('slug', '!=', 'documentos-conyuge'))
            ->pluck('id');

        $selectedIds = collect($request->input('optional_requirements', []))
            ->map(fn ($id) => (int) $id)
            ->intersect($allowedIds)
            ->values();

        $this->audit(
            $opening,
            'seleccionar_requisitos_opcionales',
            'Requisitos opcionales actualizados.',
            ['requirement_ids' => $selectedIds->all()]
        );

        return redirect()
            ->route('accounts.show', [$opening, 'paso' => 'requisitos'])
            ->with('success', 'Requisitos opcionales actualizados.');
    }

    public function uploadConsent(Request $request, AccountOpening $opening)
    {
        $data = $request->validate([
            'signed_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_FILE_KB],
            'manual_signature_confirmed' => ['required', 'accepted'],
            'observations' => ['nullable', 'string', 'max:500'],
        ], [
            'manual_signature_confirmed.required' => 'Debe revisar visualmente el consentimiento y confirmar que contiene la firma.',
            'manual_signature_confirmed.accepted' => 'Debe confirmar manualmente que el consentimiento contiene firma.',
        ]);

        $signatureValidator = app(SignatureValidationService::class);
        $signatureError = $signatureValidator->validationError(
            $request->file('signed_file'),
            'formatos/CONSENTIMIENTO_DE_DATOS_PERSONALES_LAS_NAVES.pdf'
        );
        if ($signatureError) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'requisitos'])
                ->withErrors($signatureError);
        }

        $path = $this->storeFile($request->file('signed_file'), $opening, 'consentimiento', 'Consentimiento para el Tratamiento de Datos Personales_{expediente}');
        $autoSignal = $signatureValidator->hasAutomaticSignal($request->file('signed_file'));

        $opening->consent()->updateOrCreate(
            ['account_opening_id' => $opening->id],
            [
                'signed_file_path' => $path,
                'status' => 'validado',
                'auto_signature_detected' => $autoSignal,
                'manual_signature_confirmed' => true,
                'validated_at' => now(),
                'observations' => $data['observations'] ?? null,
            ]
        );

        $consentData = app(DocumentExtractionService::class)->extract(
            'cedula',
            $request->file('signed_file'),
            config('opening.ocr_on_upload', false)
        );
        $this->syncMemberDataFromExtraction($opening, 'cedula', $consentData);

        $this->audit($opening, 'validar_consentimiento', 'Consentimiento firmado cargado y validado manualmente.');

        return redirect()
            ->route('accounts.show', [$opening, 'paso' => 'requisitos'])
            ->with('success', 'Consentimiento validado. Puede continuar con los requisitos.');
    }

    public function uploadScannedConsent(Request $request, AccountOpening $opening)
    {
        $data = $request->validate([
            'captures' => ['required', 'array'],
            'captures.*.key' => ['required', 'string', 'max:80'],
            'captures.*.title' => ['required', 'string', 'max:120'],
            'captures.*.image' => ['required', 'string'],
            'manual_signature_confirmed' => ['required', 'accepted'],
            'observations' => ['nullable', 'string', 'max:500'],
        ], [
            'manual_signature_confirmed.required' => 'Debe revisar visualmente el consentimiento y confirmar que contiene la firma.',
            'manual_signature_confirmed.accepted' => 'Debe confirmar manualmente que el consentimiento contiene firma.',
        ]);

        $scanned = $this->storeScannedDocumentPdf(
            $opening,
            $data['captures'],
            'Consentimiento para el Tratamiento de Datos Personales_{expediente}',
            'consentimiento'
        );
        [$scannedFile, $tempPath] = $this->temporaryUploadedFileFromStorage($scanned['path'], $scanned['file_name']);

        $signatureValidator = app(SignatureValidationService::class);
        $signatureError = $signatureValidator->validationError(
            $scannedFile,
            'formatos/CONSENTIMIENTO_DE_DATOS_PERSONALES_LAS_NAVES.pdf'
        );

        if ($signatureError) {
            @unlink($tempPath);

            return response()->json(['message' => $signatureError], 422);
        }

        $autoSignal = $signatureValidator->hasAutomaticSignal($scannedFile);
        $consentData = app(DocumentExtractionService::class)->extract(
            'cedula',
            $scannedFile,
            config('opening.ocr_on_upload', false)
        );
        @unlink($tempPath);

        $consentData['capturas_escaneadas'] = $scanned['captures'];
        $consentData['flujo_escaneo'] = 'documento_simple';

        $opening->consent()->updateOrCreate(
            ['account_opening_id' => $opening->id],
            [
                'signed_file_path' => $scanned['path'],
                'status' => 'validado',
                'auto_signature_detected' => $autoSignal,
                'manual_signature_confirmed' => true,
                'validated_at' => now(),
                'observations' => $data['observations'] ?? null,
            ]
        );

        $this->syncMemberDataFromExtraction($opening, 'cedula', $consentData);
        $this->audit($opening, 'escanear_consentimiento', 'Consentimiento firmado escaneado y validado manualmente.');

        return response()->json([
            'message' => 'Consentimiento escaneado y validado.',
            'file_path' => $scanned['path'],
            'captures' => $scanned['captures'],
            'redirect' => route('accounts.show', [$opening, 'paso' => 'requisitos']),
        ]);
    }

    public function previewConsent(AccountOpening $opening)
    {
        $consent = $opening->consent()->firstOrFail();

        abort_unless($consent->signed_file_path && Storage::exists($consent->signed_file_path), 404);

        return response()->file(Storage::path($consent->signed_file_path));
    }

    public function uploadRequirement(Request $request, AccountOpening $opening)
    {
        $data = $request->validate([
            'account_type_requirement_id' => [
                'required',
                Rule::exists('account_type_requirements', 'id')->where('account_type_id', $opening->account_type_id),
            ],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_FILE_KB],
            'status' => ['required', Rule::in(['cargado', 'validado', 'rechazado'])],
            'observations' => ['nullable', 'string', 'max:500'],
        ]);

        $requirement = AccountTypeRequirement::with('type')->findOrFail($data['account_type_requirement_id']);
        $path = $this->storeFile($request->file('file'), $opening, 'requisitos', $requirement->file_name_pattern);
        $extracted = app(DocumentExtractionService::class)->extract(
            $requirement->type->slug,
            $request->file('file'),
            in_array($requirement->type->slug, ['cedula', 'cedula-papeleta', 'planilla-servicios'], true)
        );
        $this->syncMemberDataFromExtraction($opening, $requirement->type->slug, $extracted);

        $this->persistRequirementDocument(
            $opening,
            $requirement,
            $path,
            $request->file('file')->getClientOriginalName(),
            $request->file('file')->getMimeType(),
            $request->file('file')->getSize(),
            $data['status'],
            $extracted
        );

        if ($data['observations'] ?? null) {
            $opening->histories()->create([
                'action' => 'observacion_documento',
                'description' => $data['observations'],
            ]);
        }

        $this->audit($opening, 'cargar_requisito', "Documento cargado: {$requirement->label}.");

        $nextStep = $this->requiredDocumentsAreValid($opening->fresh(['accountType', 'documents'])) ? 'externas' : 'requisitos';

        return redirect()
            ->route('accounts.show', [$opening, 'paso' => $nextStep])
            ->with('success', 'Requisito guardado.');
    }

    public function uploadScannedRequirement(Request $request, AccountOpening $opening)
    {
        $data = $request->validate([
            'account_type_requirement_id' => [
                'required',
                Rule::exists('account_type_requirements', 'id')->where('account_type_id', $opening->account_type_id),
            ],
            'captures' => ['required', 'array'],
            'captures.*.key' => ['required', 'string', 'max:80'],
            'captures.*.title' => ['required', 'string', 'max:120'],
            'captures.*.image' => ['required', 'string'],
        ]);

        $requirement = AccountTypeRequirement::with('type')->findOrFail($data['account_type_requirement_id']);
        $slug = $requirement->type->slug;
        $captures = collect($data['captures'])->keyBy('key');
        $expectedCaptures = $this->expectedScanCaptures($slug);
        $missingCaptures = collect($expectedCaptures)->pluck('key')->diff($captures->keys());

        if ($missingCaptures->isNotEmpty()) {
            return response()->json([
                'message' => 'Debe completar todas las capturas obligatorias para finalizar.',
                'missing' => $missingCaptures->values(),
            ], 422);
        }

        $images = [];
        $storedCaptures = [];

        foreach ($expectedCaptures as $captureConfig) {
            $capture = $captures->get($captureConfig['key']);
            $jpeg = $this->decodeScannedJpeg((string) $capture['image']);
            $captureName = $captureConfig['file_name'];
            $capturePath = "aperturas/{$opening->storage_folder}/{$captureName}.jpg";

            Storage::put($capturePath, $jpeg);

            $images[] = [
                'title' => $captureConfig['title'],
                'jpeg' => $jpeg,
            ];
            $storedCaptures[] = [
                'key' => $captureConfig['key'],
                'title' => $captureConfig['title'],
                'path' => $capturePath,
            ];
        }

        $fileName = $this->buildStoredFileName($opening, $requirement->file_name_pattern ?: $requirement->label.'_{expediente}', 'pdf');
        $path = "aperturas/{$opening->storage_folder}/{$fileName}";
        Storage::put($path, $this->makePdfFromJpegs($images));

        if (!is_dir(storage_path('tmp'))) {
            mkdir(storage_path('tmp'), 0775, true);
        }

        $tempPath = tempnam(storage_path('tmp'), 'scan_');
        if ($tempPath === false) {
            return response()->json([
                'message' => 'No se pudo preparar el archivo escaneado para validación.',
            ], 500);
        }

        file_put_contents($tempPath, Storage::get($path));
        $scannedFile = new UploadedFile($tempPath, $fileName, 'application/pdf', null, true);

        $extracted = app(DocumentExtractionService::class)->extract(
            $slug,
            $scannedFile,
            in_array($slug, ['cedula', 'cedula-papeleta', 'planilla-servicios'], true)
        );
        @unlink($tempPath);

        $extracted['capturas_escaneadas'] = $storedCaptures;
        $extracted['flujo_escaneo'] = count($expectedCaptures) === 4 ? 'cedula_y_papeleta_4_caras' : 'documento_simple';

        $this->syncMemberDataFromExtraction($opening, $slug, $extracted);

        $document = $this->persistRequirementDocument(
            $opening,
            $requirement,
            $path,
            $fileName,
            'application/pdf',
            Storage::size($path),
            'cargado',
            $extracted
        );

        $this->audit($opening, 'escanear_requisito', "Documento escaneado: {$requirement->label}.");

        return response()->json([
            'message' => 'Documento escaneado correctamente. Validación ejecutada.',
            'document_id' => $document->id,
            'file_path' => $path,
            'captures' => $storedCaptures,
            'extracted_data' => $extracted,
            'redirect' => route('accounts.show', [$opening, 'paso' => 'requisitos']),
        ]);
    }

    public function extractRequirementData(AccountOpening $opening, UploadedDocument $document)
    {
        abort_unless(
            $document->account_opening_id === $opening->id
                && $document->document_scope === 'requisito'
                && $document->account_type_requirement_id,
            404
        );

        $requirement = AccountTypeRequirement::with('type')
            ->where('account_type_id', $opening->account_type_id)
            ->findOrFail($document->account_type_requirement_id);
        $slug = $requirement->type->slug;

        abort_unless(in_array($slug, ['cedula', 'cedula-papeleta', 'planilla-servicios'], true), 404);

        if (!Storage::exists($document->file_path)) {
            return back()->withErrors('No se encontró el archivo almacenado para realizar la extracción.');
        }

        $file = new UploadedFile(
            Storage::path($document->file_path),
            $document->original_name ?: basename($document->file_path),
            $document->mime_type,
            null,
            true
        );
        $extracted = app(DocumentExtractionService::class)->extract($slug, $file, true);

        $document->update(['extracted_data' => $extracted]);
        $this->syncMemberDataFromExtraction($opening, $slug, $extracted);
        $this->audit($opening, 'extraer_datos_requisito', "Datos extraídos nuevamente de {$requirement->label}.");

        $hasUsefulData = in_array($slug, ['cedula', 'cedula-papeleta'], true)
            ? filled($extracted['nombres'] ?? null) || filled($extracted['apellidos'] ?? null)
            : filled($extracted['direccion'] ?? null);

        if (!$hasUsefulData) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'requisitos'])
                ->withErrors('No se pudieron reconocer los datos. Verifique que el documento sea legible y esté correctamente orientado.');
        }

        return redirect()
            ->route('accounts.show', [$opening, 'paso' => 'requisitos'])
            ->with('success', 'Datos extraídos. Puede copiarlos desde el requisito.');
    }

    public function uploadExternalEvidence(Request $request, AccountOpening $opening)
    {
        if (!$this->consentIsValid($opening)) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'requisitos'])
                ->withErrors('Debe cargar y validar el consentimiento firmado antes de continuar.');
        }

        $data = $request->validate([
            'evidence_images' => ['nullable', 'array'],
            'evidence_images.*' => ['nullable', 'array'],
            'evidence_images.*.*' => ['nullable', 'string'],
            'results' => ['required', 'array'],
            'results.*' => ['required', 'array'],
            'results.*.*' => [Rule::in(['sin_novedad', 'con_observacion', 'no_aplica', 'pendiente'])],
            'observations' => ['nullable', 'array'],
            'observations.*' => ['nullable', 'array'],
            'observations.*.*' => ['nullable', 'string', 'max:500'],
            'company_check_applicable' => ['nullable', 'boolean'],
        ]);

        if (!$this->requiredDocumentsAreValid($opening)) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'requisitos'])
                ->withErrors('Complete y valide todos los documentos obligatorios antes de registrar consultas externas.');
        }

        $items = ExternalCheckItem::where('active', true)->orderBy('sort_order')->get();
        $companyApplicable = $request->boolean('company_check_applicable');
        $subjects = $this->externalCheckSubjects($opening, $companyApplicable);

        foreach ($subjects as $subjectKey => $subjectLabel) {
            $existingPath = $opening->externalEvidences()
                ->where('subject_key', $subjectKey)
                ->whereNotNull('screenshot_path')
                ->value('screenshot_path');
            $subjectImages = $data['evidence_images'][$subjectKey] ?? [];
            $postedImages = collect($subjectImages)->filter(fn ($value) => filled($value));
            $mustGeneratePdf = !$existingPath || $postedImages->isNotEmpty();

            if ($mustGeneratePdf) {
                $missing = $items
                    ->filter(fn ($item) => blank($subjectImages[$item->id] ?? null))
                    ->pluck('name');

                if ($missing->isNotEmpty()) {
                    return redirect()
                        ->route('accounts.show', [$opening, 'paso' => 'externas'])
                        ->withErrors("Pegue todas las evidencias de {$subjectLabel}. Faltan: ".$missing->implode(', ').'.');
                }
            }

            $path = $mustGeneratePdf
                ? $this->storePastedImagesAsPdf(
                    $items,
                    $subjectImages,
                    $opening,
                    "Revisión listas de control {$subjectLabel}_{expediente}"
                )
                : $existingPath;

            if (!$path) {
                return redirect()
                    ->route('accounts.show', [$opening, 'paso' => 'externas'])
                    ->withErrors("Complete las evidencias de {$subjectLabel} antes de continuar.");
            }

            foreach ($items as $item) {
                ExternalCheckEvidence::updateOrCreate(
                    [
                        'account_opening_id' => $opening->id,
                        'external_check_item_id' => $item->id,
                        'subject_key' => $subjectKey,
                    ],
                    [
                        'result' => $data['results'][$subjectKey][$item->id] ?? 'pendiente',
                        'screenshot_path' => $path,
                        'advisor_observation' => $data['observations'][$subjectKey][$item->id] ?? null,
                        'uploaded_by' => auth()->id(),
                        'uploaded_at' => now(),
                    ]
                );
            }
        }

        $this->audit(
            $opening,
            'cargar_evidencia_externa',
            'Evidencias de consulta externa registradas.',
            ['company_check_applicable' => $companyApplicable]
        );

        $nextStep = $this->externalChecksAreComplete($opening) ? 'expediente' : 'externas';

        return redirect()
            ->route('accounts.show', [$opening, 'paso' => $nextStep])
            ->with('success', 'Evidencia externa guardada.');
    }

    public function confirmFileName(Request $request, AccountOpening $opening)
    {
        if (!$this->externalChecksAreComplete($opening)) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'externas'])
                ->withErrors('Complete las consultas externas antes de asignar el nombre definitivo.');
        }

        if ($opening->file_name_confirmed) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'internos'])
                ->withErrors('El nombre definitivo del expediente ya fue asignado.');
        }

        $request->merge([
            'file_name' => trim((string) $request->input('file_name')),
        ]);

        $data = $request->validate([
            'file_name' => ['required', 'string', 'max:120', Rule::unique('account_openings', 'file_name')],
        ], [
            'file_name.unique' => 'Ya existe un expediente con este número o nombre en la cooperativa.',
        ]);

        $oldName = $opening->file_name;
        $newName = $data['file_name'];
        $oldDirectory = "aperturas/{$opening->storage_folder}";
        $agencyFolder = config("opening.agencies.{$opening->agency}.folder", $opening->agency);
        $newStorageFolder = $this->safeFileNamePart($agencyFolder).'/'.$this->safeFileNamePart($newName);
        $newDirectory = "aperturas/{$newStorageFolder}";

        if (Storage::exists($newDirectory)) {
            return back()->withErrors('Ya existe una carpeta con este nombre. Ingrese otro número de expediente.');
        }

        $pathMap = [];
        $movedFiles = [];

        try {
            Storage::makeDirectory($newDirectory);

            foreach (Storage::allFiles($oldDirectory) as $oldPath) {
                $oldBaseName = basename($oldPath);
                $newBaseName = str_replace(
                    $this->safeFileNamePart($oldName),
                    $this->safeFileNamePart($newName),
                    $oldBaseName
                );
                $newPath = "{$newDirectory}/{$newBaseName}";

                if (!Storage::move($oldPath, $newPath)) {
                    throw new \RuntimeException("No se pudo mover {$oldBaseName}.");
                }

                $pathMap[$oldPath] = $newPath;
                $movedFiles[$newPath] = $oldPath;
            }

            DB::transaction(function () use ($opening, $oldName, $newName, $newStorageFolder, $pathMap) {
                if ($opening->consent?->signed_file_path && isset($pathMap[$opening->consent->signed_file_path])) {
                    $opening->consent->update([
                        'signed_file_path' => $pathMap[$opening->consent->signed_file_path],
                    ]);
                }

                foreach ($opening->documents as $document) {
                    if (isset($pathMap[$document->file_path])) {
                        $document->update(['file_path' => $pathMap[$document->file_path]]);
                    }
                }

                foreach ($opening->externalEvidences as $evidence) {
                    if ($evidence->screenshot_path && isset($pathMap[$evidence->screenshot_path])) {
                        $evidence->update(['screenshot_path' => $pathMap[$evidence->screenshot_path]]);
                    }
                }

                $opening->update([
                    'file_name' => $newName,
                    'file_name_confirmed' => true,
                    'storage_folder' => $newStorageFolder,
                ]);

                $this->audit($opening, 'asignar_nombre_expediente', 'Nombre definitivo del expediente asignado.', [
                    'anterior' => $oldName,
                    'nuevo' => $newName,
                    'storage_folder' => $newStorageFolder,
                ]);
            });

            Storage::deleteDirectory($oldDirectory);
        } catch (\Throwable $exception) {
            foreach (array_reverse($movedFiles, true) as $newPath => $oldPath) {
                if (Storage::exists($newPath)) {
                    Storage::move($newPath, $oldPath);
                }
            }
            Storage::deleteDirectory($newDirectory);

            report($exception);

            return back()->withErrors('No se pudo renombrar la carpeta del expediente. Intente nuevamente.');
        }

        return redirect()
            ->route('accounts.show', [$opening->fresh(), 'paso' => 'internos'])
            ->with('success', 'Nombre definitivo guardado. La carpeta y los archivos fueron renombrados.');
    }

    public function generateInternalDocument(Request $request, AccountOpening $opening, InternalDocumentTemplate $template)
    {
        if (!$opening->file_name_confirmed) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'expediente'])
                ->withErrors('Asigne el nombre definitivo del expediente antes de generar documentos internos.');
        }
        $template = $this->internalTemplatesForOpening($opening)
            ->where('id', $template->id)
            ->firstOrFail();

        if (!$template->template_path) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'internos'])
                ->withErrors('Este documento no tiene formato descargable configurado.');
        }

        $data = array_merge(
            $request->query('modo') === 'vacio'
                ? $this->blankPersonalDocumentDefaults($opening)
                : $this->documentDownloadDefaults($opening, true),
            $request->only([
                'apellidos_nombres',
                'cedula_identidad',
                'cuenta_numero',
                'codigo_socio',
                'tipo_cuenta',
                'direccion',
                'ciudad',
                'dia',
                'mes',
                'anio',
                'tipo_solicitante',
                'fondo_mortuorio',
            ])
        );

        if (filled($data['apellidos_nombres'] ?? null) || filled($data['cedula_identidad'] ?? null)) {
            $this->persistCorrectedMemberData($opening, $data);
        }

        $opening = $opening->fresh('accountType');
        $downloadName = $this->downloadFileName($template, $opening).'.pdf';

        $this->audit($opening, 'generar_documento_interno', "Documento generado para descarga: {$template->name}.");

        return view('accounts.generated-documents.show', [
            'opening' => $opening,
            'template' => $template,
            'fields' => $data,
            'downloadName' => $downloadName,
        ]);
    }

    public function showInternalOriginal(AccountOpening $opening, InternalDocumentTemplate $template)
    {
        if (!$opening->file_name_confirmed) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'expediente'])
                ->withErrors('Asigne el nombre definitivo del expediente antes de abrir documentos internos.');
        }
        $template = $this->internalTemplatesForOpening($opening)
            ->where('id', $template->id)
            ->firstOrFail();

        if (!$template->template_path || !is_file(public_path($template->template_path))) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'internos'])
                ->withErrors('Este documento no tiene formato original configurado.');
        }

        $this->audit($opening, 'abrir_formato_original', "Formato original abierto: {$template->name}.");

        return response()->file(public_path($template->template_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.basename($template->template_path).'"',
        ]);
    }

    public function uploadInternalDocument(Request $request, AccountOpening $opening)
    {
        if (!$this->consentIsValid($opening)) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'requisitos'])
                ->withErrors('Debe cargar y validar el consentimiento firmado antes de continuar.');
        }

        $data = $request->validate([
            'internal_document_template_id' => ['required', 'exists:internal_document_templates,id'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_FILE_KB],
            'manual_signature_confirmed' => ['nullable'],
            'status' => ['required', Rule::in(['cargado', 'validado', 'rechazado'])],
        ], [
            'manual_signature_confirmed.accepted' => 'Debe confirmar que el documento interno contiene firma cuando aplique.',
        ]);

        if (!$this->externalChecksAreComplete($opening)) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'externas'])
                ->withErrors('Complete las capturas obligatorias de consultas externas antes de documentos internos.');
        }

        if (!$opening->file_name_confirmed) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'expediente'])
                ->withErrors('Asigne el nombre definitivo del expediente antes de cargar documentos internos.');
        }

        $template = $this->internalTemplatesForOpening($opening)
            ->where('id', $data['internal_document_template_id'])
            ->firstOrFail();

        if ($template->requires_signature) {
            $request->validate([
                'manual_signature_confirmed' => ['required', 'accepted'],
            ], [
                'manual_signature_confirmed.required' => "Revise la firma de {$template->name} antes de cargarlo.",
                'manual_signature_confirmed.accepted' => "Debe confirmar que {$template->name} contiene firma.",
            ]);

            $signatureError = app(SignatureValidationService::class)->validationError(
                $request->file('file'),
                $template->template_path
            );

            if ($signatureError) {
                return redirect()
                    ->route('accounts.show', [$opening, 'paso' => 'internos'])
                    ->withErrors($signatureError);
            }
        }

        $path = $this->storeFile($request->file('file'), $opening, 'internos', $template->file_name_pattern);

        UploadedDocument::updateOrCreate(
            [
                'account_opening_id' => $opening->id,
                'internal_document_template_id' => $template->id,
                'document_scope' => 'interno',
            ],
            [
                'display_name' => $template->name,
                'file_path' => $path,
                'original_name' => $request->file('file')->getClientOriginalName(),
                'mime_type' => $request->file('file')->getMimeType(),
                'file_size' => $request->file('file')->getSize(),
                'status' => $data['status'],
                'manual_signature_confirmed' => !$template->requires_signature || $request->boolean('manual_signature_confirmed'),
                'uploaded_by' => auth()->id(),
            ]
        );

        $this->audit($opening, 'cargar_documento_interno', "Documento interno cargado: {$template->name}.");

        $nextStep = $this->internalDocumentsAreComplete($opening) ? 'servicios' : 'internos';

        return redirect()
            ->route('accounts.show', [$opening, 'paso' => $nextStep])
            ->with('success', 'Documento interno guardado.');
    }

    public function uploadScannedInternalDocument(Request $request, AccountOpening $opening)
    {
        if (!$this->consentIsValid($opening)) {
            return response()->json([
                'message' => 'Debe cargar y validar el consentimiento firmado antes de continuar.',
            ], 422);
        }

        if (!$this->externalChecksAreComplete($opening)) {
            return response()->json([
                'message' => 'Complete las capturas obligatorias de consultas externas antes de documentos internos.',
            ], 422);
        }

        if (!$opening->file_name_confirmed) {
            return response()->json([
                'message' => 'Asigne el nombre definitivo del expediente antes de cargar documentos internos.',
            ], 422);
        }

        $data = $request->validate([
            'internal_document_template_id' => ['required', 'exists:internal_document_templates,id'],
            'captures' => ['required', 'array'],
            'captures.*.key' => ['required', 'string', 'max:80'],
            'captures.*.title' => ['required', 'string', 'max:120'],
            'captures.*.image' => ['required', 'string'],
            'manual_signature_confirmed' => ['nullable'],
            'status' => ['nullable', Rule::in(['cargado', 'validado', 'rechazado'])],
        ]);

        $template = $this->internalTemplatesForOpening($opening)
            ->where('id', $data['internal_document_template_id'])
            ->firstOrFail();

        if ($template->requires_signature && !$request->boolean('manual_signature_confirmed')) {
            return response()->json([
                'message' => "Debe confirmar que {$template->name} contiene firma.",
            ], 422);
        }

        $scanned = $this->storeScannedDocumentPdf(
            $opening,
            $data['captures'],
            $template->file_name_pattern ?: $template->name.'_{expediente}',
            'interno_'.$template->slug
        );

        if ($template->requires_signature) {
            [$scannedFile, $tempPath] = $this->temporaryUploadedFileFromStorage($scanned['path'], $scanned['file_name']);
            $signatureError = app(SignatureValidationService::class)->validationError(
                $scannedFile,
                $template->template_path
            );
            @unlink($tempPath);

            if ($signatureError) {
                return response()->json(['message' => $signatureError], 422);
            }
        }

        $document = UploadedDocument::updateOrCreate(
            [
                'account_opening_id' => $opening->id,
                'internal_document_template_id' => $template->id,
                'document_scope' => 'interno',
            ],
            [
                'display_name' => $template->name,
                'file_path' => $scanned['path'],
                'original_name' => $scanned['file_name'],
                'mime_type' => 'application/pdf',
                'file_size' => $scanned['size'],
                'status' => $data['status'] ?? 'cargado',
                'extracted_data' => [
                    'capturas_escaneadas' => $scanned['captures'],
                    'flujo_escaneo' => 'documento_simple',
                ],
                'manual_signature_confirmed' => !$template->requires_signature || $request->boolean('manual_signature_confirmed'),
                'uploaded_by' => auth()->id(),
            ]
        );

        $this->audit($opening, 'escanear_documento_interno', "Documento interno escaneado: {$template->name}.");

        $nextStep = $this->internalDocumentsAreComplete($opening) ? 'servicios' : 'internos';

        return response()->json([
            'message' => 'Documento interno escaneado correctamente.',
            'document_id' => $document->id,
            'file_path' => $scanned['path'],
            'captures' => $scanned['captures'],
            'redirect' => route('accounts.show', [$opening, 'paso' => $nextStep]),
        ]);
    }

    public function saveServices(Request $request, AccountOpening $opening)
    {
        if (!$opening->file_name_confirmed) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'expediente'])
                ->withErrors('Asigne el nombre definitivo del expediente antes de registrar servicios.');
        }
        if (!$this->consentIsValid($opening)) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'requisitos'])
                ->withErrors('Debe cargar y validar el consentimiento firmado antes de continuar.');
        }

        if (!$this->internalDocumentsAreComplete($opening)) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'internos'])
                ->withErrors('Complete los documentos internos antes de registrar servicios adicionales.');
        }

        $data = $request->validate([
            'fondo_mortuorio' => ['required', Rule::in(['si', 'no'])],
            'tipo_vinculacion' => [
                Rule::requiredIf($this->contributionCertificateApplies($opening)),
                'nullable',
                Rule::in(['socio', 'cliente']),
            ],
        ]);

        DB::transaction(function () use ($opening, $data) {
            SelectedAdditionalService::where('account_opening_id', $opening->id)->delete();
            $fondoId = AdditionalService::where('slug', 'fondo-mortuorio')->value('id');
            $serviceIds = collect();

            if ($data['fondo_mortuorio'] === 'si') {
                $serviceIds->push($fondoId);
            }

            foreach ($serviceIds->filter()->unique() as $serviceId) {
                SelectedAdditionalService::create([
                    'account_opening_id' => $opening->id,
                    'additional_service_id' => $serviceId,
                    'selected_by' => auth()->id(),
                ]);
            }
        });

        $this->audit(
            $opening,
            'seleccionar_servicios',
            'Servicios adicionales actualizados.',
            [
                'fondo_mortuorio' => $data['fondo_mortuorio'],
                'tipo_vinculacion' => $this->contributionCertificateApplies($opening)
                    ? ($data['tipo_vinculacion'] ?? null)
                    : null,
            ]
        );

        return redirect()
            ->route('accounts.show', [$opening, 'paso' => 'servicios'])
            ->with('success', 'Servicios adicionales guardados.');
    }

    public function generateServiceDocument(Request $request, AccountOpening $opening, InternalDocumentTemplate $template)
    {
        if (!$opening->file_name_confirmed) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'expediente'])
                ->withErrors('Asigne el nombre definitivo del expediente antes de generar documentos de servicios.');
        }
        $template = $this->serviceDocumentTemplates()
            ->whereIn('slug', $this->requiredServiceTemplateSlugs($opening))
            ->where('id', $template->id)
            ->firstOrFail();

        if (!$template->template_path) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'servicios'])
                ->withErrors('El formato de este servicio aún está pendiente de incorporar.');
        }

        $certificateProfile = null;
        if ($this->isContributionCertificateTemplate($template)) {
            $certificateProfile = $this->contributionCertificateProfile($opening);
            if (blank($certificateProfile['original_path'] ?? null)) {
                return redirect()
                    ->route('accounts.show', [$opening, 'paso' => 'servicios'])
                    ->withErrors("El certificado de aportación para {$this->agencyDocumentPlace($opening)} está pendiente de incorporar.");
            }
        }

        $data = array_merge(
            $request->query('modo') === 'vacio'
                ? $this->blankPersonalDocumentDefaults($opening)
                : $this->documentDownloadDefaults($opening, true),
            $request->only([
            'apellidos_nombres',
            'cedula_identidad',
            'cuenta_numero',
            'valor_nominal',
            'ciudad',
            'dia',
            'mes',
            'anio',
        ]));

        if ($certificateProfile) {
            $data['certificate_profile'] = $certificateProfile;
        }

        return view('accounts.generated-documents.show', [
            'opening' => $opening->fresh('accountType'),
            'template' => $template,
            'fields' => $data,
            'downloadName' => $this->downloadFileName($template, $opening).'.pdf',
        ]);
    }

    public function showServiceOriginal(AccountOpening $opening, InternalDocumentTemplate $template)
    {
        if (!$opening->file_name_confirmed) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'expediente'])
                ->withErrors('Asigne el nombre definitivo del expediente antes de abrir documentos de servicios.');
        }
        $template = $this->serviceDocumentTemplates()
            ->whereIn('slug', $this->requiredServiceTemplateSlugs($opening))
            ->where('id', $template->id)
            ->firstOrFail();

        $templatePath = $template->template_path;
        if ($this->isContributionCertificateTemplate($template)) {
            $templatePath = $this->contributionCertificateProfile($opening)['original_path'] ?? null;
        }

        abort_unless($templatePath && is_file(public_path($templatePath)), 404);

        return response()->file(public_path($templatePath), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.basename($templatePath).'"',
        ]);
    }

    public function uploadServiceDocument(Request $request, AccountOpening $opening)
    {
        if (!$opening->file_name_confirmed) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'expediente'])
                ->withErrors('Asigne el nombre definitivo del expediente antes de cargar documentos de servicios.');
        }
        if (!$this->consentIsValid($opening)) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'requisitos'])
                ->withErrors('Debe cargar y validar el consentimiento firmado antes de continuar.');
        }

        $data = $request->validate([
            'internal_document_template_id' => ['required', 'exists:internal_document_templates,id'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_FILE_KB],
            'manual_signature_confirmed' => ['nullable'],
            'status' => ['required', Rule::in(['cargado', 'validado', 'rechazado'])],
        ], [
            'manual_signature_confirmed.accepted' => 'Debe confirmar que el documento del servicio contiene firma.',
        ]);

        if (!$this->servicesStepIsComplete($opening)) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'servicios'])
                ->withErrors('Guarde primero los servicios seleccionados.');
        }

        $template = $this->serviceDocumentTemplates()
            ->whereIn('slug', $this->requiredServiceTemplateSlugs($opening))
            ->where('id', $data['internal_document_template_id'])
            ->firstOrFail();

        if ($template->requires_signature) {
            $request->validate([
                'manual_signature_confirmed' => ['required', 'accepted'],
            ], [
                'manual_signature_confirmed.required' => "Revise la firma de {$template->name} antes de cargarlo.",
                'manual_signature_confirmed.accepted' => "Debe confirmar que {$template->name} contiene firma.",
            ]);

            $signatureError = app(SignatureValidationService::class)->validationError(
                $request->file('file'),
                $template->template_path
            );

            if ($signatureError) {
                return redirect()
                    ->route('accounts.show', [$opening, 'paso' => 'servicios'])
                    ->withErrors($signatureError);
            }
        }

        $path = $this->storeFile($request->file('file'), $opening, 'servicios', $template->file_name_pattern);

        UploadedDocument::updateOrCreate(
            [
                'account_opening_id' => $opening->id,
                'internal_document_template_id' => $template->id,
                'document_scope' => 'servicio',
            ],
            [
                'display_name' => $template->name,
                'file_path' => $path,
                'original_name' => $request->file('file')->getClientOriginalName(),
                'mime_type' => $request->file('file')->getMimeType(),
                'file_size' => $request->file('file')->getSize(),
                'status' => $data['status'],
                'manual_signature_confirmed' => !$template->requires_signature || $request->boolean('manual_signature_confirmed'),
                'uploaded_by' => auth()->id(),
            ]
        );

        $this->audit($opening, 'cargar_documento_servicio', "Documento de servicio cargado: {$template->name}.");

        $nextStep = $this->serviceDocumentsAreComplete($opening) ? 'resumen' : 'servicios';

        return redirect()
            ->route('accounts.show', [$opening, 'paso' => $nextStep])
            ->with('success', 'Documento de servicio guardado.');
    }

    public function uploadScannedServiceDocument(Request $request, AccountOpening $opening)
    {
        if (!$opening->file_name_confirmed) {
            return response()->json([
                'message' => 'Asigne el nombre definitivo del expediente antes de cargar documentos de servicios.',
            ], 422);
        }

        if (!$this->consentIsValid($opening)) {
            return response()->json([
                'message' => 'Debe cargar y validar el consentimiento firmado antes de continuar.',
            ], 422);
        }

        if (!$this->servicesStepIsComplete($opening)) {
            return response()->json([
                'message' => 'Guarde primero los servicios seleccionados.',
            ], 422);
        }

        $data = $request->validate([
            'internal_document_template_id' => ['required', 'exists:internal_document_templates,id'],
            'captures' => ['required', 'array'],
            'captures.*.key' => ['required', 'string', 'max:80'],
            'captures.*.title' => ['required', 'string', 'max:120'],
            'captures.*.image' => ['required', 'string'],
            'manual_signature_confirmed' => ['nullable'],
            'status' => ['nullable', Rule::in(['cargado', 'validado', 'rechazado'])],
        ]);

        $template = $this->serviceDocumentTemplates()
            ->whereIn('slug', $this->requiredServiceTemplateSlugs($opening))
            ->where('id', $data['internal_document_template_id'])
            ->firstOrFail();

        if ($template->requires_signature && !$request->boolean('manual_signature_confirmed')) {
            return response()->json([
                'message' => "Debe confirmar que {$template->name} contiene firma.",
            ], 422);
        }

        $scanned = $this->storeScannedDocumentPdf(
            $opening,
            $data['captures'],
            $template->file_name_pattern ?: $template->name.'_{expediente}',
            'servicio_'.$template->slug
        );

        if ($template->requires_signature) {
            [$scannedFile, $tempPath] = $this->temporaryUploadedFileFromStorage($scanned['path'], $scanned['file_name']);
            $signatureError = app(SignatureValidationService::class)->validationError(
                $scannedFile,
                $template->template_path
            );
            @unlink($tempPath);

            if ($signatureError) {
                return response()->json(['message' => $signatureError], 422);
            }
        }

        $document = UploadedDocument::updateOrCreate(
            [
                'account_opening_id' => $opening->id,
                'internal_document_template_id' => $template->id,
                'document_scope' => 'servicio',
            ],
            [
                'display_name' => $template->name,
                'file_path' => $scanned['path'],
                'original_name' => $scanned['file_name'],
                'mime_type' => 'application/pdf',
                'file_size' => $scanned['size'],
                'status' => $data['status'] ?? 'cargado',
                'extracted_data' => [
                    'capturas_escaneadas' => $scanned['captures'],
                    'flujo_escaneo' => 'documento_simple',
                ],
                'manual_signature_confirmed' => !$template->requires_signature || $request->boolean('manual_signature_confirmed'),
                'uploaded_by' => auth()->id(),
            ]
        );

        $this->audit($opening, 'escanear_documento_servicio', "Documento de servicio escaneado: {$template->name}.");

        $nextStep = $this->serviceDocumentsAreComplete($opening) ? 'resumen' : 'servicios';

        return response()->json([
            'message' => 'Documento de servicio escaneado correctamente.',
            'document_id' => $document->id,
            'file_path' => $scanned['path'],
            'captures' => $scanned['captures'],
            'redirect' => route('accounts.show', [$opening, 'paso' => $nextStep]),
        ]);
    }

    public function saveOperationalCheck(Request $request, AccountOpening $opening)
    {
        $data = $request->validate([
            'operational_check_item_id' => ['required', 'exists:operational_check_items,id'],
            'status' => ['required', Rule::in(['pendiente', 'completado', 'observado', 'no_aplica'])],
            'account_number' => ['nullable', 'string', 'max:40'],
            'observation' => ['nullable', 'string', 'max:500'],
        ]);

        if (!$this->servicesStepIsComplete($opening)) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'servicios'])
                ->withErrors('Complete los servicios adicionales antes de registrar el cierre operativo.');
        }

        OperationalCheckRecord::updateOrCreate(
            [
                'account_opening_id' => $opening->id,
                'operational_check_item_id' => $data['operational_check_item_id'],
            ],
            [
                'status' => $data['status'],
                'account_number' => $data['account_number'] ?? null,
                'observation' => $data['observation'] ?? null,
                'completed_by' => null,
                'completed_at' => $data['status'] === 'completado' ? now() : null,
            ]
        );

        $this->audit($opening, 'registrar_cierre_operativo', 'Actividad operativa actualizada segun manual de apertura.');

        $nextStep = $this->operationalChecksAreComplete($opening) ? 'resumen' : 'operativo';

        return redirect()
            ->route('accounts.show', [$opening, 'paso' => $nextStep])
            ->with('success', 'Cierre operativo actualizado.');
    }

    public function submitReview(AccountOpening $opening)
    {
        if (!$this->readyToSubmit($opening)) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => $this->workflowState($opening)['current']])
                ->withErrors('El expediente aún tiene requisitos obligatorios pendientes.');
        }

        $result = app(AutomatedReviewService::class)->review($opening);

        $opening->update([
            'status' => $result['status'],
            'submitted_at' => now(),
            'ai_review_status' => $result['status'],
            'ai_review_score' => $result['score'],
            'ai_review_result' => $result,
            'ai_reviewed_at' => now(),
        ]);

        $this->audit(
            $opening,
            'revision_digital_ia',
            $result['summary'],
            ['score' => $result['score'], 'findings' => $result['findings']]
        );

        return redirect()
            ->route('accounts.show', [$opening, 'paso' => 'resumen'])
            ->with('success', $result['summary']);
    }

    private function storeFile(UploadedFile $file, AccountOpening $opening, string $step, ?string $pattern = null): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'pdf');
        $fileName = $this->buildStoredFileName($opening, $pattern ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), $extension);

        return $file->storeAs("aperturas/{$opening->storage_folder}", $fileName);
    }

    private function storeScannedDocumentPdf(
        AccountOpening $opening,
        array $capturePayloads,
        ?string $pattern,
        string $slug,
        ?array $expectedCaptures = null
    ): array {
        $captures = collect($capturePayloads)->keyBy('key');
        $expectedCaptures ??= $this->expectedScanCaptures($slug);
        $missingCaptures = collect($expectedCaptures)->pluck('key')->diff($captures->keys());

        if ($missingCaptures->isNotEmpty()) {
            abort(422, 'Debe completar todas las capturas obligatorias para finalizar.');
        }

        $images = [];
        $storedCaptures = [];

        foreach ($expectedCaptures as $captureConfig) {
            $capture = $captures->get($captureConfig['key']);
            $jpeg = $this->decodeScannedJpeg((string) $capture['image']);
            $captureName = Str::slug($slug.' '.$captureConfig['file_name']) ?: 'documento_escaneado';
            $capturePath = "aperturas/{$opening->storage_folder}/{$captureName}.jpg";

            Storage::put($capturePath, $jpeg);

            $images[] = [
                'title' => $captureConfig['title'],
                'jpeg' => $jpeg,
            ];
            $storedCaptures[] = [
                'key' => $captureConfig['key'],
                'title' => $captureConfig['title'],
                'path' => $capturePath,
            ];
        }

        $fileName = $this->buildStoredFileName($opening, $pattern ?: 'Documento escaneado_{expediente}', 'pdf');
        $path = "aperturas/{$opening->storage_folder}/{$fileName}";
        Storage::put($path, $this->makePdfFromJpegs($images));

        return [
            'path' => $path,
            'file_name' => $fileName,
            'size' => Storage::size($path),
            'captures' => $storedCaptures,
        ];
    }

    private function temporaryUploadedFileFromStorage(string $path, string $fileName): array
    {
        if (!is_dir(storage_path('tmp'))) {
            mkdir(storage_path('tmp'), 0775, true);
        }

        $tempPath = tempnam(storage_path('tmp'), 'scan_');
        if ($tempPath === false) {
            abort(500, 'No se pudo preparar el archivo escaneado para validación.');
        }

        file_put_contents($tempPath, Storage::get($path));

        return [
            new UploadedFile($tempPath, $fileName, 'application/pdf', null, true),
            $tempPath,
        ];
    }

    private function persistRequirementDocument(
        AccountOpening $opening,
        AccountTypeRequirement $requirement,
        string $path,
        string $originalName,
        string $mimeType,
        int $fileSize,
        string $status,
        array $extracted
    ): UploadedDocument {
        return UploadedDocument::updateOrCreate(
            [
                'account_opening_id' => $opening->id,
                'account_type_requirement_id' => $requirement->id,
                'document_scope' => 'requisito',
            ],
            [
                'display_name' => $requirement->label,
                'file_path' => $path,
                'original_name' => $originalName,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'status' => $status,
                'extracted_data' => $extracted,
                'uploaded_by' => auth()->id(),
            ]
        );
    }

    private function expectedScanCaptures(string $slug): array
    {
        if (in_array($slug, ['cedula', 'cedula-papeleta'], true)) {
            return [
                [
                    'key' => 'cedula_frontal',
                    'title' => 'Cédula - lado frontal',
                    'file_name' => 'cedula_frontal',
                    'instruction' => 'Coloque el lado frontal de la cédula y presione Escanear.',
                ],
                [
                    'key' => 'cedula_posterior',
                    'title' => 'Cédula - lado posterior',
                    'file_name' => 'cedula_posterior',
                    'instruction' => 'Coloque el lado posterior de la cédula y presione Continuar.',
                ],
                [
                    'key' => 'papeleta_votacion_frontal',
                    'title' => 'Certificado de votación - lado frontal',
                    'file_name' => 'papeleta_votacion_frontal',
                    'instruction' => 'Coloque el lado frontal del certificado de votación y presione Continuar.',
                ],
                [
                    'key' => 'papeleta_votacion_posterior',
                    'title' => 'Certificado de votación - lado posterior',
                    'file_name' => 'papeleta_votacion_posterior',
                    'instruction' => 'Coloque el lado posterior del certificado de votación y presione Finalizar.',
                ],
            ];
        }

        return [
            [
                'key' => 'documento',
                'title' => 'Documento escaneado',
                'file_name' => Str::slug("documento {$slug}") ?: 'documento_escaneado',
                'instruction' => 'Coloque el documento en el escáner y presione Escanear.',
            ],
        ];
    }

    private function decodeScannedJpeg(string $dataUrl): string
    {
        if (!preg_match('/^data:image\/(?:jpeg|jpg);base64,([A-Za-z0-9+\/=]+)$/', $dataUrl, $matches)) {
            abort(422, 'El servicio local debe devolver una imagen JPG en base64.');
        }

        $binary = base64_decode($matches[1], true);

        if ($binary === false || strlen($binary) < 1000) {
            abort(422, 'La imagen escaneada está vacía o ilegible.');
        }

        if (strlen($binary) > self::MAX_FILE_KB * 1024) {
            abort(422, 'Cada captura escaneada no debe superar 5 MB.');
        }

        if (!getimagesizefromstring($binary)) {
            abort(422, 'No se pudo leer la imagen escaneada.');
        }

        return $binary;
    }

    private function storePastedImageAsPdf(string $dataUrl, AccountOpening $opening, string $step, ?string $pattern = null): string
    {
        $binary = $this->decodePastedJpeg($dataUrl);

        $fileName = $this->buildStoredFileName($opening, $pattern ?: 'Evidencia_{expediente}_'.Str::uuid(), 'pdf');
        $path = "aperturas/{$opening->storage_folder}/{$fileName}";
        Storage::put($path, $this->makePdfFromJpeg($binary));

        return $path;
    }

    private function storePastedImagesAsPdf($items, array $dataUrls, AccountOpening $opening, string $pattern): string
    {
        $images = [];

        foreach ($items as $item) {
            $images[] = [
                'title' => $item->name,
                'jpeg' => $this->decodePastedJpeg($dataUrls[$item->id]),
            ];
        }

        $fileName = $this->buildStoredFileName($opening, $pattern, 'pdf');
        $path = "aperturas/{$opening->storage_folder}/{$fileName}";
        Storage::put($path, $this->makePdfFromJpegs($images));

        return $path;
    }

    private function decodePastedJpeg(string $dataUrl): string
    {
        if (!preg_match('/^data:image\/(jpeg|jpg);base64,([A-Za-z0-9+\/=]+)$/', $dataUrl, $matches)) {
            abort(422, 'La evidencia pegada debe ser una imagen valida.');
        }

        $binary = base64_decode($matches[2], true);

        if ($binary === false || strlen($binary) === 0) {
            abort(422, 'No se pudo leer la imagen pegada.');
        }

        if (strlen($binary) > self::MAX_FILE_KB * 1024) {
            abort(422, 'La imagen pegada no debe superar 5 MB.');
        }

        return $binary;
    }

    private function buildStoredFileName(AccountOpening $opening, string $pattern, string $extension): string
    {
        $baseName = str_replace('{expediente}', $this->safeFileNamePart($opening->file_name), $pattern);
        $baseName = preg_replace('/[\\\\\/:*?"<>|]+/', ' ', $baseName);
        $baseName = preg_replace('/\s+/', ' ', trim($baseName));
        $baseName = trim($baseName, '. ');

        if ($baseName === '') {
            $baseName = $opening->public_code;
        }

        return "{$baseName}.{$extension}";
    }

    private function safeFileNamePart(?string $value): string
    {
        $value = $value ?: 'expediente';
        $value = preg_replace('/[\\\\\/:*?"<>|]+/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', trim($value));

        return trim($value, '. ') ?: 'expediente';
    }

    private function downloadFileName(InternalDocumentTemplate $template, AccountOpening $opening): string
    {
        return $this->safeFileNamePart(str_replace(
            '{expediente}',
            $opening->file_name,
            $template->file_name_pattern ?: $template->name.'_{expediente}'
        ));
    }

    private function documentDownloadDefaults(AccountOpening $opening, bool $detectMissingIdentity = false): array
    {
        $opening->loadMissing(['documents', 'accountType']);

        $cedulaData = $opening->documents
            ->where('document_scope', 'requisito')
            ->pluck('extracted_data')
            ->filter()
            ->first(fn ($data) => filled($data['cedula'] ?? null) || filled($data['nombres_apellidos'] ?? null));
        $cedulaData = $this->bestAvailableIdentityData($opening, $cedulaData, $detectMissingIdentity);

        $planillaData = $opening->documents
            ->where('document_scope', 'requisito')
            ->pluck('extracted_data')
            ->filter()
            ->first(fn ($data) => filled($data['direccion'] ?? null));

        $memberName = $this->singleLine(trim(($opening->member_first_names ?? '').' '.($opening->member_last_names ?? '')));
        $extractedName = $this->singleLine($cedulaData['nombres_apellidos'] ?? '');
        $fullName = $this->isPlausiblePersonName($extractedName)
            ? $extractedName
            : ($this->isPlausiblePersonName($memberName) ? $memberName : '');

        return [
            'apellidos_nombres' => $fullName,
            'cedula_identidad' => $this->documentIdentityNumber($opening, $cedulaData),
            'nacionalidad' => $opening->member_nationality ?: ($cedulaData['nacionalidad'] ?? ''),
            'direccion' => $opening->member_address ?: ($planillaData['direccion'] ?? ''),
            'codigo_socio' => $opening->file_name,
            'cuenta_numero' => $opening->file_name,
            'tipo_cuenta' => $opening->accountType->name,
            'ciudad' => $this->agencyDocumentPlace($opening),
            'dia' => now()->format('d'),
            'mes' => now()->locale('es')->translatedFormat('F'),
            'anio' => now()->format('Y'),
            'tipo_solicitante' => 'socio',
            'fondo_mortuorio' => 'no',
        ];
    }

    private function blankPersonalDocumentDefaults(AccountOpening $opening): array
    {
        $opening->loadMissing('accountType');

        return [
            'apellidos_nombres' => '',
            'cedula_identidad' => '',
            'nacionalidad' => '',
            'direccion' => '',
            'codigo_socio' => $opening->file_name,
            'cuenta_numero' => $opening->file_name,
            'tipo_cuenta' => $opening->accountType->name,
            'ciudad' => $this->agencyDocumentPlace($opening),
            'dia' => now()->format('d'),
            'mes' => now()->locale('es')->translatedFormat('F'),
            'anio' => now()->format('Y'),
            'tipo_solicitante' => 'socio',
            'fondo_mortuorio' => 'no',
        ];
    }

    private function bestAvailableIdentityData(AccountOpening $opening, ?array $currentData, bool $detectMissingIdentity = false): array
    {
        $currentData ??= [];
        $hasIdentification = strlen(preg_replace('/\D+/', '', (string) ($currentData['cedula'] ?? ''))) === 10;
        $hasName = $this->isPlausiblePersonName($this->singleLine($currentData['nombres_apellidos'] ?? ''));

        if ($hasIdentification && $hasName) {
            return $currentData;
        }

        if (!$detectMissingIdentity && !config('opening.rescan_stored_documents', false)) {
            return $currentData;
        }

        $storedCandidates = $opening->documents
            ->where('document_scope', 'requisito')
            ->sortByDesc(fn ($document) => str_contains(mb_strtolower($document->display_name ?? ''), 'cédula')
                || str_contains(mb_strtolower($document->display_name ?? ''), 'cedula'))
            ->map(fn ($document) => [
                'path' => $document->file_path,
                'name' => $document->original_name,
                'mime' => $document->mime_type,
            ]);

        if ($opening->consent?->signed_file_path) {
            $storedCandidates->push([
                'path' => $opening->consent->signed_file_path,
                'name' => basename($opening->consent->signed_file_path),
                'mime' => null,
            ]);
        }

        foreach ($storedCandidates as $candidate) {
            if (!Storage::exists($candidate['path'])) {
                continue;
            }

            $file = new UploadedFile(
                Storage::path($candidate['path']),
                $candidate['name'] ?: basename($candidate['path']),
                $candidate['mime'],
                null,
                true
            );
            $extracted = app(DocumentExtractionService::class)->extract(
                'cedula',
                $file,
                $detectMissingIdentity
                    ? config('opening.ocr_on_demand', true)
                    : config('opening.ocr_on_upload', false)
            );

            foreach (['cedula', 'nombres_apellidos', 'nacionalidad'] as $field) {
                if (blank($currentData[$field] ?? null) && filled($extracted[$field] ?? null)) {
                    $currentData[$field] = $extracted[$field];
                }
            }

            $hasIdentification = strlen(preg_replace('/\D+/', '', (string) ($currentData['cedula'] ?? ''))) === 10;
            $hasName = $this->isPlausiblePersonName($this->singleLine($currentData['nombres_apellidos'] ?? ''));
            if ($hasIdentification && $hasName) {
                break;
            }
        }

        $this->syncMemberDataFromExtraction($opening, 'cedula', $currentData);

        return $currentData;
    }

    private function isBdhTemplate(InternalDocumentTemplate $template): bool
    {
        $slug = strtolower($template->slug);
        $path = strtolower((string) $template->template_path);

        return str_contains($path, 'bdh.pdf')
            || str_contains($slug, 'bdh')
            || str_contains($slug, 'acreditacion')
            || str_contains($slug, 'reapertura')
            || str_contains($slug, 'cierre');
    }

    private function isSignatureRegisterTemplate(InternalDocumentTemplate $template): bool
    {
        return str_contains(strtolower($template->slug), 'registro-de-firmas');
    }

    private function isContributionCertificateTemplate(InternalDocumentTemplate $template): bool
    {
        return str_contains(strtolower($template->slug), 'certificado-de-aportacion');
    }

    private function agencyDocumentPlace(AccountOpening $opening): string
    {
        return config(
            "opening.agencies.{$opening->agency}.document_place",
            config("opening.agencies.{$opening->agency}.name", $opening->agency ?: 'Las Naves')
        );
    }

    private function contributionCertificateProfile(AccountOpening $opening): array
    {
        return config("opening.agencies.{$opening->agency}.contribution_certificate", []);
    }

    private function consentDocumentDefaults(AccountOpening $opening): array
    {
        $opening->loadMissing('accountType');
        $fullName = $this->singleLine(trim(($opening->member_first_names ?? '').' '.($opening->member_last_names ?? '')));
        $identification = preg_replace('/\D+/', '', (string) $opening->member_identification);

        return [
            'tipo_persona' => $opening->accountType?->slug === 'cuenta-juridica' ? 'juridica' : 'natural',
            'apellidos_nombres' => $fullName,
            'cedula_identidad' => strlen($identification) === 10 ? $identification : '',
            'razon_social' => '',
            'ruc' => '',
            'representante_legal' => $opening->accountType?->slug === 'cuenta-juridica' ? $fullName : '',
            'cedula_representante' => $opening->accountType?->slug === 'cuenta-juridica' && strlen($identification) === 10 ? $identification : '',
            'ciudad' => $this->agencyDocumentPlace($opening),
            'dia' => now()->format('d'),
            'mes' => now()->locale('es')->translatedFormat('F'),
            'anio' => now()->format('Y'),
            'tipo_cuenta' => $opening->accountType->name,
            'correo' => '',
            'celular' => '',
            'correo_juridico' => '',
            'celular_juridico' => '',
            'direccion' => $opening->member_address ?: '',
        ];
    }

    private function syncMemberDataFromExtraction(AccountOpening $opening, string $slug, array $extracted): void
    {
        $updates = [];

        if (in_array($slug, ['cedula', 'cedula-papeleta'], true)) {
            $identification = preg_replace('/\D+/', '', (string) ($extracted['cedula'] ?? ''));
            if (strlen($identification) === 10) {
                $updates['member_identification'] = $identification;
            }

            $fullName = $this->singleLine($extracted['nombres_apellidos'] ?? '');
            if ($this->isPlausiblePersonName($fullName)) {
                $updates['member_first_names'] = $fullName;
                $updates['member_last_names'] = null;
            }

            if (blank($opening->member_nationality) && filled($extracted['nacionalidad'] ?? null)) {
                $updates['member_nationality'] = $extracted['nacionalidad'];
            }
        }

        if ($slug === 'planilla-servicios' && blank($opening->member_address) && filled($extracted['direccion'] ?? null)) {
            $updates['member_address'] = $extracted['direccion'];
        }

        if ($updates) {
            $opening->update($updates);
        }
    }

    private function persistCorrectedMemberData(AccountOpening $opening, array $data): void
    {
        $fullName = $this->singleLine($data['apellidos_nombres']);

        $opening->update([
            'member_identification' => preg_replace('/\D+/', '', $data['cedula_identidad']) ?: null,
            'member_first_names' => $fullName,
            'member_last_names' => null,
            'member_address' => $data['direccion'] ?? $opening->member_address,
        ]);
    }

    private function documentIdentityNumber(AccountOpening $opening, ?array $cedulaData): string
    {
        $candidate = preg_replace('/\D+/', '', (string) $opening->member_identification);
        if (strlen($candidate) === 10) {
            return $candidate;
        }

        $candidate = preg_replace('/\D+/', '', (string) ($cedulaData['cedula'] ?? ''));
        if (strlen($candidate) === 10) {
            return $candidate;
        }

        foreach ($opening->documents->where('document_scope', 'requisito') as $document) {
            $candidate = preg_replace('/\D+/', '', (string) ($document->extracted_data['cedula'] ?? ''));
            if (strlen($candidate) === 10) {
                return $candidate;
            }
        }

        return '';
    }

    private function singleLine(?string $value): string
    {
        $value = trim((string) $value);

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    private function isPlausiblePersonName(?string $name): bool
    {
        $name = mb_strtoupper($this->singleLine($name), 'UTF-8');
        if ($name === '' || mb_strlen($name) < 7 || preg_match('/\d/u', $name)) {
            return false;
        }

        foreach (['CEDULA', 'CÉDULA', 'IDENTIDAD', 'APELLIDOS', 'NOMBRES', 'NOMBRE', 'NACIONALIDAD', 'CERTIFICADO', 'VOTACION', 'VOTACIÓN', 'CONYUGE', 'CÓNYUGE', 'REPRESENTANTE'] as $word) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/u', $name)) {
                return false;
            }
        }

        return count(array_filter(explode(' ', $name), fn (string $part) => mb_strlen($part) >= 2)) >= 2;
    }

    private function makePdfFromJpeg(string $jpeg): string
    {
        return $this->makePdfFromJpegs([['title' => null, 'jpeg' => $jpeg]]);
    }

    private function makePdfFromJpegs(array $images): string
    {
        if (empty($images)) {
            abort(422, 'No se recibieron evidencias para generar el PDF.');
        }

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
        ];
        $pageObjectIds = [];
        $nextObjectId = 3;

        $chunks = count($images) === 1 ? [$images] : array_chunk($images, 2);

        foreach ($chunks as $chunk) {
            $pageObjectIds[] = $nextObjectId;

            if (count($chunk) === 1) {
                [$pageObject, $imageObject, $contentObject] = $this->makePdfImagePageObjects(
                    $nextObjectId,
                    $chunk[0]['jpeg'],
                    $chunk[0]['title'] ?? null
                );

                $objects[] = $pageObject;
                $objects[] = $imageObject;
                $objects[] = $contentObject;
                $nextObjectId += 3;

                continue;
            }

            $pageObjects = $this->makePdfTwoImagePageObjects($nextObjectId, $chunk);
            array_push($objects, ...$pageObjects);
            $nextObjectId += 4;
        }

        $kids = collect($pageObjectIds)->map(fn ($id) => "{$id} 0 R")->implode(' ');
        array_splice($objects, 1, 0, ["2 0 obj\n<< /Type /Pages /Kids [{$kids}] /Count ".count($pageObjectIds)." >>\nendobj\n"]);

        return $this->compilePdfObjects($objects);
    }

    private function makePdfImagePageObjects(int $pageObjectId, string $jpeg, ?string $title): array
    {
        $size = getimagesizefromstring($jpeg);
        if (!$size) {
            abort(422, 'No se pudo leer la captura pegada.');
        }

        [$imageWidth, $imageHeight] = $size;
        $pageWidth = 595.28;
        $pageHeight = 841.89;

        if ($imageWidth > $imageHeight) {
            [$pageWidth, $pageHeight] = [$pageHeight, $pageWidth];
        }

        $margin = 28;
        $titleHeight = $title ? 28 : 0;
        $maxWidth = $pageWidth - ($margin * 2);
        $maxHeight = $pageHeight - ($margin * 2) - $titleHeight;
        $scale = min($maxWidth / $imageWidth, $maxHeight / $imageHeight);
        $drawWidth = round($imageWidth * $scale, 2);
        $drawHeight = round($imageHeight * $scale, 2);
        $x = round(($pageWidth - $drawWidth) / 2, 2);
        $y = round(($pageHeight - $drawHeight - $titleHeight) / 2, 2);

        $imageObjectId = $pageObjectId + 1;
        $contentObjectId = $pageObjectId + 2;
        $content = "q\n{$drawWidth} 0 0 {$drawHeight} {$x} {$y} cm\n/Im{$imageObjectId} Do\nQ\n";

        return [
            "{$pageObjectId} 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] /Resources << /XObject << /Im{$imageObjectId} {$imageObjectId} 0 R >> >> /Contents {$contentObjectId} 0 R >>\nendobj\n",
            "{$imageObjectId} 0 obj\n<< /Type /XObject /Subtype /Image /Width {$imageWidth} /Height {$imageHeight} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ".strlen($jpeg)." >>\nstream\n{$jpeg}\nendstream\nendobj\n",
            "{$contentObjectId} 0 obj\n<< /Length ".strlen($content)." >>\nstream\n{$content}endstream\nendobj\n",
        ];
    }

    private function makePdfTwoImagePageObjects(int $pageObjectId, array $images): array
    {
        $pageWidth = 595.28;
        $pageHeight = 841.89;
        $margin = 24;
        $gap = 18;
        $slotWidth = $pageWidth - ($margin * 2);
        $slotHeight = ($pageHeight - ($margin * 2) - $gap) / 2;
        $content = '';
        $resources = '';
        $objects = [];

        foreach (array_values($images) as $index => $image) {
            $jpeg = $image['jpeg'];
            $size = getimagesizefromstring($jpeg);
            if (!$size) {
                abort(422, 'No se pudo leer una de las capturas pegadas.');
            }

            [$imageWidth, $imageHeight] = $size;
            $imageObjectId = $pageObjectId + 1 + $index;
            $resources .= "/Im{$imageObjectId} {$imageObjectId} 0 R ";

            $scale = min($slotWidth / $imageWidth, $slotHeight / $imageHeight);
            $drawWidth = round($imageWidth * $scale, 2);
            $drawHeight = round($imageHeight * $scale, 2);
            $x = round(($pageWidth - $drawWidth) / 2, 2);
            $slotBottom = $index === 0
                ? $margin + $slotHeight + $gap
                : $margin;
            $y = round($slotBottom + (($slotHeight - $drawHeight) / 2), 2);

            $content .= "q\n{$drawWidth} 0 0 {$drawHeight} {$x} {$y} cm\n/Im{$imageObjectId} Do\nQ\n";
            $objects[] = "{$imageObjectId} 0 obj\n<< /Type /XObject /Subtype /Image /Width {$imageWidth} /Height {$imageHeight} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ".strlen($jpeg)." >>\nstream\n{$jpeg}\nendstream\nendobj\n";
        }

        $contentObjectId = $pageObjectId + 3;

        return [
            "{$pageObjectId} 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] /Resources << /XObject << {$resources}>> >> /Contents {$contentObjectId} 0 R >>\nendobj\n",
            ...$objects,
            "{$contentObjectId} 0 obj\n<< /Length ".strlen($content)." >>\nstream\n{$content}endstream\nendobj\n",
        ];
    }

    private function compilePdfObjects(array $objects): string
    {
        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function makePublicCode(): string
    {
        do {
            $code = 'AP-'.now()->format('ym').'-'.random_int(100000, 999999);
        } while (AccountOpening::where('public_code', $code)->exists());

        return $code;
    }

    private function simulateExtraction(string $slug, AccountOpening $opening, UploadedFile $file): array
    {
        $base = ['archivo' => $file->getClientOriginalName(), 'revision' => 'Pendiente de OCR certificado'];

        return match ($slug) {
            'cedula' => $base + [
                'cedula_valida' => $opening->member_identification ? $this->validEcuadorianId($opening->member_identification) : null,
                'nombres' => $opening->member_first_names,
                'apellidos' => $opening->member_last_names,
                'nacionalidad' => $opening->member_nationality,
            ],
            'papeleta-votacion' => $base + [
                'ultima_eleccion' => 'Validación manual contra CNE requerida',
                'alerta' => 'Confirmar que corresponda a la última elección disponible.',
            ],
            'planilla-servicios' => $base + [
                'direccion' => $opening->member_address,
                'fecha_emision' => 'Pendiente de OCR',
            ],
            'ruc' => $base + [
                'ruc' => null,
                'razon_social' => 'Pendiente de OCR',
            ],
            default => $base + ['legibilidad' => 'Validación manual requerida'],
        };
    }

    private function validEcuadorianId(string $id): bool
    {
        if (!preg_match('/^\d{10}$/', $id)) {
            return false;
        }

        $province = (int) substr($id, 0, 2);
        if ($province < 1 || $province > 24) {
            return false;
        }

        $digits = array_map('intval', str_split($id));
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $value = $digits[$i] * ($i % 2 === 0 ? 2 : 1);
            $sum += $value > 9 ? $value - 9 : $value;
        }

        $check = (10 - ($sum % 10)) % 10;
        return $check === $digits[9];
    }

    private function consentIsValid(AccountOpening $opening): bool
    {
        $consent = $opening->consent()->first();

        return $consent
            && $consent->status === 'validado'
            && $consent->signed_file_path
            && Storage::exists($consent->signed_file_path)
            && $consent->manual_signature_confirmed
            && $consent->validated_at;
    }

    private function requiredDocumentsAreValid(AccountOpening $opening): bool
    {
        $requiredIds = $opening->accountType->requirements()->where('is_required', true)->pluck('id');
        if ($opening->requires_spouse_documents) {
            $spouseIds = $opening->accountType->requirements()
                ->whereHas('type', fn ($query) => $query->where('slug', 'documentos-conyuge'))
                ->pluck('id');
            $requiredIds = $requiredIds->merge($spouseIds)->unique();
        }

        $requiredIds = $requiredIds->merge($this->selectedOptionalRequirementIds($opening))->unique();

        $validIds = $opening->documents()
            ->where('document_scope', 'requisito')
            ->whereIn('status', ['cargado', 'validado'])
            ->pluck('account_type_requirement_id');

        return $requiredIds->diff($validIds)->isEmpty();
    }

    private function selectedOptionalRequirementIds(AccountOpening $opening)
    {
        $allowedIds = $opening->accountType->requirements()
            ->where('is_required', false)
            ->whereHas('type', fn ($query) => $query->where('slug', '!=', 'documentos-conyuge'))
            ->pluck('id');

        $selectionHistory = $opening->histories()
            ->where('action', 'seleccionar_requisitos_opcionales')
            ->latest('id')
            ->first();

        $selectedIds = collect(data_get($selectionHistory?->metadata, 'requirement_ids', []));

        $uploadedOptionalIds = $opening->documents()
            ->where('document_scope', 'requisito')
            ->whereIn('account_type_requirement_id', $allowedIds)
            ->pluck('account_type_requirement_id');

        return ($selectionHistory ? $selectedIds : $selectedIds->merge($uploadedOptionalIds))
            ->map(fn ($id) => (int) $id)
            ->intersect($allowedIds)
            ->unique()
            ->values();
    }

    private function externalChecksAreComplete(AccountOpening $opening): bool
    {
        $requiredIds = ExternalCheckItem::where('active', true)->where('is_required', true)->pluck('id');
        $loaded = $opening->externalEvidences()->whereNotNull('screenshot_path')->get();

        foreach (array_keys($this->externalCheckSubjects($opening)) as $subjectKey) {
            $loadedIds = $loaded->where('subject_key', $subjectKey)->pluck('external_check_item_id');
            if ($requiredIds->diff($loadedIds)->isNotEmpty()) {
                return false;
            }
        }

        return true;
    }

    private function companyExternalCheckApplicable(AccountOpening $opening): bool
    {
        return (bool) data_get(
            $opening->histories()
                ->where('action', 'cargar_evidencia_externa')
                ->latest('id')
                ->first()?->metadata,
            'company_check_applicable',
            false
        );
    }

    private function externalCheckSubjects(AccountOpening $opening, ?bool $companyApplicable = null): array
    {
        $opening->loadMissing('accountType');

        return match ($opening->accountType->slug) {
            'cuenta-junior' => [
                'representante' => 'Representante',
                'menor' => 'Menor',
            ],
            'cuenta-juridica' => array_filter([
                'representante_legal' => 'Representante legal',
                'empresa' => ($companyApplicable ?? $this->companyExternalCheckApplicable($opening)) ? 'Empresa' : null,
            ]),
            default => ['titular' => 'Titular'],
        };
    }

    private function internalDocumentsAreComplete(AccountOpening $opening): bool
    {
        $requiredTemplates = $this->internalTemplatesForOpening($opening)->where('is_required', true)->get();
        $loadedDocuments = $opening->documents()
            ->where('document_scope', 'interno')
            ->whereIn('status', ['cargado', 'validado'])
            ->get()
            ->keyBy('internal_document_template_id');

        return $requiredTemplates->every(function (InternalDocumentTemplate $template) use ($loadedDocuments) {
            $document = $loadedDocuments->get($template->id);

            return $document && (!$template->requires_signature || $document->manual_signature_confirmed);
        });
    }

    private function internalTemplatesForOpening(AccountOpening $opening)
    {
        return InternalDocumentTemplate::where('active', true)
            ->where('account_type_id', $opening->account_type_id)
            ->where('source', '!=', 'servicio')
            ->orderBy('sort_order');
    }

    private function serviceDocumentTemplates()
    {
        return InternalDocumentTemplate::where('active', true)
            ->where('source', 'servicio')
            ->orderBy('sort_order');
    }

    private function serviceDocumentMap(): array
    {
        return [
            'fondo-mortuorio' => 'formulario-servicio-fondo-mortuorio',
        ];
    }

    private function contributionCertificateApplies(AccountOpening $opening): bool
    {
        return in_array($opening->accountType->slug, ['cuenta-ahorro-programado', 'cuenta-juridica'], true);
    }

    private function membershipDecision(AccountOpening $opening): ?string
    {
        return data_get($opening->histories()
            ->where('action', 'seleccionar_servicios')
            ->latest('id')
            ->first()?->metadata, 'tipo_vinculacion');
    }

    private function orderedChecklistRows(AccountOpening $opening): array
    {
        $opening->loadMissing(['accountType.requirements.type', 'documents', 'consent', 'externalEvidences', 'histories']);

        $requirements = $opening->accountType->requirements;
        $requirementDocs = $opening->documents
            ->where('document_scope', 'requisito')
            ->keyBy('account_type_requirement_id');
        $internalTemplates = $this->internalTemplatesForOpening($opening)->get();
        $internalDocs = $opening->documents
            ->where('document_scope', 'interno')
            ->keyBy('internal_document_template_id');
        $serviceTemplates = $this->serviceDocumentTemplates()->get();
        $serviceDocs = $opening->documents
            ->where('document_scope', 'servicio')
            ->keyBy('internal_document_template_id');

        $rows = [];
        $add = function (string $name, ?string $path, string $category = 'Documento', ?bool $loaded = null) use (&$rows): void {
            $rows[] = [
                'name' => $name,
                'path' => $path,
                'category' => $category,
                'loaded' => $loaded ?? (filled($path) && Storage::exists($path)),
            ];
        };
        $addRequirement = function (callable $match, ?string $label = null) use ($requirements, $requirementDocs, $add): void {
            $requirement = $requirements->first($match);
            if (!$requirement) {
                return;
            }

            $document = $requirementDocs->get($requirement->id);
            $add($label ?: $requirement->label, $document?->file_path, 'Requisito');
        };
        $addInternal = function (
            string $needle,
            ?string $label = null,
            bool $includeOptionalWhenEmpty = false,
            string $fallbackSource = 'sistema'
        ) use ($internalTemplates, $internalDocs, $add): void {
            $template = $internalTemplates->first(fn ($item) => str_contains(mb_strtolower($item->name), mb_strtolower($needle)));
            if (!$template) {
                $add(($label ?: $needle)." ({$fallbackSource})", null, 'Interno');
                return;
            }

            $document = $internalDocs->get($template->id);
            if (!$template->is_required && !$includeOptionalWhenEmpty && !$document?->file_path) {
                return;
            }

            $source = $template->source === 'manual' ? 'manual' : 'sistema';
            $add(($label ?: $template->name)." ({$source})", $document?->file_path, 'Interno');
        };

        $identityRequirements = $requirements
            ->filter(fn ($requirement) => in_array($requirement->type->slug, ['cedula-papeleta', 'cedula', 'cedula-menor', 'documentos-conyuge'], true))
            ->sortBy(fn ($requirement) => match ($requirement->type->slug) {
                'cedula-papeleta', 'cedula' => 1,
                'cedula-menor', 'documentos-conyuge' => 2,
                default => 9,
            });

        foreach ($identityRequirements as $requirement) {
            if ($requirement->type->slug === 'documentos-conyuge' && !$opening->requires_spouse_documents) {
                continue;
            }

            $document = $requirementDocs->get($requirement->id);
            $add($requirement->label, $document?->file_path, 'Identificación');
        }

        $addInternal('formulario solicitud apertura', 'Formulario solicitud apertura de cuenta/actualización de datos');
        $addInternal('formulario conozca a su cliente / socio', 'Formulario conozca a su cliente / socio');
        $addInternal('solicitud de ingreso al consejo', 'Solicitud de ingreso al consejo de administración', false, 'manual');
        $add(
            'Consentimiento para el Tratamiento de Datos Personales',
            $opening->consent?->signed_file_path,
            'Consentimiento'
        );
        $addInternal('contrato de apertura', 'Contrato de apertura de cuenta de ahorros');
        $addInternal('autocertificación residencia fiscal', 'Formulario autocertificación residencia fiscal');
        $add(
            'Revisión de listas de control (manual)',
            $opening->externalEvidences->firstWhere('screenshot_path', '!=', null)?->screenshot_path,
            'Consulta externa'
        );

        if ($opening->accountType->slug === 'cuenta-juridica') {
            foreach ([
                ['ruc', 'RUC'],
                ['planilla-servicios', 'Planilla de servicio básico de la institución'],
                ['planilla-servicios', 'Planilla de servicio básico del representante legal'],
                ['nombramiento', 'Nombramiento'],
                ['estatutos', 'Estatuto'],
                ['estados-financieros', 'Estados financieros'],
                ['declaracion-renta', 'Pago de impuesto a la renta del año inmediato anterior'],
                ['poder-autorizacion', 'Poder para trámite por tercero (si aplica)'],
                ['acta-constitucion', 'Acta notariada de constitución de la sociedad (si aplica)'],
            ] as [$slug, $label]) {
                $addRequirement(
                    fn ($requirement) => $requirement->type->slug === $slug
                        && ($slug !== 'planilla-servicios' || $requirement->label === $label),
                    $label
                );
            }

            $addInternal('conozca su cliente - jurídica', 'Formulario conozca a su cliente - jurídica', false, 'manual');
            $addInternal('conozca su cliente - representante legal', 'Formulario conozca a su cliente - representante legal', false, 'manual');
        } else {
            $addRequirement(
                fn ($requirement) => $requirement->type->slug === 'planilla-servicios',
                'Planilla de servicio básico'
            );
        }

        foreach ($serviceTemplates->whereIn('slug', $this->requiredServiceTemplateSlugs($opening)) as $serviceTemplate) {
            $add($serviceTemplate->name, $serviceDocs->get($serviceTemplate->id)?->file_path, 'Servicio');
        }

        $addInternal('registro de firmas', 'Registro de firmas', false, 'manual');
        $add('Check List', null, 'Control', true);

        if ($opening->accountType->slug === 'cuenta-basica') {
            $addInternal('autorización para acreditación del bdh', 'Autorización para acreditación del BDH', true, 'manual');
        }

        return $rows;
    }

    private function fondoMortuorioDecision(AccountOpening $opening): ?string
    {
        return data_get($opening->histories()
            ->where('action', 'seleccionar_servicios')
            ->latest('id')
            ->first()?->metadata, 'fondo_mortuorio');
    }

    private function requiredServiceTemplateSlugs(AccountOpening $opening)
    {
        $templateSlugs = collect();

        $decision = $this->fondoMortuorioDecision($opening);
        if ($decision === 'si') {
            $templateSlugs->push('formulario-servicio-fondo-mortuorio');
        } elseif ($decision === 'no') {
            $templateSlugs->push('sin-fondo-mortuorio');
        }

        if ($this->contributionCertificateApplies($opening) && $this->membershipDecision($opening) === 'socio') {
            $templateSlugs->push('certificado-de-aportacion');
        }

        return $templateSlugs->unique()->values();
    }

    private function servicesStepIsComplete(AccountOpening $opening): bool
    {
        if (!$opening->histories()->where('action', 'seleccionar_servicios')->exists()) {
            return false;
        }

        return !$this->contributionCertificateApplies($opening)
            || in_array($this->membershipDecision($opening), ['socio', 'cliente'], true);
    }

    private function serviceDocumentsAreComplete(AccountOpening $opening): bool
    {
        if (!$this->servicesStepIsComplete($opening)) {
            return false;
        }

        $templateSlugs = $this->requiredServiceTemplateSlugs($opening);

        if ($templateSlugs->isEmpty()) {
            return true;
        }

        $requiredTemplates = $this->serviceDocumentTemplates()->whereIn('slug', $templateSlugs)->get();
        $loadedDocuments = $opening->documents()
            ->where('document_scope', 'servicio')
            ->whereIn('status', ['cargado', 'validado'])
            ->get()
            ->keyBy('internal_document_template_id');

        return $requiredTemplates->every(function (InternalDocumentTemplate $template) use ($loadedDocuments) {
            $document = $loadedDocuments->get($template->id);

            return $document && (!$template->requires_signature || $document->manual_signature_confirmed);
        });
    }

    private function workflowState(AccountOpening $opening): array
    {
        $consentComplete = $this->consentIsValid($opening);
        $requirementsComplete = $this->requiredDocumentsAreValid($opening);

        $complete = [
            'requisitos' => $requirementsComplete && $consentComplete,
            'externas' => $this->externalChecksAreComplete($opening),
            'expediente' => $opening->file_name_confirmed,
            'internos' => $this->internalDocumentsAreComplete($opening),
            'servicios' => $this->serviceDocumentsAreComplete($opening),
            'resumen' => in_array($opening->status, ['en_revision', 'aprobado', 'finalizado'], true),
        ];

        $unlocked = [
            'requisitos' => true,
            'externas' => $complete['requisitos'],
            'expediente' => $complete['externas'],
            'internos' => $complete['expediente'],
            'servicios' => $complete['internos'],
            'resumen' => $complete['servicios'],
        ];

        foreach ($complete as $step => $isComplete) {
            if (!$isComplete && $unlocked[$step]) {
                return ['complete' => $complete, 'unlocked' => $unlocked, 'current' => $step];
            }
        }

        return ['complete' => $complete, 'unlocked' => $unlocked, 'current' => 'resumen'];
    }

    private function readyToSubmit(AccountOpening $opening): bool
    {
        return $this->consentIsValid($opening)
            && $this->requiredDocumentsAreValid($opening)
            && $this->externalChecksAreComplete($opening)
            && $opening->file_name_confirmed
            && $this->internalDocumentsAreComplete($opening)
            && $this->serviceDocumentsAreComplete($opening);
    }

    private function calculateProgress(AccountOpening $opening): int
    {
        $checks = [
            $this->consentIsValid($opening) && $this->requiredDocumentsAreValid($opening),
            $this->externalChecksAreComplete($opening),
            $opening->file_name_confirmed,
            $this->internalDocumentsAreComplete($opening),
            $this->serviceDocumentsAreComplete($opening),
            in_array($opening->status, ['en_revision', 'aprobado', 'finalizado'], true),
        ];

        return (int) round((count(array_filter($checks)) / count($checks)) * 100);
    }

    private function audit(AccountOpening $opening, string $action, string $description, ?array $metadata = null): void
    {
        ActionHistory::create([
            'account_opening_id' => $opening->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}

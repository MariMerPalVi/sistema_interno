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
use App\Services\InternalDocumentPdfService;
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
            'file_name' => ['required', 'string', 'max:120'],
        ]);

        $opening = DB::transaction(function () use ($data) {
            $publicCode = $this->makePublicCode();
            $fileName = trim($data['file_name']);

            $opening = AccountOpening::create([
                'public_code' => $publicCode,
                'file_name' => $fileName,
                'storage_folder' => $this->safeFileNamePart($fileName),
                'account_type_id' => $data['account_type_id'],
                'created_by' => null,
                'status' => 'borrador',
            ]);

            PersonalDataConsent::create([
                'account_opening_id' => $opening->id,
                'template_path' => 'formatos/CONSENTIMIENTO_DE_DATOS_PERSONALES_LAS_NAVES.pdf',
            ]);

            $this->audit($opening, 'crear_expediente', 'Expediente de apertura creado.');

            return $opening;
        });

        return redirect()->route('accounts.show', $opening)->with('success', 'Expediente creado. Complete el flujo paso a paso.');
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
            'consentDefaults' => $this->consentDocumentDefaults($opening),
            'documentDefaults' => $this->documentDownloadDefaults($opening),
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
                    'apellidos_nombres',
                    'cedula_identidad',
                    'ciudad',
                    'dia',
                    'mes',
                    'anio',
                    'tipo_cuenta',
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
                ? 'Se marco que aplica documentacion de conyuge.'
                : 'Se marco que no aplica documentacion de conyuge.'
        );

        return redirect()
            ->route('accounts.show', [$opening, 'paso' => 'requisitos'])
            ->with('success', 'Condicion de conyuge actualizada.');
    }

    public function uploadConsent(Request $request, AccountOpening $opening)
    {
        $data = $request->validate([
            'signed_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_FILE_KB],
            'manual_signature_confirmed' => ['accepted'],
            'observations' => ['nullable', 'string', 'max:500'],
        ], [
            'manual_signature_confirmed.accepted' => 'Debe confirmar manualmente que el consentimiento contiene firma.',
        ]);

        $signatureError = $this->consentSignatureError($request->file('signed_file'));
        if ($signatureError) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'consentimiento'])
                ->withErrors($signatureError);
        }

        $path = $this->storeFile($request->file('signed_file'), $opening, 'consentimiento', 'Consentimiento para el Tratamiento de Datos Personales_{expediente}');
        $autoSignal = $this->hasSignatureSignal($request->file('signed_file'));

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

        $this->audit($opening, 'validar_consentimiento', 'Consentimiento firmado cargado y validado manualmente.');

        return redirect()
            ->route('accounts.show', [$opening, 'paso' => 'requisitos'])
            ->with('success', 'Consentimiento validado. Puede continuar con los requisitos.');
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

        if (!$this->consentIsValid($opening)) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'consentimiento'])
                ->withErrors('No puede cargar requisitos hasta validar el consentimiento firmado.');
        }

        $requirement = AccountTypeRequirement::with('type')->findOrFail($data['account_type_requirement_id']);
        $path = $this->storeFile($request->file('file'), $opening, 'requisitos', $requirement->file_name_pattern);
        $extracted = app(DocumentExtractionService::class)->extract($requirement->type->slug, $request->file('file'));
        $this->syncMemberDataFromExtraction($opening, $requirement->type->slug, $extracted);

        UploadedDocument::updateOrCreate(
            [
                'account_opening_id' => $opening->id,
                'account_type_requirement_id' => $requirement->id,
                'document_scope' => 'requisito',
            ],
            [
                'display_name' => $requirement->label,
                'file_path' => $path,
                'original_name' => $request->file('file')->getClientOriginalName(),
                'mime_type' => $request->file('file')->getMimeType(),
                'file_size' => $request->file('file')->getSize(),
                'status' => $data['status'],
                'extracted_data' => $extracted,
                'uploaded_by' => null,
            ]
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

    public function uploadExternalEvidence(Request $request, AccountOpening $opening)
    {
        $data = $request->validate([
            'evidence_images' => ['nullable', 'array'],
            'evidence_images.*' => ['nullable', 'string'],
            'results' => ['required', 'array'],
            'results.*' => [Rule::in(['sin_novedad', 'con_observacion', 'no_aplica', 'pendiente'])],
            'observations' => ['nullable', 'array'],
            'observations.*' => ['nullable', 'string', 'max:500'],
        ]);

        if (!$this->requiredDocumentsAreValid($opening)) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'requisitos'])
                ->withErrors('Complete y valide todos los documentos obligatorios antes de registrar consultas externas.');
        }

        $items = ExternalCheckItem::where('active', true)->orderBy('sort_order')->get();
        $existingPath = $opening->externalEvidences()->whereNotNull('screenshot_path')->value('screenshot_path');
        $postedImages = collect($data['evidence_images'] ?? [])->filter(fn ($value) => filled($value));
        $mustGeneratePdf = !$existingPath || $postedImages->isNotEmpty();

        if ($mustGeneratePdf) {
            $missing = $items
                ->filter(fn ($item) => blank($data['evidence_images'][$item->id] ?? null))
                ->pluck('name');

            if ($missing->isNotEmpty()) {
                return redirect()
                    ->route('accounts.show', [$opening, 'paso' => 'externas'])
                    ->withErrors('Pegue la evidencia de: '.$missing->implode(', ').'.');
            }
        }

        $path = $mustGeneratePdf
            ? $this->storePastedImagesAsPdf($items, $data['evidence_images'] ?? [], $opening, 'Revision listas de control_{expediente}')
            : $existingPath;

        if (!$path) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'externas'])
                ->withErrors('Pegue una evidencia para cada linea de control antes de continuar.');
        }

        foreach ($items as $item) {
            ExternalCheckEvidence::updateOrCreate(
                [
                    'account_opening_id' => $opening->id,
                    'external_check_item_id' => $item->id,
                ],
                [
                    'result' => $data['results'][$item->id] ?? 'pendiente',
                    'screenshot_path' => $path,
                    'advisor_observation' => $data['observations'][$item->id] ?? null,
                    'uploaded_by' => null,
                    'uploaded_at' => now(),
                ]
            );
        }

        $this->audit($opening, 'cargar_evidencia_externa', 'Evidencia de consulta externa registrada.');

        $nextStep = $this->externalChecksAreComplete($opening) ? 'internos' : 'externas';

        return redirect()
            ->route('accounts.show', [$opening, 'paso' => $nextStep])
            ->with('success', 'Evidencia externa guardada.');
    }

    public function generateInternalDocument(Request $request, AccountOpening $opening, InternalDocumentTemplate $template, InternalDocumentPdfService $pdfService)
    {
        $template = $this->internalTemplatesForOpening($opening)
            ->where('id', $template->id)
            ->firstOrFail();

        if (!$template->template_path) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'internos'])
                ->withErrors('Este documento no tiene formato descargable configurado.');
        }

        if ($this->isBdhTemplate($template)) {
            $this->audit($opening, 'abrir_formato_bdh', "Formato original abierto: {$template->name}.");

            return response()->file(public_path($template->template_path), [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.basename($template->template_path).'"',
            ]);
        }

        $data = array_merge(
            $this->documentDownloadDefaults($opening),
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

        if ($this->isSignatureRegisterTemplate($template)) {
            return response($pdfService->generate($opening, $template, $data), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$downloadName.'"',
            ]);
        }

        return view('accounts.generated-documents.show', [
            'opening' => $opening,
            'template' => $template,
            'fields' => $data,
            'downloadName' => $downloadName,
        ]);
    }

    public function uploadInternalDocument(Request $request, AccountOpening $opening)
    {
        $data = $request->validate([
            'internal_document_template_id' => ['required', 'exists:internal_document_templates,id'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_FILE_KB],
            'manual_signature_confirmed' => ['accepted'],
            'status' => ['required', Rule::in(['cargado', 'validado', 'rechazado'])],
        ], [
            'manual_signature_confirmed.accepted' => 'Debe confirmar que el documento interno contiene firma cuando aplique.',
        ]);

        if (!$this->externalChecksAreComplete($opening)) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'externas'])
                ->withErrors('Complete las capturas obligatorias de consultas externas antes de documentos internos.');
        }

        $template = $this->internalTemplatesForOpening($opening)
            ->where('id', $data['internal_document_template_id'])
            ->firstOrFail();
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
                'manual_signature_confirmed' => true,
                'uploaded_by' => null,
            ]
        );

        $this->audit($opening, 'cargar_documento_interno', "Documento interno cargado: {$template->name}.");

        $nextStep = $this->internalDocumentsAreComplete($opening) ? 'servicios' : 'internos';

        return redirect()
            ->route('accounts.show', [$opening, 'paso' => $nextStep])
            ->with('success', 'Documento interno guardado.');
    }

    public function saveServices(Request $request, AccountOpening $opening)
    {
        if (!$this->internalDocumentsAreComplete($opening)) {
            return redirect()
                ->route('accounts.show', [$opening, 'paso' => 'internos'])
                ->withErrors('Complete los documentos internos antes de registrar servicios adicionales.');
        }

        $data = $request->validate([
            'services' => ['nullable', 'array'],
            'services.*' => ['exists:additional_services,id'],
        ]);

        DB::transaction(function () use ($opening, $data) {
            SelectedAdditionalService::where('account_opening_id', $opening->id)->delete();
            foreach ($data['services'] ?? [] as $serviceId) {
                SelectedAdditionalService::create([
                    'account_opening_id' => $opening->id,
                    'additional_service_id' => $serviceId,
                    'selected_by' => null,
                ]);
            }
        });

        $this->audit($opening, 'seleccionar_servicios', 'Servicios adicionales actualizados.');

        return redirect()
            ->route('accounts.show', [$opening, 'paso' => 'servicios'])
            ->with('success', 'Servicios adicionales guardados.');
    }

    public function uploadServiceDocument(Request $request, AccountOpening $opening)
    {
        $data = $request->validate([
            'internal_document_template_id' => ['required', 'exists:internal_document_templates,id'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_FILE_KB],
            'manual_signature_confirmed' => ['accepted'],
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
            ->where('id', $data['internal_document_template_id'])
            ->firstOrFail();

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
                'manual_signature_confirmed' => true,
                'uploaded_by' => null,
            ]
        );

        $this->audit($opening, 'cargar_documento_servicio', "Documento de servicio cargado: {$template->name}.");

        $nextStep = $this->serviceDocumentsAreComplete($opening) ? 'resumen' : 'servicios';

        return redirect()
            ->route('accounts.show', [$opening, 'paso' => $nextStep])
            ->with('success', 'Documento de servicio guardado.');
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
                ->withErrors('El expediente aun tiene requisitos obligatorios pendientes.');
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

    private function documentDownloadDefaults(AccountOpening $opening): array
    {
        $opening->loadMissing(['documents', 'accountType']);

        $cedulaData = $opening->documents
            ->where('document_scope', 'requisito')
            ->pluck('extracted_data')
            ->filter()
            ->first(fn ($data) => filled($data['cedula'] ?? null) || filled($data['nombres_apellidos'] ?? null));

        $planillaData = $opening->documents
            ->where('document_scope', 'requisito')
            ->pluck('extracted_data')
            ->filter()
            ->first(fn ($data) => filled($data['direccion'] ?? null));

        $memberName = $this->singleLine(trim(($opening->member_first_names ?? '').' '.($opening->member_last_names ?? '')));
        $extractedName = $this->singleLine($cedulaData['nombres_apellidos'] ?? '');
        $fullName = mb_strlen($extractedName) > mb_strlen($memberName) ? $extractedName : $memberName;

        return [
            'apellidos_nombres' => $fullName,
            'cedula_identidad' => $this->documentIdentityNumber($opening, $cedulaData),
            'nacionalidad' => $opening->member_nationality ?: ($cedulaData['nacionalidad'] ?? ''),
            'direccion' => $opening->member_address ?: ($planillaData['direccion'] ?? ''),
            'codigo_socio' => $opening->file_name,
            'cuenta_numero' => $opening->file_name,
            'tipo_cuenta' => $opening->accountType->name,
            'ciudad' => 'Las Naves',
            'dia' => now()->format('d'),
            'mes' => now()->locale('es')->translatedFormat('F'),
            'anio' => now()->format('Y'),
            'tipo_solicitante' => 'socio',
            'fondo_mortuorio' => 'no',
        ];
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

    private function consentDocumentDefaults(AccountOpening $opening): array
    {
        $opening->loadMissing('accountType');
        $fullName = $this->singleLine(trim(($opening->member_first_names ?? '').' '.($opening->member_last_names ?? '')));
        $identification = preg_replace('/\D+/', '', (string) $opening->member_identification);

        return [
            'apellidos_nombres' => $fullName,
            'cedula_identidad' => strlen($identification) === 10 ? $identification : '',
            'ciudad' => 'Las Naves',
            'dia' => now()->format('d'),
            'mes' => now()->locale('es')->translatedFormat('F'),
            'anio' => now()->format('Y'),
            'tipo_cuenta' => $opening->accountType->name,
        ];
    }

    private function syncMemberDataFromExtraction(AccountOpening $opening, string $slug, array $extracted): void
    {
        $updates = [];

        if (in_array($slug, ['cedula', 'cedula-papeleta'], true)) {
            if (blank($opening->member_identification) && filled($extracted['cedula'] ?? null)) {
                $updates['member_identification'] = $extracted['cedula'];
            }

            if (blank($opening->member_first_names) && blank($opening->member_last_names) && filled($extracted['nombres_apellidos'] ?? null)) {
                $updates['member_first_names'] = $extracted['nombres_apellidos'];
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

    private function hasSignatureSignal(UploadedFile $file): bool
    {
        $name = strtolower($file->getClientOriginalName());
        return str_contains($name, 'firm') || str_contains($name, 'signed') || str_contains($name, 'firma');
    }

    private function consentSignatureError(UploadedFile $file): ?string
    {
        $blankTemplate = public_path('formatos/CONSENTIMIENTO_DE_DATOS_PERSONALES_LAS_NAVES.pdf');
        if (is_file($blankTemplate) && hash_file('sha256', $blankTemplate) === hash_file('sha256', $file->getRealPath())) {
            return 'El archivo cargado parece ser el formato original sin firma. Imprima el consentimiento, obtenga la firma del socio y cargue el documento firmado.';
        }

        $name = strtolower($file->getClientOriginalName());
        if (str_contains($name, 'sin_firma') || str_contains($name, 'sin-firma') || str_contains($name, 'no_firmado')) {
            return 'El nombre del archivo indica que el consentimiento no esta firmado. Cargue el documento firmado para continuar.';
        }

        return null;
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
                'ultima_eleccion' => 'Validacion manual contra CNE requerida',
                'alerta' => 'Confirmar que corresponda a la ultima eleccion disponible.',
            ],
            'planilla-servicios' => $base + [
                'direccion' => $opening->member_address,
                'fecha_emision' => 'Pendiente de OCR',
            ],
            'ruc' => $base + [
                'ruc' => null,
                'razon_social' => 'Pendiente de OCR',
            ],
            default => $base + ['legibilidad' => 'Validacion manual requerida'],
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
        return $consent && $consent->signed_file_path && $consent->manual_signature_confirmed;
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

        $validIds = $opening->documents()
            ->where('document_scope', 'requisito')
            ->whereIn('status', ['cargado', 'validado'])
            ->pluck('account_type_requirement_id');

        return $requiredIds->diff($validIds)->isEmpty();
    }

    private function externalChecksAreComplete(AccountOpening $opening): bool
    {
        $requiredIds = ExternalCheckItem::where('active', true)->where('is_required', true)->pluck('id');
        $loadedIds = $opening->externalEvidences()->whereNotNull('screenshot_path')->pluck('external_check_item_id');

        return $requiredIds->diff($loadedIds)->isEmpty();
    }

    private function internalDocumentsAreComplete(AccountOpening $opening): bool
    {
        $requiredIds = $this->internalTemplatesForOpening($opening)->where('is_required', true)->pluck('id');
        $loadedIds = $opening->documents()
            ->where('document_scope', 'interno')
            ->whereIn('status', ['cargado', 'validado'])
            ->pluck('internal_document_template_id');

        return $requiredIds->diff($loadedIds)->isEmpty();
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
            'tarjeta-de-debito' => 'solicitud-tarjeta-de-debito',
        ];
    }

    private function servicesStepIsComplete(AccountOpening $opening): bool
    {
        return $opening->histories()->where('action', 'seleccionar_servicios')->exists();
    }

    private function serviceDocumentsAreComplete(AccountOpening $opening): bool
    {
        if (!$this->servicesStepIsComplete($opening)) {
            return false;
        }

        $selectedSlugs = $opening->services()
            ->join('additional_services', 'additional_services.id', '=', 'selected_additional_services.additional_service_id')
            ->pluck('additional_services.slug');
        $templateSlugs = $selectedSlugs
            ->map(fn ($slug) => $this->serviceDocumentMap()[$slug] ?? null)
            ->filter()
            ->values();

        if ($templateSlugs->isEmpty()) {
            return true;
        }

        $requiredIds = $this->serviceDocumentTemplates()->whereIn('slug', $templateSlugs)->pluck('id');
        $loadedIds = $opening->documents()
            ->where('document_scope', 'servicio')
            ->whereIn('status', ['cargado', 'validado'])
            ->pluck('internal_document_template_id');

        return $requiredIds->diff($loadedIds)->isEmpty();
    }

    private function workflowState(AccountOpening $opening): array
    {
        $complete = [
            'consentimiento' => $this->consentIsValid($opening),
            'requisitos' => $this->requiredDocumentsAreValid($opening),
            'externas' => $this->externalChecksAreComplete($opening),
            'internos' => $this->internalDocumentsAreComplete($opening),
            'servicios' => $this->serviceDocumentsAreComplete($opening),
            'resumen' => in_array($opening->status, ['en_revision', 'aprobado', 'finalizado'], true),
        ];

        $unlocked = [
            'consentimiento' => true,
            'requisitos' => $complete['consentimiento'],
            'externas' => $complete['requisitos'],
            'internos' => $complete['externas'],
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
            && $this->internalDocumentsAreComplete($opening)
            && $this->serviceDocumentsAreComplete($opening);
    }

    private function calculateProgress(AccountOpening $opening): int
    {
        $checks = [
            $this->consentIsValid($opening),
            $this->requiredDocumentsAreValid($opening),
            $this->externalChecksAreComplete($opening),
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
            'user_id' => null,
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}

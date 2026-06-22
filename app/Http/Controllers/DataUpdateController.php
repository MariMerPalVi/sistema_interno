<?php

namespace App\Http\Controllers;

use App\Models\DataUpdateDocument;
use App\Models\DataUpdateHistory;
use App\Models\DataUpdateProcess;
use App\Services\SignatureValidationService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DataUpdateController extends Controller
{
    private const MAX_FILE_KB = 5120;

    public function index()
    {
        $processes = DataUpdateProcess::with('creator')
            ->when(
                !auth()->user()->isAdministrator(),
                fn ($query) => $query->where('agency', auth()->user()->agency)
            )
            ->latest()
            ->limit(40)
            ->get();

        return view('data-updates.index', [
            'processes' => $processes,
        ]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'file_name' => trim((string) $request->input('file_name')),
            'member_identification' => preg_replace('/\D+/', '', (string) $request->input('member_identification')),
        ]);

        $data = $request->validate([
            'file_name' => ['required', 'string', 'max:120', Rule::unique('data_update_processes', 'file_name')],
            'member_identification' => ['required', 'digits_between:10,13'],
            'member_name' => ['nullable', 'string', 'max:160'],
            'selected_changes' => ['required', 'array', 'min:1'],
            'selected_changes.*' => [Rule::in(array_keys($this->changeOptions()))],
            'observations' => ['nullable', 'string', 'max:600'],
        ], [
            'file_name.unique' => 'Ya existe una actualización de datos con este número o nombre.',
            'selected_changes.required' => 'Seleccione al menos un dato que será actualizado.',
        ]);

        $process = DB::transaction(function () use ($data) {
            $agency = auth()->user()->agency;
            $agencyFolder = config("opening.agencies.{$agency}.folder", $agency);

            $process = DataUpdateProcess::create([
                'public_code' => $this->makePublicCode(),
                'file_name' => $data['file_name'],
                'storage_folder' => $this->safeFileNamePart($agencyFolder).'/'.$this->safeFileNamePart($data['file_name']),
                'agency' => $agency,
                'created_by' => auth()->id(),
                'member_identification' => $data['member_identification'],
                'member_name' => $data['member_name'] ?? null,
                'selected_changes' => array_values($data['selected_changes']),
                'observations' => $data['observations'] ?? null,
            ]);

            $this->audit($process, 'crear_actualizacion', 'Trámite de actualización de datos creado.');

            return $process;
        });

        return redirect()->route('data-updates.show', $process)->with('success', 'Actualización creada. Complete los datos del socio.');
    }

    public function show(Request $request, DataUpdateProcess $update)
    {
        $update->load(['documents', 'histories.user']);
        $steps = $this->steps($update);
        $activeStep = $request->query('paso', $this->currentStep($update));
        if (!array_key_exists($activeStep, $steps)) {
            $activeStep = $this->currentStep($update);
        }

        return view('data-updates.show', [
            'update' => $update,
            'steps' => $steps,
            'activeStep' => $activeStep,
            'changeOptions' => $this->changeOptions(),
            'requiredDocuments' => $this->requiredDocuments($update),
            'documentsComplete' => $this->documentsComplete($update),
            'progress' => $this->progress($update),
            'agencyName' => config("opening.agencies.{$update->agency}.name", $update->agency),
            'storagePath' => storage_path('app/private/actualizaciones/'.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $update->storage_folder)),
        ]);
    }

    public function updateData(Request $request, DataUpdateProcess $update)
    {
        $fields = array_keys($this->dataFields());
        $rules = [];
        foreach ($fields as $field) {
            $rules["current_data.{$field}"] = ['nullable', 'string', 'max:220'];
            $rules["new_data.{$field}"] = ['nullable', 'string', 'max:220'];
        }

        $data = $request->validate($rules + [
            'member_name' => ['nullable', 'string', 'max:160'],
            'observations' => ['nullable', 'string', 'max:600'],
        ]);

        $update->update([
            'member_name' => $data['member_name'] ?? $update->member_name,
            'current_data' => array_filter($data['current_data'] ?? [], fn ($value) => filled($value)),
            'new_data' => array_filter($data['new_data'] ?? [], fn ($value) => filled($value)),
            'observations' => $data['observations'] ?? null,
        ]);

        $this->audit($update, 'guardar_datos', 'Datos actuales y nuevos registrados.');

        return redirect()->route('data-updates.show', [$update, 'paso' => 'documentos'])->with('success', 'Datos guardados.');
    }

    public function uploadDocument(Request $request, DataUpdateProcess $update)
    {
        $requiredDocuments = collect($this->requiredDocuments($update))->keyBy('key');

        $data = $request->validate([
            'document_key' => ['required', Rule::in($requiredDocuments->keys()->all())],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_FILE_KB],
            'status' => ['required', Rule::in(['cargado', 'validado', 'rechazado'])],
            'manual_signature_confirmed' => ['nullable'],
            'observations' => ['nullable', 'string', 'max:500'],
        ]);

        $document = $requiredDocuments->get($data['document_key']);

        if ($document['requires_signature']) {
            $request->validate([
                'manual_signature_confirmed' => ['required', 'accepted'],
            ], [
                'manual_signature_confirmed.required' => "Revise la firma de {$document['name']} antes de cargarlo.",
                'manual_signature_confirmed.accepted' => "Debe confirmar que {$document['name']} contiene firma.",
            ]);

            $signatureError = app(SignatureValidationService::class)->validationError($request->file('file'));
            if ($signatureError) {
                return back()->withErrors($signatureError);
            }
        }

        $path = $this->storeFile($request->file('file'), $update, $document['file_name']);

        DataUpdateDocument::updateOrCreate(
            [
                'data_update_process_id' => $update->id,
                'document_key' => $data['document_key'],
            ],
            [
                'display_name' => $document['name'],
                'file_path' => $path,
                'original_name' => $request->file('file')->getClientOriginalName(),
                'mime_type' => $request->file('file')->getMimeType() ?: 'application/octet-stream',
                'file_size' => $request->file('file')->getSize(),
                'status' => $data['status'],
                'manual_signature_confirmed' => !$document['requires_signature'] || $request->boolean('manual_signature_confirmed'),
                'observations' => $data['observations'] ?? null,
                'uploaded_by' => auth()->id(),
            ]
        );

        $this->audit($update, 'cargar_documento', "Documento cargado: {$document['name']}.");

        return back()->with('success', 'Documento cargado.');
    }

    public function submit(DataUpdateProcess $update)
    {
        if (!$this->documentsComplete($update)) {
            return redirect()
                ->route('data-updates.show', [$update, 'paso' => 'documentos'])
                ->withErrors('Debe cargar todos los documentos obligatorios antes de finalizar.');
        }

        $update->update([
            'status' => 'finalizado',
            'submitted_at' => now(),
        ]);

        $this->audit($update, 'finalizar_actualizacion', 'Actualización de datos finalizada.');

        return redirect()->route('data-updates.show', [$update, 'paso' => 'resumen'])->with('success', 'Actualización finalizada.');
    }

    private function steps(DataUpdateProcess $update): array
    {
        return [
            'datos' => '1. Datos',
            'documentos' => '2. Documentos',
            'resumen' => '3. Check List',
        ];
    }

    private function currentStep(DataUpdateProcess $update): string
    {
        if (!$update->current_data && !$update->new_data) {
            return 'datos';
        }

        return $this->documentsComplete($update) ? 'resumen' : 'documentos';
    }

    private function changeOptions(): array
    {
        return [
            'direccion' => 'Dirección domiciliaria',
            'contacto' => 'Teléfono o correo electrónico',
            'estado_civil' => 'Estado civil o datos del cónyuge',
            'actividad_economica' => 'Actividad económica o ingresos',
            'datos_personales' => 'Corrección de datos personales',
            'residencia_fiscal' => 'Residencia fiscal',
        ];
    }

    private function dataFields(): array
    {
        return [
            'direccion' => 'Dirección domiciliaria',
            'telefono' => 'Teléfono',
            'correo' => 'Correo electrónico',
            'estado_civil' => 'Estado civil',
            'actividad_economica' => 'Actividad económica',
            'ingresos' => 'Ingresos aproximados',
            'residencia_fiscal' => 'Residencia fiscal',
        ];
    }

    private function requiredDocuments(DataUpdateProcess $update): array
    {
        $documents = [
            'cedula-papeleta' => [
                'name' => 'Cédula y papeleta de votación',
                'file_name' => '1. Cedula y papeleta_{expediente}',
                'requires_signature' => false,
            ],
            'formulario-actualizacion' => [
                'name' => 'Formulario de actualización de datos firmado',
                'file_name' => '2. Formulario actualizacion datos_{expediente}',
                'requires_signature' => true,
            ],
        ];

        $changes = collect($update->selected_changes ?? []);

        if ($changes->contains('direccion')) {
            $documents['planilla-servicios'] = [
                'name' => 'Planilla de servicios básicos',
                'file_name' => '3. Planilla de SB_{expediente}',
                'requires_signature' => false,
            ];
        }

        if ($changes->contains('estado_civil')) {
            $documents['respaldo-estado-civil'] = [
                'name' => 'Respaldo de estado civil o documentos del cónyuge',
                'file_name' => '4. Respaldo estado civil_{expediente}',
                'requires_signature' => false,
            ];
        }

        if ($changes->contains('actividad_economica')) {
            $documents['respaldo-actividad'] = [
                'name' => 'Respaldo de actividad económica o ingresos',
                'file_name' => '5. Respaldo actividad economica_{expediente}',
                'requires_signature' => false,
            ];
        }

        if ($changes->contains('residencia_fiscal')) {
            $documents['residencia-fiscal'] = [
                'name' => 'Formulario de autocertificación de residencia fiscal',
                'file_name' => '6. Residencia fiscal_{expediente}',
                'requires_signature' => true,
            ];
        }

        return array_values(array_map(
            fn ($key, $document) => ['key' => $key] + $document,
            array_keys($documents),
            $documents
        ));
    }

    private function documentsComplete(DataUpdateProcess $update): bool
    {
        $update->loadMissing('documents');
        $loaded = $update->documents
            ->whereIn('status', ['cargado', 'validado'])
            ->keyBy('document_key');

        return collect($this->requiredDocuments($update))->every(function (array $required) use ($loaded) {
            $document = $loaded->get($required['key']);

            return $document && (!$required['requires_signature'] || $document->manual_signature_confirmed);
        });
    }

    private function progress(DataUpdateProcess $update): int
    {
        $checks = [
            filled($update->member_identification),
            filled($update->current_data) || filled($update->new_data),
            $this->documentsComplete($update),
            $update->status === 'finalizado',
        ];

        return (int) round((count(array_filter($checks)) / count($checks)) * 100);
    }

    private function storeFile(UploadedFile $file, DataUpdateProcess $update, string $pattern): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'pdf');
        $baseName = str_replace('{expediente}', $this->safeFileNamePart($update->file_name), $pattern);
        $baseName = $this->safeFileNamePart($baseName);

        return $file->storeAs("actualizaciones/{$update->storage_folder}", "{$baseName}.{$extension}");
    }

    private function makePublicCode(): string
    {
        do {
            $code = 'AD-'.now()->format('ym').'-'.random_int(100000, 999999);
        } while (DataUpdateProcess::where('public_code', $code)->exists());

        return $code;
    }

    private function safeFileNamePart(?string $value): string
    {
        $value = $value ?: 'expediente';
        $value = preg_replace('/[\\\\\/:*?"<>|]+/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', trim($value));

        return trim($value, '. ') ?: 'expediente';
    }

    private function audit(DataUpdateProcess $update, string $action, string $description, ?array $metadata = null): void
    {
        DataUpdateHistory::create([
            'data_update_process_id' => $update->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}

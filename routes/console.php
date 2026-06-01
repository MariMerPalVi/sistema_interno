<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('about:aperturas', function () {
    $this->info('Modulo de apertura de cuentas instalado.');
});

Artisan::command('aperturas:reprocesar-documentos', function () {
    $docs = \App\Models\UploadedDocument::where('document_scope', 'requisito')
        ->whereNotNull('account_type_requirement_id')
        ->get();

    $updated = 0;
    foreach ($docs as $doc) {
        $requirement = \App\Models\AccountTypeRequirement::with('type')->find($doc->account_type_requirement_id);
        if (!$requirement) {
            continue;
        }

        if (!\Illuminate\Support\Facades\Storage::exists($doc->file_path)) {
            $doc->update([
                'display_name' => $requirement->label,
                'extracted_data' => [
                    'archivo' => $doc->original_name,
                    'fuente_texto' => 'Archivo no disponible en storage',
                    'alerta' => 'El archivo cargado no se encuentra en el almacenamiento. Reemplace el documento para ejecutar la extraccion actualizada.',
                    'requiere_validacion_manual' => true,
                ],
            ]);
            $updated++;
            continue;
        }

        $file = new \Illuminate\Http\UploadedFile(
            \Illuminate\Support\Facades\Storage::path($doc->file_path),
            $doc->original_name,
            $doc->mime_type,
            null,
            true
        );

        $doc->update([
            'display_name' => $requirement->label,
            'extracted_data' => app(\App\Services\DocumentExtractionService::class)->extract($requirement->type->slug, $file),
        ]);
        $updated++;
    }

    $this->info("Documentos reprocesados: {$updated}");
});

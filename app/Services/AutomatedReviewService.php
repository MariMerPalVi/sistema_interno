<?php

namespace App\Services;

use App\Models\AccountOpening;
use App\Models\ExternalCheckItem;
use App\Models\InternalDocumentTemplate;
use Illuminate\Support\Facades\Storage;

class AutomatedReviewService
{
    public function review(AccountOpening $opening): array
    {
        $opening->loadMissing([
            'accountType.requirements.type',
            'consent',
            'documents',
            'externalEvidences',
            'histories',
            'services.additionalService',
        ]);

        $findings = collect()
            ->merge($this->reviewConsent($opening))
            ->merge($this->reviewRequirements($opening))
            ->merge($this->reviewExternalChecks($opening))
            ->merge($this->reviewInternalDocuments($opening))
            ->merge($this->reviewServiceDocuments($opening))
            ->values();

        $errors = $findings->where('severity', 'error')->count();
        $warnings = $findings->where('severity', 'warning')->count();
        $score = max(0, 100 - ($errors * 15) - ($warnings * 5));
        $status = $errors === 0 && $warnings === 0 ? 'aprobado' : 'observado';

        return [
            'status' => $status,
            'score' => $score,
            'summary' => $status === 'aprobado'
                ? 'Revision digital completada sin observaciones.'
                : 'Revision digital completada con observaciones que deben corregirse.',
            'findings' => $findings->all(),
            'reviewed_at' => now()->toISOString(),
        ];
    }

    private function reviewConsent(AccountOpening $opening): array
    {
        $consent = $opening->consent;

        if (!$consent || !$consent->signed_file_path) {
            return [$this->finding('error', 'Consentimiento', 'No se encontro consentimiento firmado cargado.')];
        }

        if (!Storage::exists($consent->signed_file_path)) {
            return [$this->finding('error', 'Consentimiento', 'El archivo del consentimiento no existe en almacenamiento.')];
        }

        if (!$consent->manual_signature_confirmed) {
            return [$this->finding('error', 'Consentimiento', 'El consentimiento no tiene confirmacion de firma.')];
        }

        return [];
    }

    private function reviewRequirements(AccountOpening $opening): array
    {
        $findings = [];
        $documents = $opening->documents->where('document_scope', 'requisito')->keyBy('account_type_requirement_id');
        $required = $opening->accountType->requirements->where('is_required', true);

        if ($opening->requires_spouse_documents) {
            $required = $required->merge(
                $opening->accountType->requirements->filter(fn ($requirement) => $requirement->type->slug === 'documentos-conyuge')
            )->unique('id');
        }

        foreach ($required as $requirement) {
            $document = $documents->get($requirement->id);

            if (!$document) {
                $findings[] = $this->finding('error', $requirement->label, 'Documento obligatorio no cargado.');
                continue;
            }

            $findings = array_merge($findings, $this->reviewDocumentFile($document->display_name, $document));

            if ($document->status === 'rechazado') {
                $findings[] = $this->finding('error', $document->display_name, 'El documento esta rechazado.');
            }

            $data = $document->extracted_data ?? [];
            if (in_array($requirement->type->slug, ['cedula-papeleta', 'cedula'], true)) {
                if (blank($data['cedula'] ?? null)) {
                    $findings[] = $this->finding('error', $document->display_name, 'No se detecto numero de cedula.');
                }
                $name = $data['nombres_apellidos'] ?? trim(($data['nombres'] ?? '').' '.($data['apellidos'] ?? ''));
                if (blank($name)) {
                    $findings[] = $this->finding('warning', $document->display_name, 'No se detecto nombre y apellido.');
                }
            }

            if ($requirement->type->slug === 'planilla-servicios' && blank($data['direccion'] ?? null)) {
                $findings[] = $this->finding('warning', $document->display_name, 'No se detecto direccion en la planilla.');
            }
        }

        return $findings;
    }

    private function reviewExternalChecks(AccountOpening $opening): array
    {
        $findings = [];
        $evidences = $opening->externalEvidences->keyBy(
            fn ($evidence) => $evidence->subject_key.'_'.$evidence->external_check_item_id
        );

        foreach ($this->externalCheckSubjects($opening) as $subjectKey => $subjectLabel) {
            foreach (ExternalCheckItem::where('active', true)->orderBy('sort_order')->get() as $item) {
                $evidence = $evidences->get($subjectKey.'_'.$item->id);
                $findingTitle = "{$item->name} - {$subjectLabel}";

                if (!$evidence || !$evidence->screenshot_path) {
                    $findings[] = $this->finding('error', $findingTitle, 'No tiene evidencia cargada.');
                    continue;
                }

                if (!Storage::exists($evidence->screenshot_path)) {
                    $findings[] = $this->finding('error', $findingTitle, 'El PDF consolidado de evidencias no existe.');
                }

                if ($evidence->result === 'pendiente') {
                    $findings[] = $this->finding('error', $findingTitle, 'La linea de control sigue pendiente.');
                }

                if ($evidence->result === 'con_observacion') {
                    $findings[] = $this->finding('warning', $findingTitle, 'La consulta fue marcada con observacion.');
                }
            }
        }

        return $findings;
    }

    private function externalCheckSubjects(AccountOpening $opening): array
    {
        $companyApplicable = (bool) data_get(
            $opening->histories
                ->where('action', 'cargar_evidencia_externa')
                ->sortByDesc('id')
                ->first()?->metadata,
            'company_check_applicable',
            false
        );

        return match ($opening->accountType->slug) {
            'cuenta-junior' => [
                'representante' => 'Representante',
                'menor' => 'Menor',
            ],
            'cuenta-juridica' => array_filter([
                'representante_legal' => 'Representante legal',
                'empresa' => $companyApplicable ? 'Empresa' : null,
            ]),
            default => ['titular' => 'Titular'],
        };
    }

    private function reviewInternalDocuments(AccountOpening $opening): array
    {
        $findings = [];
        $documents = $opening->documents->where('document_scope', 'interno')->keyBy('internal_document_template_id');

        $requiredTemplates = InternalDocumentTemplate::where('active', true)
            ->where('account_type_id', $opening->account_type_id)
            ->where('source', '!=', 'servicio')
            ->where('is_required', true)
            ->orderBy('sort_order')
            ->get();

        foreach ($requiredTemplates as $template) {
            $document = $documents->get($template->id);

            if (!$document) {
                $findings[] = $this->finding('error', $template->name, 'Documento interno obligatorio no cargado.');
                continue;
            }

            $findings = array_merge($findings, $this->reviewDocumentFile($document->display_name, $document));

            if ($template->requires_signature && !$document->manual_signature_confirmed) {
                $findings[] = $this->finding('error', $template->name, 'Documento interno sin confirmacion de firma.');
            }
        }

        return $findings;
    }

    private function reviewServiceDocuments(AccountOpening $opening): array
    {
        $findings = [];
        $documents = $opening->documents->where('document_scope', 'servicio')->keyBy('internal_document_template_id');
        $map = [
            'fondo-mortuorio' => 'formulario-servicio-fondo-mortuorio',
            'tarjeta-de-debito' => 'solicitud-tarjeta-de-debito',
        ];

        foreach ($opening->services as $selectedService) {
            $serviceSlug = $selectedService->additionalService?->slug;
            $templateSlug = $map[$serviceSlug] ?? null;
            if (!$templateSlug) {
                continue;
            }

            $template = InternalDocumentTemplate::where('slug', $templateSlug)->where('active', true)->first();
            if (!$template) {
                continue;
            }

            $document = $documents->get($template->id);
            if (!$document) {
                $findings[] = $this->finding('error', $template->name, 'Documento del servicio seleccionado no cargado.');
                continue;
            }

            $findings = array_merge($findings, $this->reviewDocumentFile($document->display_name, $document));
        }

        return $findings;
    }

    private function reviewDocumentFile(string $label, $document): array
    {
        $findings = [];

        if (!Storage::exists($document->file_path)) {
            $findings[] = $this->finding('error', $label, 'El archivo no existe en almacenamiento.');
        }

        if (!in_array($document->status, ['cargado', 'validado'], true)) {
            $findings[] = $this->finding('error', $label, 'El documento no esta en estado cargado o validado.');
        }

        return $findings;
    }

    private function finding(string $severity, string $subject, string $message): array
    {
        return compact('severity', 'subject', 'message');
    }
}

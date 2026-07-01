<?php

namespace App\Services;

use App\Models\AccountOpening;
use App\Models\ExternalCheckItem;
use App\Models\InternalDocumentTemplate;
use Illuminate\Support\Facades\Storage;

class AccountOpeningWorkflowService
{
    public function workflowState(AccountOpening $opening): array
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

    public function readyToSubmit(AccountOpening $opening): bool
    {
        return $this->consentIsValid($opening)
            && $this->requiredDocumentsAreValid($opening)
            && $this->externalChecksAreComplete($opening)
            && $opening->file_name_confirmed
            && $this->internalDocumentsAreComplete($opening)
            && $this->serviceDocumentsAreComplete($opening);
    }

    public function calculateProgress(AccountOpening $opening): int
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

    public function consentIsValid(AccountOpening $opening): bool
    {
        $consent = $opening->consent()->first();

        return $consent
            && $consent->status === 'validado'
            && $consent->signed_file_path
            && Storage::exists($consent->signed_file_path)
            && $consent->manual_signature_confirmed
            && $consent->validated_at;
    }

    public function requiredDocumentsAreValid(AccountOpening $opening): bool
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

    public function externalChecksAreComplete(AccountOpening $opening): bool
    {
        $requiredIds = ExternalCheckItem::where('active', true)
            ->where('is_required', true)
            ->where('name', 'not like', '%360%')
            ->pluck('id');
        $loaded = $opening->externalEvidences()->whereNotNull('screenshot_path')->get();

        foreach (array_keys($this->externalCheckSubjects($opening)) as $subjectKey) {
            $loadedIds = $loaded->where('subject_key', $subjectKey)->pluck('external_check_item_id');
            if ($requiredIds->diff($loadedIds)->isNotEmpty()) {
                return false;
            }
        }

        return true;
    }

    public function internalDocumentsAreComplete(AccountOpening $opening): bool
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

    public function serviceDocumentsAreComplete(AccountOpening $opening): bool
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

    private function externalCheckSubjects(AccountOpening $opening): array
    {
        $opening->loadMissing('accountType');

        return match ($opening->accountType->slug) {
            'cuenta-junior' => [
                'representante' => 'Representante',
                'menor' => 'Menor',
            ],
            'cuenta-juridica' => array_filter([
                'representante_legal' => 'Representante legal',
                'empresa' => $this->companyExternalCheckApplicable($opening) ? 'Empresa' : null,
            ]),
            default => ['titular' => 'Titular'],
        };
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

    private function fondoMortuorioDecision(AccountOpening $opening): ?string
    {
        return data_get($opening->histories()
            ->where('action', 'seleccionar_servicios')
            ->latest('id')
            ->first()?->metadata, 'fondo_mortuorio');
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
}

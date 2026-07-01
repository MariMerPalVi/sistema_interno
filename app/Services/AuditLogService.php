<?php

namespace App\Services;

use App\Models\AccountOpening;
use App\Models\ActionHistory;
use App\Models\PersonalDataConsent;
use App\Models\UploadedDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogService
{
    public function record(string $action, ?string $description = null, array $context = [], ?Request $request = null): ?ActionHistory
    {
        $request ??= request();
        $user = $request->user();

        $opening = $this->resolveOpening($context);
        $subject = $this->resolveSubject($context);
        $metadata = $this->metadata($context);

        return ActionHistory::create([
            'account_opening_id' => $opening?->id,
            'user_id' => $user?->id,
            'agency' => $user?->agency ?? $opening?->agency,
            'role' => $user?->roleName(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'subject_name' => $this->subjectName($subject),
            'description' => $description,
            'metadata' => $metadata ?: null,
        ]);
    }

    private function resolveOpening(array $context): ?AccountOpening
    {
        $opening = $context['opening'] ?? null;
        if ($opening instanceof AccountOpening) {
            return $opening;
        }

        foreach (['document', 'consent', 'subject'] as $key) {
            $model = $context[$key] ?? null;
            if ($model instanceof UploadedDocument) {
                return $model->opening ?? AccountOpening::find($model->account_opening_id);
            }
            if ($model instanceof PersonalDataConsent) {
                return $model->opening ?? AccountOpening::find($model->account_opening_id);
            }
        }

        if (!empty($context['account_opening_id'])) {
            return AccountOpening::find($context['account_opening_id']);
        }

        return null;
    }

    private function resolveSubject(array $context): ?Model
    {
        foreach (['subject', 'document', 'consent', 'opening'] as $key) {
            $model = $context[$key] ?? null;
            if ($model instanceof Model) {
                return $model;
            }
        }

        return null;
    }

    private function subjectName(?Model $subject): ?string
    {
        if (!$subject) {
            return null;
        }

        return match (true) {
            $subject instanceof AccountOpening => $subject->public_code,
            $subject instanceof UploadedDocument => $subject->display_name ?? $subject->original_name,
            $subject instanceof PersonalDataConsent => 'Consentimiento de datos personales',
            default => method_exists($subject, 'getAttribute') ? ($subject->getAttribute('name') ?? $subject->getAttribute('label')) : null,
        };
    }

    private function metadata(array $context): array
    {
        $metadata = $context['metadata'] ?? [];
        unset($context['metadata'], $context['opening'], $context['subject'], $context['document'], $context['consent']);

        return array_filter(array_merge($metadata, $context), fn ($value) => $value !== null && $value !== '');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AccountOpening;
use App\Models\AccountType;
use App\Models\UploadedDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class OperationalReportController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->isAdministrator(), 403);

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'agency' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:60'],
            'account_type_id' => ['nullable', 'integer', 'exists:account_types,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $base = AccountOpening::query()
            ->with(['accountType', 'creator']);
        $this->applyOpeningFilters($base, $filters);

        $total = (clone $base)->count();
        $byStatus = (clone $base)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->orderByDesc('total')
            ->pluck('total', 'status');

        $pendingConsents = (clone $base)
            ->where(function (Builder $query) {
                $query->whereDoesntHave('consent')
                    ->orWhereHas('consent', fn (Builder $consent) => $consent->where('status', '!=', 'validado'));
            })
            ->count();

        $documentsForFilteredOpenings = UploadedDocument::query()
            ->whereHas('opening', function (Builder $query) use ($filters) {
                $this->applyOpeningFilters($query, $filters);
            });

        $documentAlerts = [
            'observados' => (clone $documentsForFilteredOpenings)->whereIn('status', ['observado', 'rechazado'])->count(),
            'revision_manual' => (clone $documentsForFilteredOpenings)->where('requires_manual_data_review', true)->count(),
        ];

        $submitted = (clone $base)
            ->whereNotNull('submitted_at')
            ->get(['created_at', 'submitted_at']);
        $averageHoursToSubmit = $submitted->isEmpty()
            ? null
            : round($submitted->avg(fn (AccountOpening $opening) => $opening->created_at->diffInMinutes($opening->submitted_at)) / 60, 1);

        $openings = (clone $base)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('reports.index', [
            'filters' => $filters,
            'openings' => $openings,
            'total' => $total,
            'byStatus' => $byStatus,
            'pendingConsents' => $pendingConsents,
            'documentAlerts' => $documentAlerts,
            'averageHoursToSubmit' => $averageHoursToSubmit,
            'agencies' => collect(config('opening.agencies', []))->mapWithKeys(fn ($agency, $key) => [$key => $agency['name'] ?? $key]),
            'accountTypes' => AccountType::orderBy('name')->get(['id', 'name']),
            'users' => User::orderBy('name')->get(['id', 'name', 'agency']),
        ]);
    }

    private function applyOpeningFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['agency'] ?? null, fn (Builder $query, string $agency) => $query->where('agency', $agency))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['account_type_id'] ?? null, fn (Builder $query, int $typeId) => $query->where('account_type_id', $typeId))
            ->when($filters['user_id'] ?? null, fn (Builder $query, int $userId) => $query->where('created_by', $userId));
    }
}

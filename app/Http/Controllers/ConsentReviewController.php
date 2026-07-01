<?php

namespace App\Http\Controllers;

use App\Models\PersonalDataConsent;
use Illuminate\Http\Request;

class ConsentReviewController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'max:30'],
            'agency' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $user = $request->user();

        abort_unless(
            $user->canReviewConsents() || $user->isAdministrator(),
            403,
            'Solo el perfil de la abogada o administrador puede revisar el control de consentimientos.'
        );

        $agencies = collect(config('opening.agencies'))
            ->map(fn ($agency, $key) => ['key' => $key, 'name' => $agency['name']])
            ->values();

        $query = PersonalDataConsent::query()
            ->with(['opening.accountType', 'opening.creator', 'validator'])
            ->whereHas('opening')
            ->latest('updated_at');

        if (filled($filters['agency'] ?? null)) {
            $query->whereHas('opening', fn ($opening) => $opening->where('agency', $filters['agency']));
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        if (filled($filters['from'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (filled($filters['to'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        if (filled($filters['q'] ?? null)) {
            $term = trim($filters['q']);
            $query->whereHas('opening', function ($opening) use ($term) {
                $opening->where('public_code', 'like', "%{$term}%")
                    ->orWhere('file_name', 'like', "%{$term}%")
                    ->orWhere('member_identification', 'like', "%{$term}%")
                    ->orWhere('member_first_names', 'like', "%{$term}%")
                    ->orWhere('member_last_names', 'like', "%{$term}%");
            });
        }

        return view('consents.index', [
            'consents' => $query->paginate(15)->withQueryString(),
            'filters' => $filters,
            'agencies' => $agencies,
        ]);
    }
}

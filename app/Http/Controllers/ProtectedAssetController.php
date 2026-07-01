<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProtectedAssetController extends Controller
{
    public function certificateSignature(Request $request, string $authority)
    {
        abort_unless($request->user()?->canAccessProtectedAssets(), 403);

        $signatures = [
            'presidente' => [
                'path' => resource_path('assets/signatures/nancy-alegria.avif'),
                'type' => 'image/avif',
            ],
            'gerente' => [
                'path' => resource_path('assets/signatures/hiter-mera.jpg'),
                'type' => 'image/jpeg',
            ],
        ];

        abort_unless(isset($signatures[$authority]), 404);
        $signature = $signatures[$authority];
        abort_unless(is_file($signature['path']), 404);

        return response()->file($signature['path'], [
            'Content-Type' => $signature['type'],
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

class ProtectedAssetController extends Controller
{
    public function certificateSignature(string $authority)
    {
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

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemHealthController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->isAdministrator(), 403);

        $checks = [
            [
                'name' => 'Base de datos',
                'status' => $this->databaseIsAvailable(),
                'detail' => 'Conexión principal de Laravel.',
            ],
            [
                'name' => 'Almacenamiento privado',
                'status' => is_dir(storage_path('app/private')) && is_writable(storage_path('app/private')),
                'detail' => storage_path('app/private'),
            ],
            [
                'name' => 'Formatos institucionales',
                'status' => is_dir(public_path('formatos')),
                'detail' => public_path('formatos'),
            ],
            [
                'name' => 'Servicio local de escáner',
                'status' => filled(config('opening.scanner_service_url')),
                'detail' => config('opening.scanner_service_url') ?: 'No configurado',
            ],
            [
                'name' => 'Documentos privados no públicos',
                'status' => config('filesystems.disks.local.serve') === false,
                'detail' => 'Las descargas pasan por controlador protegido.',
            ],
            [
                'name' => 'APP_DEBUG',
                'status' => !config('app.debug'),
                'detail' => config('app.debug') ? 'Activo: desactivar en producción.' : 'Inactivo.',
            ],
        ];

        return view('system-health.index', compact('checks'));
    }

    private function databaseIsAvailable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}

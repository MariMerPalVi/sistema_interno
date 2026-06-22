<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class SignatureValidationService
{
    public function validationError(UploadedFile $file, ?string $templatePath = null): ?string
    {
        if ($templatePath) {
            $originalPath = public_path(ltrim($templatePath, '/\\'));

            if (is_file($originalPath) && hash_file('sha256', $originalPath) === hash_file('sha256', $file->getRealPath())) {
                return 'El archivo cargado es igual al formato original y no contiene una firma incorporada. Obtenga la firma y cargue nuevamente el documento.';
            }
        }

        $name = strtolower($file->getClientOriginalName());
        $unsignedMarkers = [
            'sin_firma',
            'sin-firma',
            'sin firma',
            'no_firmado',
            'no-firmado',
            'no firmado',
        ];

        foreach ($unsignedMarkers as $marker) {
            if (str_contains($name, $marker)) {
                return 'El nombre del archivo indica que el documento no está firmado. Cargue el documento firmado para continuar.';
            }
        }

        return null;
    }

    public function hasAutomaticSignal(UploadedFile $file): bool
    {
        $name = strtolower($file->getClientOriginalName());

        return str_contains($name, 'firmado')
            || str_contains($name, 'firmada')
            || str_contains($name, 'signed');
    }
}

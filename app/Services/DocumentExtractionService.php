<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;
use Throwable;

class DocumentExtractionService
{
    public function extract(string $slug, UploadedFile $file, bool $forceOcr = false): array
    {
        $base = [
            'archivo' => $file->getClientOriginalName(),
        ];

        if (!in_array($slug, ['cedula-papeleta', 'cedula', 'papeleta-votacion', 'planilla-servicios', 'ruc'], true)) {
            return $base + [
                'revision' => 'Carga registrada. Validación documental pendiente.',
                'requiere_validacion_manual' => true,
            ];
        }

        $text = $this->extractText($file, $forceOcr);
        $normalized = $this->normalize($text);
        $base += [
            'fuente_texto' => $text !== '' ? 'Texto embebido en el PDF/archivo' : 'Sin texto detectable',
        ];

        if ($text === '') {
            return $base + [
                'alerta' => 'El archivo parece ser un escaneo como imagen. Para extraer nombres/dirección automáticamente se requiere OCR; mientras tanto debe validarse manualmente.',
                'requiere_validacion_manual' => true,
            ];
        }

        return match ($slug) {
            'cedula-papeleta', 'cedula' => $base + $this->extractIdAndVoting($normalized),
            'papeleta-votacion' => $base + $this->extractVoting($normalized),
            'planilla-servicios' => $base + $this->extractUtilityBill($normalized),
            'ruc' => $base + $this->extractRuc($normalized),
            default => $base + [
                'texto_detectado' => mb_substr($normalized, 0, 600),
                'requiere_validacion_manual' => true,
            ],
        };
    }

    private function extractText(UploadedFile $file, bool $forceOcr = false): string
    {
        $text = '';

        if ($file->getMimeType() === 'application/pdf') {
            try {
                $text = trim((new Parser())->parseFile($file->getRealPath())->getText());
            } catch (Throwable) {
                $text = '';
            }
        }

        if ($forceOcr || config('opening.ocr_enabled', false)) {
            $ocrText = $this->extractTextWithWindowsOcr($file);
            if (mb_strlen($ocrText) > 30) {
                // La capa de texto de algunos PDF escaneados conserva datos de un
                // documento anterior. El OCR representa lo que el asesor ve.
                $text = trim($ocrText."\n".$text);
            }
        }

        return $text;
    }

    private function extractTextWithWindowsOcr(UploadedFile $file): string
    {
        $script = base_path('app/Support/windows_ocr.ps1');
        if (!is_file($script)) {
            return '';
        }

        $command = [
            'powershell',
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-File',
            $script,
            '-Path',
            $file->getRealPath(),
            '-Language',
            'es-ES',
            '-MaxPages',
            '3',
        ];

        $descriptorSpec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, base_path());
        if (!is_resource($process)) {
            return '';
        }

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            Log::warning('Windows OCR failed', ['error' => $error]);
            return '';
        }

        return trim($output);
    }

    private function normalize(string $text): string
    {
        $text = mb_strtoupper($text, 'UTF-8');
        $text = str_replace(["\r", "\t"], "\n", $text);
        $text = preg_replace('/[ ]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{2,}/', "\n", $text) ?? $text;

        return trim($text);
    }

    private function extractIdAndVoting(string $text): array
    {
        return $this->extractId($text) + $this->extractVoting($text);
    }

    private function extractId(string $text): array
    {
        $ids = $this->validEcuadorianIds($text);
        [$fullName, $lastNames, $firstNames] = $this->extractPersonNameParts($text);

        return [
            'cedula' => $ids[0] ?? null,
            'cedula_valida' => isset($ids[0]),
            'nombres_apellidos' => $fullName,
            'nombres' => $firstNames,
            'apellidos' => $lastNames,
            'nacionalidad' => $this->matchFirst($text, [
                '/\b(ECUATORIANA|ECUATORIANO|COLOMBIANA|COLOMBIANO|VENEZOLANA|VENEZOLANO)\b/',
                '/NACIONALIDAD\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ]{4,20})\b/',
            ]),
            'alerta_cedula' => isset($ids[0]) ? null : 'No se detectó un número de cédula ecuatoriana válido en el texto extraído.',
        ];
    }

    private function extractVoting(string $text): array
    {
        $latestYear = (string) config('opening.latest_election_year', '2025');
        $hasVotingWords = str_contains($text, 'VOTACION') || str_contains($text, 'VOTACIÓN') || str_contains($text, 'ELECCION') || str_contains($text, 'ELECCIÓN');
        $hasLatestYear = str_contains($text, $latestYear);

        return [
            'papeleta_detectada' => $hasVotingWords,
            'proceso_electoral' => $this->matchFirst($text, [
                '/(ELECCIONES? [A-ZÁÉÍÓÚÑ ]{3,60}\d{4})/',
                '/(REFER[ÉE]NDUM [A-ZÁÉÍÓÚÑ ]{0,60}\d{4})/',
                '/(CONSULTA POPULAR [A-ZÁÉÍÓÚÑ ]{0,60}\d{4})/',
            ]),
            'ultima_eleccion_configurada' => $latestYear,
            'corresponde_ultima_eleccion' => $hasLatestYear,
            'alerta_papeleta' => $hasVotingWords && $hasLatestYear
                ? null
                : "Confirmar manualmente que la papeleta corresponda a la última elección configurada ({$latestYear}).",
        ];
    }

    private function extractUtilityBill(string $text): array
    {
        return [
            'direccion' => $this->matchFirst($text, [
                '/UNIDAD\s+DE\s+LECTURA\s+\S+\s+(.{12,220}?)\s+1\.\s*INFORMACI[ÓO]N/isu',
                '/((?:VIA|AV\.?|CALLE|CDLA\.?|BARRIO|RECINTO|CIUDADELA)\s+[A-ZÁÉÍÓÚÑ0-9 .\/-]{12,180}(?:LAS NAVES|GUARANDA|GUAYAQUIL|QUITO|AMBATO|RIOBAMBA|CUENCA|MILAGRO|BABAHOYO)[A-ZÁÉÍÓÚÑ0-9 .\/-]*)/si',
                '/(?:UNIDAD|IJNIDAD)\s+DE\s+LECTURA\s+[A-Z0-9]+\s+(.+?)\s+1\.\s*INFORMACI[ÓO]N/si',
                '/DIRECCI[ÓO]N\s+SERVICIO\s+(.+?)\s+CONCEPTO/si',
                '/DIRECCI[ÓO]N\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ0-9 #.\-]{8,120})/',
                '/DOMICILIO\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ0-9 #.\-]{8,120})/',
            ], clean: true),
            'titular' => $this->matchFirst($text, [
                '/NOMBRE\s+CLIENTE\s+(?:C[ÉE]DULA\s+)?(?:DIRECCI[ÓO]N\s+DEL\s+SERVICIO\s+)?(?:[A-ZÁÉÍÓÚÑ ]{5,80}\s+)?\d{8,13}\s+([A-ZÁÉÍÓÚÑ ]{8,80})/',
                '/(?:CLIENTE|TITULAR|NOMBRE)\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ ]{8,80})/',
            ], clean: true),
            'fecha_emision' => $this->matchFirst($text, [
                '/(?:FECHA DE EMISI[ÓO]N|EMISI[ÓO]N)\s*[:\-]?\s*([0-9]{2}[\/\-][0-9]{2}[\/\-][0-9]{4})/',
                '/([0-9]{2}[\/\-][0-9]{2}[\/\-][0-9]{4})/',
            ]),
        ];
    }

    private function extractRuc(string $text): array
    {
        return [
            'ruc' => $this->matchFirst($text, ['/(\d{13})/']),
            'razon_social' => $this->matchFirst($text, [
                '/RAZ[ÓO]N SOCIAL\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ0-9 .,&\-]{5,120})/',
                '/CONTRIBUYENTE\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ0-9 .,&\-]{5,120})/',
            ]),
        ];
    }

    private function matchFirst(string $text, array $patterns, bool $clean = false): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $value = trim(preg_replace('/\s+/', ' ', $matches[1] ?? $matches[0]));
                return $clean ? $this->cleanExtractedText($value) : $value;
            }
        }

        return null;
    }

    private function cleanExtractedText(string $value): string
    {
        $value = preg_replace('/\s+1\.\s*INFORMACI[ÓO]N.*$/iu', '', $value) ?? $value;
        $value = preg_replace('/\b(CONCEPTO|VALOR|RESUMEN DE VALORES|SERVICIO EL[ÉE]CTRICO|ALUMBRADO P[ÚU]BLICO)\b.*$/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value, " \t\n\r\0\x0B-/:");
    }

    private function cleanPersonName(?string $name): ?string
    {
        if (!$name) {
            return null;
        }

        $name = preg_replace('/\b(PRIMERA|SEGUNDA|VUELTA|ELECCIONES|GENERALES|CERTIFICADO|VOTACION|VOTACIÓN)\b/u', ' ', $name) ?? $name;
        $name = str_replace(['ESCPBAR', 'BOSOUEZ', 'BOSQUEZ'], ['ESCOBAR', 'BOSQUEZ', 'BOSQUEZ'], $name);
        $name = preg_replace('/\b4(?=[A-ZÁÉÍÓÚÑ])/u', 'A', $name) ?? $name;
        $name = preg_replace('/\b0(?=[A-ZÁÉÍÓÚÑ])/u', 'O', $name) ?? $name;
        $name = preg_replace('/[^A-ZÁÉÍÓÚÑ ]/u', ' ', $name) ?? $name;
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        $name = preg_replace('/(?:^|\s)[A-ZÁÉÍÓÚÑ](?=\s|$)/u', ' ', $name) ?? $name;
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;

        $name = trim($name);

        return $this->isPlausiblePersonName($name) ? $name : null;
    }

    private function extractPersonName(string $text): ?string
    {
        $patterns = [
            '/(?:APELLIDOS\s+NOMBRES|NOMBRES\s+APELLIDOS)\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ\s]{8,100}?)(?=\s+LUGAR DE NACIMIENTO\b)/si',
            '/(?:APELLIDOS Y NOMBRES|NOMBRES Y APELLIDOS)\s*[:\-]?\s*.{0,6}?([A-ZÁÉÍÓÚÑ0-9\'\s]{8,100}?)(?=\s+(?:NACIONALIDAD|SEXO|LUGAR|FECHA|PROVIN|C[ÉE]DULA)\b)/si',
            // Varias papeletas ubican el número y el nombre completo al final.
            '/\b\d{10}\b\s+([A-ZÁÉÍÓÚÑ\s]{8,100}?)\s*(?:---PAGE---)?\s*$/si',
            // En los archivos combinados, la papeleta suele conservar mejor el nombre completo.
            '/CERTIFICADO\s+DE\s+VOTACI[ÓO]N\s+([A-ZÁÉÍÓÚÑ\s]{8,100}?)\s+PROVINCIA\b/si',
            '/CERTIFICADO DE VOTACI[ÓO]N.*?\b\d{4}\b\s+([A-ZÁÉÍÓÚÑ\s]{8,100}?)\s+PROVINCIA\b/si',
            '/(?:REFER[ÉE]NDUM|CONSULTA POPULAR|ELECCIONES).*?\b\d{4}\b\s+([A-ZÁÉÍÓÚÑ\s]{8,100}?)\s+PROVINCIA\b/si',
            '/(?:PRIMERA|SEGUNDA)\s+VUELTA\s+([A-ZÁÉÍÓÚÑ0-9\'\s]{8,100}?)(?=\s+APELLIDOS Y NOMBRES DEL PADRE\b)/si',
            '/NOMBRE DEL TITULAR\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ\s]{8,100}?)(?=\s+(?:C[ÉE]DULA|CORREO|TEL[ÉE]FONO|FIRMA|FECHA)\b)/si',
            '/YO[,\s]+([A-ZÁÉÍÓÚÑ\s]{8,100}?)(?=\s*,?\s*PORTADOR(?:A)?\b)/si',
            '/CERTIFICADO DE VOTACI[ÓO]N.*?(?:PRIMERA|SEGUNDA)\s+VUELTA\s+([A-ZÁÉÍÓÚÑ\s]{8,100}?)\s+PROVIN[A-ZÁÉÍÓÚÑ]{0,6}\b/si',
            '/(?:PRIMERA|SEGUNDA)\s+VUELTA\s+([A-ZÁÉÍÓÚÑ\s]{8,100}?)\s+PROVIN[A-ZÁÉÍÓÚÑ]{0,6}\b/si',
            '/(?:CIUDADANO|CIUDADANA)\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ\s]{8,100}?)(?=\s+(?:NACIONALIDAD|SEXO|LUGAR|FECHA|PROVIN|C[ÉE]DULA)\b)/si',
        ];

        foreach ($patterns as $pattern) {
            if (!preg_match($pattern, $text, $matches)) {
                continue;
            }

            $candidate = $this->cleanPersonName($matches[1] ?? null);
            if ($candidate) {
                return $candidate;
            }
        }

        return null;
    }

    private function extractPersonNameParts(string $text): array
    {
        $fullName = $this->extractPersonName($text);
        if ($fullName) {
            [$lastNames, $firstNames] = $this->splitEcuadorianName($fullName);

            return [$fullName, $lastNames, $firstNames];
        }

        // Algunas cédulas nuevas imprimen APELLIDOS y NOMBRES como campos separados.
        $patterns = [
            '/\bAPELLIDOS\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ\s]{2,70}?)\s+NOMBRES\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ\s]{2,70}?)(?=\s+NACIONALIDAD\b)/si',
            '/\bAPELLIDO(?:S)?\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ\s]{2,70}?)\s+NOMBRE(?:S)?\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ\s]{2,70}?)(?=\s+(?:NACIONALIDAD|SEXO|FECHA)\b)/si',
        ];

        foreach ($patterns as $pattern) {
            if (!preg_match($pattern, $text, $matches)) {
                continue;
            }

            $lastNames = $this->cleanNamePart($matches[1] ?? null);
            $firstNames = $this->cleanNamePart($matches[2] ?? null);
            $candidate = trim($lastNames.' '.$firstNames);

            if ($lastNames && $firstNames && $this->isPlausiblePersonName($candidate)) {
                return [$candidate, $lastNames, $firstNames];
            }
        }

        return [null, null, null];
    }

    private function cleanNamePart(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $value = preg_replace('/[^A-ZÁÉÍÓÚÑ ]/u', ' ', $value) ?? $value;
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        return $value !== '' ? $value : null;
    }

    private function isPlausiblePersonName(string $name): bool
    {
        if ($name === '' || mb_strlen($name) < 7 || mb_strlen($name) > 100) {
            return false;
        }

        $blockedWords = [
            'CEDULA', 'CÉDULA', 'IDENTIDAD', 'REPUBLICA', 'REPÚBLICA', 'ECUADOR',
            'APELLIDOS', 'NOMBRES', 'NOMBRE', 'NACIONALIDAD', 'CIUDADANIA', 'CIUDADANÍA',
            'CERTIFICADO', 'VOTACION', 'VOTACIÓN', 'PROVINCIA', 'CANTON', 'CANTÓN',
            'PARROQUIA', 'FECHA', 'ELECCIONES', 'PADRE', 'MADRE', 'CONDICION', 'CONDICIÓN',
            'CONYUGE', 'CÓNYUGE', 'REPRESENTANTE',
        ];

        foreach ($blockedWords as $word) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/u', $name)) {
                return false;
            }
        }

        $tokens = array_values(array_filter(explode(' ', $name), fn (string $token) => mb_strlen($token) >= 2));

        return count($tokens) >= 2 && count($tokens) <= 7;
    }

    private function splitEcuadorianName(?string $fullName): array
    {
        if (!$fullName) {
            return [null, null];
        }

        $parts = array_values(array_filter(explode(' ', trim($fullName))));
        if (count($parts) < 2) {
            return [null, $fullName];
        }

        $lastNameCount = count($parts) >= 4 ? 2 : 1;

        return [
            implode(' ', array_slice($parts, 0, $lastNameCount)),
            implode(' ', array_slice($parts, $lastNameCount)),
        ];
    }

    private function validEcuadorianIds(string $text): array
    {
        preg_match_all('/\b\d{10}\b/', $text, $matches);

        return array_values(array_filter(array_unique($matches[0] ?? []), fn (string $id) => $this->validEcuadorianId($id)));
    }

    private function validEcuadorianId(string $id): bool
    {
        $province = (int) substr($id, 0, 2);
        if ($province < 1 || $province > 24) {
            return false;
        }

        $digits = array_map('intval', str_split($id));
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $value = $digits[$i] * ($i % 2 === 0 ? 2 : 1);
            $sum += $value > 9 ? $value - 9 : $value;
        }

        return ((10 - ($sum % 10)) % 10) === $digits[9];
    }
}

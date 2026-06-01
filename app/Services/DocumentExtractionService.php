<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;
use Throwable;

class DocumentExtractionService
{
    public function extract(string $slug, UploadedFile $file): array
    {
        $text = $this->extractText($file);
        $normalized = $this->normalize($text);
        $base = [
            'archivo' => $file->getClientOriginalName(),
            'fuente_texto' => $text !== '' ? 'Texto embebido en el PDF/archivo' : 'Sin texto detectable',
        ];

        if ($text === '') {
            return $base + [
                'alerta' => 'El archivo parece ser un escaneo como imagen. Para extraer nombres/direccion automaticamente se requiere OCR; mientras tanto debe validarse manualmente.',
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

    private function extractText(UploadedFile $file): string
    {
        $text = '';

        if ($file->getMimeType() === 'application/pdf') {
            try {
                $text = trim((new Parser())->parseFile($file->getRealPath())->getText());
            } catch (Throwable) {
                $text = '';
            }
        }

        $ocrText = $this->extractTextWithWindowsOcr($file);
        if (mb_strlen($ocrText) > 30) {
            $text = trim($text."\n".$ocrText);
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
        $fullName = $this->matchFirst($text, [
            '/CERTIFICADO DE VOTACI[ÓO]N.*?(?:\d{4}|VUELTA)\s+([A-ZÁÉÍÓÚÑ ]{8,80})\s+PROVIN/si',
            '/(?:SEGUNDA VUELTA|PRIMERA VUELTA)\s+([A-ZÁÉÍÓÚÑ ]{8,80})\s+PROVIN/si',
            '/(?:APELLIDOS Y NOMBRES|NOMBRES Y APELLIDOS|NOMBRE)\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ ]{8,80})/',
            '/(?:CIUDADANO|CIUDADANA)\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ ]{8,80})/',
        ]);

        return [
            'cedula' => $ids[0] ?? null,
            'cedula_valida' => isset($ids[0]),
            'nombres_apellidos' => $this->cleanPersonName($fullName),
            'nacionalidad' => $this->matchFirst($text, [
                '/NACIONALIDAD\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ ]{4,40})/',
                '/\b(ECUATORIANA|ECUATORIANO|COLOMBIANA|COLOMBIANO|VENEZOLANA|VENEZOLANO)\b/',
            ]),
            'alerta_cedula' => isset($ids[0]) ? null : 'No se detecto un numero de cedula ecuatoriana valido en el texto extraido.',
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
                : "Confirmar manualmente que la papeleta corresponda a la ultima eleccion configurada ({$latestYear}).",
        ];
    }

    private function extractUtilityBill(string $text): array
    {
        return [
            'direccion' => $this->matchFirst($text, [
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
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;

        return trim($name) ?: null;
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

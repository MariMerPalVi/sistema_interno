<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;
use Throwable;

class DocumentExtractionService
{
    private const ID_NAME_CONFIDENCE_MIN = 72;

    private const NAME_BLOCKED_WORDS = [
        'CIUDADANA', 'CIUDADANO', 'CIUDADANIA', 'CIUDADANÍA', 'CEDULA', 'CÉDULA',
        'IDENTIDAD', 'REPUBLICA', 'REPÚBLICA', 'ECUADOR', 'REGISTRO', 'CIVIL',
        'DIRECCION', 'DIRECCIÓN', 'GENERAL', 'CERTIFICADO', 'VOTACION', 'VOTACIÓN',
        'CONSEJO', 'NACIONAL', 'ELECTORAL', 'PROVINCIA', 'PROVINGA', 'CANTON',
        'CANTÓN', 'PARROQUIA', 'ZONA', 'JUNTA', 'PADRE', 'MADRE', 'LUGAR',
        'NACIMIENTO', 'FECHA', 'NACIONALIDAD', 'SEXO', 'ESTADO', 'SOLTERO',
        'CASADO', 'CASADA', 'DIVORCIADO', 'DIVORCIADA', 'VIUDO', 'VIUDA',
        'DOCUMENTO', 'ACREDITA', 'SUFRAGO', 'SUFRAGÓ', 'DIRECTOR', 'PRESIDENTE',
        'PRESIDENTA', 'INSTRUCCION', 'INSTRUCCIÓN', 'PROFESION', 'PROFESIÓN',
    ];

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
                if ($forceOcr) {
                    return trim($ocrText);
                }

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
            $this->warningLog('Windows OCR failed', ['error' => $error]);
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

    private function debugLog(string $message, array $context = []): void
    {
        if (Log::getFacadeRoot()) {
            Log::debug($message, $context);
        }
    }

    private function warningLog(string $message, array $context = []): void
    {
        if (Log::getFacadeRoot()) {
            Log::warning($message, $context);
        }
    }

    private function extractIdAndVoting(string $text): array
    {
        return $this->extractId($text) + $this->extractVoting($text);
    }

    private function extractId(string $text): array
    {
        $ids = $this->validEcuadorianIds($text);
        $identity = $this->extractIdentityData($text, $ids[0] ?? null);

        return [
            'cedula' => $ids[0] ?? null,
            'cedula_valida' => isset($ids[0]),
            'nombres_apellidos' => $identity['nombres_apellidos'],
            'nombres' => $identity['nombres'],
            'apellidos' => $identity['apellidos'],
            'tipo_documento_detectado' => $identity['tipo_documento_detectado'],
            'fuente_usada' => $identity['fuente_usada'],
            'confianza' => $identity['confianza'],
            'observacion_extraccion' => $identity['observacion'],
            'requiere_revision_manual_datos' => $identity['requiere_revision_manual'],
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
            'direccion' => $this->extractUtilityAddress($text) ?? $this->matchFirst($text, [
                '/UNIDAD\s+DE\s+LECTURA\s+\S+\s+(.{12,220}?)\s+1\.\s*INFORMACI[ÓO]N/isu',
                '/((?:VIA|AV\.?|CALLE|CDLA\.?|BARRIO|RECINTO|CIUDADELA)\s+[A-ZÁÉÍÓÚÑ0-9 .\/-]{12,180}(?:LAS NAVES|GUARANDA|GUAYAQUIL|QUITO|AMBATO|RIOBAMBA|CUENCA|MILAGRO|BABAHOYO)[A-ZÁÉÍÓÚÑ0-9 .\/-]*)/si',
                '/(?:UNIDAD|IJNIDAD)\s+DE\s+LECTURA\s+[A-Z0-9]+\s+(.+?)\s+1\.\s*INFORMACI[ÓO]N/si',
                '/DIRECCI[ÓO]N\s+(?:DEL\s+)?SERVICIO\s+(.+?)\s+1\.\s*INFORMACI[ÓO]N/si',
                '/DIRECCI[ÓO]N\s*[:\-]?\s*((?:VIA|VÍA|AV\.?|AVENIDA|CALLE|CDLA\.?|BARRIO|RECINTO|CIUDADELA|SECTOR|KM\.?|S\/N)[A-ZÁÉÍÓÚÑ0-9 #.\/\-]{8,160})/',
                '/DOMICILIO\s*[:\-]?\s*((?:VIA|VÍA|AV\.?|AVENIDA|CALLE|CDLA\.?|BARRIO|RECINTO|CIUDADELA|SECTOR|KM\.?|S\/N)[A-ZÁÉÍÓÚÑ0-9 #.\/\-]{8,160})/',
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

    private function extractUtilityAddress(string $text): ?string
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\n+/u', $text) ?: [])));
        $addressLabels = [];

        foreach ($lines as $index => $line) {
            if (preg_match('/DIRECCI[ÓO]N(?:\s+DEL)?\s+SERVICIO|DIRECCI[ÓO]N\s+DOMICILIARIA|DOMICILIO|GEOC[ÓO]DIGO/u', $line)) {
                $addressLabels[] = $index;
            }
        }

        foreach ($addressLabels as $labelIndex) {
            for ($offset = 1; $offset <= 20 && isset($lines[$labelIndex + $offset]); $offset++) {
                $candidate = $this->cleanExtractedText($lines[$labelIndex + $offset]);
                if (!$this->isPlausibleAddressLine($candidate)) {
                    continue;
                }

                return $this->joinAddressContinuation($candidate, $lines, $labelIndex + $offset);
            }
        }

        $candidates = [];
        foreach ($lines as $index => $line) {
            $candidate = $this->cleanExtractedText($line);
            if (!$this->isPlausibleAddressLine($candidate)) {
                continue;
            }

            if (preg_match('/\b(MATRIZ|SUCURSAL|EMPRESA EL[ÉE]CTRICA|CNEL)\b/u', $candidate)) {
                continue;
            }

            $distance = $addressLabels === []
                ? 20
                : min(array_map(fn (int $label) => abs($index - $label), $addressLabels));
            $score = max(0, 14 - $distance);
            $score += preg_match('/\b(VIA|VÍA|AV\.?|AVENIDA|CALLE|CDLA\.?|CIUDADELA|URB\.?|URBANIZACI[ÓO]N|COOP\.?|COOPERATIVA|BARRIO|RECINTO|SECTOR|KM\.?|LOTE|MANZANA|MZ\.?|S\/N)\b/u', $candidate) ? 8 : 0;
            $score += preg_match('/\b(JUNTO|FRENTE|ENTRE|ESQUINA)\b/u', $candidate) ? 3 : 0;

            if (isset($lines[$index + 1])) {
                $joined = $this->joinAddressContinuation($candidate, $lines, $index);
                if ($joined !== $candidate) {
                    $candidate = $joined;
                    $score += 2;
                }
            }

            $candidates[$candidate] = max($candidates[$candidate] ?? 0, $score);
        }

        if ($candidates === []) {
            return null;
        }

        arsort($candidates);

        return array_key_first($candidates);
    }

    private function joinAddressContinuation(string $candidate, array $lines, int $index): string
    {
        if (!isset($lines[$index + 1])) {
            return $candidate;
        }

        $continuation = $this->cleanExtractedText($lines[$index + 1]);
        if ($this->isPlausibleAddressLine($continuation)
            && !preg_match('/^\d+[\d\s.,-]*$/u', $continuation)) {
            return trim($candidate.' '.$continuation);
        }

        return $candidate;
    }

    private function isPlausibleAddressLine(string $line): bool
    {
        if (mb_strlen($line) < 10 || mb_strlen($line) > 220) {
            return false;
        }

        if (preg_match('/\b(CUENTA|CONTRATO|C[ÉE]DULA|MEDIDOR|LECTURA|TARIFA|CONSUMO|FACTURA|RUC|FECHA|VALOR|TOTAL|OTAL|GEOC[ÓO]DIGO|SECTOR EL[ÉE]CTRICO|RECAUDACI[ÓO]N|INGRESOS|RESUMEN|CONTRIBUCI[ÓO]N|BOMBEROS|CONSUMIDOR|CALIFICADO|DISCAPACIDAD|ADULTO|MAYOR|ARCONEL)\b/u', $line)) {
            return false;
        }

        if (!preg_match('/[A-ZÁÉÍÓÚÑ]{3}/u', $line)) {
            return false;
        }

        $tokens = array_values(array_filter(preg_split('/\s+/u', $line) ?: []));
        if (count($tokens) < 3) {
            return false;
        }

        return (bool) preg_match('/\b(VIA|VÍA|AV\.?|AVENIDA|CALLE|CDLA\.?|CIUDADELA|URB\.?|URBANIZACI[ÓO]N|COOP\.?|COOPERATIVA|BARRIO|RECINTO|SECTOR|KM\.?|LOTE|MANZANA|MZ\.?|S\/N|JUNTO|FRENTE|ENTRE|ESQUINA)\b/u', $line);
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

        $name = mb_strtoupper($name, 'UTF-8');
        $name = preg_replace('/\b(PRIMERA|SEGUNDA|VUELTA|ELECCIONES|GENERALES|CERTIFICADO|VOTACION|VOTACIÓN)\b/u', ' ', $name) ?? $name;
        $name = str_replace(
            ['ESCPBAR', 'ESC0BAR', 'BOSOUEZ', 'BOSQUEZ'],
            ['ESCOBAR', 'ESCOBAR', 'BOSQUEZ', 'BOSQUEZ'],
            $name
        );
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
            ['/\b(?:APELLIDOS\s+(?:Y\s+)?NOMBRES|NOMBRES\s+(?:Y\s+)?APELLIDOS)\s*[:\-]?\s*.{0,6}?([A-ZÁÉÍÓÚÑ0-9\'\s]{8,100}?)(?=\s+(?:NACIONALIDAD|SEXO|LUGAR|FECHA|PROVIN|C[ÉE]DULA)\b)/si', 12],
            // Varias papeletas ubican el número y el nombre completo al final.
            ['/\b\d{10}\b\s+([A-ZÁÉÍÓÚÑ\s]{8,100}?)\s*(?:---PAGE---)?\s*$/si', 10],
            // En los archivos combinados, la papeleta suele conservar mejor el nombre completo.
            ['/CERTIFICADO.{0,30}VOTACI.{0,3}N\s+([A-ZÁÉÍÓÚÑ\s]{8,100}?)\s+PROVINCIA\b/si', 12],
            ['/CERTIFICADO\s+DE\s+VOTACI[ÓO]N\s+([A-ZÁÉÍÓÚÑ\s]{8,100}?)\s+PROVINCIA\b/si', 11],
            ['/CERTIFICADO DE VOTACI[ÓO]N.*?\b\d{4}\b\s+([A-ZÁÉÍÓÚÑ\s]{8,100}?)\s+PROVINCIA\b/si', 10],
            ['/(?:REFER[ÉE]NDUM|CONSULTA POPULAR|ELECCIONES).*?\b\d{4}\b\s+([A-ZÁÉÍÓÚÑ\s]{8,100}?)\s+PROVINCIA\b/si', 9],
            ['/(?:PRIMERA|SEGUNDA)\s+VUELTA\s+([A-ZÁÉÍÓÚÑ0-9\'\s]{8,100}?)(?=\s+APELLIDOS Y NOMBRES DEL PADRE\b)/si', 8],
            ['/NOMBRE DEL TITULAR\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ\s]{8,100}?)(?=\s+(?:C[ÉE]DULA|CORREO|TEL[ÉE]FONO|FIRMA|FECHA)\b)/si', 12],
            ['/(?:AL\s+)?CIUDADAN[OA](?:\/A)?\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ\s]{8,100}?)(?=\s*,?\s*PORTADOR(?:A|\/A)?\b)/si', 16],
            ['/YO[,\s]+([A-ZÁÉÍÓÚÑ\s]{8,100}?)(?=\s*,?\s*PORTADOR(?:A)?\b)/si', 9],
            ['/(?:CIUDADANO|CIUDADANA)\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ\s]{8,100}?)(?=\s+(?:NACIONALIDAD|SEXO|LUGAR|FECHA|PROVIN|C[ÉE]DULA)\b)/si', 7],
        ];

        $candidates = [];
        foreach ($patterns as [$pattern, $weight]) {
            if (!preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                $this->addNameCandidate($candidates, $match[1] ?? null, $weight);
            }
        }

        $lines = array_values(array_filter(array_map('trim', preg_split('/\n+/u', $text) ?: [])));
        foreach ($lines as $index => $line) {
            if (preg_match('/CERTIFICADO.{0,20}VOTACI.{0,3}N/u', $line)) {
                $nameLines = [];
                for ($offset = 1; $offset <= 3 && isset($lines[$index + $offset]); $offset++) {
                    $nextLine = $lines[$index + $offset];
                    if ($this->isDocumentMetadataLine($nextLine)) {
                        break;
                    }

                    $part = $this->cleanNamePart($nextLine);
                    if (!$part) {
                        break;
                    }

                    $nameLines[] = $part;
                    $joined = implode(' ', $nameLines);
                    $this->addNameCandidate($candidates, $joined, $offset === 1 ? 13 : 15);

                    if ($this->nameTokenCount($joined) >= 4) {
                        break;
                    }
                }
            }

            $withoutLabel = preg_replace('/^.*?\b(?:APELLIDOS\s+(?:Y\s+)?NOMBRES|NOMBRES\s+(?:Y\s+)?APELLIDOS)\b\s*[:\-]?/u', '', $line);
            if ($withoutLabel !== $line) {
                $withoutLabel = preg_split('/\b(?:NACIONALIDAD|SEXO|LUGAR|FECHA|PROVINCIA|C[ÉE]DULA)\b/u', $withoutLabel, 2)[0] ?? $withoutLabel;
                $this->addNameCandidate($candidates, $withoutLabel, 13);
            }

            if ($index > 0
                && preg_match('/\b(?:APELLIDOS\s+(?:Y\s+)?NOMBRES|NOMBRES\s+(?:Y\s+)?APELLIDOS)\b/u', $lines[$index - 1])
                && !preg_match('/\b(PADRE|MADRE|C[ÓO]NYUGE|REPRESENTANTE)\b/u', $lines[$index - 1])) {
                $this->addNameCandidate($candidates, $line, 12);
            }

            if ($index + 1 < count($lines) && preg_match('/\bPROVINCIA\b/u', $lines[$index + 1])) {
                $this->addNameCandidate($candidates, $line, 7);
            }

            if ($index > 0 && preg_match('/\b\d{10}\b/u', $lines[$index - 1])) {
                $this->addNameCandidate($candidates, $line, 6);
            }
        }

        if ($candidates === []) {
            return null;
        }

        arsort($candidates);

        return array_key_first($candidates);
    }

    private function isDocumentMetadataLine(string $line): bool
    {
        if (preg_match('/\d/u', $line)) {
            return true;
        }

        if (preg_match('/\b(PROVINCIA|PROVINGA|CIRCUNSCRIPCI[ÓO]N|CANT[ÓO]N|PARROQUIA|ZONA|JUNTA|MASCULINO|FEMENINO|CIUDADAN[AO]|DOCUMENTO|ACREDITA|SUFRAG[ÓO]|DIRECTOR|PRESIDENT[AE])\b/u', $line)) {
            return true;
        }

        $normalized = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], trim($line));
        $provinces = [
            'AZUAY', 'BOLIVAR', 'CANAR', 'CARCHI', 'CHIMBORAZO', 'COTOPAXI',
            'EL ORO', 'ESMERALDAS', 'GALAPAGOS', 'GUAYAS', 'IMBABURA', 'LOJA',
            'LOS RIOS', 'MANABI', 'MORONA SANTIAGO', 'NAPO', 'ORELLANA', 'PASTAZA',
            'PICHINCHA', 'SANTA ELENA', 'SANTO DOMINGO', 'SUCUMBIOS', 'TUNGURAHUA',
            'ZAMORA CHINCHIPE', 'POLIVAR',
        ];

        return in_array($normalized, $provinces, true);
    }

    private function addNameCandidate(array &$candidates, ?string $value, int $weight): void
    {
        $candidate = $this->cleanPersonName($value);
        if (!$candidate) {
            return;
        }

        $tokens = array_values(array_filter(explode(' ', $candidate)));
        $tokenScore = match (count($tokens)) {
            4 => 6,
            3 => 4,
            2, 5 => 2,
            default => 0,
        };

        $candidates[$candidate] = max($candidates[$candidate] ?? 0, $weight + $tokenScore);
    }

    private function extractIdentityData(string $text, ?string $mainId): array
    {
        $documentType = $this->detectIdentityDocumentType($text);
        $candidates = [];

        $this->pushIdentityCandidate(
            $candidates,
            $this->extractSeparatedLineNameParts($text),
            'cedula',
            96,
            'Campos separados APELLIDOS / NOMBRES'
        );

        $this->pushIdentityCandidate(
            $candidates,
            $this->extractCombinedIdNameParts($text),
            'cedula',
            90,
            'Campo APELLIDOS Y NOMBRES de la cedula'
        );

        $this->pushIdentityCandidate(
            $candidates,
            $this->extractMrzNameParts($text),
            'mrz',
            84,
            'Zona MRZ de cedula'
        );

        $votingCandidate = $this->extractVotingCertificateNameParts($text, $mainId);
        if ($votingCandidate) {
            $this->pushIdentityCandidate(
                $candidates,
                $votingCandidate,
                'certificado_votacion',
                $votingCandidate['cedula_coincide'] ? 78 : 70,
                $votingCandidate['cedula_coincide']
                    ? 'Certificado de votacion con cedula coincidente'
                    : 'Certificado de votacion sin cedula coincidente verificable'
            );
        }

        $genericName = $this->extractPersonName($text);
        if ($genericName) {
            [$lastNames, $firstNames] = $this->splitEcuadorianName($genericName);
            $this->pushIdentityCandidate(
                $candidates,
                [$genericName, $lastNames, $firstNames],
                'revision_manual',
                54,
                'Extractor generico de baja confianza'
            );
        }

        usort($candidates, fn (array $a, array $b) => $b['confianza'] <=> $a['confianza']);
        $selected = $candidates[0] ?? null;

        $this->debugLog('OCR identity extraction', [
            'tipo_documento_detectado' => $documentType,
            'cedula_detectada' => $mainId,
            'candidatos' => array_map(fn (array $candidate) => [
                'fuente' => $candidate['fuente_usada'],
                'confianza' => $candidate['confianza'],
                'nombre' => $candidate['nombres_apellidos'],
                'motivo' => $candidate['motivo'],
                'lineas' => $candidate['lineas'] ?? [],
            ], $candidates),
            'seleccionado' => $selected ? [
                'fuente' => $selected['fuente_usada'],
                'confianza' => $selected['confianza'],
                'nombre' => $selected['nombres_apellidos'],
            ] : null,
        ]);

        if (!$selected || $selected['confianza'] < self::ID_NAME_CONFIDENCE_MIN) {
            return [
                'nombres_apellidos' => null,
                'nombres' => null,
                'apellidos' => null,
                'tipo_documento_detectado' => $documentType,
                'fuente_usada' => 'revision_manual',
                'confianza' => $selected['confianza'] ?? 0,
                'observacion' => 'No se reconocieron nombres y apellidos con confianza suficiente. Revise el documento manualmente.',
                'requiere_revision_manual' => true,
            ];
        }

        return [
            'nombres_apellidos' => $selected['nombres_apellidos'],
            'nombres' => $selected['nombres'],
            'apellidos' => $selected['apellidos'],
            'tipo_documento_detectado' => $documentType,
            'fuente_usada' => $selected['fuente_usada'],
            'confianza' => $selected['confianza'],
            'observacion' => $selected['observacion'] ?? null,
            'requiere_revision_manual' => false,
        ];
    }

    private function pushIdentityCandidate(array &$candidates, null|array $parts, string $source, int $confidence, string $reason): void
    {
        if (!$parts) {
            return;
        }

        $fullName = $this->cleanPersonName($parts['full_name'] ?? $parts[0] ?? null);
        $lastNames = $this->cleanNamePart($parts['last_names'] ?? $parts[1] ?? null);
        $firstNames = $this->cleanNamePart($parts['first_names'] ?? $parts[2] ?? null);

        if (!$fullName || !$lastNames || !$firstNames || !$this->isPlausiblePersonName($fullName)) {
            $this->debugLog('OCR identity candidate discarded', [
                'fuente' => $source,
                'motivo' => $reason,
                'valor' => $parts,
            ]);
            return;
        }

        $confidence += match ($this->nameTokenCount($fullName)) {
            4 => 4,
            3, 5 => 2,
            default => 0,
        };

        $candidates[] = [
            'nombres_apellidos' => $fullName,
            'apellidos' => $lastNames,
            'nombres' => $firstNames,
            'fuente_usada' => $source,
            'confianza' => min(100, $confidence),
            'motivo' => $reason,
            'lineas' => $parts['lines'] ?? [],
        ];
    }

    private function detectIdentityDocumentType(string $text): string
    {
        $normalized = $this->normalizeNameLabel($text);
        $hasId = preg_match('/\b(CEDULA|IDENTIDAD|CIUDADANIA|REGISTRO CIVIL)\b/u', $normalized);
        $hasVoting = preg_match('/CERTIFICADO.{0,40}VOTACI/u', $normalized);
        $hasMrz = preg_match('/[A-Z<]{5,}<<[A-Z<]{3,}/u', str_replace(' ', '', $text));
        $hasReverse = preg_match('/APELLIDOS? Y NOMBRES? DEL PADRE|APELLIDOS? Y NOMBRES? DE LA MADRE/u', $normalized);
        $hasNewFields = preg_match('/\bAPELLIDOS\b.*\bNOMBRES\b|\bNOMBRES\b.*\bAPELLIDOS\b/u', $normalized);

        if ($hasId && $hasVoting) {
            return 'documento_mixto_cedula_certificado_votacion';
        }

        if ($hasVoting) {
            return 'certificado_votacion';
        }

        if ($hasReverse && !$hasNewFields) {
            return 'reverso_cedula';
        }

        if ($hasMrz || $hasNewFields) {
            return 'cedula_ecuatoriana_formato_nuevo';
        }

        if ($hasId) {
            return 'cedula_ecuatoriana_formato_anterior';
        }

        return 'formato_no_reconocido';
    }

    private function extractPersonNameParts(string $text): array
    {
        $mrzBlock = $this->extractMrzNameParts($text);
        if ($mrzBlock) {
            return $mrzBlock;
        }

        $lineFields = $this->extractSeparatedLineNameParts($text);
        if ($lineFields) {
            return $lineFields;
        }

        $votingBlock = $this->extractVotingCertificateNameParts($text, null);
        if ($votingBlock) {
            return $votingBlock;
        }

        $ownerBlock = $this->extractIdCardOwnerNameParts($text);
        if ($ownerBlock) {
            return $ownerBlock;
        }

        // Algunas cédulas nuevas imprimen APELLIDOS y NOMBRES como campos separados.
        $separatedFields = null;
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
                $separatedFields = [$candidate, $lastNames, $firstNames];
                break;
            }
        }

        $fullName = $this->extractPersonName($text);
        if ($fullName) {
            if ($separatedFields && $this->nameTokenCount($separatedFields[0]) >= $this->nameTokenCount($fullName)) {
                return $separatedFields;
            }

            [$lastNames, $firstNames] = $this->splitEcuadorianName($fullName);

            return [$fullName, $lastNames, $firstNames];
        }

        return $separatedFields ?? [null, null, null];
    }

    private function extractMrzNameParts(string $text): ?array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\n+/u', $text) ?: [])));

        foreach ($lines as $line) {
            $line = mb_strtoupper($line, 'UTF-8');
            $line = str_replace(['«', '‹', ' '], ['<', '<', ''], $line);

            if (!str_contains($line, '<<') || preg_match('/^I<|^ID|^ECU|\d/u', $line)) {
                continue;
            }

            if (!preg_match('/([A-ZÁÉÍÓÚÑ<]{5,})<<([A-ZÁÉÍÓÚÑ<]{3,})/', $line, $matches)) {
                continue;
            }

            $lastNames = $this->cleanNamePart(str_replace('<', ' ', trim($matches[1], '<')));
            $firstNames = $this->cleanNamePart(str_replace('<', ' ', trim($matches[2], '<')));
            $fullName = trim($lastNames.' '.$firstNames);

            if ($lastNames && $firstNames && $this->isPlausiblePersonName($fullName)) {
                return [$fullName, $lastNames, $firstNames];
            }
        }

        return null;
    }

    private function extractCombinedIdNameParts(string $text): ?array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\n+/u', $text) ?: [])));

        foreach ($lines as $index => $line) {
            $normalized = $this->normalizeNameLabel($line);

            if (!preg_match('/\bAPELLIDOS?\s+Y\s+NOMBRES?\b/u', $normalized)) {
                continue;
            }

            if (preg_match('/\b(PADRE|MADRE|CONYUGE|CÓNYUGE|REPRESENTANTE)\b/u', $normalized)) {
                $this->debugLog('OCR line discarded as parent/representative label', ['linea' => $line]);
                continue;
            }

            $parts = [];
            $rawLines = [];
            for ($offset = 1; $offset <= 5 && isset($lines[$index + $offset]); $offset++) {
                $current = $lines[$index + $offset];
                $currentNormalized = $this->normalizeNameLabel($current);

                if (preg_match('/\b(LUGAR|NACIMIENTO|NACIONALIDAD|SEXO|FECHA|ESTADO|CEDULA|IDENTIDAD|CIUDADANIA)\b/u', $currentNormalized)) {
                    break;
                }

                if ($this->isIdCardNoiseLine($currentNormalized)) {
                    $this->debugLog('OCR line discarded as metadata while reading combined name', ['linea' => $current]);
                    continue;
                }

                $part = $this->cleanNamePart($current);
                if (!$part || !$this->isPlausibleNameLine($part)) {
                    $this->debugLog('OCR line discarded as implausible name', ['linea' => $current, 'normalizada' => $part]);
                    continue;
                }

                $parts[] = $part;
                $rawLines[] = $current;

                if ($this->nameTokenCount(implode(' ', $parts)) >= 4 || count($parts) >= 3) {
                    break;
                }
            }

            $fullName = $this->cleanPersonName(implode(' ', $parts));
            if (!$fullName || !$this->isPlausiblePersonName($fullName)) {
                continue;
            }

            [$lastNames, $firstNames] = $this->splitEcuadorianName($fullName);

            return [
                'full_name' => $fullName,
                'last_names' => $lastNames,
                'first_names' => $firstNames,
                'lines' => $rawLines,
            ];
        }

        return null;
    }

    private function extractSeparatedLineNameParts(string $text): ?array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\n+/u', $text) ?: [])));

        foreach ($lines as $index => $line) {
            if (!preg_match('/\bAPELLIDOS?\b/u', $this->normalizeNameLabel($line))) {
                continue;
            }

            if (preg_match('/\b(PADRE|MADRE|CONYUGE|CÓNYUGE|REPRESENTANTE)\b/u', $this->normalizeNameLabel($line))) {
                continue;
            }

            $lastNames = $this->collectNameLinesAfterLabel($lines, $index, 'NOMBRES?', 2);
            if (!$lastNames) {
                continue;
            }

            $nameLabelIndex = null;
            for ($offset = 1; $offset <= 5 && isset($lines[$index + $offset]); $offset++) {
                if (preg_match('/\bNOMBRES?\b/u', $this->normalizeNameLabel($lines[$index + $offset]))) {
                    $nameLabelIndex = $index + $offset;
                    break;
                }
            }

            if ($nameLabelIndex === null) {
                continue;
            }

            $firstNames = $this->collectNameLinesAfterLabel($lines, $nameLabelIndex, 'NACIONALIDAD|SEXO|LUGAR|FECHA|CONDICION|CONDICIÓN', 2);
            $fullName = trim($lastNames.' '.$firstNames);

            if ($firstNames && $this->isPlausiblePersonName($fullName)) {
                return [$fullName, $lastNames, $firstNames];
            }
        }

        return null;
    }

    private function collectNameLinesAfterLabel(array $lines, int $labelIndex, string $stopPattern, int $maxLines): ?string
    {
        $parts = [];

        for ($offset = 1; $offset <= 6 && isset($lines[$labelIndex + $offset]); $offset++) {
            $current = $lines[$labelIndex + $offset];
            $normalized = $this->normalizeNameLabel($current);

            if (preg_match('/\b('.$stopPattern.')\b/u', $normalized)
                || preg_match('/\b(PADRE|MADRE|CONYUGE|CÓNYUGE|REPRESENTANTE|NACIONALIDAD|SEXO|LUGAR|FECHA|ESTADO|INSTRUCCION|INSTRUCCIÓN|PROFESION|PROFESIÓN)\b/u', $normalized)) {
                break;
            }

            if ($this->isIdCardNoiseLine($normalized)) {
                continue;
            }

            $part = $this->cleanNamePart($current);
            if (!$part || !$this->isPlausibleNameLine($part)) {
                continue;
            }

            $parts[] = $part;
            if (count($parts) >= $maxLines) {
                break;
            }
        }

        return $parts ? implode(' ', $parts) : null;
    }

    private function extractIdCardOwnerNameParts(string $text): ?array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\n+/u', $text) ?: [])));

        foreach ($lines as $index => $line) {
            if (!preg_match('/\b\d{9}[-\s]?\d\b/u', $line)) {
                continue;
            }

            $nameLines = [];
            for ($offset = 1; $offset <= 10 && isset($lines[$index + $offset]); $offset++) {
                $current = $lines[$index + $offset];
                $normalized = $this->normalizeNameLabel($current);

                if (preg_match('/(^|\b)(LUGAR|LUGARDE|NACIMIENTO|NACIONALIDAD|SEXO|INSTRUCCION|INSTRUCCIÓN|PROFESION|PROFESIÓN|OCUPACION|OCUPACIÓN|APELLIDOS?\s+Y\s+NOMBRES?\s+DEL|PADRE|MADRE|CONYUGE|CÓNYUGE|ESTADO\s+CIVIL|FECHA\s+DE\s+EXPIRACION|FECHA\s+DE\s+EXPIRACIÓN)/u', $normalized)) {
                    break;
                }

                if ($this->isIdCardNoiseLine($normalized)) {
                    continue;
                }

                $part = $this->cleanNamePart($current);
                if (!$part || !$this->isPlausibleNameLine($part)) {
                    continue;
                }

                $nameLines[] = $part;
                if (count($nameLines) >= 2) {
                    break;
                }
            }

            if (count($nameLines) >= 2) {
                $lastNames = $nameLines[0];
                $firstNames = implode(' ', array_slice($nameLines, 1));
                $fullName = trim($lastNames.' '.$firstNames);

                if ($this->isPlausiblePersonName($fullName)) {
                    return [$fullName, $lastNames, $firstNames];
                }
            }
        }

        return $this->extractVotingOwnerNameParts($lines);
    }

    private function extractVotingOwnerNameParts(array $lines): ?array
    {
        $blockedAfterCitizen = false;
        foreach ($lines as $index => $line) {
            $normalizedLine = $this->normalizeNameLabel($line);
            if (preg_match('/\bESTE\s+DOCUMENTO\s+ACREDITA\b/u', $normalizedLine)) {
                $blockedAfterCitizen = true;
            }

            if (!preg_match('/\bCIUDADAN[AO](?:\/O|\/A)?\b/u', $this->normalizeNameLabel($line))) {
                continue;
            }

            if ($blockedAfterCitizen) {
                continue;
            }

            $nameLines = [];
            for ($offset = 1; $offset <= 3 && isset($lines[$index + $offset]); $offset++) {
                $current = $lines[$index + $offset];
                if ($this->isDocumentMetadataLine($current)) {
                    break;
                }

                $part = $this->cleanNamePart($current);
                if (!$part || !$this->isPlausibleNameLine($part)) {
                    break;
                }

                $nameLines[] = $part;
            }

            if ($nameLines) {
                $fullName = trim(implode(' ', $nameLines));
                $fullName = $this->cleanPersonName($fullName);
                if ($fullName && $this->isPlausiblePersonName($fullName)) {
                    [$lastNames, $firstNames] = $this->splitEcuadorianName($fullName);

                    return [$fullName, $lastNames, $firstNames];
                }
            }
        }

        return null;
    }

    private function extractVotingCertificateNameParts(string $text, ?string $mainId): ?array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\n+/u', $text) ?: [])));

        foreach ($lines as $index => $line) {
            if (!preg_match('/CERTIFICADO.{0,30}VOTACI/u', $this->normalizeNameLabel($line))) {
                continue;
            }

            $nameLines = [];
            for ($offset = 1; $offset <= 10 && isset($lines[$index + $offset]); $offset++) {
                $current = $lines[$index + $offset];
                $normalized = $this->normalizeNameLabel($current);

                if (preg_match('/\b(PROVINCIA|PROVINGA|CIRCUNSCRIPCION|CIRCUNSCRIPCIÓN|CANTON|CANTÓN|PARROQUIA|ZONA|JUNTA|FEMENINO|MASCULINO|ESTE\s+DOCUMENTO)\b/u', $normalized)) {
                    break;
                }

                if (preg_match('/\d/u', $normalized)
                    || preg_match('/\b(ABRIL|NOVIEMBRE|VUELTA|ELECCIONES|GENERALES|REFERENDUM|REFERÉNDUM|CONSULTA|POPULAR)\b/u', $normalized)) {
                    continue;
                }

                $part = $this->cleanNamePart($current);
                if (!$part || !$this->isPlausibleNameLine($part)) {
                    continue;
                }

                $nameLines[] = $part;
                if (count($nameLines) >= 2 || $this->nameTokenCount(implode(' ', $nameLines)) >= 4) {
                    break;
                }
            }

            if ($nameLines) {
                $fullName = trim(implode(' ', $nameLines));
                $fullName = $this->cleanPersonName($fullName);
                if ($fullName && $this->isPlausiblePersonName($fullName)) {
                    [$lastNames, $firstNames] = $this->splitEcuadorianName($fullName);

                    $blockText = implode("\n", array_slice($lines, $index, 18));
                    $blockIds = $this->validEcuadorianIds($blockText);
                    $cedulaCoincide = $mainId !== null && in_array($mainId, $blockIds, true);
                    $cedulaIncompatible = $mainId !== null && $blockIds !== [] && !$cedulaCoincide;

                    if ($cedulaIncompatible) {
                        $this->debugLog('OCR voting fallback discarded because ID does not match', [
                            'cedula_principal' => $mainId,
                            'cedulas_bloque_votacion' => $blockIds,
                            'nombre' => $fullName,
                        ]);

                        return null;
                    }

                    $this->debugLog('OCR using voting certificate fallback', [
                        'cedula_principal' => $mainId,
                        'cedulas_bloque_votacion' => $blockIds,
                        'nombre' => $fullName,
                        'lineas' => $nameLines,
                    ]);

                    return [
                        'full_name' => $fullName,
                        'last_names' => $lastNames,
                        'first_names' => $firstNames,
                        'cedula_coincide' => $cedulaCoincide,
                        'lines' => $nameLines,
                    ];
                }
            }
        }

        return null;
    }

    private function normalizeNameLabel(string $line): string
    {
        $line = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], mb_strtoupper($line, 'UTF-8'));
        $line = str_replace([' V ', ' MOMBRES', 'NOM8RES', 'APELLI)OS', 'APELLIÍ)OS', 'APELLIIDOS'], [' Y ', ' NOMBRES', 'NOMBRES', 'APELLIDOS', 'APELLIDOS', 'APELLIDOS'], $line);
        $line = preg_replace('/[^A-Z0-9 ]/u', ' ', $line) ?? $line;

        return trim(preg_replace('/\s+/', ' ', $line) ?? $line);
    }

    private function isIdCardNoiseLine(string $line): bool
    {
        if ($line === '') {
            return true;
        }

        if (preg_match('/\d/u', $line)) {
            return true;
        }

        return (bool) preg_match('/\b(REPUBLICA|REPUBLICA DEL ECUADOR|ECUADOR|DIRECCION|GENERAL|REGISTRO|IDENTIFICACION|CEDULA|IDENTIDAD|CIUDADANIA|APELLIDOS|NOMBRES|CERTIFICADO|VOTACION|VOTACIÓN|ELECCIONES|VUELTA|DOCUMENTO|ACREDITA|SUFRAGO|SUFRAGÓ)\b/u', $line);
    }

    private function isPlausibleNameLine(string $line): bool
    {
        if (mb_strlen($line) < 4 || mb_strlen($line) > 45) {
            return false;
        }

        if (preg_match('/\d/u', $line)) {
            return false;
        }

        if (preg_match('/\b(REPUBLICA|ECUADOR|CEDULA|IDENTIDAD|CIUDADANIA|CIUDADANO|CIUDADANA|NACIONALIDAD|MASCULINO|FEMENINO|SOLTERO|CASADO|DIVORCIADO|VIUDO|CERTIFICADO|VOTACION|VOTACIÓN|ELECCIONES|VUELTA|DOCUMENTO|ACREDITA|SUFRAGO|SUFRAGÓ|DIRECTOR|PRESIDENTE|PRESIDENTA)\b/u', $line)) {
            return false;
        }

        return count(array_filter(explode(' ', trim($line)), fn (string $token) => mb_strlen($token) >= 2)) <= 4;
    }

    private function nameTokenCount(string $name): int
    {
        return count(array_filter(explode(' ', trim($name))));
    }

    private function cleanNamePart(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $value = mb_strtoupper($value, 'UTF-8');
        $value = preg_replace('/\b[ÍI1L]4AR[ÍI]A\b/u', 'MARIA', $value) ?? $value;
        $value = str_replace(
            ['I4ARIA', '14ARIA', 'IARIA', 'ESCPBAR', 'ESC0BAR', 'BOSOUEZ'],
            ['MARIA', 'MARIA', 'MARIA', 'ESCOBAR', 'ESCOBAR', 'BOSQUEZ'],
            $value
        );
        $value = preg_replace('/[^A-ZÁÉÍÓÚÑ ]/u', ' ', $value) ?? $value;
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        return $value !== '' ? $value : null;
    }

    private function isPlausiblePersonName(string $name): bool
    {
        if ($name === '' || mb_strlen($name) < 7 || mb_strlen($name) > 100) {
            return false;
        }

        foreach (self::NAME_BLOCKED_WORDS as $word) {
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

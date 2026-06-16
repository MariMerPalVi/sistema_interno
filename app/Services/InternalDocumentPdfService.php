<?php

namespace App\Services;

use App\Models\AccountOpening;
use App\Models\InternalDocumentTemplate;
use setasign\Fpdi\Fpdi;
use Throwable;

class InternalDocumentPdfService
{
    public function generate(AccountOpening $opening, InternalDocumentTemplate $template, array $fields): string
    {
        $opening->loadMissing('documents');
        $fields = $this->normalizeFields($opening, $fields);

        $templateFile = public_path($template->template_path);
        if (!is_file($templateFile)) {
            abort(404, 'No se encontró el formato PDF almacenado.');
        }

        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(false);

        try {
            $pageCount = $pdf->setSourceFile($templateFile);
        } catch (Throwable) {
            abort(422, 'El PDF almacenado no se puede rellenar por su tipo de compresion. Reexporte el formato como PDF compatible o use un formato almacenado compatible.');
        }

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $pageId = $pdf->importPage($pageNumber);
            $size = $pdf->getTemplateSize($pageId);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($pageId);

            if ($pageNumber === 1) {
                $this->writeFields($pdf, $opening, $template, $fields);
            }
        }

        return $pdf->Output('S');
    }

    private function writeFields(Fpdi $pdf, AccountOpening $opening, InternalDocumentTemplate $template, array $fields): void
    {
        $pdf->SetTextColor(20, 31, 43);
        $pdf->SetFont('Helvetica', 'B', 9);

        if (str_contains($template->slug, 'solicitud-de-ingreso')) {
            $this->fillSolicitudIngreso($pdf, $fields);
            return;
        }

        if (str_contains($template->slug, 'registro-de-firmas')) {
            $this->fillRegistroFirmas($pdf, $fields);
            return;
        }

        if (str_contains($template->slug, 'bdh') || str_contains($template->slug, 'acreditacion') || str_contains($template->slug, 'reapertura') || str_contains($template->slug, 'cierre')) {
            $this->fillGenericStoredFormat($pdf, $fields);
            return;
        }

        $this->fillGenericStoredFormat($pdf, $fields);
    }

    private function fillSolicitudIngreso(Fpdi $pdf, array $fields): void
    {
        $this->fieldText($pdf, 70, 51.8, $fields['ciudad'] ?? 'Las Naves', 36, 7);
        $this->fieldText($pdf, 126, 51.8, $fields['dia'] ?? now()->format('d'), 9, 7);
        $this->fieldText($pdf, 146, 51.8, $fields['mes'] ?? now()->format('m'), 28, 7);
        $this->fieldText($pdf, 183, 51.8, $this->shortYear($fields['anio'] ?? now()->format('Y')), 8, 7);

        $this->fieldText($pdf, 31, 90.5, $fields['apellidos_nombres'] ?? '', 126, 7);
        $this->fieldText($pdf, 47, 99.2, $this->digitsOnly($fields['cedula_identidad'] ?? ''), 42, 7);

        if (($fields['tipo_solicitante'] ?? 'socio') === 'socio') {
            $pdf->Text(166.5, 114.8, 'X');
        } else {
            $pdf->Text(189.5, 114.8, 'X');
        }

        if (($fields['fondo_mortuorio'] ?? 'no') === 'si') {
            $pdf->Text(20.5, 137.6, 'X');
        } else {
            $pdf->Text(20.5, 155.4, 'X');
        }
    }

    private function fillRegistroFirmas(Fpdi $pdf, array $fields): void
    {
        $this->fieldText($pdf, 82, 35.4, $fields['codigo_socio'] ?? '', 105, 8);
        $this->fieldText($pdf, 82, 45.0, $fields['cuenta_numero'] ?? '', 105, 8);
        $this->fieldText($pdf, 82, 54.7, $fields['apellidos_nombres'] ?? '', 105, 8);
        $this->fieldText($pdf, 82, 64.3, $this->digitsOnly($fields['cedula_identidad'] ?? ''), 105, 8);
        $this->fieldText($pdf, 82, 73.9, $fields['tipo_cuenta'] ?? '', 105, 8);
    }

    private function fillGenericStoredFormat(Fpdi $pdf, array $fields): void
    {
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Text(45, 54, $this->pdfText($fields['apellidos_nombres'] ?? ''));
        $pdf->Text(45, 63, $this->pdfText($fields['cedula_identidad'] ?? ''));
        $pdf->Text(45, 72, $this->pdfText($fields['cuenta_numero'] ?? ''));
        $pdf->Text(45, 81, $this->pdfText($fields['direccion'] ?? ''));
        $pdf->Text(45, 90, $this->pdfText($this->fechaTexto($fields)));
    }

    private function fechaTexto(array $fields): string
    {
        $day = $fields['dia'] ?? now()->format('d');
        $month = $fields['mes'] ?? now()->format('m');
        $year = $fields['anio'] ?? now()->format('Y');

        return "{$day} de {$month} del {$year}";
    }

    private function shortYear(string $year): string
    {
        $year = trim($year);

        return $year !== '' ? substr($year, -1) : '';
    }

    private function pdfText(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return iconv('UTF-8', 'windows-1252//TRANSLIT', $value) ?: $value;
    }

    private function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function fieldText(Fpdi $pdf, float $x, float $y, string $value, float $maxWidth, int $fontSize): void
    {
        $value = $this->pdfText($value);
        $size = $fontSize;

        do {
            $pdf->SetFont('Helvetica', 'B', $size);
            $fits = $pdf->GetStringWidth($value) <= $maxWidth;
            if ($fits || $size <= 5) {
                break;
            }
            $size--;
        } while (true);

        $pdf->Text($x, $y, $value);
    }

    private function normalizeFields(AccountOpening $opening, array $fields): array
    {
        $fields['apellidos_nombres'] = $this->singleLine($fields['apellidos_nombres'] ?? '');

        $id = $this->digitsOnly((string) ($fields['cedula_identidad'] ?? ''));
        if (strlen($id) !== 10) {
            $id = $this->digitsOnly((string) $opening->member_identification);
        }
        if (strlen($id) !== 10) {
            foreach ($opening->documents as $document) {
                $candidate = $this->digitsOnly((string) ($document->extracted_data['cedula'] ?? ''));
                if (strlen($candidate) === 10) {
                    $id = $candidate;
                    break;
                }
            }
        }

        $fields['cedula_identidad'] = strlen($id) === 10 ? $id : '';

        return $fields;
    }

    private function singleLine(string $value): string
    {
        $value = trim($value);

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }
}

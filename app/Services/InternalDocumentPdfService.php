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
        $templateFile = public_path($template->template_path);
        if (!is_file($templateFile)) {
            abort(404, 'No se encontro el formato PDF almacenado.');
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
        $pdf->SetFont('Helvetica', 'B', 7);
        $pdf->Text(70, 51.8, $this->pdfText($fields['ciudad'] ?? 'Las Naves'));
        $pdf->Text(124, 51.8, $this->pdfText($fields['dia'] ?? now()->format('d')));
        $pdf->Text(145, 51.8, $this->pdfText($fields['mes'] ?? now()->format('m')));
        $pdf->Text(190, 51.8, $this->pdfText($this->shortYear($fields['anio'] ?? now()->format('Y'))));

        $pdf->Text(46, 90.5, $this->pdfText($fields['apellidos_nombres'] ?? ''));
        $pdf->Text(63, 97.8, $this->pdfText($fields['cedula_identidad'] ?? ''));

        if (($fields['tipo_solicitante'] ?? 'socio') === 'socio') {
            $pdf->Text(157.5, 113.2, 'X');
        } else {
            $pdf->Text(179, 113.2, 'X');
        }

        if (($fields['fondo_mortuorio'] ?? 'no') === 'si') {
            $pdf->Text(23.5, 136.8, 'X');
        } else {
            $pdf->Text(23.5, 154.8, 'X');
        }
    }

    private function fillRegistroFirmas(Fpdi $pdf, array $fields): void
    {
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Text(76, 32, $this->pdfText($fields['codigo_socio'] ?? ''));
        $pdf->Text(76, 42, $this->pdfText($fields['cuenta_numero'] ?? ''));
        $pdf->Text(76, 51, $this->pdfText($fields['apellidos_nombres'] ?? ''));
        $pdf->Text(76, 61, $this->pdfText($fields['cedula_identidad'] ?? ''));
        $pdf->Text(76, 70, $this->pdfText($fields['tipo_cuenta'] ?? ''));
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

        return iconv('UTF-8', 'windows-1252//TRANSLIT', $value) ?: $value;
    }
}

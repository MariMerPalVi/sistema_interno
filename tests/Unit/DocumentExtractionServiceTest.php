<?php

namespace Tests\Unit;

use App\Services\DocumentExtractionService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class DocumentExtractionServiceTest extends TestCase
{
    public function test_extracts_names_from_old_id_combined_field(): void
    {
        $result = $this->extractIdFromText(<<<'OCR'
        REPUBLICA DEL ECUADOR
        CEDULA DE CIUDADANIA NUI 1207012152
        APELLIDOS Y NOMBRES
        CASTRO ESCOBAR
        MILLY CRISOL
        LUGAR DE NACIMIENTO
        LOS RIOS
        NACIONALIDAD ECUATORIANA
        OCR);

        $this->assertSame('1207012152', $result['cedula']);
        $this->assertSame('CASTRO ESCOBAR', $result['apellidos']);
        $this->assertSame('MILLY CRISOL', $result['nombres']);
        $this->assertSame('cedula', $result['fuente_usada']);
        $this->assertFalse($result['requiere_revision_manual_datos']);
    }

    public function test_extracts_names_from_new_id_separated_fields(): void
    {
        $result = $this->extractIdFromText(<<<'OCR'
        CEDULA DE IDENTIDAD
        REPUBLICA DEL ECUADOR
        APELLIDOS
        ESCOBAR
        BOSQUEZ
        NOMBRES
        HEIDY NOELY
        NACIONALIDAD
        ECUATORIANA
        NUI 0202223822
        OCR);

        $this->assertSame('0202223822', $result['cedula']);
        $this->assertSame('ESCOBAR BOSQUEZ', $result['apellidos']);
        $this->assertSame('HEIDY NOELY', $result['nombres']);
        $this->assertSame('cedula', $result['fuente_usada']);
    }

    public function test_uses_voting_certificate_as_fallback_when_id_front_is_incomplete(): void
    {
        $result = $this->extractIdFromText(<<<'OCR'
        CEDULA DE IDENTIDAD
        REPUBLICA DEL ECUADOR
        APELLIDOS Y DEL PADRE
        ESCOBAR CHARIGUAMAN ANGEL ANIBAL
        APELLIDOS Y NOMBRES DE LA MADRE
        BOSQUEZ VELASCO NARCISA IVON NINA
        NUI 0202223822
        CERTIFICADO DE VOTACION
        13 DE ABRIL DE 2025 - SEGUNDA VUELTA
        ESCPBAR BOSQUEZ HEIDY
        NOELY
        PROVINCIA BOLIVAR
        CANTON LAS NAVES
        0202223822
        OCR);

        $this->assertSame('0202223822', $result['cedula']);
        $this->assertSame('ESCOBAR BOSQUEZ', $result['apellidos']);
        $this->assertSame('HEIDY NOELY', $result['nombres']);
        $this->assertSame('certificado_votacion', $result['fuente_usada']);
        $this->assertSame('documento_mixto_cedula_certificado_votacion', $result['tipo_documento_detectado']);
    }

    public function test_does_not_extract_ciudadana_footer_as_name(): void
    {
        $result = $this->extractIdFromText(<<<'OCR'
        CERTIFICADO DE VOTACION
        13 DE ABRIL DE 2025 - SEGUNDA VUELTA
        0202223822
        CIUDADANA/O:
        ESTE DOCUMENTO ACREDITA QUE USTED SUFRAGO
        ONFGZ
        F PRESIDENTA/E DE LA JRV
        OCR);

        $this->assertSame('0202223822', $result['cedula']);
        $this->assertNull($result['nombres']);
        $this->assertSame('revision_manual', $result['fuente_usada']);
        $this->assertTrue($result['requiere_revision_manual_datos']);
    }

    public function test_unrecognized_text_returns_manual_review(): void
    {
        $result = $this->extractIdFromText('TEXTO BORROSO SIN CAMPOS UTILES NI NOMBRES VALIDOS');

        $this->assertNull($result['cedula']);
        $this->assertNull($result['nombres']);
        $this->assertSame('formato_no_reconocido', $result['tipo_documento_detectado']);
        $this->assertSame('revision_manual', $result['fuente_usada']);
        $this->assertTrue($result['requiere_revision_manual_datos']);
    }

    private function extractIdFromText(string $text): array
    {
        $service = new DocumentExtractionService();
        $normalized = $this->invokePrivate($service, 'normalize', [$text]);

        return $this->invokePrivate($service, 'extractId', [$normalized]);
    }

    private function invokePrivate(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $arguments);
    }
}

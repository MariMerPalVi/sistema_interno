<?php

namespace Database\Seeders;

use App\Models\AccountType;
use App\Models\AccountTypeRequirement;
use App\Models\AdditionalService;
use App\Models\ExternalCheckItem;
use App\Models\InternalDocumentTemplate;
use App\Models\OperationalCheckItem;
use App\Models\Process;
use App\Models\RequirementType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $roles = collect([
            ['name' => 'asesor', 'label' => 'Asesor'],
            ['name' => 'supervisor', 'label' => 'Supervisor'],
            ['name' => 'administrador', 'label' => 'Administrador'],
            ['name' => 'abogada', 'label' => 'Abogada'],
            ['name' => 'jefe_captaciones', 'label' => 'Jefe de Captaciones'],
            ['name' => 'oficial_cumplimiento', 'label' => 'Oficial de Cumplimiento'],
        ])->map(fn ($role) => Role::updateOrCreate(['name' => $role['name']], $role));

        User::where('email', 'asesor@cooperativa.local')->delete();

        foreach ([
            ['Administrador', ' ', 'administrador@cooperativa.local', 'administrador', 'matriz-las-naves'],
            ['Abogada', 'abogada', 'abogada@cooperativa.local', 'abogada', 'matriz-las-naves'],
            ['Matriz - Las Naves', 'matriz', 'matriz@cooperativa.local', 'asesor', 'matriz-las-naves'],
            ['Echeandía', 'echeandia', 'echeandia@cooperativa.local', 'asesor', 'echeandia'],
            ['Caluma', 'caluma', 'caluma@cooperativa.local', 'asesor', 'caluma'],
            ['San José del Tambo', 'tambo', 'tambo@cooperativa.local', 'asesor', 'san-jose-del-tambo'],
            ['Montalvo', 'montalvo', 'montalvo@cooperativa.local', 'asesor', 'montalvo'],
            ['Quinsaloma', 'quinsaloma', 'quinsaloma@cooperativa.local', 'asesor', 'quinsaloma'],
        ] as [$name, $username, $email, $role, $agency]) {
            $user = User::firstOrNew(['email' => $email]);
            $user->fill([
                'username' => $username,
                'role_id' => $roles->firstWhere('name', $role)->id,
                'agency' => $agency,
                'name' => $name,
                'active' => true,
            ]);
            if (!$user->exists) {
                $user->password = Hash::make('Cambio123!');
                $user->must_change_password = true;
                $user->password_changed_at = null;
                $user->failed_login_attempts = 0;
                $user->locked_until = null;
            }
            $user->save();
        }

        foreach ([
            ['Apertura de cuentas', 'apertura-cuentas', 'Creación y revisión de expedientes para nuevos socios.', true, 'accounts.create', 'folder-plus'],
            ['Solicitud de crédito', 'solicitud-credito', 'Proceso visual reservado para futuras fases.', false, null, 'badge-dollar-sign'],
            ['Actualización de datos', 'actualizacion-datos', 'Actualización de información del socio con respaldo documental y auditoría.', true, 'data-updates.index', 'refresh-cw'],
            ['Bloqueo de tarjeta', 'bloqueo-tarjeta', 'Proceso visual reservado para futuras fases.', false, null, 'shield-alert'],
            ['Emisión de certificados', 'emision-certificados', 'Proceso visual reservado para futuras fases.', false, null, 'file-check'],
            ['Reclamos o solicitudes internas', 'reclamos-solicitudes', 'Proceso visual reservado para futuras fases.', false, null, 'message-square'],
        ] as [$name, $slug, $description, $enabled, $route, $icon]) {
            Process::updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'description' => $description,
                'is_enabled' => $enabled,
                'route_name' => $route,
                'icon' => $icon,
            ]);
        }

        $types = collect([
            ['Cuenta Básica', 'cuenta-basica', 'Si es casado o mantiene unión de hecho, solicitar documentos del cónyuge.', true],
            ['Cuenta Ahorros a la Vista', 'cuenta-ahorro-programado', 'Incluye revisión de documentos del cónyuge cuando aplique.', true],
            ['Cuenta Ahorro Junior', 'cuenta-junior', 'Apertura para menor con representante.', false],
            ['Cuenta Jurídica', 'cuenta-juridica', 'Apertura para instituciones o personas jurídicas.', false],
        ])->map(fn ($type) => AccountType::updateOrCreate(['slug' => $type[1]], [
            'name' => $type[0],
            'notes' => $type[2],
            'requires_spouse_docs' => $type[3],
            'active' => true,
        ]));

        $requirementTypes = collect([
            ['Cédula', 'cedula', 'Validar número de cédula y extraer nombres, apellidos y nacionalidad.', true, true],
            ['Papeleta de votación', 'papeleta-votacion', 'Verificar última elección disponible y datos del titular.', true, true],
            ['Cédula y papeleta de votación', 'cedula-papeleta', 'Validar cédula, nombres, apellidos, nacionalidad y papeleta en un mismo archivo escaneado.', true, true],
            ['Planilla de servicios básicos', 'planilla-servicios', 'Extraer dirección, titular y fecha de emisión.', true, true],
            ['Documentos de cónyuge', 'documentos-conyuge', 'Validación manual de documentos del cónyuge cuando aplique.', false, true],
            ['Cédula del menor', 'cedula-menor', 'Validación manual del documento del menor.', true, true],
            ['RUC', 'ruc', 'Extraer número de RUC, razón social e información básica.', true, true],
            ['Estatutos', 'estatutos', 'Validar carga y legibilidad.', false, true],
            ['Nombramiento', 'nombramiento', 'Validar carga, vigencia y legibilidad.', false, true],
            ['Estados financieros', 'estados-financieros', 'Validar carga y legibilidad del último período.', false, true],
            ['Declaración de impuesto a la renta', 'declaracion-renta', 'Validar carga del año inmediato anterior.', false, true],
            ['Poder de autorización', 'poder-autorizacion', 'Validar carga y legibilidad cuando un tercero realiza el trámite.', false, true],
            ['Acta notariada de constitución', 'acta-constitucion', 'Validar carga y legibilidad cuando aplique.', false, true],
            ['Acta de autorización', 'acta-autorizacion', 'Validar autorización para apertura de cuenta.', false, true],
            ['Personas autorizadas para firmas', 'firmas-autorizadas', 'Validar listado de firmantes autorizados.', false, true],
        ])->map(fn ($type) => RequirementType::updateOrCreate(['slug' => $type[1]], [
            'name' => $type[0],
            'validation_rules' => $type[2],
            'allows_auto_extraction' => $type[3],
            'requires_manual_validation' => $type[4],
        ]));

        $this->syncRequirements($types->firstWhere('slug', 'cuenta-basica'), $requirementTypes, [
            ['cedula-papeleta', 'Cédula y papeleta de votación', '1. Cedula titular_{expediente}'],
            ['planilla-servicios', 'Planilla de servicios básicos', '6. Planilla de SB_{expediente}'],
            ['documentos-conyuge', 'Documentos del cónyuge si aplica', 'Documentos conyuge_{expediente}', false],
        ]);
        $this->syncRequirements($types->firstWhere('slug', 'cuenta-ahorro-programado'), $requirementTypes, [
            ['cedula-papeleta', 'Cédula y papeleta de votación', '1. Cedula titular_{expediente}'],
            ['planilla-servicios', 'Planilla de servicios básicos', '6. Planilla de SB_{expediente}'],
            ['documentos-conyuge', 'Documentos del cónyuge si aplica', 'Documentos conyuge_{expediente}', false],
        ]);
        $this->syncRequirements($types->firstWhere('slug', 'cuenta-junior'), $requirementTypes, [
            ['cedula-papeleta', 'Cédula y papeleta de votación del representante', '1. Cedula Representante_{expediente}'],
            ['cedula-menor', 'Original de la cédula del menor', '2. Cedula menor_{expediente}'],
            ['planilla-servicios', 'Planilla de servicios básicos', '6. Planilla de SB_{expediente}'],
        ]);
        $this->syncRequirements($types->firstWhere('slug', 'cuenta-juridica'), $requirementTypes, [
            ['cedula-papeleta', 'Cédula y papeleta de votación del representante legal', '3. Cedula Representante_{expediente}'],
            ['ruc', 'RUC', '7. Ruc_{expediente}'],
            ['nombramiento', 'Nombramiento', '8. Nombramiento_{expediente}'],
            ['estatutos', 'Estatuto', '9. Estatuto_{expediente}'],
            ['planilla-servicios', 'Planilla de servicio básico de la institución', '10. Planilla de SB Institucion_{expediente}'],
            ['planilla-servicios', 'Planilla de servicio básico del representante legal', '11. Planilla de SB representante_{expediente}'],
            ['estados-financieros', 'Estados financieros', 'Estados financieros_{expediente}'],
            ['declaracion-renta', 'Pago de impuesto a la renta del año inmediato anterior', 'Pago impuesto renta_{expediente}'],
            ['poder-autorizacion', 'Poder en caso de trámite por tercero si aplica', 'Poder_{expediente}', false],
            ['acta-constitucion', 'Acta notariada de la constitución de la sociedad si aplica', 'Acta constitucion_{expediente}', false],
        ]);

        foreach ([
            ['Consulta de procesos judiciales', 'https://procesosjudiciales.funcionjudicial.gob.ec/busqueda-filtros'],
            ['Certificado de antecedentes penales', 'https://certificados.ministeriodelinterior.gob.ec/gestorcertificados/antecedentes/'],
            ['Coactiva', 'https://pjc.refla.org/refla-webapp/faces/login.xhtml'],
            ['Consulta de noticias del delito - Fiscalía', 'https://www.fiscalia.gob.ec/consulta-de-noticias-del-delito/'],
        ] as $index => [$name, $url]) {
            ExternalCheckItem::updateOrCreate(['url' => $url], [
                'name' => $name,
                'is_required' => true,
                'active' => true,
                'sort_order' => $index + 1,
            ]);
        }

        ExternalCheckItem::updateOrCreate(['url' => 'https://360.coop'], [
            'name' => 'Sistema 360',
            'is_required' => false,
            'active' => true,
            'sort_order' => 5,
        ]);

        InternalDocumentTemplate::whereNull('account_type_id')
            ->where('source', '!=', 'servicio')
            ->update(['active' => false]);
        foreach ($types as $accountType) {
            $this->syncInternalDocuments($accountType);
        }

        AdditionalService::query()->update(['active' => false]);
        foreach (['Fondo mortuorio'] as $name) {
            AdditionalService::updateOrCreate(['slug' => Str::slug($name)], [
                'name' => $name,
                'description' => 'Servicio configurable para registrar dentro del expediente.',
                'active' => true,
            ]);
        }

        foreach ([
            ['Formulario del servicio de fondo mortuorio', 'formulario-servicio-fondo-mortuorio', 'formatos/CON_FONDO_MORTUORIO.pdf', 'Formulario servicio Fondo Mortuorio_{expediente}', 1],
            ['Declaración sin fondo mortuorio', 'sin-fondo-mortuorio', 'formatos/SIN_FONDO_MORTUORIO.pdf', 'Sin Fondo Mortuorio_{expediente}', 2],
            ['Certificado de aportación', 'certificado-de-aportacion', 'formatos/CERTIFICADO_2026.pdf', 'Certificado de aportacion_{expediente}', 3],
        ] as [$name, $slug, $templatePath, $pattern, $order]) {
            InternalDocumentTemplate::updateOrCreate(['slug' => $slug], [
                'account_type_id' => null,
                'name' => $name,
                'template_path' => $templatePath,
                'file_name_pattern' => $pattern,
                'source' => 'servicio',
                'requires_signature' => true,
                'is_required' => true,
                'active' => true,
                'sort_order' => $order,
            ]);
        }
        InternalDocumentTemplate::where('slug', 'solicitud-tarjeta-de-debito')->update(['active' => false]);

        foreach ([
            ['Brindar información de servicios', 'Registrar que se informaron requisitos, beneficios, tasas y servicios de la cooperativa.', 'Asistente Operativo'],
            ['Aperturar cuenta en el sistema', 'Registrar datos del socio/cliente en el sistema y generar el número de cuenta respectivo.', 'Asistente Operativo'],
            ['Imprimir contratos y formularios', 'Imprimir contratos, formularios de apertura y demás documentos generados por el sistema.', 'Asistente Operativo'],
            ['Verificar firmas contra cédula', 'Confirmar que la firma del socio/cliente corresponde y coincide con la cédula de ciudadanía.', 'Asistente Operativo'],
            ['Generar cuentas solicitadas', 'Habilitar ahorros, certificados de aportación, ahorro programado, depósitos a plazo u otros productos solicitados.', 'Asistente Operativo'],
            ['Direccionar a caja y libreta', 'Indicar depósito inicial por apertura y posterior entrega de libreta cuando aplique.', 'Asistente Operativo'],
            ['Archivar expediente físico y digital', 'Escanear, guardar en red institucional y conservar expediente físico ordenado y seguro.', 'Asistente Operativo'],
            ['Revisión final de documentación', 'Verificar documentación física y digital completa y correcta antes del archivo.', 'Jefe de Captaciones'],
            ['Verificación de cumplimiento', 'Corroborar que expedientes físicos y digitales se encuentren completos y sin errores.', 'Oficial de Cumplimiento'],
        ] as $index => [$name, $description, $role]) {
            OperationalCheckItem::updateOrCreate(['name' => $name], [
                'description' => $description,
                'responsible_role' => $role,
                'is_required' => true,
                'active' => true,
                'sort_order' => $index + 1,
            ]);
        }
    }

    private function syncRequirements(AccountType $accountType, $requirementTypes, array $items): void
    {
        $patterns = collect($items)->pluck(2)->all();
        AccountTypeRequirement::where('account_type_id', $accountType->id)
            ->whereNotIn('file_name_pattern', $patterns)
            ->update(['active' => false]);

        $order = 1;
        foreach ($items as $item) {
            [$slug, $label, $pattern] = $item;
            $isRequired = $item[3] ?? !str_contains(strtolower($label), 'si aplica');

            AccountTypeRequirement::updateOrCreate(
                [
                    'account_type_id' => $accountType->id,
                    'file_name_pattern' => $pattern,
                ],
                [
                    'requirement_type_id' => $requirementTypes->firstWhere('slug', $slug)->id,
                    'label' => $label,
                    'file_name_pattern' => $pattern,
                    'is_required' => $isRequired,
                    'active' => true,
                    'sort_order' => $order++,
                ]
            );
        }
    }

    private function syncInternalDocuments(AccountType $accountType): void
    {
        $isLegal = $accountType->slug === 'cuenta-juridica';
        $solicitudPath = match ($accountType->slug) {
            'cuenta-junior' => 'formatos/SOLICITUD DE INGRESO-MENOR DE EDAD.pdf',
            'cuenta-juridica' => 'formatos/SOLICITUD_INGRESO_CUENTA_JURIDICA.pdf',
            default => 'formatos/1-solicitud-de-ingreso.pdf',
        };

        if (in_array($accountType->slug, ['cuenta-basica', 'cuenta-ahorro-programado'], true)) {
            $items = [
                ['Formulario solicitud apertura de cuenta', null, 'Formulario solicitud apertura cuenta_{expediente}', true, 'sistema'],
                ['Formulario conozca a su cliente / socio', null, 'Formulario conozca cliente socio_{expediente}', true, 'sistema'],
                ['Solicitud de ingreso al consejo de administración', $solicitudPath, 'Solicitud ingreso consejo administracion_{expediente}', true, 'manual'],
                ['Contrato de apertura de cuenta de ahorros', null, 'Contrato apertura cuenta ahorros_{expediente}', true, 'sistema'],
                ['Formulario autocertificación residencia fiscal', null, 'Formulario autocertificación residencia fiscal_{expediente}', true, 'sistema'],
                ['Registro de firmas', 'formatos/REGISTRO_DE_FIRMAS.pdf', '7. Registro de firmas_{expediente}', true, 'manual'],
            ];

            if ($accountType->slug === 'cuenta-basica') {
                $items[] = ['Autorización para acreditación del BDH', 'formatos/BDH.pdf', 'Autorizacion acreditacion BDH_{expediente}', false, 'manual'];
            }
        } elseif ($isLegal) {
            $items = [
                ['Formulario solicitud apertura de cuenta/actualización de datos', null, 'Formulario solicitud apertura cuenta_{expediente}', true, 'sistema'],
                ['Formulario conozca a su cliente / socio', null, 'Formulario conozca cliente socio_{expediente}', true, 'sistema'],
                ['Solicitud de ingreso al consejo de administración', $solicitudPath, 'Solicitud ingreso consejo administracion_{expediente}', true, 'manual'],
                ['Contrato de apertura de cuenta de ahorros', null, 'Contrato apertura cuenta ahorros_{expediente}', true, 'sistema'],
                ['Formulario autocertificación residencia fiscal', null, 'Formulario autocertificación residencia fiscal_{expediente}', true, 'sistema'],
                ['Formulario conozca su cliente - jurídica', null, 'Formulario conozca cliente juridica_{expediente}', true, 'manual'],
                ['Formulario conozca su cliente - representante legal', null, 'Formulario conozca cliente representante legal_{expediente}', true, 'manual'],
                ['Registro de firmas', 'formatos/REGISTRO_DE_FIRMAS.pdf', '6. Registro de firmas_{expediente}', true, 'manual'],
            ];
        } else {
            $items = [
                ['Solicitud de ingreso al consejo de administración', $solicitudPath, 'Solicitud ingreso consejo administracion_{expediente}', true, 'manual'],
                ['Registro de firmas', 'formatos/REGISTRO_DE_FIRMAS.pdf', '7. Registro de firmas_{expediente}', true, 'manual'],
                ['Contrato de apertura de cuenta de ahorros', null, 'Contrato apertura cuenta ahorros_{expediente}', true, 'econx'],
                ['No residente', null, 'No residente_{expediente}', true, 'econx'],
            ];
        }

        $slugs = collect($items)->map(fn ($item) => $accountType->slug.'-'.Str::slug($item[0]))->all();
        InternalDocumentTemplate::where('account_type_id', $accountType->id)
            ->whereNotIn('slug', $slugs)
            ->update(['active' => false]);

        foreach ($items as $index => [$name, $templatePath, $pattern, $required, $source]) {
            InternalDocumentTemplate::updateOrCreate(['slug' => $accountType->slug.'-'.Str::slug($name)], [
                'account_type_id' => $accountType->id,
                'name' => $name,
                'template_path' => $templatePath,
                'file_name_pattern' => $pattern,
                'source' => $source,
                'requires_signature' => true,
                'is_required' => $required,
                'active' => true,
                'sort_order' => $index + 1,
            ]);
        }
    }
}

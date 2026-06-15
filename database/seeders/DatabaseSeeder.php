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
            ['name' => 'jefe_captaciones', 'label' => 'Jefe de Captaciones'],
            ['name' => 'oficial_cumplimiento', 'label' => 'Oficial de Cumplimiento'],
        ])->map(fn ($role) => Role::updateOrCreate(['name' => $role['name']], $role));

        User::updateOrCreate(
            ['email' => 'asesor@cooperativa.local'],
            [
                'role_id' => $roles->firstWhere('name', 'asesor')->id,
                'name' => 'Asesor Demo',
                'password' => Hash::make('secret123'),
                'active' => true,
            ]
        );

        foreach ([
            ['Apertura de cuentas', 'apertura-cuentas', 'Creacion y revision de expedientes para nuevos socios.', true, 'accounts.create', 'folder-plus'],
            ['Solicitud de credito', 'solicitud-credito', 'Proceso visual reservado para futuras fases.', false, null, 'badge-dollar-sign'],
            ['Actualizacion de datos', 'actualizacion-datos', 'Proceso visual reservado para futuras fases.', false, null, 'refresh-cw'],
            ['Bloqueo de tarjeta', 'bloqueo-tarjeta', 'Proceso visual reservado para futuras fases.', false, null, 'shield-alert'],
            ['Emision de certificados', 'emision-certificados', 'Proceso visual reservado para futuras fases.', false, null, 'file-check'],
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
            ['Cuenta Basica', 'cuenta-basica', 'Si es casado o mantiene union de hecho, solicitar documentos del conyuge.', true],
            ['Cuenta Ahorros', 'cuenta-ahorro-programado', 'Incluye revision de documentos del conyuge cuando aplique.', true],
            ['Cuenta Junior', 'cuenta-junior', 'Apertura para menor con representante.', false],
            ['Cuenta Juridica', 'cuenta-juridica', 'Apertura para instituciones o personas juridicas.', false],
        ])->map(fn ($type) => AccountType::updateOrCreate(['slug' => $type[1]], [
            'name' => $type[0],
            'notes' => $type[2],
            'requires_spouse_docs' => $type[3],
            'active' => true,
        ]));

        $requirementTypes = collect([
            ['Cedula', 'cedula', 'Validar numero de cedula y extraer nombres, apellidos y nacionalidad.', true, true],
            ['Papeleta de votacion', 'papeleta-votacion', 'Verificar ultima eleccion disponible y datos del titular.', true, true],
            ['Cedula y papeleta de votacion', 'cedula-papeleta', 'Validar cedula, nombres, apellidos, nacionalidad y papeleta en un mismo archivo escaneado.', true, true],
            ['Planilla de servicios basicos', 'planilla-servicios', 'Extraer direccion, titular y fecha de emision.', true, true],
            ['Documentos de conyuge', 'documentos-conyuge', 'Validacion manual de documentos del conyuge cuando aplique.', false, true],
            ['Cedula del menor', 'cedula-menor', 'Validacion manual del documento del menor.', true, true],
            ['RUC', 'ruc', 'Extraer numero de RUC, razon social e informacion basica.', true, true],
            ['Estatutos', 'estatutos', 'Validar carga y legibilidad.', false, true],
            ['Nombramiento', 'nombramiento', 'Validar carga, vigencia y legibilidad.', false, true],
            ['Estados financieros', 'estados-financieros', 'Validar carga y legibilidad del ultimo periodo.', false, true],
            ['Declaracion de impuesto a la renta', 'declaracion-renta', 'Validar carga del anio inmediato anterior.', false, true],
            ['Poder de autorizacion', 'poder-autorizacion', 'Validar carga y legibilidad cuando un tercero realiza el tramite.', false, true],
            ['Acta notariada de constitucion', 'acta-constitucion', 'Validar carga y legibilidad cuando aplique.', false, true],
            ['Acta de autorizacion', 'acta-autorizacion', 'Validar autorizacion para apertura de cuenta.', false, true],
            ['Personas autorizadas para firmas', 'firmas-autorizadas', 'Validar listado de firmantes autorizados.', false, true],
        ])->map(fn ($type) => RequirementType::updateOrCreate(['slug' => $type[1]], [
            'name' => $type[0],
            'validation_rules' => $type[2],
            'allows_auto_extraction' => $type[3],
            'requires_manual_validation' => $type[4],
        ]));

        $this->syncRequirements($types->firstWhere('slug', 'cuenta-basica'), $requirementTypes, [
            ['cedula-papeleta', 'Cedula y papeleta de votacion', '1. Cedula titular_{expediente}'],
            ['planilla-servicios', 'Planilla de servicios basicos', '6. Planilla de SB_{expediente}'],
            ['documentos-conyuge', 'Documentos del conyuge si aplica', 'Documentos conyuge_{expediente}', false],
        ]);
        $this->syncRequirements($types->firstWhere('slug', 'cuenta-ahorro-programado'), $requirementTypes, [
            ['cedula-papeleta', 'Cedula y papeleta de votacion', '1. Cedula titular_{expediente}'],
            ['planilla-servicios', 'Planilla de servicios basicos', '6. Planilla de SB_{expediente}'],
            ['documentos-conyuge', 'Documentos del conyuge si aplica', 'Documentos conyuge_{expediente}', false],
        ]);
        $this->syncRequirements($types->firstWhere('slug', 'cuenta-junior'), $requirementTypes, [
            ['cedula-papeleta', 'Cedula y papeleta de votacion del representante', '1. Cedula Representante_{expediente}'],
            ['cedula-menor', 'Original de la cedula del menor', '2. Cedula menor_{expediente}'],
            ['planilla-servicios', 'Planilla de servicios basicos', '6. Planilla de SB_{expediente}'],
        ]);
        $this->syncRequirements($types->firstWhere('slug', 'cuenta-juridica'), $requirementTypes, [
            ['cedula-papeleta', 'Cedula y papeleta de votacion del representante legal', '3. Cedula Representante_{expediente}'],
            ['ruc', 'RUC', '7. Ruc_{expediente}'],
            ['nombramiento', 'Nombramiento', '8. Nombramiento_{expediente}'],
            ['estatutos', 'Estatuto', '9. Estatuto_{expediente}'],
            ['planilla-servicios', 'Planilla de servicio basico de la institucion', '10. Planilla de SB Institucion_{expediente}'],
            ['planilla-servicios', 'Planilla de servicio basico del representante legal', '11. Planilla de SB representante_{expediente}'],
            ['estados-financieros', 'Estados financieros', 'Estados financieros_{expediente}'],
            ['declaracion-renta', 'Pago de impuesto a la renta del anio inmediato anterior', 'Pago impuesto renta_{expediente}'],
            ['poder-autorizacion', 'Poder en caso de tramite por tercero si aplica', 'Poder_{expediente}', false],
            ['acta-constitucion', 'Acta notariada de la constitucion de la sociedad si aplica', 'Acta constitucion_{expediente}', false],
        ]);

        foreach ([
            ['Consulta de procesos judiciales', 'https://procesosjudiciales.funcionjudicial.gob.ec/busqueda-filtros'],
            ['Certificado de antecedentes penales', 'https://certificados.ministeriodelinterior.gob.ec/gestorcertificados/antecedentes/'],
            ['Plataforma REFLA', 'https://pjc.refla.org/refla-webapp/faces/login.xhtml'],
            ['Consulta de noticias del delito - Fiscalia', 'https://www.fiscalia.gob.ec/consulta-de-noticias-del-delito/'],
        ] as $index => [$name, $url]) {
            ExternalCheckItem::updateOrCreate(['url' => $url], [
                'name' => $name,
                'is_required' => true,
                'active' => true,
                'sort_order' => $index + 1,
            ]);
        }

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
            ['Declaracion sin fondo mortuorio', 'sin-fondo-mortuorio', 'formatos/SIN_FONDO_MORTUORIO.pdf', 'Sin Fondo Mortuorio_{expediente}', 2],
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
            ['Brindar informacion de servicios', 'Registrar que se informaron requisitos, beneficios, tasas y servicios de la cooperativa.', 'Asistente Operativo'],
            ['Aperturar cuenta en Econx', 'Registrar datos del socio/cliente en Econx y generar el numero de cuenta respectivo.', 'Asistente Operativo'],
            ['Imprimir contratos y formularios', 'Imprimir contratos, formularios de apertura y demas documentos generados por el sistema.', 'Asistente Operativo'],
            ['Verificar firmas contra cedula', 'Confirmar que la firma del socio/cliente corresponde y coincide con la cedula de ciudadania.', 'Asistente Operativo'],
            ['Generar cuentas solicitadas', 'Habilitar ahorros, certificados de aportacion, ahorro programado, depositos a plazo u otros productos solicitados.', 'Asistente Operativo'],
            ['Direccionar a caja y libreta', 'Indicar deposito inicial por apertura y posterior entrega de libreta cuando aplique.', 'Asistente Operativo'],
            ['Archivar expediente fisico y digital', 'Escanear, guardar en red institucional y conservar expediente fisico ordenado y seguro.', 'Asistente Operativo'],
            ['Revision final de documentacion', 'Verificar documentacion fisica y digital completa y correcta antes del archivo.', 'Jefe de Captaciones'],
            ['Verificacion de cumplimiento', 'Corroborar que expedientes fisicos y digitales se encuentren completos y sin errores.', 'Oficial de Cumplimiento'],
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
        $labels = collect($items)->pluck(1)->all();
        AccountTypeRequirement::where('account_type_id', $accountType->id)
            ->whereNotIn('label', $labels)
            ->update(['active' => false]);

        $order = 1;
        foreach ($items as $item) {
            [$slug, $label, $pattern] = $item;
            $isRequired = $item[3] ?? !str_contains(strtolower($label), 'si aplica');

            AccountTypeRequirement::updateOrCreate(
                [
                    'account_type_id' => $accountType->id,
                    'label' => $label,
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
                ['Solicitud de ingreso al consejo de administracion', $solicitudPath, 'Solicitud ingreso consejo administracion_{expediente}', true, 'manual'],
                ['Contrato de apertura de cuenta de ahorros', null, 'Contrato apertura cuenta ahorros_{expediente}', true, 'sistema'],
                ['Formulario autocertificacion residencia fiscal', null, 'Formulario autocertificacion residencia fiscal_{expediente}', true, 'sistema'],
                ['Registro de firmas', 'formatos/REGISTRO_DE_FIRMAS.pdf', '7. Registro de firmas_{expediente}', true, 'manual'],
            ];

            if ($accountType->slug === 'cuenta-basica') {
                $items[] = ['Autorizacion para acreditacion del BDH', 'formatos/BDH.pdf', 'Autorizacion acreditacion BDH_{expediente}', false, 'manual'];
            }
        } elseif ($isLegal) {
            $items = [
                ['Formulario solicitud apertura de cuenta/actualizacion de datos', null, 'Formulario solicitud apertura cuenta_{expediente}', true, 'sistema'],
                ['Formulario conozca a su cliente / socio', null, 'Formulario conozca cliente socio_{expediente}', true, 'sistema'],
                ['Solicitud de ingreso al consejo de administracion', $solicitudPath, 'Solicitud ingreso consejo administracion_{expediente}', true, 'manual'],
                ['Contrato de apertura de cuenta de ahorros', null, 'Contrato apertura cuenta ahorros_{expediente}', true, 'sistema'],
                ['Formulario autocertificacion residencia fiscal', null, 'Formulario autocertificacion residencia fiscal_{expediente}', true, 'sistema'],
                ['Formulario conozca su cliente - juridica', null, 'Formulario conozca cliente juridica_{expediente}', true, 'manual'],
                ['Formulario conozca su cliente - representante legal', null, 'Formulario conozca cliente representante legal_{expediente}', true, 'manual'],
                ['Registro de firmas', 'formatos/REGISTRO_DE_FIRMAS.pdf', '6. Registro de firmas_{expediente}', true, 'manual'],
            ];
        } else {
            $items = [
                ['Solicitud de ingreso al consejo de administracion', $solicitudPath, 'Solicitud ingreso consejo administracion_{expediente}', true, 'manual'],
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

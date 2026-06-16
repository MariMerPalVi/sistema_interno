# Propuesta funcional y tecnica

## Descripcion general

El sistema web interno centraliza procesos operativos de la cooperativa. En esta etapa el unico modulo funcional es **Apertura de cuentas**. La interfaz deja visibles otros procesos para mostrar la arquitectura futura, pero permanecen deshabilitados.

## Flujo funcional

1. El asesor ingresa al panel de procesos.
2. Selecciona **Apertura de cuentas**.
3. Escoge el tipo de cuenta: Basica, Ahorro Programado, Junior o Juridica.
4. El sistema crea un expediente en estado `borrador`.
5. El asesor registra datos principales del socio.
6. Descarga, imprime y carga el consentimiento firmado.
7. El asesor confirma manualmente la firma; el sistema registra auditoria.
8. El sistema habilita carga de requisitos segun tipo de cuenta.
9. Cada requisito puede quedar `pendiente`, `cargado`, `validado` o `rechazado`.
10. El asesor registra evidencias de consultas externas obligatorias.
11. Se cargan documentos internos firmados.
12. Se seleccionan servicios adicionales.
13. Se registra el cierre operativo segun el manual institucional: apertura en el sistema, impresion de documentos, verificacion de firmas contra cedula, generacion de cuentas solicitadas, direccionamiento a caja para deposito inicial y libreta, archivo fisico/digital, revision del Jefe de Captaciones y verificacion del Oficial de Cumplimiento.
14. Se revisa el resumen y se envia el expediente a `en_revision`.

## Pantallas

- **Panel principal:** tarjetas de procesos, con Apertura de cuentas activa.
- **Seleccion de cuenta:** tarjetas con requisitos configurados por base de datos.
- **Wizard de expediente:** secciones ancladas por pasos, barra de progreso y mensajes de bloqueo.
- **Checklist documental:** archivo por requisito, estado, observaciones y datos extraidos.
- **Control externo:** enlaces oficiales, resultado, observacion y captura.
- **Resumen:** estado general, conteos, servicios e historial.
- **Cierre operativo:** checklist posterior a documentos internos, alineado al manual de apertura de cuentas.

## Base de datos

Tablas principales:

- `roles`
- `users`
- `processes`
- `account_types`
- `requirement_types`
- `account_type_requirements`
- `account_openings`
- `personal_data_consents`
- `uploaded_documents`
- `document_validations`
- `external_check_items`
- `external_check_evidences`
- `internal_document_templates`
- `additional_services`
- `selected_additional_services`
- `operational_check_items`
- `operational_check_records`
- `observations`
- `action_histories`

## Validaciones

- Consentimiento firmado obligatorio antes de cargar requisitos.
- Confirmacion manual obligatoria de firma en consentimiento y documentos internos.
- Requisitos obligatorios por tipo de cuenta antes de consultas externas.
- Capturas obligatorias de consultas externas antes de documentos internos.
- Documentos internos obligatorios antes de enviar a revision.
- Cierre operativo obligatorio antes de enviar a revision.
- Archivos permitidos: PDF, JPG, JPEG, PNG.
- Tamano maximo por archivo: 5 MB.
- Validacion de cedula ecuatoriana por digito verificador cuando el asesor registra el numero.
- Extraccion preliminar registrada en JSON para conectar luego OCR real.

## Seguridad recomendada

- Activar autenticacion Laravel con politicas por rol.
- Guardar documentos en disco privado, no publico.
- Servir documentos mediante controladores con autorizacion.
- Cifrar respaldos y limitar acceso a `storage`.
- Registrar todo cambio en `action_histories`.
- No eliminar documentos: reemplazar y conservar versiones en una tabla de versiones documental.
- Agregar aprobacion de supervisor para rechazos, correcciones y eliminaciones logicas.

## Ajustes incorporados desde el manual de apertura

El manual institucional define el proceso `PROD-CAP-PR-13`, macroproceso Captaciones, proceso Manejo de cuentas, subproceso Apertura de cuentas. El sistema incorpora sus responsables y actividades:

- Asistente Operativo: informa servicios, solicita consentimiento, revisa documentos, revisa listas de control, apertura en el sistema, imprime documentos, verifica firmas, genera cuentas, direcciona a caja/libreta y archiva expediente.
- Jefe de Captaciones: revisa documentacion fisica y digital completa y correcta.
- Oficial de Cumplimiento: verifica expedientes fisicos y digitales sin errores.

La apertura en el sistema queda registrada como actividad operativa con numero de cuenta, observacion, estado, fecha/hora y auditoria.

## Integraciones futuras

- OCR con servicio certificado para cedulas, RUC y planillas.
- Deteccion de firma asistida por IA, siempre con validacion manual.
- Consulta automatica a servicios publicos si existen API oficiales.
- Autenticacion corporativa con MFA.
- Administracion CRUD de requisitos, documentos internos y servicios.

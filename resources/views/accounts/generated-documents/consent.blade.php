<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Consentimiento de datos personales</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #172232;
            --muted: #4f647f;
            --green: #008872;
            --blue: #0879bd;
            --line: #172232;
            --paper-shadow: 0 24px 60px rgba(14, 35, 54, .18);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #eaf1f6;
            color: var(--ink);
            font-family: "Poppins", Arial, sans-serif;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            gap: 12px;
            align-items: center;
            justify-content: flex-end;
            padding: 14px 22px;
            background: rgba(255, 255, 255, .94);
            border-bottom: 1px solid #d6e3ef;
            backdrop-filter: blur(12px);
        }

        .toolbar a,
        .toolbar button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 18px;
            border: 1px solid #b8d8ea;
            border-radius: 7px;
            background: #fff;
            color: #0a4778;
            font-family: inherit;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .toolbar button {
            border-color: var(--green);
            background: var(--green);
            color: #fff;
        }

        .sheet-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 22px;
            padding: 28px 18px 56px;
        }

        .sheet {
            position: relative;
            width: 210mm;
            height: 297mm;
            min-height: 297mm;
            overflow: hidden;
            background: #fff;
            box-shadow: var(--paper-shadow);
        }

        .sheet::before {
            content: "";
            position: absolute;
            inset: 0;
            background: url("{{ asset('formatos/Fondo_page-0001.jpg') }}") center top / 100% 100% no-repeat;
            pointer-events: none;
        }

        .content {
            position: relative;
            z-index: 1;
            min-height: 297mm;
            height: 297mm;
            padding: 33mm 16mm 42mm;
            font-size: 7.15pt;
            line-height: 1.16;
        }

        h1 {
            margin: 0 0 5mm;
            text-align: center;
            font-size: 12pt;
            line-height: 1.2;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0;
        }

        p {
            margin: 0 0 1.6mm;
            text-align: justify;
        }

        .info-block {
            margin-bottom: 2mm;
        }

        .info-block p {
            margin-bottom: .55mm;
        }

        .date-row .editable {
            min-width: 22mm;
            text-align: center;
        }

        .field-line {
            display: inline-block;
            min-width: 36mm;
            padding: 0 2mm 1mm;
            border-bottom: 1px solid var(--line);
            font-weight: 700;
            line-height: 1.1;
            vertical-align: baseline;
        }

        .field-line.long {
            min-width: 106mm;
        }

        .field-line.medium {
            min-width: 48mm;
        }

        .field-line.short {
            min-width: 18mm;
        }

        .editable {
            outline: none;
            color: var(--ink);
            font-weight: 700;
        }

        .editable:focus {
            background: rgba(8, 121, 189, .12);
            box-shadow: 0 0 0 2px rgba(8, 121, 189, .2);
        }

        .section-title {
            margin: 2.2mm 0 .8mm;
            font-weight: 800;
            font-size: 7.8pt;
        }

        .list {
            margin: 0 0 1.1mm;
            padding-left: 4.3mm;
        }

        .list li {
            margin-bottom: .28mm;
            text-align: justify;
        }

        .check-row {
            display: flex;
            gap: 3mm;
            align-items: flex-start;
            margin: 1.4mm 0;
        }

        .box {
            width: 4mm;
            height: 4mm;
            flex: 0 0 4mm;
            border: 1.5px solid var(--line);
            text-align: center;
            font-size: 7pt;
            font-weight: 800;
            line-height: 3.5mm;
        }

        .signature {
            width: 76mm;
            margin: 8mm auto 0;
            text-align: center;
            font-weight: 800;
        }

        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6mm;
            margin-top: 1.5mm;
        }

        .data-lines {
            display: grid;
            gap: .8mm;
        }

        .data-line {
            min-height: 4.7mm;
        }

        .line {
            display: block;
            min-height: 3mm;
            border-bottom: 1px dashed #4d5967;
            font-weight: 700;
            outline: none;
        }

        .line:focus {
            background: rgba(8, 121, 189, .12);
        }

        .line-label {
            display: block;
            margin-top: .4mm;
            font-size: 5.9pt;
            color: var(--muted);
        }

        .consent-checks {
            display: grid;
            gap: .8mm;
            margin: 1.2mm 0 1.6mm;
        }

        .signature::before {
            content: "";
            display: block;
            height: 1px;
            margin-bottom: 2mm;
            background: var(--line);
        }

        .signature small {
            display: block;
            margin-top: 1mm;
            font-weight: 600;
        }

        @media print {
            @page {
                size: A4;
                margin: 0;
            }

            body {
                background: #fff;
            }

            .toolbar {
                display: none;
            }

            .sheet-wrap {
                padding: 0;
            }

            .sheet {
                width: 210mm;
                height: 297mm;
                min-height: 297mm;
                box-shadow: none;
                break-after: page;
                page-break-after: always;
            }

            .sheet:last-child {
                break-after: auto;
                page-break-after: auto;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ asset('formatos/CONSENTIMIENTO_DE_DATOS_PERSONALES_LAS_NAVES.pdf') }}" target="_blank">Ver formato original</a>
        <button type="button" onclick="window.print()">Guardar o imprimir PDF</button>
        <button type="button" onclick="window.close()">Cerrar</button>
    </div>

    <main class="sheet-wrap">
        <article class="sheet">
            <section class="content">
                <h1>Consentimiento para el tratamiento de datos personales</h1>

                <p>En cumplimiento de la Ley Organica de Proteccion de Datos Personales (LOPDP) vigente en materia de proteccion de datos personales y privacidad, <strong>COOPERATIVA DE AHORRO Y CREDITO LAS NAVES LTDA.</strong>, en adelante “la Cooperativa”, informa a sus socios y clientes sobre el tratamiento de sus datos personales y solicita su consentimiento para dicho tratamiento.</p>

                <div class="info-block">
                    <p><strong>Nombre de la Cooperativa:</strong> Cooperativa de Ahorro y Credito Las Naves Ltda.</p>
                    <p><strong>Direccion:</strong> Las Naves, Calle 12 de octubre y 10 de agosto.</p>
                    <p><strong>Telefono:</strong> (03) 2996730</p>
                    <p><strong>Correo Electronico:</strong> info@cooplasnaves.fin.ec</p>
                </div>

                <div class="info-block">
                    <p><strong>Identificacion del Delegado de Proteccion de Datos (DPO)</strong></p>
                    <p><strong>Nombre:</strong> Abg. Nathaly Roxana Barcenes Coles</p>
                    <p><strong>Direccion:</strong> Las Naves, Calle 12 de octubre y 10 de agosto.</p>
                    <p><strong>Telefono:</strong> (03) 2996730</p>
                    <p><strong>Correo Electronico:</strong> protecciondatospersonales@cooplasnaves.fin.ec</p>
                </div>

                <div class="section-title">1. Principios del Tratamiento de Datos Personales</div>
                <p>El tratamiento de sus datos personales se realizara bajo los siguientes principios establecidos en la Ley Organica de Proteccion de Datos Personales (LOPDP):</p>
                <ul class="list">
                    <li><strong>Juridicidad:</strong> Los datos personales deben tratarse conforme a los principios, derechos y obligaciones establecidos en la Constitucion, los instrumentos internacionales, esta ley y su reglamento.</li>
                    <li><strong>Lealtad:</strong> El tratamiento de datos debe ser leal y claro para los titulares, quienes deben estar al tanto de como se estan manejando sus datos personales.</li>
                    <li><strong>Finalidad:</strong> Los datos deben ser tratados con fines determinados, explicitos, legitimos y comunicados al titular. No se pueden tratar datos para fines distintos a los inicialmente establecidos sin autorizacion.</li>
                    <li><strong>Minimizacion de datos personales:</strong> Los datos tratados deben ser pertinentes y limitados a lo estrictamente necesario para cumplir con la finalidad del tratamiento.</li>
                    <li><strong>Confidencialidad:</strong> Los datos personales deben ser tratados de manera confidencial y solo ser accesibles a personas autorizadas.</li>
                    <li><strong>Conservacion:</strong> Los datos personales deben ser conservados solo durante el tiempo necesario para cumplir con la finalidad para la cual fueron recogidos.</li>
                    <li><strong>Seguridad de datos personales:</strong> Los responsables del tratamiento deben implementar medidas tecnicas y organizativas para garantizar la seguridad de los datos personales y prevenir su acceso no autorizado, perdida o destruccion.</li>
                </ul>

                <div class="section-title">2. Finalidad del Tratamiento de Datos Personales</div>
                <p>Los datos personales que usted proporcione seran tratados para las siguientes finalidades:</p>
                <p><strong>Finalidades necesarias (no requieren consentimiento):</strong></p>
                <ul class="list">
                    <li>Gestion operativa y administracion de la relacion contractual derivada de los servicios financieros ofrecidos por la Cooperativa.</li>
                    <li>Cumplimiento de obligaciones legales, regulatorias y contractuales aplicables.</li>
                    <li>Evaluacion crediticia, analisis de riesgo y verificacion de informacion para la prestacion de servicios financieros.</li>
                </ul>
                <p><strong>Finalidades opcionales (requieren su consentimiento):</strong></p>
                <ul class="list">
                    <li>Envio de informacion comercial, promociones, ofertas y campanas relacionadas con los productos y servicios de la Cooperativa.</li>
                    <li>Contacto a traves de medios electronicos, telefonicos o digitales con fines informativos o comerciales.</li>
                    <li>Uso de imagen para fines publicitarios.</li>
                </ul>
                <p>El titular podra aceptar o rechazar las finalidades opcionales, sin que ello afecte la prestacion de los servicios principales ofrecidos por la Cooperativa.</p>

                <div class="section-title">3. Tipos de Datos Personales Recolectados</div>
                <ul class="list">
                    <li><strong>Datos personales:</strong> Nombres, Apellidos, cedula de identidad, codigo dactilar, fecha de nacimiento, edad, lugar de nacimiento, estado civil, etc.</li>
                    <li><strong>Datos de contacto:</strong> Direccion domicilio, direccion de trabajo, telefono, correo electronico, referencias personales, etc.</li>
                    <li><strong>Datos laborales:</strong> instruccion academica, Cargo, historial laboral, etc.</li>
                    <li><strong>Datos financieros:</strong> Cuentas bancarias, saldo en las cuentas, ingresos y egresos, salario, etc.</li>
                    <li><strong>Datos sensibles:</strong> Solo se recolectaran y trataran datos personales sensibles, tales como estado de salud, identidad de genero, etnia, pasado judicial, cargas familiares u otros de naturaleza similar, con su consentimiento explicito y unicamente cuando resulte estrictamente necesario para el cumplimiento de finalidades legitimas, aplicando medidas reforzadas de seguridad y confidencialidad conforme a la normativa vigente.</li>
                </ul>
            </section>
        </article>

        <article class="sheet">
            <section class="content">
                <div class="section-title">4. Base Legal del Tratamiento</div>
                <p>El tratamiento de sus datos personales se realiza conforme a lo establecido en la Ley Organica de Proteccion de Datos Personales (LOPDP) y su Reglamento General. La base legal para el tratamiento incluye:</p>
                <p>a) Su consentimiento explicito para el tratamiento de sus datos personales. b) El cumplimiento de obligaciones legales y contractuales. c) El interes legitimo de la Cooperativa, siempre que no prevalezcan sus derechos fundamentales.</p>
                <p>El consentimiento sera requerido unicamente cuando el tratamiento no pueda sustentarse en otra base de legitimacion prevista en la normativa aplicable.</p>

                <div class="section-title">5. Derechos del Titular de los Datos Personales</div>
                <ul class="list">
                    <li><strong>Acceso:</strong> Conocer que datos personales estan siendo tratados y como.</li>
                    <li><strong>Rectificacion:</strong> Solicitar la correccion de datos inexactos o incompletos.</li>
                    <li><strong>Eliminacion:</strong> Solicitar la eliminacion de sus datos cuando ya no sean necesarios para las finalidades para las que fueron recogidos.</li>
                    <li><strong>Oposicion:</strong> Oponerse al tratamiento de sus datos personales en ciertos casos, como el uso para mercadotecnia directa.</li>
                    <li><strong>Portabilidad:</strong> Recibir sus datos personales en un formato estructurado y transmitirlos a otro responsable.</li>
                    <li><strong>Revocacion del consentimiento:</strong> Revocar su consentimiento en cualquier momento, sin que esto afecte la legalidad del tratamiento realizado antes de la revocacion.</li>
                    <li><strong>No ser objeto de decisiones automatizadas:</strong> No ser sometido a decisiones basadas unicamente en valoraciones automatizadas.</li>
                </ul>

                <div class="section-title">6. Procedimiento para Ejercer sus Derechos</div>
                <p>Para ejercer sus derechos, puede presentar una solicitud por escrito o por correo electronico dirigido al Responsable del Tratamiento de Datos Personales y al Delegado de Proteccion de Datos (DPO), indicando claramente el derecho que desea ejercer. La solicitud debe incluir:</p>
                <ul class="list">
                    <li>Su nombre completo y copia de su documento de identidad.</li>
                    <li>Descripcion clara y precisa de los datos personales sobre los cuales desea ejercer el derecho.</li>
                    <li>Medio de contacto para recibir notificaciones.</li>
                </ul>
                <p>El responsable del tratamiento respondera a su solicitud en un plazo maximo de 15 dias habiles.</p>

                <div class="section-title">7. Transferencia de Datos Personales</div>
                <p>Sus datos personales podran ser transferidos a terceros unicamente en los casos permitidos por la Ley y para las finalidades descritas en este documento. En caso de transferencias internacionales, se garantizara que los destinatarios cumplan con los estandares de proteccion de datos establecidos en la LOPDP.</p>

                <div class="section-title">8. Plazo de Conservacion de los Datos</div>
                <p>Sus datos personales seran conservados durante el tiempo necesario para cumplir con las finalidades descritas en el presente documento, asi como para atender obligaciones legales, regulatorias y contractuales aplicables.</p>
                <ul class="list">
                    <li>Datos relacionados con la relacion contractual y servicios financieros: durante la vigencia de la relacion y hasta 10 anos posteriores a su finalizacion, cuando exista obligacion regulatoria aplicable.</li>
                    <li>Documentacion contable, tributaria y contractual: hasta 7 anos, conforme a la normativa aplicable.</li>
                    <li>Registros de videovigilancia u otros medios de control: entre 30 y 90 dias, salvo requerimiento de autoridad competente o existencia de incidentes.</li>
                </ul>
                <p>Los plazos especificos de conservacion seran gestionados internamente conforme a la Politica de Retencion de Datos de la Cooperativa. Una vez cumplidos dichos plazos, sus datos personales seran eliminados o anonimizados de manera segura, conforme a la normativa vigente.</p>

                <div class="section-title">9. Seguridad de los Datos Personales</div>
                <p>La cooperativa implementara medidas tecnicas y organizativas para garantizar la seguridad y confidencialidad de sus datos personales, incluyendo:</p>
                <ul class="list">
                    <li>Cifrado de datos.</li>
                    <li>Control de acceso restringido a personal autorizado.</li>
                    <li>Auditorias periodicas de los sistemas de informacion.</li>
                </ul>

                <div class="section-title">10. Consentimiento</div>
                <p>El tratamiento de sus datos personales necesario para la gestion de la relacion contractual, evaluacion crediticia y cumplimiento de obligaciones legales no requiere su consentimiento, conforme a la normativa aplicable.</p>
                <p>Usted podra otorgar o negar su consentimiento para las siguientes finalidades opcionales:</p>
                <div class="consent-checks">
                    <div class="check-row"><span class="box editable" contenteditable="true"></span><span>Envio de informacion comercial, promociones y ofertas de productos o servicios.</span></div>
                    <div class="check-row"><span class="box editable" contenteditable="true"></span><span>Contacto a traves de medios electronicos, telefonicos o digitales.</span></div>
                    <div class="check-row"><span class="box editable" contenteditable="true"></span><span>Uso de imagen para fines publicitarios.</span></div>
                </div>
                <p>En caso de tratamiento de datos personales sensibles, se solicitara su consentimiento explicito de manera independiente cuando corresponda. La negativa a otorgar el consentimiento para finalidades no necesarias no afectara la prestacion de los servicios principales ofrecidos por la Cooperativa.</p>
            </section>
        </article>

        <article class="sheet">
            <section class="content">
                <div class="section-title">11. Revocacion del Consentimiento</div>
                <p>Usted puede revocar su consentimiento en cualquier momento, presentando una solicitud por escrito al Responsable del Tratamiento de Datos Personales y al Delegado de Proteccion de Datos (DPO). La revocacion no tendra efectos retroactivos.</p>

                <div class="section-title">12. Consecuencias de no Proporcionar los Datos</div>
                <p>En caso de que decida no proporcionar sus datos personales o revocar su consentimiento, la Cooperativa no podra realizar los procesos que dependan de esos datos, lo que podria afectar la relacion comercial o contractual.</p>

                <div class="section-title">13. Reclamaciones ante la Autoridad de Control</div>
                <p>Si considera que sus derechos han sido vulnerados, tiene derecho a presentar una reclamacion ante la Autoridad de Proteccion de Datos Personales del Ecuador.</p>

                <div class="section-title">Datos del titular y declaracion de consentimiento</div>
                <p>El titular declara que ha leido, comprendido y aceptado el contenido del presente documento, y que ha otorgado su consentimiento de manera libre, especifica, informada e inequivoca conforme a las finalidades seleccionadas.</p>

                <div class="section-title">Para personas naturales:</div>
                <div class="two-columns">
                    <div class="data-lines">
                        <div class="data-line"><span class="line editable" contenteditable="true">{{ $fields['apellidos_nombres'] ?? '' }}</span><span class="line-label">Nombre del Titular:</span></div>
                        <div class="data-line"><span class="line editable" contenteditable="true">{{ $fields['cedula_identidad'] ?? '' }}</span><span class="line-label">Cedula de Identidad:</span></div>
                        <div class="data-line"><span class="line editable" contenteditable="true"></span><span class="line-label">Correo electronico:</span></div>
                        <div class="data-line"><span class="line editable" contenteditable="true"></span><span class="line-label">Telefono:</span></div>
                    </div>
                    <div class="data-lines">
                        <div class="data-line"><span class="line editable" contenteditable="true"></span><span class="line-label">Firma:</span></div>
                        <div class="data-line"><span class="line editable" contenteditable="true">{{ ($fields['dia'] ?? now()->format('d')) . '/' . ($fields['mes'] ?? now()->locale('es')->translatedFormat('F')) . '/' . ($fields['anio'] ?? now()->format('Y')) }}</span><span class="line-label">Fecha:</span></div>
                    </div>
                </div>

                <div class="section-title">Para personas Juridicas:</div>
                <div class="two-columns">
                    <div class="data-lines">
                        <div class="data-line"><span class="line editable" contenteditable="true"></span><span class="line-label">Razon social:</span></div>
                        <div class="data-line"><span class="line editable" contenteditable="true"></span><span class="line-label">RUC:</span></div>
                        <div class="data-line"><span class="line editable" contenteditable="true"></span><span class="line-label">Nombre del representante legal:</span></div>
                        <div class="data-line"><span class="line editable" contenteditable="true"></span><span class="line-label">Cedula del representante legal:</span></div>
                        <div class="data-line"><span class="line editable" contenteditable="true"></span><span class="line-label">Correo electronico:</span></div>
                        <div class="data-line"><span class="line editable" contenteditable="true"></span><span class="line-label">Telefono:</span></div>
                    </div>
                    <div class="data-lines">
                        <div class="data-line"><span class="line editable" contenteditable="true"></span><span class="line-label">Firma del representante legal:</span></div>
                        <div class="data-line"><span class="line editable" contenteditable="true"></span><span class="line-label">Fecha:</span></div>
                    </div>
                </div>

                <p style="margin-top: 5mm;">El firmante declara que, en caso de actuar en representacion de una persona juridica, cuenta con las facultades legales suficientes para otorgar el presente consentimiento en nombre de la misma.</p>
            </section>
        </article>
    </main>
</body>
</html>

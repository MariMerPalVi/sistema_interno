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
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }

        .sheet::before {
            content: "";
            position: absolute;
            inset: 0;
            background: url("{{ asset('formatos/Fondo_page-0001.jpg') }}") center top / 100% 100% no-repeat;
            pointer-events: none;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }

        .sheet-bg {
            position: absolute;
            inset: 0;
            z-index: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            pointer-events: none;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }

        .content {
            position: relative;
            z-index: 1;
            min-height: 297mm;
            height: 297mm;
            padding: 33mm 16mm 42mm;
            font-size: 7.65pt;
            line-height: 1.24;
        }

        h1 {
            margin: 0 0 6mm;
            text-align: center;
            font-size: 12pt;
            line-height: 1.2;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0;
        }

        p {
            margin: 0 0 2mm;
            text-align: justify;
        }

        .info-block {
            margin-bottom: 2.5mm;
        }

        .info-block p {
            margin-bottom: .8mm;
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
            margin: 3mm 0 1.2mm;
            font-weight: 800;
            font-size: 8.35pt;
        }

        .list {
            margin: 0 0 1.6mm;
            padding-left: 4.8mm;
        }

        .list li {
            margin-bottom: .55mm;
            text-align: justify;
        }

        .check-row {
            display: flex;
            gap: 3mm;
            align-items: flex-start;
            margin: 2mm 0;
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
            gap: 7mm;
            margin-top: 2.2mm;
        }

        .data-lines {
            display: grid;
            gap: 1.2mm;
        }

        .data-line {
            min-height: 5.4mm;
        }

        .line {
            display: block;
            min-height: 3.8mm;
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
            font-size: 6.5pt;
            color: var(--muted);
        }

        .consent-checks {
            display: grid;
            gap: 1.2mm;
            margin: 1.8mm 0 2.2mm;
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
            <img class="sheet-bg" src="{{ asset('formatos/Fondo_page-0001.jpg') }}" alt="">
            <section class="content">
                <h1>Consentimiento para el tratamiento de datos personales</h1>

                <p>En cumplimiento de la Ley Orgánica de Protección de Datos Personales (LOPDP) vigente en materia de proteccion de datos personales y privacidad, <strong>COOPERATIVA DE AHORRO Y CRÉDITO LAS NAVES LTDA.</strong>, en adelante “la Cooperativa”, informa a sus socios y clientes sobre el tratamiento de sus datos personales y solicita su consentimiento para dicho tratamiento.</p>

                <div class="info-block">
                    <p><strong>Nombre de la Cooperativa:</strong> Cooperativa de Ahorro y Crédito Las Naves Ltda.</p>
                    <p><strong>Dirección:</strong> Las Naves, Calle 12 de octubre y 10 de agosto.</p>
                    <p><strong>Teléfono:</strong> (03) 2996730</p>
                    <p><strong>Correo electrónico:</strong> info@cooplasnaves.fin.ec</p>
                </div>

                <div class="info-block">
                    <p><strong>Identificación del Delegado de Protección de Datos (DPO)</strong></p>
                    <p><strong>Nombre:</strong> Abg. Nathaly Roxana Barcenes Coles</p>
                    <p><strong>Dirección:</strong> Las Naves, Calle 12 de octubre y 10 de agosto.</p>
                    <p><strong>Teléfono:</strong> (03) 2996730</p>
                    <p><strong>Correo electrónico:</strong> protecciondatospersonales@cooplasnaves.fin.ec</p>
                </div>

                <div class="section-title">1. Principios del Tratamiento de Datos Personales</div>
                <p>El tratamiento de sus datos personales se realizará bajo los siguientes principios establecidos en la Ley Orgánica de Protección de Datos Personales (LOPDP):</p>
                <ul class="list">
                    <li><strong>Juridicidad:</strong> Los datos personales deben tratarse conforme a los principios, derechos y obligaciones establecidos en la Constitución, los instrumentos internacionales, esta ley y su reglamento.</li>
                    <li><strong>Lealtad:</strong> El tratamiento de datos debe ser leal y claro para los titulares, quienes deben estar al tanto de cómo se están manejando sus datos personales.</li>
                    <li><strong>Finalidad:</strong> Los datos deben ser tratados con fines determinados, explícitos, legítimos y comunicados al titular. No se pueden tratar datos para fines distintos a los inicialmente establecidos sin autorización.</li>
                    <li><strong>Minimización de datos personales:</strong> Los datos tratados deben ser pertinentes y limitados a lo estrictamente necesario para cumplir con la finalidad del tratamiento.</li>
                    <li><strong>Confidencialidad:</strong> Los datos personales deben ser tratados de manera confidencial y solo ser accesibles a personas autorizadas.</li>
                    <li><strong>Conservación:</strong> Los datos personales deben ser conservados solo durante el tiempo necesario para cumplir con la finalidad para la cual fueron recogidos.</li>
                    <li><strong>Seguridad de datos personales:</strong> Los responsables del tratamiento deben implementar medidas técnicas y organizativas para garantizar la seguridad de los datos personales y prevenir su acceso no autorizado, pérdida o destrucción.</li>
                </ul>

                <div class="section-title">2. Finalidad del Tratamiento de Datos Personales</div>
                <p>Los datos personales que usted proporcione serán tratados para las siguientes finalidades:</p>
                <p><strong>Finalidades necesarias (no requieren consentimiento):</strong></p>
                <ul class="list">
                    <li>Gestión operativa y administración de la relación contractual derivada de los servicios financieros ofrecidos por la Cooperativa.</li>
                    <li>Cumplimiento de obligaciones legales, regulatorias y contractuales aplicables.</li>
                    <li>Evaluación crediticia, análisis de riesgo y verificación de información para la prestación de servicios financieros.</li>
                </ul>
                <p><strong>Finalidades opcionales (requieren su consentimiento):</strong></p>
                <ul class="list">
                    <li>Envío de información comercial, promociones, ofertas y campañas relacionadas con los productos y servicios de la Cooperativa.</li>
                    <li>Contacto a través de medios electrónicos, telefónicos o digitales con fines informativos o comerciales.</li>
                    <li>Uso de imagen para fines publicitarios.</li>
                </ul>
                <p>El titular podrá aceptar o rechazar las finalidades opcionales, sin que ello afecte la prestación de los servicios principales ofrecidos por la Cooperativa.</p>

                <div class="section-title">3. Tipos de Datos Personales Recolectados</div>
                <ul class="list">
                    <li><strong>Datos personales:</strong> Nombres, Apellidos, cédula de identidad, código dactilar, fecha de nacimiento, edad, lugar de nacimiento, estado civil, etc.</li>
                    <li><strong>Datos de contacto:</strong> Dirección domiciliaria, dirección de trabajo, teléfono, correo electrónico, referencias personales, etc.</li>
                    <li><strong>Datos laborales:</strong> instrucción académica, Cargo, historial laboral, etc.</li>
                    <li><strong>Datos financieros:</strong> Cuentas bancarias, saldo en las cuentas, ingresos y egresos, salario, etc.</li>
                    <li><strong>Datos sensibles:</strong> Solo se recolectarán y tratarán datos personales sensibles, tales como estado de salud, identidad de género, etnia, pasado judicial, cargas familiares u otros de naturaleza similar, con su consentimiento explícito y únicamente cuando resulte estrictamente necesario para el cumplimiento de finalidades legítimas, aplicando medidas reforzadas de seguridad y confidencialidad conforme a la normativa vigente.</li>
                </ul>
            </section>
        </article>

        <article class="sheet">
            <img class="sheet-bg" src="{{ asset('formatos/Fondo_page-0001.jpg') }}" alt="">
            <section class="content">
                <div class="section-title">4. Base Legal del Tratamiento</div>
                <p>El tratamiento de sus datos personales se realiza conforme a lo establecido en la Ley Orgánica de Protección de Datos Personales (LOPDP) y su Reglamento General. La base legal para el tratamiento incluye:</p>
                <p>a) Su consentimiento explícito para el tratamiento de sus datos personales. b) El cumplimiento de obligaciones legales y contractuales. c) El interés legítimo de la Cooperativa, siempre que no prevalezcan sus derechos fundamentales.</p>
                <p>El consentimiento será requerido únicamente cuando el tratamiento no pueda sustentarse en otra base de legitimación prevista en la normativa aplicable.</p>

                <div class="section-title">5. Derechos del Titular de los Datos Personales</div>
                <ul class="list">
                    <li><strong>Acceso:</strong> Conocer qué datos personales están siendo tratados y cómo.</li>
                    <li><strong>Rectificación:</strong> Solicitar la corrección de datos inexactos o incompletos.</li>
                    <li><strong>Eliminación:</strong> Solicitar la eliminacion de sus datos cuando ya no sean necesarios para las finalidades para las que fueron recogidos.</li>
                    <li><strong>Oposición:</strong> Oponerse al tratamiento de sus datos personales en ciertos casos, como el uso para mercadotecnia directa.</li>
                    <li><strong>Portabilidad:</strong> Recibir sus datos personales en un formato estructurado y transmitirlos a otro responsable.</li>
                    <li><strong>Revocación del consentimiento:</strong> Revocar su consentimiento en cualquier momento, sin que esto afecte la legalidad del tratamiento realizado antes de la revocación.</li>
                    <li><strong>No ser objeto de decisiones automatizadas:</strong> No ser sometido a decisiones basadas únicamente en valoraciones automatizadas.</li>
                </ul>

                <div class="section-title">6. Procedimiento para Ejercer sus Derechos</div>
                <p>Para ejercer sus derechos, puede presentar una solicitud por escrito o por correo electrónico dirigido al Responsable del Tratamiento de Datos Personales y al Delegado de Protección de Datos (DPO), indicando claramente el derecho que desea ejercer. La solicitud debe incluir:</p>
                <ul class="list">
                    <li>Su nombre completo y copia de su documento de identidad.</li>
                    <li>Descripción clara y precisa de los datos personales sobre los cuales desea ejercer el derecho.</li>
                    <li>Medio de contacto para recibir notificaciones.</li>
                </ul>
                <p>El responsable del tratamiento responderá a su solicitud en un plazo máximo de 15 días hábiles.</p>

                <div class="section-title">7. Transferencia de Datos Personales</div>
                <p>Sus datos personales podrán ser transferidos a terceros únicamente en los casos permitidos por la Ley y para las finalidades descritas en este documento. En caso de transferencias internacionales, se garantizará que los destinatarios cumplan con los estándares de protección de datos establecidos en la LOPDP.</p>

                <div class="section-title">8. Plazo de Conservación de los Datos</div>
                <p>Sus datos personales serán conservados durante el tiempo necesario para cumplir con las finalidades descritas en el presente documento, así como para atender obligaciones legales, regulatorias y contractuales aplicables.</p>
                <ul class="list">
                    <li>Datos relacionados con la relación contractual y servicios financieros: durante la vigencia de la relación y hasta 10 años posteriores a su finalización, cuando exista obligación regulatoria aplicable.</li>
                    <li>Documentación contable, tributaria y contractual: hasta 7 años, conforme a la normativa aplicable.</li>
                    <li>Registros de videovigilancia u otros medios de control: entre 30 y 90 días, salvo requerimiento de autoridad competente o existencia de incidentes.</li>
                </ul>
                <p>Los plazos específicos de conservación serán gestionados internamente conforme a la Política de Retención de Datos de la Cooperativa. Una vez cumplidos dichos plazos, sus datos personales serán eliminados o anonimizados de manera segura, conforme a la normativa vigente.</p>

                <div class="section-title">9. Seguridad de los Datos Personales</div>
                <p>La Cooperativa implementará medidas técnicas y organizativas para garantizar la seguridad y confidencialidad de sus datos personales, incluyendo:</p>
                <ul class="list">
                    <li>Cifrado de datos.</li>
                    <li>Control de acceso restringido a personal autorizado.</li>
                    <li>Auditorías periódicas de los sistemas de información.</li>
                </ul>
            </section>
        </article>

        <article class="sheet">
            <img class="sheet-bg" src="{{ asset('formatos/Fondo_page-0001.jpg') }}" alt="">
            <section class="content">
                <div class="section-title">10. Consentimiento</div>
                <p>El tratamiento de sus datos personales necesario para la gestión de la relación contractual, evaluación crediticia y cumplimiento de obligaciones legales no requiere su consentimiento, conforme a la normativa aplicable.</p>
                <p>Usted podrá otorgar o negar su consentimiento para las siguientes finalidades opcionales:</p>
                <div class="consent-checks">
                    <div class="check-row"><span class="box editable" contenteditable="true"></span><span>Envío de información comercial, promociones y ofertas de productos o servicios.</span></div>
                    <div class="check-row"><span class="box editable" contenteditable="true"></span><span>Contacto a través de medios electrónicos, telefónicos o digitales.</span></div>
                    <div class="check-row"><span class="box editable" contenteditable="true"></span><span>Uso de imagen para fines publicitarios.</span></div>
                </div>
                <p>En caso de tratamiento de datos personales sensibles, se solicitara su consentimiento explícito de manera independiente cuando corresponda. La negativa a otorgar el consentimiento para finalidades no necesarias no afectará la prestación de los servicios principales ofrecidos por la Cooperativa.</p>

                <div class="section-title">11. Revocación del Consentimiento</div>
                <p>Usted puede revocar su consentimiento en cualquier momento, presentando una solicitud por escrito al Responsable del Tratamiento de Datos Personales y al Delegado de Protección de Datos (DPO). La revocación no tendrá efectos retroactivos.</p>

                <div class="section-title">12. Consecuencias de no proporcionar los Datos</div>
                <p>En caso de que decida no proporcionar sus datos personales o revocar su consentimiento, la Cooperativa no podrá realizar los procesos que dependan de esos datos, lo que podría afectar la relación comercial o contractual.</p>

                <div class="section-title">13. Reclamaciones ante la Autoridad de Control</div>
                <p>Si considera que sus derechos han sido vulnerados, tiene derecho a presentar una reclamación ante la Autoridad de Protección de Datos Personales del Ecuador.</p>

                <div class="section-title">Datos del titular y declaración de consentimiento</div>
                <p>El titular declara que ha leído, comprendido y aceptado el contenido del presente documento, y que ha otorgado su consentimiento de manera libre, específica, informada e inequívoca conforme a las finalidades seleccionadas.</p>

                <div class="section-title">Para personas naturales:</div>
                <div class="two-columns">
                    <div class="data-lines">
                        <div class="data-line"><span class="line editable" contenteditable="true">{{ $fields['apellidos_nombres'] ?? '' }}</span><span class="line-label">Nombre del Titular:</span></div>
                        <div class="data-line"><span class="line editable" contenteditable="true">{{ $fields['cedula_identidad'] ?? '' }}</span><span class="line-label">Cédula de identidad:</span></div>
                        <div class="data-line"><span class="line editable" contenteditable="true"></span><span class="line-label">Correo electrónico:</span></div>
                        <div class="data-line"><span class="line editable" contenteditable="true"></span><span class="line-label">Teléfono:</span></div>
                    </div>
                    <div class="data-lines">
                        <div class="data-line"><span class="line editable" contenteditable="true"></span><span class="line-label">Firma:</span></div>
                        <div class="data-line"><span class="line editable" contenteditable="true">{{ ($fields['dia'] ?? now()->format('d')) . '/' . ($fields['mes'] ?? now()->locale('es')->translatedFormat('F')) . '/' . ($fields['anio'] ?? now()->format('Y')) }}</span><span class="line-label">Fecha:</span></div>
                    </div>
                </div>

                <div class="section-title">Para personas jurídicas:</div>
                <div class="two-columns">
                    <div class="data-lines">
                        <div class="data-line"><span class="line editable" contenteditable="true"></span><span class="line-label">Razón social:</span></div>
                        <div class="data-line"><span class="line editable" contenteditable="true"></span><span class="line-label">RUC:</span></div>
                        <div class="data-line"><span class="line editable" contenteditable="true"></span><span class="line-label">Nombre del representante legal:</span></div>
                        <div class="data-line"><span class="line editable" contenteditable="true"></span><span class="line-label">Cédula del representante legal:</span></div>
                        <div class="data-line"><span class="line editable" contenteditable="true"></span><span class="line-label">Correo electrónico:</span></div>
                        <div class="data-line"><span class="line editable" contenteditable="true"></span><span class="line-label">Teléfono:</span></div>
                    </div>
                    <div class="data-lines">
                        <div class="data-line"><span class="line editable" contenteditable="true"></span><span class="line-label">Firma del representante legal:</span></div>
                        <div class="data-line"><span class="line editable" contenteditable="true"></span><span class="line-label">Fecha:</span></div>
                    </div>
                </div>

                <p style="margin-top: 5mm;">El firmante declara que, en caso de actuar en representación de una persona jurídica, cuenta con las facultades legales suficientes para otorgar el presente consentimiento en nombre de esta..</p>
            </section>
        </article>
    </main>
</body>
</html>

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
            justify-content: center;
            padding: 28px 18px 56px;
        }

        .sheet {
            position: relative;
            width: 210mm;
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
            padding: 42mm 18mm 28mm;
            font-size: 10.4pt;
            line-height: 1.52;
        }

        h1 {
            margin: 0 0 13mm;
            text-align: center;
            font-size: 15pt;
            line-height: 1.2;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0;
        }

        p {
            margin: 0 0 5mm;
            text-align: justify;
        }

        .date-row {
            margin-bottom: 10mm;
            text-align: right;
            font-size: 10pt;
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
            margin: 8mm 0 3mm;
            font-weight: 800;
            text-transform: uppercase;
        }

        .list {
            margin: 0 0 5mm;
            padding-left: 7mm;
        }

        .list li {
            margin-bottom: 2.2mm;
            text-align: justify;
        }

        .check-row {
            display: flex;
            gap: 3mm;
            align-items: flex-start;
            margin: 4mm 0;
        }

        .box {
            width: 5mm;
            height: 5mm;
            flex: 0 0 5mm;
            border: 1.5px solid var(--line);
            text-align: center;
            font-size: 8pt;
            font-weight: 800;
            line-height: 4.4mm;
        }

        .signature {
            width: 76mm;
            margin: 18mm auto 0;
            text-align: center;
            font-weight: 800;
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
                min-height: 297mm;
                box-shadow: none;
                page-break-after: always;
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

                <div class="date-row">
                    <span class="field-line medium editable" contenteditable="true">{{ $fields['ciudad'] ?? 'Las Naves' }}</span>,
                    <span class="field-line short editable" contenteditable="true">{{ $fields['dia'] ?? now()->format('d') }}</span>
                    de <span class="field-line short editable" contenteditable="true">{{ $fields['mes'] ?? now()->locale('es')->translatedFormat('F') }}</span>
                    del 20<span class="field-line short editable" contenteditable="true">{{ isset($fields['anio']) ? substr((string) $fields['anio'], -1) : now()->format('y')[1] }}</span>
                </div>

                <p>
                    Yo, <span class="field-line long editable" contenteditable="true">{{ $fields['apellidos_nombres'] ?? '' }}</span>,
                    portador(a) de la cedula de identidad No.
                    <span class="field-line medium editable" contenteditable="true">{{ $fields['cedula_identidad'] ?? '' }}</span>,
                    en calidad de socio, cliente, usuario o solicitante de productos y servicios de la
                    <strong>Cooperativa de Ahorro y Credito Las Naves</strong>, declaro que he sido informado(a)
                    de forma clara, expresa y suficiente sobre el tratamiento de mis datos personales.
                </p>

                <p>
                    Autorizo de manera libre, especifica, informada e inequivoca a la Cooperativa de Ahorro y
                    Credito Las Naves para que recopile, conserve, utilice, procese, actualice, consulte,
                    verifique y transfiera, cuando corresponda, mis datos personales, financieros, crediticios,
                    laborales, patrimoniales, biometricos y de contacto, de acuerdo con la normativa aplicable
                    de proteccion de datos personales.
                </p>

                <div class="section-title">Finalidades autorizadas</div>
                <ol class="list">
                    <li>Gestionar la apertura, mantenimiento, actualizacion y administracion de productos o servicios contratados con la cooperativa.</li>
                    <li>Validar mi identidad, analizar riesgos, prevenir fraude, cumplir controles internos y atender procesos de debida diligencia.</li>
                    <li>Consultar y reportar informacion ante entidades de control, burós de informacion crediticia, organismos publicos y proveedores autorizados, cuando sea necesario.</li>
                    <li>Gestionar comunicaciones, notificaciones, solicitudes, reclamos, auditorias, archivo documental y cumplimiento de obligaciones legales.</li>
                </ol>

                <p>
                    Declaro conocer que los datos proporcionados deben ser veraces, exactos y actualizados. La
                    cooperativa podra conservarlos durante el tiempo necesario para cumplir la relacion
                    contractual, normativa, administrativa, contable, tributaria, judicial o de auditoria que
                    corresponda.
                </p>

                <div class="section-title">Derechos del titular</div>
                <p>
                    He sido informado(a) de que puedo ejercer mis derechos de acceso, rectificacion,
                    actualizacion, eliminacion, oposicion, suspension del tratamiento y demas derechos
                    reconocidos por la normativa vigente, mediante los canales oficiales de la cooperativa.
                </p>

                <div class="check-row">
                    <span class="box editable" contenteditable="true">X</span>
                    <span>Acepto y autorizo el tratamiento de mis datos personales para las finalidades indicadas en este documento.</span>
                </div>

                <div class="check-row">
                    <span class="box editable" contenteditable="true"></span>
                    <span>No autorizo finalidades adicionales que no sean indispensables para la relacion contractual o cumplimiento legal.</span>
                </div>

                <p>
                    Tipo de cuenta o tramite:
                    <span class="field-line medium editable" contenteditable="true">{{ $fields['tipo_cuenta'] ?? '' }}</span>
                </p>

                <div class="signature">
                    FIRMA DEL TITULAR
                    <small>C.I. <span class="editable" contenteditable="true">{{ $fields['cedula_identidad'] ?? '' }}</span></small>
                </div>
            </section>
        </article>
    </main>
</body>
</html>

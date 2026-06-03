@php
    $slug = $template->slug;
    $isSignatureRegister = str_contains($slug, 'registro-de-firmas');
    $isApplication = str_contains($slug, 'solicitud-de-ingreso');
    $isBdh = str_contains($slug, 'bdh') || str_contains($slug, 'acreditacion') || str_contains($slug, 'reapertura') || str_contains($slug, 'cierre');
    $fullName = trim(preg_replace('/\s+/', ' ', $fields['apellidos_nombres'] ?? ''));
    $identification = preg_replace('/\D+/', '', $fields['cedula_identidad'] ?? '');
    $accountNumber = $fields['cuenta_numero'] ?? $opening->file_name;
    $memberCode = $fields['codigo_socio'] ?? $opening->file_name;
    $accountType = $fields['tipo_cuenta'] ?? $opening->accountType->name;
    $city = $fields['ciudad'] ?? 'Las Naves';
    $day = $fields['dia'] ?? now()->format('d');
    $month = $fields['mes'] ?? now()->locale('es')->translatedFormat('F');
    $year = $fields['anio'] ?? now()->format('Y');
    $yearSuffix = substr((string) $year, -1);
    $requestType = $fields['tipo_solicitante'] ?? 'socio';
    $mortuaryFund = $fields['fondo_mortuorio'] ?? 'no';
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $template->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #e7edf2;
            color: #161d26;
            font-family: "Poppins", Arial, Helvetica, sans-serif;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 5;
            display: flex;
            gap: 10px;
            justify-content: center;
            padding: 12px;
            background: #ffffff;
            border-bottom: 1px solid #cbd7e2;
        }

        button {
            min-height: 38px;
            padding: 0 16px;
            border: 0;
            border-radius: 6px;
            background: #00796b;
            color: #fff;
            font-weight: 800;
            cursor: pointer;
        }

        .page {
            position: relative;
            width: 210mm;
            min-height: 297mm;
            margin: 18px auto;
            padding: 18mm 18mm 16mm;
            background: #fff;
            overflow: hidden;
            box-shadow: 0 14px 36px rgba(15, 23, 42, .18);
        }

        .page.pdf-template {
            height: 297mm;
            min-height: 297mm;
            padding: 0;
        }

        .template-bg {
            position: absolute;
            inset: 0;
            z-index: 0;
            width: 100%;
            height: 100%;
            border: 0;
            object-fit: cover;
            pointer-events: none;
            background: #fff;
        }

        .template-content {
            position: relative;
            z-index: 1;
            padding: 26mm 17mm 16mm;
        }

        .application-document {
            width: 100%;
            color: #111;
            font-family: "Poppins", Arial, Helvetica, sans-serif;
        }

        .application-document h1 {
            margin: 4mm 0 18mm;
            font-size: 18px;
            font-weight: 800;
            text-align: center;
        }

        .application-document p {
            margin: 0 0 3.8mm;
            font-size: 11px;
            line-height: 1.32;
        }

        .application-document .letter-date {
            margin-left: 58mm;
            margin-bottom: 14mm;
            white-space: nowrap;
        }

        .application-document .editable {
            min-width: 32mm;
            padding: 0 .8mm;
            border-bottom: 1px solid #111;
            font-weight: 700;
            line-height: 1.1;
        }

        .application-document .editable.short {
            min-width: 8mm;
        }

        .application-document .editable.medium {
            min-width: 22mm;
        }

        .application-document .editable.long {
            min-width: 116mm;
        }

        .application-document .check {
            width: auto;
            height: auto;
            min-width: 4mm;
            border: 0;
            margin: 0;
            line-height: inherit;
        }

        .application-document .signature {
            margin: 15mm 0 8mm;
            text-align: center;
        }

        .application-document .signature span {
            min-width: 54mm;
            border-top: 1px solid #111;
            padding-top: 2mm;
            font-size: 10px;
            font-weight: 800;
        }

        .approval-box {
            margin-top: 7mm;
            padding: 5mm 6mm 4mm;
            border: 1.4px solid #111;
            font-size: 9px;
            line-height: 1.35;
        }

        .approval-box p {
            margin-bottom: 8mm;
            font-size: 9px;
            line-height: 1.35;
            text-align: justify;
        }

        .manager-signature {
            text-align: center;
            font-size: 9px;
        }

        .manager-signature span {
            display: inline-block;
            min-width: 64mm;
            border-top: 1px solid #111;
            padding-top: 2mm;
        }

        .pdf-field {
            position: absolute;
            min-height: 4mm;
            padding: 0 .8mm;
            border: 0;
            background: transparent;
            color: #161d26;
            font-size: 9px;
            font-weight: 800;
            line-height: 1.1;
            outline: none;
            white-space: nowrap;
            overflow: hidden;
        }

        .pdf-check {
            position: absolute;
            width: 5mm;
            height: 5mm;
            color: #161d26;
            font-size: 11px;
            font-weight: 900;
            line-height: 5mm;
            text-align: center;
            outline: none;
        }

        .brand-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 12px;
        }

        .brand-row img {
            width: 56mm;
            height: auto;
        }

        .brand-line {
            flex: 1;
            height: 4px;
            margin-top: 12mm;
            background: #147fbe;
        }

        h1 {
            margin: 6mm 0 12mm;
            text-align: center;
            font-size: 18px;
            letter-spacing: .3px;
        }

        p {
            margin: 0 0 4.2mm;
            font-size: 12px;
            line-height: 1.42;
        }

        strong {
            font-weight: 800;
        }

        .editable {
            display: inline-block;
            min-width: 32mm;
            padding: 0 1.5mm;
            border-bottom: 1px solid #161d26;
            font-weight: 800;
            line-height: 1.25;
            outline: none;
        }

        .editable.short {
            min-width: 10mm;
            text-align: center;
        }

        .editable.medium {
            min-width: 24mm;
            text-align: center;
        }

        .editable.long {
            min-width: 122mm;
        }

        .letter-date {
            margin-left: 42mm;
            margin-bottom: 14mm;
            white-space: nowrap;
        }

        .signature {
            margin-top: 14mm;
            text-align: center;
        }

        .signature span {
            display: inline-block;
            min-width: 54mm;
            border-top: 1px solid #161d26;
            padding-top: 2mm;
            font-size: 10px;
            font-weight: 800;
        }

        .footer-strip {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 10mm;
            background: linear-gradient(90deg, #00a66c 0 58%, #147fbe 58% 100%);
        }

        .watermark {
            position: absolute;
            left: -34mm;
            bottom: -28mm;
            width: 72mm;
            height: 72mm;
            border: 9mm solid #e7f1f8;
            border-radius: 50%;
        }

        .firm-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8mm;
            font-size: 11px;
        }

        .firm-table th,
        .firm-table td {
            border: 1.5px solid #111827;
            padding: 2.2mm;
            text-align: left;
            vertical-align: middle;
        }

        .firm-table th {
            width: 38%;
            font-weight: 800;
        }

        .firm-box {
            height: 34mm;
            text-align: center;
        }

        .firm-box.large {
            height: 48mm;
        }

        .exclusive {
            margin-top: 5mm;
            font-size: 11px;
            font-weight: 800;
        }

        .generic-box {
            border: 1.5px solid #111827;
            padding: 8mm;
            min-height: 120mm;
        }

        .generic-row {
            display: grid;
            grid-template-columns: 46mm 1fr;
            gap: 4mm;
            margin-bottom: 5mm;
            font-size: 12px;
        }

        .check {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 6mm;
            height: 6mm;
            border: 1px solid #161d26;
            margin-right: 2mm;
            font-weight: 800;
        }

        @media print {
            body {
                background: #fff;
            }

            .toolbar {
                display: none;
            }

            .page {
                margin: 0;
                box-shadow: none;
            }

            .editable {
                outline: none;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Guardar o imprimir PDF</button>
        <button type="button" onclick="window.close()">Cerrar</button>
    </div>

    <main class="page {{ $isApplication ? 'pdf-template' : '' }}">
        @unless ($isApplication)
            <div class="watermark"></div>
            <div class="brand-row">
                <img src="{{ asset('images/logo-las-naves.png') }}" alt="Las Naves">
                <div class="brand-line"></div>
            </div>
        @endunless

        @if ($isSignatureRegister)
            <h1 style="text-align: left; margin-bottom: 8mm;">REGISTRO DE FIRMAS</h1>
            <table class="firm-table">
                <tr>
                    <th>CODIGO DE SOCIO</th>
                    <td contenteditable="true">{{ $memberCode }}</td>
                </tr>
                <tr>
                    <th>CUENTA NUMERO:</th>
                    <td contenteditable="true">{{ $accountNumber }}</td>
                </tr>
                <tr>
                    <th>APELLIDOS Y NOMBRES</th>
                    <td contenteditable="true">{{ $fullName }}</td>
                </tr>
                <tr>
                    <th>CEDULA DE IDENTIDAD</th>
                    <td contenteditable="true">{{ $identification }}</td>
                </tr>
                <tr>
                    <th>TIPO DE CUENTA</th>
                    <td contenteditable="true">{{ $accountType }}</td>
                </tr>
            </table>

            <table class="firm-table">
                <tr>
                    <th class="firm-box">FIRMA UNICA</th>
                    <td class="firm-box"></td>
                </tr>
                <tr>
                    <th class="firm-box large">FIRMA CONJUNTA O INDISTINTA</th>
                    <td class="firm-box large">
                        FIRMANTE 1 (Titular)
                        <br><br><br>
                        FIRMANTE 2
                    </td>
                </tr>
            </table>

            <p style="margin-top: 4mm;">
                Estimado(s) firmante(s), le(s) informamos que esta firma se actualizara de forma automatica en todas las cuenta de ahorro donde usted(es) esten registrado(s) como firma autorizada.
            </p>
            <p class="exclusive">PARA USO EXCLUSIVO DE LA COOPERATIVA</p>
            <table class="firm-table">
                <tr><td style="height: 18mm;">Observaciones:</td><td>Firma:</td></tr>
                <tr><td>Asistente Operativo</td><td>Fecha</td></tr>
            </table>
        @elseif ($isApplication)
            <img class="template-bg" src="{{ asset('formatos/Fondo_page-0001.jpg') }}" alt="">
            <section class="template-content application-document" aria-label="Solicitud de ingreso editable">
                <h1>SOLICITUD DE INGRESO</h1>
                <p class="letter-date">
                    <span class="editable medium" contenteditable="true">{{ $city }}</span>,
                    <span class="editable short" contenteditable="true">{{ $day }}</span>
                    de <span class="editable medium" contenteditable="true">{{ $month }}</span>
                    del 202<span class="editable short" contenteditable="true">{{ $yearSuffix }}</span>
                </p>

                <p>Señores</p>
                <p><strong>CONSEJO DE ADMINISTRACIÓN DE LA COAC LAS NAVES</strong></p>
                <p style="margin-bottom: 16mm;">Presente. -</p>

                <p>
                    Yo,
                    <span class="editable long" contenteditable="true">{{ $fullName }}</span>,
                    portador de la cédula N°
                    <span class="editable medium" contenteditable="true">{{ $identification }}</span>,
                    ante ustedes muy respetuosamente comparezco y solicito.
                </p>

                <p>
                    Se sirvan aceptar la presente solicitud de ingreso en calidad de
                    SOCIO ( <span class="check" contenteditable="true">{{ $requestType === 'socio' ? 'X' : '' }}</span> )
                    CLIENTE ( <span class="check" contenteditable="true">{{ $requestType === 'cliente' ? 'X' : '' }}</span> )
                    a la COAC LAS NAVES, comprometiéndome a cumplir la Ley Orgánica Economía Popular Solidaria, el Sector Financiero, el Reglamento de la Presente Ley, los estatutos y demás reglamentos internos de la misma.
                </p>

                <p>
                    ( <span class="check" contenteditable="true">{{ $mortuaryFund === 'si' ? 'X' : '' }}</span> )
                    Solicito ser beneficiario del fondo mortuorio y acogerme a las políticas establecidas por la Institución.
                </p>
                <p>
                    ( <span class="check" contenteditable="true">{{ $mortuaryFund === 'no' ? 'X' : '' }}</span> )
                    Solicito no ser beneficiario del fondo mortuorio.
                </p>

                <p style="margin-top: 8mm;">Por la atención que se sirvan dar a la presente, anticipo mis agradecimientos.</p>

                <div class="signature">
                    <span>SOLICITANTE</span>
                </div>

                <section class="approval-box">
                    <p>
                        La presente SOLICITUD DE INGRESO es aprobada por el Gerente, de acuerdo con lo establecido en el Estatuto Social, Artículo 21, numeral 6 “Aceptar o rechazar las solicitudes de ingreso o retiro de socios, la atribución de aceptar socios podrá ser delegada a la gerencia o administradores de las oficinas operativas, en los segmentos que la reglamentación lo permita” y mediante <strong>Resolución N° 2015-22-05</strong> emitida por el Consejo de Administración el 15 de junio del 2015.
                    </p>
                    <div class="manager-signature">
                        <span>APROBADO POR GERENTE</span>
                    </div>
                </section>
            </section>
        @else
            <h1>{{ strtoupper($template->name) }}</h1>
            <section class="generic-box">
                <div class="generic-row"><strong>Expediente</strong><span contenteditable="true">{{ $opening->file_name }}</span></div>
                <div class="generic-row"><strong>Apellidos y nombres</strong><span contenteditable="true">{{ $fullName }}</span></div>
                <div class="generic-row"><strong>Cedula de identidad</strong><span contenteditable="true">{{ $identification }}</span></div>
                <div class="generic-row"><strong>Tipo de cuenta</strong><span contenteditable="true">{{ $accountType }}</span></div>
                <div class="generic-row"><strong>Cuenta numero</strong><span contenteditable="true">{{ $accountNumber }}</span></div>
                <div class="generic-row"><strong>Fecha</strong><span contenteditable="true">{{ $day }} de {{ $month }} del {{ $year }}</span></div>
                @if ($isBdh)
                    <div class="generic-row"><strong>Direccion</strong><span contenteditable="true">{{ $fields['direccion'] ?? '' }}</span></div>
                @endif
                <div class="signature">
                    <span>SOLICITANTE</span>
                </div>
            </section>
        @endif
    </main>
</body>
</html>

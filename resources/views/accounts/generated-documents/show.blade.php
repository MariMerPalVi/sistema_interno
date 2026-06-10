@php
    $slug = $template->slug;
    $isSignatureRegister = str_contains($slug, 'registro-de-firmas');
    $isApplication = str_contains($slug, 'solicitud-de-ingreso');
    $isBdh = str_contains($slug, 'bdh') || str_contains($slug, 'acreditacion') || str_contains($slug, 'reapertura') || str_contains($slug, 'cierre');
    $isNoMortuaryFund = str_contains($slug, 'sin-fondo-mortuorio');
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

        .page.bdh-template {
            padding: 0;
            background: #fff;
        }

        .bdh-document {
            position: relative;
            min-height: 297mm;
            padding: 4mm 8mm 0;
            color: #000;
            font-family: "Poppins", Arial, Helvetica, sans-serif;
            font-size: 15px;
            line-height: 1.38;
        }

        .bdh-logo {
            width: 72mm;
            height: auto;
            margin-left: 1mm;
        }

        .bdh-account-row {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 9mm;
            height: 10mm;
            margin-top: 1mm;
            padding-right: 8mm;
            font-weight: 900;
        }

        .bdh-title {
            margin: 0 -8mm 2.5mm;
            min-height: 14mm;
            padding: 2mm 5mm;
            background: #0879bd;
            color: #fff;
            font-size: 13px;
            font-weight: 900;
            line-height: 1.35;
            text-align: center;
            text-transform: uppercase;
        }

        .bdh-line {
            margin: 0 0 2.4mm;
            font-size: 13px;
            line-height: 1.55;
        }

        .bdh-line.center {
            text-align: center;
        }

        .bdh-line.justify {
            text-align: justify;
        }

        .bdh-person-row {
            display: block;
            margin-bottom: 2.4mm;
            font-size: 13px;
            line-height: 1.55;
        }

        .bdh-person-part {
            display: inline;
        }

        .bdh-person-part.identity {
            display: inline;
        }

        .bdh-person-part.identity span:first-child {
            white-space: normal;
        }

        .bdh-place-date {
            display: grid;
            grid-template-columns: auto minmax(28mm, 42mm) auto minmax(45mm, 1fr);
            align-items: baseline;
            gap: 1.4mm;
            margin-top: 2mm;
            font-weight: 900;
        }

        .bdh-place-date .place {
            min-width: 31mm;
            margin-left: 1mm;
            margin-right: 1mm;
        }

        .bdh-document strong {
            font-weight: 900;
        }

        .bdh-document .editable {
            display: inline-block;
            min-height: 5.2mm;
            padding: 0 1mm;
            border: 0;
            background: transparent;
            font-weight: 800;
            line-height: 1.25;
            outline: none;
            vertical-align: baseline;
        }

        .bdh-document .editable:focus {
            background: rgba(8, 121, 189, .16);
            box-shadow: inset 0 -2px 0 #0879bd;
        }

        .bdh-document .editable.name {
            width: 72mm;
            min-width: 72mm;
            margin: 0 1.5mm;
            border-bottom: 1px solid #111;
        }

        .bdh-document .editable.id {
            width: 34mm;
            min-width: 34mm;
            margin-left: 1.5mm;
            text-align: center;
            border-bottom: 1px solid #111;
        }

        .bdh-document .editable.id:empty::before {
            content: attr(data-placeholder);
            color: transparent;
            white-space: pre;
        }

        .bdh-document .editable.account {
            min-width: 30mm;
            color: #000;
            font-size: 18px;
            font-weight: 900;
            text-align: center;
        }

        .bdh-document .editable.benefit-account {
            min-width: 45mm;
            text-align: center;
        }

        .bdh-document .editable.date {
            width: 100%;
            min-width: 45mm;
        }

        .bdh-signature-row {
            display: grid;
            grid-template-columns: 92mm 45mm;
            align-items: end;
            column-gap: 22mm;
            margin: 18mm 0 6mm 5mm;
        }

        .bdh-stamp {
            width: 42mm;
            height: 42mm;
            object-fit: contain;
            justify-self: end;
        }

        .bdh-signature {
            width: 92mm;
            margin: 0;
        }

        .bdh-signature .signature-edit {
            display: block;
            min-height: 9mm;
            border-bottom: 1.6px dashed #111;
            outline: none;
        }

        .bdh-signature .signature-name {
            display: block;
            min-height: 5mm;
            padding-top: 1.5mm;
            font-weight: 900;
            outline: none;
        }

        .bdh-id-line {
            margin-left: 5mm;
            font-size: 13px;
        }

        .bdh-id-line .editable {
            min-width: 48mm;
        }

        .no-mortuary-document {
            position: relative;
            min-height: 255mm;
            padding: 4mm 5mm;
            font-family: "Poppins", Arial, Helvetica, sans-serif;
            color: #111;
            font-size: 11px;
            line-height: 1.45;
        }

        .no-mortuary-header {
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: end;
            margin-bottom: 8mm;
        }

        .no-mortuary-header img {
            width: 103mm;
            height: auto;
        }

        .no-mortuary-account {
            display: flex;
            align-items: baseline;
            gap: 5mm;
            padding-right: 8mm;
            font-size: 10px;
            font-weight: 900;
        }

        .no-mortuary-account .editable-line {
            min-width: 24mm;
            color: #e20b13;
            font-size: 17px;
        }

        .no-mortuary-title {
            margin: 0 -5mm 7mm;
            padding: 2mm;
            background: #0879bd;
            color: #fff;
            font-size: 16px;
            text-align: center;
        }

        .no-mortuary-document p {
            margin: 0 0 3mm;
        }

        .no-mortuary-document .editable-line {
            display: inline-block;
            min-width: 48mm;
            padding: 0 1mm;
            border-bottom: 1px solid transparent;
            font-weight: 800;
            outline: none;
        }

        .no-mortuary-document .editable-line.short {
            min-width: 28mm;
        }

        .no-mortuary-document .editable-line:focus,
        .no-mortuary-check:focus {
            background: rgba(8, 121, 189, .14);
            border-bottom-color: #0879bd;
        }

        .no-mortuary-intro {
            display: grid;
            grid-template-columns: auto minmax(50mm, 1fr) auto;
            align-items: baseline;
            gap: 2mm;
        }

        .no-mortuary-reasons {
            display: grid;
            gap: 1.6mm;
            margin: 2mm 0 3mm;
        }

        .no-mortuary-reason {
            display: grid;
            grid-template-columns: 7mm 1fr;
            align-items: baseline;
        }

        .no-mortuary-check {
            min-height: 5mm;
            font-weight: 900;
            text-align: center;
            outline: none;
        }

        .no-mortuary-date {
            display: flex;
            align-items: baseline;
            gap: 2mm;
            font-weight: 800;
        }

        .no-mortuary-signature {
            width: 72mm;
            margin: 27mm 0 0;
        }

        .no-mortuary-signature .signature-space {
            display: block;
            min-height: 13mm;
            border-bottom: 1.4px dashed #111;
            outline: none;
        }

        .no-mortuary-signature .signature-name {
            display: block;
            padding-top: 2mm;
            font-weight: 900;
            outline: none;
        }

        .no-mortuary-signature .signature-id {
            display: flex;
            gap: 3mm;
            align-items: baseline;
            margin-top: 1mm;
            font-weight: 900;
        }

        .no-mortuary-signature .signature-id .editable-line {
            min-width: 30mm;
            font-weight: 500;
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
            margin-top: 5mm;
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

        .signature-register-header {
            display: grid;
            grid-template-columns: 1fr 1fr;
            align-items: start;
            margin-bottom: 7mm;
        }

        .signature-register-header h1 {
            margin: 6mm 0 0;
            text-align: left;
            font-size: 16px;
            line-height: 1.2;
        }

        .signature-register-header img {
            width: 46mm;
            justify-self: center;
        }

        .signature-register .firm-table {
            margin-top: 4mm;
            font-size: 10px;
        }

        .signature-register .firm-table th,
        .signature-register .firm-table td {
            border: 1.5px solid #111;
            padding: 1.7mm;
        }

        .signature-register .firm-table th {
            width: 37%;
        }

        .signature-register .firm-box {
            height: 31mm;
        }

        .signature-register .firm-box.large {
            height: 43mm;
        }

        .signature-register .joint-signatures {
            display: grid;
            grid-template-rows: 1fr 1fr;
            height: 43mm;
            margin: -1.7mm;
        }

        .signature-register .joint-signatures div {
            display: flex;
            align-items: flex-end;
            padding: 1.7mm;
        }

        .signature-register .joint-signatures div + div {
            border-top: 1.5px solid #111;
        }

        .signature-register .notice {
            margin-top: 4mm;
            font-size: 9.5px;
            line-height: 1.35;
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

            .edit-hint {
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

    <main class="page {{ $isApplication ? 'pdf-template' : '' }} {{ $isBdh ? 'bdh-template' : '' }}">
        @unless ($isApplication || $isSignatureRegister || $isBdh || $isNoMortuaryFund)
            <div class="watermark"></div>
            <div class="brand-row">
                <img src="{{ asset('images/logo-las-naves.png') }}" alt="Las Naves">
                <div class="brand-line"></div>
            </div>
        @endunless

        @if ($isSignatureRegister)
            <section class="signature-register">
            <div class="signature-register-header">
                <h1>REGISTRO DE FIRMAS</h1>
                <img src="{{ asset('images/logo-las-naves.png') }}" alt="Las Naves">
            </div>
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
                        <div class="joint-signatures">
                            <div>FIRMANTE 1 (Titular)</div>
                            <div>FIRMANTE 2</div>
                        </div>
                    </td>
                </tr>
            </table>

            <p class="notice">
                Estimado(s) firmante(s), le(s) informamos que esta firma se actualizara de forma automatica en todas las cuenta de ahorro donde usted(es) esten registrado(s) como firma autorizada.
                Por lo tanto <strong>COOPERATIVA DE AHORRO Y CRÉDITO LAS NAVES</strong> devolvera los retiros/transferencias/notas de débito u otro medio de pago cuya firma no corresponda a esta actualizacion.
            </p>
            <p class="exclusive">PARA USO EXCLUSIVO DE LA COOPERATIVA</p>
            <table class="firm-table">
                <tr><td style="height: 18mm;">Observaciones:</td><td>Firma:</td></tr>
                <tr><td>Asistente Operativo</td><td>Fecha</td></tr>
            </table>
            <table class="firm-table">
                <tr><td style="height: 18mm;">Observaciones:</td><td>Firma:</td></tr>
                <tr><td>Jefe de Captaciones</td><td>Fecha</td></tr>
            </table>
            </section>
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
        @elseif ($isBdh)
            <section class="bdh-document" aria-label="Autorizacion BDH editable">
                <img class="bdh-logo" src="{{ asset('images/logo-las-naves.png') }}" alt="Las Naves">

                <div class="bdh-account-row">
                    <span>CUENTA N°:</span>
                    <span class="editable account" contenteditable="true" spellcheck="false">{{ $accountNumber }}</span>
                </div>

                <div class="bdh-title">
                    SOLICITUD DE AUTORIZACIÓN PARA ACCEDER AL PAGO DE LA TRANSFERENCIA MONETARIA MEDIANTE<br>
                    DEPÓSITO EN CUENTA
                </div>

                <div class="bdh-person-row">
                    <div class="bdh-person-part">
                        <strong>Yo,</strong>
                        <span class="editable name" contenteditable="true" spellcheck="false">{{ $fullName ?: ' ' }}</span>
                    </div>
                    <div class="bdh-person-part identity">
                        <span>portador(a) de la cédula de identidad N°</span>
                        <span class="editable id" contenteditable="true" spellcheck="false" data-placeholder="0000000000">{{ $identification ?: '' }}</span>
                    </div>
                </div>
                <p class="bdh-line justify">
                    en calidad de usuario de las Transferencias Monetarias MIES otorgado por el Gobierno Nacional, en forma expresa autorizo a la COOPERATIVA DE AHORRO Y CRÉDITO LAS NAVES LTDA., a que se acredite mensualmente el monto del beneficio que me corresponde; a mi cuenta de ahorros que mantengo en dicha institución:
                </p>
                <p class="bdh-line center">
                    <strong>Número de Cuenta:</strong>
                    <span class="editable benefit-account" contenteditable="true" spellcheck="false">{{ $accountNumber }}</span>
                </p>
                <p class="bdh-line justify">
                    Para constancia suscribo el presente documento y/o (imprimo la huella digital de mi pulgar derecho).
                </p>
                <p class="bdh-line bdh-place-date">
                    <strong>Lugar y Fecha :</strong>
                    <span class="editable place" contenteditable="true" spellcheck="false">{{ $city ?: 'Las Naves' }}</span>
                    <span>,</span>
                    <span class="editable date" contenteditable="true" spellcheck="false">{{ $day }} de {{ $month }} del {{ $year }}</span>
                </p>

                <div class="bdh-signature-row">
                    <div class="bdh-signature">
                        <span class="signature-edit" contenteditable="true" spellcheck="false"></span>
                        <span class="signature-name" contenteditable="true" spellcheck="false">{{ $fullName ?: ' ' }}</span>
                    </div>
                    <img class="bdh-stamp" src="{{ asset('images/sello-las-naves.png') }}" alt="Sello Las Naves">
                </div>
                <div class="bdh-id-line">
                    <strong>Cédula:</strong>
                    <span class="editable" contenteditable="true" spellcheck="false">{{ $identification ?: ' ' }}</span>
                </div>
            </section>
        @elseif ($isNoMortuaryFund)
            <section class="no-mortuary-document" aria-label="Declaracion sin fondo mortuorio editable">
                <header class="no-mortuary-header">
                    <img src="{{ asset('images/logo-las-naves.png') }}" alt="Las Naves">
                    <div class="no-mortuary-account">
                        <span>CUENTA N°:</span>
                        <span class="editable-line short" contenteditable="true" spellcheck="false">{{ $accountNumber }}</span>
                    </div>
                </header>

                <div class="no-mortuary-title">ACUERDO</div>

                <p class="no-mortuary-intro">
                    <span>Yo,</span>
                    <span class="editable-line" contenteditable="true" spellcheck="false">{{ $fullName ?: ' ' }}</span>
                    <span>certifico que tengo pleno conocimiento de que no soy</span>
                </p>
                <p>beneficiario del SERVICIO DE FONDO MORTUORIO, por las razones señaladas a continuación:</p>

                <div class="no-mortuary-reasons">
                    <div class="no-mortuary-reason">
                        <span class="no-mortuary-check" contenteditable="true" spellcheck="false">( &nbsp; )</span>
                        <span>a. Por superar los 65 años de edad al momento de aperturar la cuenta.</span>
                    </div>
                    <div class="no-mortuary-reason">
                        <span class="no-mortuary-check" contenteditable="true" spellcheck="false">( &nbsp; )</span>
                        <span>b. No deseo acceder al servicio de Fondo Mortuorio, a pesar de haber sido informado(a) de los beneficios.</span>
                    </div>
                    <div class="no-mortuary-reason">
                        <span class="no-mortuary-check" contenteditable="true" spellcheck="false">( &nbsp; )</span>
                        <span>c. Por contar con un seguro de vida en otra entidad.</span>
                    </div>
                    <div class="no-mortuary-reason">
                        <span class="no-mortuary-check" contenteditable="true" spellcheck="false">( &nbsp; )</span>
                        <span>d. Otros <span class="editable-line short" contenteditable="true" spellcheck="false"></span></span>
                    </div>
                </div>

                <p class="no-mortuary-date">
                    <span>Lugar y Fecha : {{ $city ?: 'Las Naves' }},</span>
                    <span class="editable-line" contenteditable="true" spellcheck="false">{{ $day }} de {{ $month }} de {{ $year }}</span>
                </p>

                <div class="no-mortuary-signature">
                    <span class="signature-space" contenteditable="true" spellcheck="false"></span>
                    <span class="signature-name" contenteditable="true" spellcheck="false">{{ $fullName ?: ' ' }}</span>
                    <span class="signature-id">
                        C.I.:
                        <span class="editable-line short" contenteditable="true" spellcheck="false">{{ $identification ?: ' ' }}</span>
                    </span>
                </div>
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

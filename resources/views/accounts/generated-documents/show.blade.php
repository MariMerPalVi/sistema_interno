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
            font-family: Arial, Helvetica, sans-serif;
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

    <main class="page">
        <div class="watermark"></div>
        <div class="brand-row">
            <img src="{{ asset('images/logo-las-naves.png') }}" alt="Las Naves">
            <div class="brand-line"></div>
        </div>

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
                SOCIO <span class="check" contenteditable="true">{{ $requestType === 'socio' ? 'X' : '' }}</span>
                CLIENTE <span class="check" contenteditable="true">{{ $requestType === 'cliente' ? 'X' : '' }}</span>
                a la COAC LAS NAVES, comprometiéndome a cumplir la Ley Orgánica Economía Popular Solidaria, el Sector Financiero, el Reglamento de la Presente Ley, los estatutos y demás reglamentos internos de la misma.
            </p>

            <p>
                <span class="check" contenteditable="true">{{ $mortuaryFund === 'si' ? 'X' : '' }}</span>
                Solicito ser beneficiario del fondo mortuorio y acogerme a las políticas establecidas por la Institución.
            </p>
            <p>
                <span class="check" contenteditable="true">{{ $mortuaryFund === 'no' ? 'X' : '' }}</span>
                Solicito no ser beneficiario del fondo mortuorio.
            </p>

            <p style="margin-top: 8mm;">Por la atención que se sirvan dar a la presente, anticipo mis agradecimientos.</p>

            <div class="signature">
                <span>SOLICITANTE</span>
            </div>
            <div class="footer-strip"></div>
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

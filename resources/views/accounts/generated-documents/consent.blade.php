@php
    $fullName = trim(preg_replace('/\s+/', ' ', $fields['apellidos_nombres'] ?? ''));
    $identification = preg_replace('/\D+/', '', $fields['cedula_identidad'] ?? '');
    $city = $fields['ciudad'] ?? 'Las Naves';
    $day = $fields['dia'] ?? now()->format('d');
    $month = $fields['mes'] ?? now()->locale('es')->translatedFormat('F');
    $year = $fields['anio'] ?? now()->format('Y');
    $accountType = $fields['tipo_cuenta'] ?? $opening->accountType->name;
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Consentimiento de datos personales</title>
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
            color: #111827;
            font-family: "Poppins", Arial, Helvetica, sans-serif;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            gap: 10px;
            justify-content: center;
            padding: 12px;
            background: #fff;
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
            padding: 18mm 17mm 16mm;
            background: #fff;
            overflow: hidden;
            box-shadow: 0 14px 36px rgba(15, 23, 42, .18);
        }

        .background {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 0;
            pointer-events: none;
        }

        .content {
            position: relative;
            z-index: 1;
        }

        h1 {
            margin: 20mm 0 8mm;
            text-align: center;
            font-size: 17px;
            line-height: 1.25;
            text-transform: uppercase;
        }

        p {
            margin: 0 0 4mm;
            font-size: 10.5px;
            line-height: 1.45;
            text-align: justify;
        }

        .date {
            margin-left: 58mm;
            margin-bottom: 9mm;
            white-space: nowrap;
            text-align: left;
        }

        .editable {
            display: inline-block;
            min-width: 30mm;
            padding: 0 1mm;
            border-bottom: 1px solid #111827;
            font-weight: 800;
            line-height: 1.15;
            outline: none;
        }

        .editable.short {
            min-width: 8mm;
            text-align: center;
        }

        .editable.medium {
            min-width: 24mm;
            text-align: center;
        }

        .editable.long {
            min-width: 112mm;
        }

        .signature {
            display: grid;
            justify-content: center;
            margin-top: 18mm;
            text-align: center;
        }

        .signature span {
            display: inline-block;
            min-width: 62mm;
            border-top: 1px solid #111827;
            padding-top: 2mm;
            font-size: 10px;
            font-weight: 800;
        }

        .check {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 5mm;
            min-height: 5mm;
            border: 1px solid #111827;
            font-weight: 800;
            vertical-align: middle;
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
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Guardar o imprimir PDF</button>
        <button type="button" onclick="window.close()">Cerrar</button>
    </div>

    <main class="page">
        <img class="background" src="{{ asset('formatos/Fondo_page-0001.jpg') }}" alt="">
        <section class="content">
            <h1>Consentimiento para el tratamiento de datos personales</h1>

            <p class="date">
                <span class="editable medium" contenteditable="true">{{ $city }}</span>,
                <span class="editable short" contenteditable="true">{{ $day }}</span>
                de <span class="editable medium" contenteditable="true">{{ $month }}</span>
                del <span class="editable short" contenteditable="true">{{ $year }}</span>
            </p>

            <p>
                Yo,
                <span class="editable long" contenteditable="true">{{ $fullName }}</span>,
                con cédula de identidad N°
                <span class="editable medium" contenteditable="true">{{ $identification }}</span>,
                en calidad de solicitante de
                <span class="editable medium" contenteditable="true">{{ $accountType }}</span>,
                autorizo de manera libre, previa, expresa e informada a la Cooperativa de Ahorro y Crédito Las Naves para que realice el tratamiento de mis datos personales.
            </p>

            <p>
                La autorización comprende la recolección, registro, almacenamiento, conservación, consulta, actualización, uso, validación, análisis, transferencia y eliminación de mis datos personales, conforme a la normativa vigente de protección de datos personales y a las finalidades propias de los servicios financieros solicitados.
            </p>

            <p>
                Declaro que la información entregada es verdadera, completa y actualizada. Conozco que podré ejercer los derechos de acceso, rectificación, actualización, eliminación, oposición, suspensión y demás derechos reconocidos por la ley, mediante los canales oficiales de la Cooperativa.
            </p>

            <p>
                Finalidades autorizadas:
                <br>
                <span class="check" contenteditable="true">X</span> Evaluación y gestión de productos y servicios financieros.
                <br>
                <span class="check" contenteditable="true">X</span> Verificación de identidad, prevención de fraude, cumplimiento normativo y análisis de riesgos.
                <br>
                <span class="check" contenteditable="true">X</span> Comunicación de información relacionada con el expediente, productos contratados y obligaciones del socio o cliente.
            </p>

            <p>
                Con mi firma dejo constancia de haber leído y aceptado el presente consentimiento para el tratamiento de mis datos personales.
            </p>

            <div class="signature">
                <span>FIRMA DEL TITULAR</span>
                <strong contenteditable="true">{{ $fullName }}</strong>
                <small contenteditable="true">C.I. {{ $identification }}</small>
            </div>
        </section>
    </main>
</body>
</html>

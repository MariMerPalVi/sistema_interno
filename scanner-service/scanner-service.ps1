param(
    [string] $HostAddress = "127.0.0.1",
    [int] $Port = 8765,
    [switch] $KeepDiagnosticCopies = $true
)

$ErrorActionPreference = "Stop"

function Write-Log {
    param([string] $Message)
    $stamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Write-Host "[$stamp] $Message"
}

function New-HttpResponse {
    param(
        [int] $StatusCode = 200,
        [string] $ContentType = "application/json; charset=utf-8",
        [string] $Body = "{}"
    )

    $reason = switch ($StatusCode) {
        200 { "OK" }
        204 { "No Content" }
        404 { "Not Found" }
        405 { "Method Not Allowed" }
        500 { "Internal Server Error" }
        default { "OK" }
    }

    $bytes = [System.Text.Encoding]::UTF8.GetBytes($Body)
    $headers = @(
        "HTTP/1.1 $StatusCode $reason",
        "Content-Type: $ContentType",
        "Content-Length: $($bytes.Length)",
        "Access-Control-Allow-Origin: *",
        "Access-Control-Allow-Methods: POST, OPTIONS",
        "Access-Control-Allow-Headers: Content-Type, Accept",
        "Connection: close",
        "",
        ""
    ) -join "`r`n"

    $headerBytes = [System.Text.Encoding]::ASCII.GetBytes($headers)
    $response = New-Object byte[] ($headerBytes.Length + $bytes.Length)
    [System.Array]::Copy($headerBytes, 0, $response, 0, $headerBytes.Length)
    [System.Array]::Copy($bytes, 0, $response, $headerBytes.Length, $bytes.Length)

    return $response
}

function New-OptionsResponse {
    $headers = @(
        "HTTP/1.1 204 No Content",
        "Access-Control-Allow-Origin: *",
        "Access-Control-Allow-Methods: POST, OPTIONS",
        "Access-Control-Allow-Headers: Content-Type, Accept",
        "Connection: close",
        "",
        ""
    ) -join "`r`n"

    return [System.Text.Encoding]::UTF8.GetBytes($headers)
}

function ConvertTo-JsonText {
    param([hashtable] $Data)
    return ($Data | ConvertTo-Json -Depth 6 -Compress)
}

function Invoke-WiaScan {
    $jpegFormat = "{B96B3CAE-0728-11D3-9D7B-0000F81EF32E}"
    $scannerDeviceType = 1
    $intentUnspecified = 0
    $biasMaximizeQuality = 131072

    $scanId = [guid]::NewGuid().ToString("N")
    $tempPath = Join-Path $env:TEMP ("las_naves_scan_{0}.jpg" -f $scanId)
    $diagnosticPath = $null

    try {
        $dialog = New-Object -ComObject WIA.CommonDialog
        $image = $dialog.ShowAcquireImage(
            $scannerDeviceType,
            $intentUnspecified,
            $biasMaximizeQuality,
            $jpegFormat,
            $false,
            $true,
            $true
        )

        if ($null -eq $image) {
            throw "El escaneo fue cancelado o no devolvió imagen."
        }

        $processor = New-Object -ComObject WIA.ImageProcess
        $processor.Filters.Add($processor.FilterInfos.Item("Convert").FilterID) | Out-Null
        $processor.Filters.Item(1).Properties.Item("FormatID").Value = $jpegFormat
        $processor.Filters.Item(1).Properties.Item("Quality").Value = 90
        $jpegImage = $processor.Apply($image)

        if ($null -eq $jpegImage) {
            throw "WIA no pudo convertir la imagen escaneada a JPG."
        }

        if (Test-Path -LiteralPath $tempPath) {
            Remove-Item -LiteralPath $tempPath -Force
        }

        $jpegImage.SaveFile($tempPath)

        if (!(Test-Path -LiteralPath $tempPath)) {
            throw "WIA no generó el archivo temporal del escaneo."
        }

        $file = Get-Item -LiteralPath $tempPath
        if ($file.Length -lt 1024) {
            throw "La imagen escaneada está vacía o incompleta."
        }

        if ($KeepDiagnosticCopies) {
            $diagnosticFolder = Join-Path $PSScriptRoot "ultimos-escaneos"
            if (!(Test-Path -LiteralPath $diagnosticFolder)) {
                New-Item -Path $diagnosticFolder -ItemType Directory | Out-Null
            }

            $diagnosticPath = Join-Path $diagnosticFolder ("scan_{0}.jpg" -f (Get-Date -Format "yyyyMMdd_HHmmss"))
            Copy-Item -LiteralPath $tempPath -Destination $diagnosticPath -Force
        }

        $bytes = [System.IO.File]::ReadAllBytes($tempPath)
        $base64 = [Convert]::ToBase64String($bytes)

        Write-Log ("Imagen JPG generada: {0:N0} bytes" -f $bytes.Length)
        if ($diagnosticPath) {
            Write-Log "Copia de diagnostico: $diagnosticPath"
        }

        return @{
            ok = $true
            mime = "image/jpeg"
            bytes = $bytes.Length
            diagnostic_copy = $diagnosticPath
            image = "data:image/jpeg;base64,$base64"
        }
    } finally {
        if (Test-Path -LiteralPath $tempPath) {
            Remove-Item -LiteralPath $tempPath -Force -ErrorAction SilentlyContinue
        }
    }
}

function Read-HttpRequest {
    param([System.Net.Sockets.NetworkStream] $Stream)

    $buffer = New-Object byte[] 65536
    $builder = New-Object System.Text.StringBuilder

    do {
        $read = $Stream.Read($buffer, 0, $buffer.Length)
        if ($read -le 0) { break }

        $chunk = [System.Text.Encoding]::UTF8.GetString($buffer, 0, $read)
        [void] $builder.Append($chunk)

        if ($builder.ToString().Contains("`r`n`r`n")) {
            break
        }
    } while ($Stream.DataAvailable)

    return $builder.ToString()
}

$listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Parse($HostAddress), $Port)
$listener.Start()

Write-Log "Servicio local de escaner iniciado en http://$HostAddress`:$Port/scan"
Write-Log "Mantenga esta ventana abierta mientras use el sistema."

try {
    while ($true) {
        $client = $listener.AcceptTcpClient()

        try {
            $stream = $client.GetStream()
            $request = Read-HttpRequest -Stream $stream
            $requestLine = ($request -split "`r`n")[0]
            $parts = $requestLine -split " "
            $method = $parts[0]
            $path = $parts[1]

            if ($method -eq "OPTIONS") {
                $response = New-OptionsResponse
            } elseif ($method -ne "POST") {
                $response = New-HttpResponse -StatusCode 405 -Body (ConvertTo-JsonText @{
                    ok = $false
                    message = "Use POST /scan."
                })
            } elseif ($path -notlike "/scan*") {
                $response = New-HttpResponse -StatusCode 404 -Body (ConvertTo-JsonText @{
                    ok = $false
                    message = "Ruta no encontrada."
                })
            } else {
                Write-Log "Solicitud de escaneo recibida."
                $scan = Invoke-WiaScan
                $response = New-HttpResponse -StatusCode 200 -Body (ConvertTo-JsonText $scan)
                Write-Log "Escaneo enviado al navegador."
            }

            $stream.Write($response, 0, $response.Length)
        } catch {
            $message = $_.Exception.Message
            Write-Log "Error: $message"

            try {
                $body = ConvertTo-JsonText @{
                    ok = $false
                    message = "No se pudo escanear: $message"
                }
                $response = New-HttpResponse -StatusCode 500 -Body $body
                $stream.Write($response, 0, $response.Length)
            } catch {
                # Nothing else can be done for this request.
            }
        } finally {
            $client.Close()
        }
    }
} finally {
    $listener.Stop()
}

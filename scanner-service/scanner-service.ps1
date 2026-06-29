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
        "Access-Control-Allow-Methods: GET, POST, OPTIONS",
        "Access-Control-Allow-Headers: Content-Type, Accept",
        "Access-Control-Allow-Private-Network: true",
        "Access-Control-Max-Age: 86400",
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

function New-BinaryHttpResponse {
    param(
        [int] $StatusCode = 200,
        [string] $ContentType = "image/jpeg",
        [byte[]] $Bytes = @()
    )

    $reason = switch ($StatusCode) {
        200 { "OK" }
        404 { "Not Found" }
        500 { "Internal Server Error" }
        default { "OK" }
    }

    $headers = @(
        "HTTP/1.1 $StatusCode $reason",
        "Content-Type: $ContentType",
        "Content-Length: $($Bytes.Length)",
        "Cache-Control: no-store, no-cache, must-revalidate",
        "Pragma: no-cache",
        "Access-Control-Allow-Origin: *",
        "Access-Control-Allow-Methods: GET, POST, OPTIONS",
        "Access-Control-Allow-Headers: Content-Type, Accept",
        "Access-Control-Allow-Private-Network: true",
        "Access-Control-Max-Age: 86400",
        "Connection: close",
        "",
        ""
    ) -join "`r`n"

    $headerBytes = [System.Text.Encoding]::ASCII.GetBytes($headers)
    $response = New-Object byte[] ($headerBytes.Length + $Bytes.Length)
    [System.Array]::Copy($headerBytes, 0, $response, 0, $headerBytes.Length)
    [System.Array]::Copy($Bytes, 0, $response, $headerBytes.Length, $Bytes.Length)

    return $response
}

function New-OptionsResponse {
    $headers = @(
        "HTTP/1.1 204 No Content",
        "Access-Control-Allow-Origin: *",
        "Access-Control-Allow-Methods: GET, POST, OPTIONS",
        "Access-Control-Allow-Headers: Content-Type, Accept",
        "Access-Control-Allow-Private-Network: true",
        "Access-Control-Max-Age: 86400",
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

function Get-DiagnosticFolder {
    return (Join-Path $PSScriptRoot "ultimos-escaneos")
}

function Get-LastScanPath {
    $diagnosticFolder = Get-DiagnosticFolder

    if (!(Test-Path -LiteralPath $diagnosticFolder)) {
        return $null
    }

    $file = Get-ChildItem -LiteralPath $diagnosticFolder -Filter "*.jpg" -File |
        Sort-Object LastWriteTime -Descending |
        Select-Object -First 1

    if ($null -eq $file) {
        return $null
    }

    return $file.FullName
}

function New-LastScanResponse {
    $lastScanPath = Get-LastScanPath

    if (!$lastScanPath -or !(Test-Path -LiteralPath $lastScanPath)) {
        return New-HttpResponse -StatusCode 404 -Body (ConvertTo-JsonText @{
            ok = $false
            message = "Todavia no hay una imagen escaneada para previsualizar."
        })
    }

    $bytes = [System.IO.File]::ReadAllBytes($lastScanPath)
    return New-BinaryHttpResponse -StatusCode 200 -ContentType "image/jpeg" -Bytes $bytes
}

function Get-Brightness {
    param([System.Drawing.Color] $Color)
    return (($Color.R * 0.299) + ($Color.G * 0.587) + ($Color.B * 0.114))
}

function Get-ColorDistance {
    param(
        [System.Drawing.Color] $A,
        [System.Drawing.Color] $B
    )

    $dr = $A.R - $B.R
    $dg = $A.G - $B.G
    $db = $A.B - $B.B

    return [Math]::Sqrt(($dr * $dr) + ($dg * $dg) + ($db * $db))
}

function Get-AutoCropRectangle {
    param(
        [System.Drawing.Bitmap] $Bitmap,
        [int] $Padding = 28
    )

    $width = $Bitmap.Width
    $height = $Bitmap.Height
    $cornerSamples = @(
        $Bitmap.GetPixel(4, 4),
        $Bitmap.GetPixel([Math]::Max(0, $width - 5), 4),
        $Bitmap.GetPixel(4, [Math]::Max(0, $height - 5)),
        $Bitmap.GetPixel([Math]::Max(0, $width - 5), [Math]::Max(0, $height - 5))
    )

    $avgR = [int](($cornerSamples | Measure-Object -Property R -Average).Average)
    $avgG = [int](($cornerSamples | Measure-Object -Property G -Average).Average)
    $avgB = [int](($cornerSamples | Measure-Object -Property B -Average).Average)
    $background = [System.Drawing.Color]::FromArgb($avgR, $avgG, $avgB)

    $minX = $width
    $minY = $height
    $maxX = 0
    $maxY = 0
    $step = [Math]::Max(2, [int][Math]::Floor([Math]::Max($width, $height) / 900))

    for ($y = 0; $y -lt $height; $y += $step) {
        for ($x = 0; $x -lt $width; $x += $step) {
            $pixel = $Bitmap.GetPixel($x, $y)
            $brightness = Get-Brightness $pixel
            $distance = Get-ColorDistance $pixel $background
            $saturation = $pixel.GetSaturation()

            $isUsefulPixel = ($distance -gt 22 -and $brightness -lt 248) -or
                ($brightness -lt 232) -or
                ($saturation -gt 0.12 -and $distance -gt 14)

            if ($isUsefulPixel) {
                if ($x -lt $minX) { $minX = $x }
                if ($y -lt $minY) { $minY = $y }
                if ($x -gt $maxX) { $maxX = $x }
                if ($y -gt $maxY) { $maxY = $y }
            }
        }
    }

    if ($minX -ge $maxX -or $minY -ge $maxY) {
        return [System.Drawing.Rectangle]::new(0, 0, $width, $height)
    }

    $minX = [Math]::Max(0, $minX - $Padding)
    $minY = [Math]::Max(0, $minY - $Padding)
    $maxX = [Math]::Min($width - 1, $maxX + $Padding)
    $maxY = [Math]::Min($height - 1, $maxY + $Padding)

    $cropWidth = [Math]::Max(1, $maxX - $minX + 1)
    $cropHeight = [Math]::Max(1, $maxY - $minY + 1)
    $cropArea = $cropWidth * $cropHeight
    $sourceArea = $width * $height

    if ($cropArea -lt ($sourceArea * 0.03) -or $cropWidth -lt 260 -or $cropHeight -lt 120) {
        return [System.Drawing.Rectangle]::new(0, 0, $width, $height)
    }

    return [System.Drawing.Rectangle]::new($minX, $minY, $cropWidth, $cropHeight)
}

function Save-OptimizedJpeg {
    param(
        [string] $SourcePath,
        [string] $TargetPath,
        [int] $MaxSide = 1500,
        [int] $Quality = 76,
        [bool] $AutoCrop = $false
    )

    Add-Type -AssemblyName System.Drawing

    $source = [System.Drawing.Bitmap]::FromFile($SourcePath)

    try {
        $crop = [System.Drawing.Rectangle]::new(0, 0, $source.Width, $source.Height)

        if ($AutoCrop) {
            $crop = Get-AutoCropRectangle -Bitmap $source
            Write-Log "Recorte automatico: x=$($crop.X), y=$($crop.Y), w=$($crop.Width), h=$($crop.Height)"
        }

        $scale = [Math]::Min(1, $MaxSide / [Math]::Max($crop.Width, $crop.Height))
        $width = [Math]::Max(1, [int][Math]::Round($crop.Width * $scale))
        $height = [Math]::Max(1, [int][Math]::Round($crop.Height * $scale))

        $bitmap = New-Object System.Drawing.Bitmap($width, $height)

        try {
            $graphics = [System.Drawing.Graphics]::FromImage($bitmap)

            try {
                $graphics.Clear([System.Drawing.Color]::White)
                $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
                $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
                $graphics.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
                $graphics.DrawImage(
                    $source,
                    [System.Drawing.Rectangle]::new(0, 0, $width, $height),
                    $crop,
                    [System.Drawing.GraphicsUnit]::Pixel
                )

                $codec = [System.Drawing.Imaging.ImageCodecInfo]::GetImageEncoders() |
                    Where-Object { $_.MimeType -eq "image/jpeg" } |
                    Select-Object -First 1

                $encoder = [System.Drawing.Imaging.Encoder]::Quality
                $encoderParams = New-Object System.Drawing.Imaging.EncoderParameters(1)
                $encoderParams.Param[0] = New-Object System.Drawing.Imaging.EncoderParameter($encoder, [int64] $Quality)

                if (Test-Path -LiteralPath $TargetPath) {
                    Remove-Item -LiteralPath $TargetPath -Force
                }

                $bitmap.Save($TargetPath, $codec, $encoderParams)
            } finally {
                $graphics.Dispose()
            }
        } finally {
            $bitmap.Dispose()
        }
    } finally {
        $source.Dispose()
    }
}

function Set-WiaPropertyValue {
    param(
        $Properties,
        [int] $PropertyId,
        $Value,
        [string] $Label
    )

    $property = $null

    try {
        foreach ($candidate in $Properties) {
            if ($candidate.PropertyID -eq $PropertyId) {
                $property = $candidate
                break
            }
        }

        if ($null -eq $property) {
            throw "La propiedad no existe en este dispositivo."
        }

        $property.Value = $Value
        Write-Log "WIA propiedad $Label ($PropertyId) = $Value"
        return $true
    } catch {
        Write-Log "WIA no permite fijar $Label ($PropertyId): $($_.Exception.Message)"
        return $false
    }
}

function Get-DefaultScannerDevice {
    $manager = New-Object -ComObject WIA.DeviceManager

    foreach ($deviceInfo in $manager.DeviceInfos) {
        if ($deviceInfo.Type -eq 1) {
            return @{
                manager = $manager
                device = $deviceInfo.Connect()
            }
        }
    }

    if ([System.Runtime.InteropServices.Marshal]::IsComObject($manager)) {
        [void][System.Runtime.InteropServices.Marshal]::FinalReleaseComObject($manager)
    }

    throw "No se encontró un escáner WIA instalado en este computador."
}

function Invoke-WiaFixedAreaScan {
    param(
        [int] $WidthMm = 92,
        [int] $HeightMm = 165,
        [int] $Dpi = 300
    )

    $jpegFormat = "{B96B3CAE-0728-11D3-9D7B-0000F81EF32E}"
    $scanner = $null
    $manager = $null
    $device = $null
    $item = $null
    $image = $null

    try {
        $scanner = Get-DefaultScannerDevice
        $manager = $scanner.manager
        $device = $scanner.device
        $item = $device.Items.Item(1)

        $widthPixels = [Math]::Max(1, [int][Math]::Round(($WidthMm / 25.4) * $Dpi))
        $heightPixels = [Math]::Max(1, [int][Math]::Round(($HeightMm / 25.4) * $Dpi))

        Write-Log "Escaneo de identidad: area $WidthMm x $HeightMm mm, $Dpi DPI, $widthPixels x $heightPixels px."

        # WIA scanner item property IDs. Some drivers expose only a subset.
        [void](Set-WiaPropertyValue -Properties $item.Properties -PropertyId 6146 -Value 1 -Label "Intento color")
        $resolutionOk = (Set-WiaPropertyValue -Properties $item.Properties -PropertyId 6147 -Value $Dpi -Label "Resolucion horizontal") -and
            (Set-WiaPropertyValue -Properties $item.Properties -PropertyId 6148 -Value $Dpi -Label "Resolucion vertical")
        $originOk = (Set-WiaPropertyValue -Properties $item.Properties -PropertyId 6149 -Value 0 -Label "Inicio horizontal") -and
            (Set-WiaPropertyValue -Properties $item.Properties -PropertyId 6150 -Value 0 -Label "Inicio vertical")
        $extentOk = (Set-WiaPropertyValue -Properties $item.Properties -PropertyId 6151 -Value $widthPixels -Label "Extension horizontal") -and
            (Set-WiaPropertyValue -Properties $item.Properties -PropertyId 6152 -Value $heightPixels -Label "Extension vertical")

        if (!$resolutionOk -or !$originOk -or !$extentOk) {
            throw "El controlador WIA no expone las propiedades necesarias para fijar automaticamente 92 x 165 mm."
        }

        $image = $item.Transfer($jpegFormat)

        if ($null -eq $image) {
            throw "WIA no devolvió imagen en el escaneo directo."
        }

        return $image
    } finally {
        foreach ($comObject in @($item, $device, $manager)) {
            if ($null -ne $comObject -and [System.Runtime.InteropServices.Marshal]::IsComObject($comObject)) {
                try {
                    [void][System.Runtime.InteropServices.Marshal]::FinalReleaseComObject($comObject)
                } catch {
                    # Ignore COM release errors.
                }
            }
        }
    }
}

function Invoke-WiaDialogScan {
    $jpegFormat = "{B96B3CAE-0728-11D3-9D7B-0000F81EF32E}"
    $scannerDeviceType = 1
    $intentUnspecified = 0
    $biasMaximizeQuality = 131072

    $dialog = New-Object -ComObject WIA.CommonDialog

    return @{
        dialog = $dialog
        image = $dialog.ShowAcquireImage(
            $scannerDeviceType,
            $intentUnspecified,
            $biasMaximizeQuality,
            $jpegFormat,
            $false,
            $true,
            $true
        )
    }
}

function Invoke-WiaScan {
    param(
        [bool] $AutoCrop = $false,
        [bool] $FixedScanArea = $false,
        [int] $PageWidthMm = 92,
        [int] $PageHeightMm = 165,
        [int] $Dpi = 300,
        [int] $Quality = 76,
        [int] $MaxSide = 1500
    )

    $jpegFormat = "{B96B3CAE-0728-11D3-9D7B-0000F81EF32E}"

    $scanId = [guid]::NewGuid().ToString("N")
    $rawPath = Join-Path $env:TEMP ("las_naves_scan_raw_{0}.jpg" -f $scanId)
    $tempPath = Join-Path $env:TEMP ("las_naves_scan_{0}.jpg" -f $scanId)
    $diagnosticPath = $null
    $dialog = $null
    $image = $null
    $processor = $null
    $jpegImage = $null

    try {
        if ($FixedScanArea) {
            try {
                $image = Invoke-WiaFixedAreaScan -WidthMm $PageWidthMm -HeightMm $PageHeightMm -Dpi $Dpi
            } catch {
                Write-Log "No se pudo usar area fija 92x165mm; se usara ventana WIA como respaldo. Detalle: $($_.Exception.Message)"
            }
        }

        if ($null -eq $image) {
            $dialogResult = Invoke-WiaDialogScan
            $dialog = $dialogResult.dialog
            $image = $dialogResult.image
        }

        if ($null -eq $image) {
            throw "El escaneo fue cancelado o no devolvió imagen."
        }

        foreach ($pathToDelete in @($rawPath, $tempPath)) {
            if (Test-Path -LiteralPath $pathToDelete) {
                Remove-Item -LiteralPath $pathToDelete -Force
            }
        }

        try {
            $processor = New-Object -ComObject WIA.ImageProcess
            $processor.Filters.Add($processor.FilterInfos.Item("Convert").FilterID) | Out-Null
            $processor.Filters.Item(1).Properties.Item("FormatID").Value = $jpegFormat
            $processor.Filters.Item(1).Properties.Item("Quality").Value = 90
            $jpegImage = $processor.Apply($image)

            if ($null -eq $jpegImage) {
                throw "WIA no pudo convertir la imagen escaneada a JPG."
            }

            $jpegImage.SaveFile($rawPath)
        } catch {
            Write-Log "WIA no pudo convertir directamente a JPG; se guardara la imagen original para optimizarla. Detalle: $($_.Exception.Message)"
            $image.SaveFile($rawPath)
        }

        try {
            Save-OptimizedJpeg -SourcePath $rawPath -TargetPath $tempPath -AutoCrop $AutoCrop -MaxSide $MaxSide -Quality $Quality
        } catch {
            Write-Log "No se pudo optimizar la imagen; se enviara la captura original. Detalle: $($_.Exception.Message)"
            Copy-Item -LiteralPath $rawPath -Destination $tempPath -Force
        }

        if (!(Test-Path -LiteralPath $tempPath)) {
            throw "WIA no generó el archivo temporal del escaneo."
        }

        $file = Get-Item -LiteralPath $tempPath
        if ($file.Length -lt 1024) {
            throw "La imagen escaneada está vacía o incompleta."
        }

        if ($KeepDiagnosticCopies) {
            $diagnosticFolder = Get-DiagnosticFolder
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
            preview_url = "http://127.0.0.1:$Port/last-scan.jpg?ts=$([DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds())"
            image = "data:image/jpeg;base64,$base64"
        }
    } finally {
        foreach ($comObject in @($jpegImage, $processor, $image, $dialog)) {
            if ($null -ne $comObject -and [System.Runtime.InteropServices.Marshal]::IsComObject($comObject)) {
                try {
                    [void][System.Runtime.InteropServices.Marshal]::FinalReleaseComObject($comObject)
                } catch {
                    # Ignore COM release errors; the next GC pass will clean up remaining handles.
                }
            }
        }

        [System.GC]::Collect()
        [System.GC]::WaitForPendingFinalizers()
        [System.GC]::Collect()

        foreach ($pathToDelete in @($rawPath, $tempPath)) {
            if (Test-Path -LiteralPath $pathToDelete) {
                Remove-Item -LiteralPath $pathToDelete -Force -ErrorAction SilentlyContinue
            }
        }
    }
}

function Test-IsBusyScannerError {
    param([string] $Message)

    if ([string]::IsNullOrWhiteSpace($Message)) {
        return $false
    }

    $normalized = $Message.ToUpperInvariant()
    return $normalized.Contains("OCUPADO") -or
        $normalized.Contains("BUSY") -or
        $normalized.Contains("EN USO") -or
        $normalized.Contains("WIA")
}

function Invoke-WiaScanWithRetry {
    param(
        [bool] $AutoCrop = $false,
        [bool] $FixedScanArea = $false,
        [int] $PageWidthMm = 92,
        [int] $PageHeightMm = 165,
        [int] $Dpi = 300,
        [int] $Quality = 76,
        [int] $MaxSide = 1500,
        [int] $MaxAttempts = 3
    )

    for ($attempt = 1; $attempt -le $MaxAttempts; $attempt++) {
        try {
            if ($attempt -gt 1) {
                Write-Log "Reintentando escaneo. Intento $attempt de $MaxAttempts."
            }

            return Invoke-WiaScan -AutoCrop $AutoCrop -FixedScanArea $FixedScanArea -PageWidthMm $PageWidthMm -PageHeightMm $PageHeightMm -Dpi $Dpi -Quality $Quality -MaxSide $MaxSide
        } catch {
            $message = $_.Exception.Message
            $isBusy = Test-IsBusyScannerError -Message $message

            if (!$isBusy -or $attempt -ge $MaxAttempts) {
                if ($isBusy) {
                    throw "El dispositivo WIA está ocupado. Cierre otras ventanas o aplicaciones de escaneo, espere unos segundos y vuelva a intentar. Si continúa, apague y encienda el escáner."
                }

                throw
            }

            Write-Log "WIA ocupado; liberando recursos y esperando antes de reintentar."
            [System.GC]::Collect()
            [System.GC]::WaitForPendingFinalizers()
            Start-Sleep -Seconds 3
        }
    }
}

function Read-HttpRequest {
    param([System.Net.Sockets.NetworkStream] $Stream)

    $Stream.ReadTimeout = 3000
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

    $requestText = $builder.ToString()
    $headersText = ($requestText -split "`r`n`r`n", 2)[0]
    $bodyText = if ($requestText.Contains("`r`n`r`n")) { ($requestText -split "`r`n`r`n", 2)[1] } else { "" }
    $contentLength = 0

    foreach ($headerLine in ($headersText -split "`r`n")) {
        if ($headerLine -match '^Content-Length:\s*(\d+)\s*$') {
            $contentLength = [int] $matches[1]
            break
        }
    }

    while ($contentLength -gt 0 -and ([System.Text.Encoding]::UTF8.GetByteCount($bodyText) -lt $contentLength)) {
        $remaining = $contentLength - [System.Text.Encoding]::UTF8.GetByteCount($bodyText)
        $read = $Stream.Read($buffer, 0, [Math]::Min($buffer.Length, $remaining))
        if ($read -le 0) { break }

        $chunk = [System.Text.Encoding]::UTF8.GetString($buffer, 0, $read)
        [void] $builder.Append($chunk)
        $bodyText += $chunk
    }

    return $builder.ToString()
}

function Get-RequestJson {
    param([string] $Request)

    if (!$Request.Contains("`r`n`r`n")) {
        return $null
    }

    $body = ($Request -split "`r`n`r`n", 2)[1]
    if ([string]::IsNullOrWhiteSpace($body)) {
        return $null
    }

    try {
        return ($body | ConvertFrom-Json)
    } catch {
        return $null
    }
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
            } elseif ($method -eq "GET" -and $path -like "/health*") {
                $response = New-HttpResponse -StatusCode 200 -Body (ConvertTo-JsonText @{
                    ok = $true
                    service = "las-naves-scanner"
                    scan_url = "http://127.0.0.1:$Port/scan"
                    preview_url = "http://127.0.0.1:$Port/last-scan.jpg"
                    last_scan = (Get-LastScanPath)
                })
            } elseif ($method -eq "GET" -and $path -like "/last-scan.jpg*") {
                $response = New-LastScanResponse
            } elseif ($method -ne "POST") {
                $response = New-HttpResponse -StatusCode 405 -Body (ConvertTo-JsonText @{
                    ok = $false
                    message = "Use POST /scan o GET /last-scan.jpg."
                })
            } elseif ($path -notlike "/scan*") {
                $response = New-HttpResponse -StatusCode 404 -Body (ConvertTo-JsonText @{
                    ok = $false
                    message = "Ruta no encontrada."
                })
            } else {
                Write-Log "Solicitud de escaneo recibida."
                $payload = Get-RequestJson -Request $request
                $autoCrop = $false
                $fixedScanArea = $false
                $pageWidthMm = 92
                $pageHeightMm = 165
                $dpi = 300
                $quality = 76
                $maxSide = 1500

                if ($null -ne $payload) {
                    $autoCrop = [bool]($payload.auto_crop -or $payload.autoCrop -or $payload.crop_document -or $payload.cropDocument)
                    $fixedScanArea = [bool]($payload.fixed_scan_area -or $payload.fixedScanArea)

                    if ($payload.page_width_mm -or $payload.pageWidthMm) {
                        $pageWidthMm = if ($payload.page_width_mm) { [int]$payload.page_width_mm } else { [int]$payload.pageWidthMm }
                    }

                    if ($payload.page_height_mm -or $payload.pageHeightMm) {
                        $pageHeightMm = if ($payload.page_height_mm) { [int]$payload.page_height_mm } else { [int]$payload.pageHeightMm }
                    }

                    if ($payload.dpi) {
                        $dpi = [int]$payload.dpi
                    }

                    if ($payload.jpeg_quality -or $payload.jpegQuality) {
                        $quality = if ($payload.jpeg_quality) { [int]$payload.jpeg_quality } else { [int]$payload.jpegQuality }
                    }

                    if ($payload.max_side -or $payload.maxSide) {
                        $maxSide = if ($payload.max_side) { [int]$payload.max_side } else { [int]$payload.maxSide }
                    }
                }

                if ($autoCrop) {
                    Write-Log "Recorte automatico solicitado por el navegador."
                }

                if ($fixedScanArea) {
                    Write-Log "Perfil de identidad solicitado: $pageWidthMm x $pageHeightMm mm, $dpi DPI."
                }

                $scan = Invoke-WiaScanWithRetry -AutoCrop $autoCrop -FixedScanArea $fixedScanArea -PageWidthMm $pageWidthMm -PageHeightMm $pageHeightMm -Dpi $dpi -Quality $quality -MaxSide $maxSide
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

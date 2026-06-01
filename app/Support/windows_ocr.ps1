param(
    [Parameter(Mandatory=$true)][string]$Path,
    [string]$Language = "es-ES",
    [int]$MaxPages = 3
)

$ErrorActionPreference = "Stop"

Add-Type -AssemblyName System.Runtime.WindowsRuntime
[Windows.Storage.StorageFile, Windows.Storage, ContentType = WindowsRuntime] | Out-Null
[Windows.Data.Pdf.PdfDocument, Windows.Data.Pdf, ContentType = WindowsRuntime] | Out-Null
[Windows.Data.Pdf.PdfPageRenderOptions, Windows.Data.Pdf, ContentType = WindowsRuntime] | Out-Null
[Windows.Storage.Streams.InMemoryRandomAccessStream, Windows.Storage.Streams, ContentType = WindowsRuntime] | Out-Null
[Windows.Graphics.Imaging.BitmapDecoder, Windows.Graphics.Imaging, ContentType = WindowsRuntime] | Out-Null
[Windows.Graphics.Imaging.SoftwareBitmap, Windows.Graphics.Imaging, ContentType = WindowsRuntime] | Out-Null
[Windows.Media.Ocr.OcrEngine, Windows.Foundation, ContentType = WindowsRuntime] | Out-Null
[Windows.Globalization.Language, Windows.Globalization, ContentType = WindowsRuntime] | Out-Null

function AwaitOperation($operation, [Type]$resultType) {
    $method = [System.WindowsRuntimeSystemExtensions].GetMethods() |
        Where-Object {
            $_.Name -eq "AsTask" -and
            $_.IsGenericMethodDefinition -and
            $_.GetParameters().Count -eq 1 -and
            $_.GetParameters()[0].ParameterType.Name -eq 'IAsyncOperation`1'
        } |
        Select-Object -First 1

    if ($null -eq $method) {
        throw "Unable to locate WinRT AsTask method for IAsyncOperation."
    }

    $task = $method.MakeGenericMethod($resultType).Invoke($null, @($operation))
    $task.Wait()
    return $task.Result
}

function AwaitAction($action) {
    $method = [System.WindowsRuntimeSystemExtensions].GetMethods() |
        Where-Object {
            $_.Name -eq "AsTask" -and
            -not $_.IsGenericMethodDefinition -and
            $_.GetParameters().Count -eq 1 -and
            $_.GetParameters()[0].ParameterType.Name -eq "IAsyncAction"
        } |
        Select-Object -First 1

    if ($null -eq $method) {
        throw "Unable to locate WinRT AsTask method for IAsyncAction."
    }

    $task = $method.Invoke($null, @($action))
    $task.Wait()
}

function Get-OcrTextFromStream($stream, $engine) {
    $decoder = AwaitOperation ([Windows.Graphics.Imaging.BitmapDecoder]::CreateAsync($stream)) ([Windows.Graphics.Imaging.BitmapDecoder])
    $bitmap = AwaitOperation ($decoder.GetSoftwareBitmapAsync()) ([Windows.Graphics.Imaging.SoftwareBitmap])

    if ($bitmap.BitmapPixelFormat -ne [Windows.Graphics.Imaging.BitmapPixelFormat]::Bgra8 -or
        $bitmap.BitmapAlphaMode -ne [Windows.Graphics.Imaging.BitmapAlphaMode]::Premultiplied) {
        $bitmap = [Windows.Graphics.Imaging.SoftwareBitmap]::Convert(
            $bitmap,
            [Windows.Graphics.Imaging.BitmapPixelFormat]::Bgra8,
            [Windows.Graphics.Imaging.BitmapAlphaMode]::Premultiplied
        )
    }

    $result = AwaitOperation ($engine.RecognizeAsync($bitmap)) ([Windows.Media.Ocr.OcrResult])
    return $result.Text
}

$resolvedPath = [System.IO.Path]::GetFullPath($Path)
if (-not [System.IO.File]::Exists($resolvedPath)) {
    throw "File not found: $resolvedPath"
}

$languageObject = [Windows.Globalization.Language]::new($Language)
$engine = [Windows.Media.Ocr.OcrEngine]::TryCreateFromLanguage($languageObject)
if ($null -eq $engine) {
    $engine = [Windows.Media.Ocr.OcrEngine]::TryCreateFromUserProfileLanguages()
}
if ($null -eq $engine) {
    throw "Windows OCR engine is not available."
}

$storageFile = AwaitOperation ([Windows.Storage.StorageFile]::GetFileFromPathAsync($resolvedPath)) ([Windows.Storage.StorageFile])
$extension = [System.IO.Path]::GetExtension($resolvedPath).ToLowerInvariant()
$texts = New-Object System.Collections.Generic.List[string]

if ($extension -eq ".pdf") {
    $document = AwaitOperation ([Windows.Data.Pdf.PdfDocument]::LoadFromFileAsync($storageFile)) ([Windows.Data.Pdf.PdfDocument])
    $pageCount = [Math]::Min([int]$document.PageCount, $MaxPages)

    for ($i = 0; $i -lt $pageCount; $i++) {
        $page = $document.GetPage([uint32]$i)
        $stream = [Windows.Storage.Streams.InMemoryRandomAccessStream]::new()
        $options = [Windows.Data.Pdf.PdfPageRenderOptions]::new()
        $options.DestinationWidth = [uint32]([Math]::Max(1200, $page.Size.Width * 3))
        AwaitAction ($page.RenderToStreamAsync($stream, $options))
        $stream.Seek(0) | Out-Null
        $texts.Add((Get-OcrTextFromStream $stream $engine))
        $page.Dispose()
        $stream.Dispose()
    }
} else {
    $readStream = AwaitOperation ($storageFile.OpenReadAsync()) ([Windows.Storage.Streams.IRandomAccessStreamWithContentType])
    $texts.Add((Get-OcrTextFromStream $readStream $engine))
    $readStream.Dispose()
}

($texts -join "`n---PAGE---`n")

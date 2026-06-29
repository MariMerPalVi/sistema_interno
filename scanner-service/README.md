# Servicio local de escaner

Este servicio permite que el sistema web solicite un escaneo desde el computador del asesor.

## Uso en cada computador

1. Conecte el escaner al computador.
2. Instale el controlador del escaner en Windows.
3. Abra `scanner-service\iniciar-escaner.bat`.
4. Mantenga la ventana abierta mientras use el sistema.
5. En el sistema web, presione `Escanear`.

El servicio queda escuchando en:

```env
http://127.0.0.1:8765/scan
```

Tambien publica la ultima imagen escaneada para previsualizacion en:

```env
http://127.0.0.1:8765/last-scan.jpg
```

Debe coincidir con la variable del sistema:

```env
SCANNER_SERVICE_URL=http://127.0.0.1:8765/scan
```

## Notas

- El servicio usa WIA de Windows, por eso funciona con escaneres que tengan controlador compatible.
- Si Windows muestra una ventana del escaner, seleccione el dispositivo y confirme el escaneo.
- Si el servicio no esta abierto, el sistema web mostrara una alerta y podra continuar con carga manual.
- `127.0.0.1` siempre significa el computador actual del asesor, por eso este servicio debe ejecutarse en cada equipo que vaya a escanear.
- Cada escaneo correcto guarda una copia de diagnostico en `scanner-service\ultimos-escaneos`. Si la hoja pasa pero el navegador no muestra imagen, revise primero esa carpeta.
- Si la hoja pasa pero no aparece en el sistema, abra `http://127.0.0.1:8765/last-scan.jpg` en el mismo computador del asesor. Si ahi se ve, cierre y abra nuevamente el servicio y actualice el sistema con `Ctrl + F5`.
- Si aparece `El dispositivo WIA no esta en linea`, apague y encienda el escaner, revise el cable/red y confirme que Windows pueda escanear desde la aplicacion `Fax y Escaner de Windows` o `Escaner`.
- Si aparece `El dispositivo WIA esta ocupado`, cierre cualquier ventana de escaneo abierta, espere unos segundos y vuelva a intentar. El servicio reintenta automaticamente hasta 3 veces, pero si Windows dejo bloqueado el equipo conviene cerrar `Fax y Escaner de Windows`, la aplicacion del fabricante o reiniciar el escaner.
- En cedula y papeleta, el sistema solicita recorte automatico para conservar solo el documento y evitar que entre toda la bandeja del escaner.
- En cedula y papeleta, el navegador solicita al servicio un perfil de identidad de `92 x 165 mm` a `300 DPI`. El servicio intenta fijar esas medidas directamente en WIA. Si el controlador no expone esas propiedades, usa la ventana WIA como respaldo; en ese caso seleccione manualmente `Sobre 6 3/4 - 92 x 165 mm`.
- Para mejorar el OCR, el perfil de identidad guarda JPG con mayor calidad que los documentos normales y conserva mas resolucion antes de armar el PDF.
- Las imagenes se reducen y recomprimen antes de enviarse al sistema para que los PDF del expediente no queden pesados.

## Prueba rapida

Con el servicio abierto, en PowerShell ejecute:

```powershell
Invoke-WebRequest -Uri "http://127.0.0.1:8765/scan" -Method POST -ContentType "application/json" -Body "{}"
```

Debe abrirse la ventana de escaneo. Si no se abre, revise el controlador del escaner.

Luego de escanear, pruebe la previsualizacion:

```powershell
Start-Process "http://127.0.0.1:8765/last-scan.jpg"
```

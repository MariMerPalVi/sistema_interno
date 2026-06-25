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
- Si aparece `El dispositivo WIA no esta en linea`, apague y encienda el escaner, revise el cable/red y confirme que Windows pueda escanear desde la aplicacion `Fax y Escaner de Windows` o `Escaner`.

## Prueba rapida

Con el servicio abierto, en PowerShell ejecute:

```powershell
Invoke-WebRequest -Uri "http://127.0.0.1:8765/scan" -Method POST -ContentType "application/json" -Body "{}"
```

Debe abrirse la ventana de escaneo. Si no se abre, revise el controlador del escaner.

# Sistema interno cooperativa

Aplicacion Laravel + Vite para procesos internos de una cooperativa. La primera etapa implementa el modulo funcional de **Apertura de cuentas**; los demas procesos se muestran como tarjetas visuales sin accion.

## Alcance funcional

- Panel principal con procesos internos.
- Wizard de apertura de cuentas.
- Tipos de cuenta configurables desde base de datos.
- Consentimiento de datos personales con descarga, carga de firmado y validacion manual obligatoria.
- Checklist de requisitos por tipo de cuenta.
- Carga/reemplazo de archivos PDF, JPG y PNG.
- Validacion de tamano maximo de 5 MB.
- Extraccion preliminar desde texto incorporado en PDF. El OCR de imagenes queda desactivado temporalmente con `OCR_ENABLED=false`.
- Lista de control externa con enlaces oficiales y un PDF consolidado con evidencias.
- Documentos internos separados entre manuales y documentos generados por el sistema.
- Seleccion de servicios adicionales y documentos firmados por servicio.
- Check List del expediente con documentos adjuntados e historial de auditoria.
- Revision digital automatica del expediente, con estado aprobado u observado, puntaje y hallazgos.
- Escaneo local asistido mediante servicio Windows en cada computador del asesor.

## Requisitos locales

- PHP 8.2 o superior. En XAMPP: `C:\xampp\php\php.exe`.
- Composer.
- Node.js y npm. En PowerShell use `npm.cmd` si `npm` esta bloqueado por politica de scripts.
- MySQL/MariaDB de XAMPP.

## Instalacion

1. Crear la base de datos:

```sql
CREATE DATABASE sistema_interno CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Copiar el archivo de entorno:

```powershell
Copy-Item .env.example .env
```

3. Instalar dependencias PHP:

```powershell
C:\xampp\php\php.exe -d extension=zip composer.phar install
```

Si Composer esta instalado globalmente:

```powershell
composer install
```

4. Generar clave de aplicacion:

```powershell
C:\xampp\php\php.exe artisan key:generate
```

5. Ejecutar migraciones y seeders:

```powershell
C:\xampp\php\php.exe artisan migrate --seed
```

Si este comando falla con conexion rechazada, inicie MySQL/MariaDB desde el panel de XAMPP y confirme que exista la base `sistema_interno`.

6. Instalar dependencias frontend y compilar:

```powershell
npm.cmd install
npm.cmd run build
```

7. Abrir el sistema:

```text
http://localhost/sistema_interno/public
```

## Escaner local

Para usar el boton `Escanear`, cada computador que tenga escaner debe ejecutar:

```text
scanner-service\iniciar-escaner.bat
```

Ese servicio local responde en:

```env
SCANNER_SERVICE_URL=http://127.0.0.1:8765/scan
```

Mantenga la ventana del servicio abierta mientras use el sistema. Si el servicio no esta abierto o el escaner no responde, el sistema permite continuar con carga manual de archivos.

## Desarrollo con Vite

En una terminal:

```powershell
npm.cmd run dev
```

En otra terminal, si prefiere servir Laravel sin Apache:

```powershell
C:\xampp\php\php.exe artisan serve
```

## Credencial demo

El seeder crea un usuario de referencia para la futura autenticacion:

- Correo: `asesor@cooperativa.local`
- Clave: `secret123`

La autenticacion formal queda preparada a nivel de tablas y roles, pero no se activo login en esta primera version para facilitar la demostracion del flujo.

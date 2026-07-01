# Operación, seguridad y mantenimiento

## Roles principales

- **Administrador:** acceso global a expedientes, reportes, salud del sistema y restablecimiento de contraseñas.
- **Asesor:** acceso solo a expedientes de su agencia asignada.
- **Abogada:** acceso exclusivo al control de consentimientos de datos personales.

## Rutas sensibles

- `/aperturas`: requiere sesión, usuario activo, contraseña cambiada y permiso de apertura de cuentas.
- `/consentimientos`: requiere sesión y rol de abogada o administrador.
- `/reportes`: requiere administrador.
- `/salud-sistema`: requiere administrador.
- `/recursos/*`: pasa por controlador protegido; no debe exponerse por enlaces públicos directos.

## Documentos privados

Los documentos de expedientes deben permanecer en `storage/app/private`. No deben copiarse a `public` salvo formatos institucionales sin datos personales.

Si se agrega una nueva descarga, debe pasar por un controlador que valide:

- usuario autenticado;
- usuario activo;
- permiso por rol;
- agencia del expediente, salvo administrador;
- ruta real dentro del almacenamiento esperado.

## Auditoría

El sistema registra acciones relevantes en `action_histories`, incluyendo:

- usuario;
- rol;
- agencia;
- fecha y hora;
- IP;
- navegador;
- acción;
- expediente o documento relacionado.

## Contraseñas

Los usuarios nuevos quedan con `must_change_password = true`. Al iniciar sesión deben cambiar la clave antes de usar módulos internos.

El administrador puede restablecer contraseña temporal desde ruta protegida:

```bash
PUT /usuarios/{user}/contrasena-temporal
```

## Reportes

El administrador puede entrar a `/reportes` para filtrar por:

- rango de fechas;
- agencia;
- tipo de cuenta;
- usuario;
- estado.

## Salud del sistema

El administrador puede entrar a `/salud-sistema` para revisar:

- conexión a base de datos;
- almacenamiento privado;
- formatos institucionales;
- URL del servicio local de escáner;
- `APP_DEBUG`.

## Comandos después de actualizar

```bash
php artisan migrate
php artisan optimize:clear
php artisan route:list --except-vendor
vendor/bin/phpunit
```

En Windows con XAMPP:

```powershell
C:\xampp\php\php.exe artisan migrate
C:\xampp\php\php.exe artisan optimize:clear
C:\xampp\php\php.exe artisan route:list --except-vendor
vendor\bin\phpunit.bat
```

## Revisión manual pendiente

- Confirmar que `APP_DEBUG=false` en producción.
- Confirmar que `FILESYSTEM_SERVE=false` en producción.
- Validar que cada usuario tenga agencia correcta.
- Eliminar periódicamente temporales del escáner que no pertenezcan a expedientes.

<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$path = dirname(__DIR__).'/app/private/aperturas/Matriz - Las Naves/Temporales/AP-2606-165950/1. Cedula titular_AP-2606-165950.pdf';
$file = new Illuminate\Http\UploadedFile($path, basename($path), 'application/pdf', null, true);
$data = app(App\Services\DocumentExtractionService::class)->extract('cedula-papeleta', $file, true);

print_r(array_intersect_key($data, array_flip(['cedula', 'nombres_apellidos', 'nombres', 'apellidos', 'nacionalidad'])));

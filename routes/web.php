<?php

use App\Http\Controllers\AccountOpeningController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConsentReviewController;
use App\Http\Controllers\DataUpdateController;
use App\Http\Controllers\OperationalReportController;
use App\Http\Controllers\ProcessController;
use App\Http\Controllers\ProtectedAssetController;
use App\Http\Controllers\SystemHealthController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/ingresar', [AuthController::class, 'create'])->name('login');
    Route::post('/ingresar', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'user.active'])->group(function () {
    Route::post('/salir', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/mi-contrasena', [AuthController::class, 'editPassword'])->name('password.edit');
    Route::put('/mi-contrasena', [AuthController::class, 'updatePassword'])->name('password.update');
    Route::put('/usuarios/{user}/contrasena-temporal', [AuthController::class, 'resetTemporaryPassword'])
        ->middleware('password.changed')
        ->name('users.password.reset-temporary');

    Route::middleware('password.changed')->group(function () {
    Route::get('/', [ProcessController::class, 'index'])->name('processes.index');
    Route::get('/reportes', [OperationalReportController::class, 'index'])->name('reports.index');
    Route::get('/salud-sistema', [SystemHealthController::class, 'index'])->name('system-health.index');
    Route::get('/consentimientos', [ConsentReviewController::class, 'index'])
        ->middleware('review-consents')
        ->name('consents.index');
    Route::get('/recursos/firmas-certificado/{authority}', [ProtectedAssetController::class, 'certificateSignature'])
        ->middleware('account-openings')
        ->whereIn('authority', ['presidente', 'gerente'])
        ->name('protected-assets.certificate-signature');

    Route::prefix('actualizacion-datos')->name('data-updates.')->middleware('data-updates')->group(function () {
        Route::get('/', [DataUpdateController::class, 'index'])->name('index');
        Route::post('/', [DataUpdateController::class, 'store'])->name('store');
        Route::get('/{update}', [DataUpdateController::class, 'show'])->name('show');
        Route::post('/{update}/datos', [DataUpdateController::class, 'updateData'])->name('data');
        Route::post('/{update}/documentos', [DataUpdateController::class, 'uploadDocument'])->name('documents');
        Route::post('/{update}/finalizar', [DataUpdateController::class, 'submit'])->name('submit');
    });

    Route::prefix('aperturas')->name('accounts.')->group(function () {
        Route::get('/{opening}/consentimiento/ver', [AccountOpeningController::class, 'previewConsent'])->name('consent.preview');
    });

    Route::prefix('aperturas')->name('accounts.')->middleware('account-openings')->group(function () {
        Route::get('/crear/{accountType?}', [AccountOpeningController::class, 'create'])->name('create');
        Route::post('/', [AccountOpeningController::class, 'store'])->name('store');
        Route::get('/{opening}', [AccountOpeningController::class, 'show'])->name('show');
        Route::get('/{opening}/consentimiento/editar', [AccountOpeningController::class, 'editConsentDocument'])->name('consent.edit');
        Route::get('/{opening}/consentimiento', fn ($opening) => redirect()->route('accounts.show', [$opening, 'paso' => 'requisitos']));
        Route::get('/{opening}/documentos', fn ($opening) => redirect()->route('accounts.show', [$opening, 'paso' => 'requisitos']));
        Route::get('/{opening}/consulta-externa', fn ($opening) => redirect()->route('accounts.show', [$opening, 'paso' => 'externas']));
        Route::get('/{opening}/nombre-expediente', fn ($opening) => redirect()->route('accounts.show', [$opening, 'paso' => 'expediente']));
        Route::get('/{opening}/documentos-internos', fn ($opening) => redirect()->route('accounts.show', [$opening, 'paso' => 'internos']));
        Route::get('/{opening}/servicios', fn ($opening) => redirect()->route('accounts.show', [$opening, 'paso' => 'servicios']));
        Route::get('/{opening}/enviar-revision', fn ($opening) => redirect()->route('accounts.show', [$opening, 'paso' => 'resumen']));
        Route::post('/{opening}/socio', [AccountOpeningController::class, 'updateMember'])->name('member.update');
        Route::post('/{opening}/conyuge', [AccountOpeningController::class, 'updateSpouseRequirement'])->name('spouse.update');
        Route::post('/{opening}/requisitos-opcionales', [AccountOpeningController::class, 'updateOptionalRequirements'])->name('optional-requirements.update');
        Route::post('/{opening}/consentimiento', [AccountOpeningController::class, 'uploadConsent'])->name('consent.upload');
        Route::post('/{opening}/consentimiento/escanear', [AccountOpeningController::class, 'uploadScannedConsent'])->name('consent.scan');
        Route::post('/{opening}/documentos', [AccountOpeningController::class, 'uploadRequirement'])->name('requirements.upload');
        Route::post('/{opening}/documentos/escanear', [AccountOpeningController::class, 'uploadScannedRequirement'])->name('requirements.scan');
        Route::post('/{opening}/documentos/{document}/extraer', [AccountOpeningController::class, 'extractRequirementData'])->name('requirements.extract');
        Route::post('/{opening}/consulta-externa', [AccountOpeningController::class, 'uploadExternalEvidence'])->name('external.upload');
        Route::post('/{opening}/nombre-expediente', [AccountOpeningController::class, 'confirmFileName'])->name('file-name.update');
        Route::get('/{opening}/documentos-internos/{template}/generar', [AccountOpeningController::class, 'generateInternalDocument'])->name('internal.generate');
        Route::get('/{opening}/documentos-internos/{template}/original', [AccountOpeningController::class, 'showInternalOriginal'])->name('internal.original');
        Route::post('/{opening}/documentos-internos', [AccountOpeningController::class, 'uploadInternalDocument'])->name('internal.upload');
        Route::post('/{opening}/documentos-internos/escanear', [AccountOpeningController::class, 'uploadScannedInternalDocument'])->name('internal.scan');
        Route::get('/{opening}/servicios/documentos/{template}/generar', [AccountOpeningController::class, 'generateServiceDocument'])->name('services.documents.generate');
        Route::get('/{opening}/servicios/documentos/{template}/original', [AccountOpeningController::class, 'showServiceOriginal'])->name('services.documents.original');
        Route::post('/{opening}/servicios/documentos', [AccountOpeningController::class, 'uploadServiceDocument'])->name('services.documents.upload');
        Route::post('/{opening}/servicios/documentos/escanear', [AccountOpeningController::class, 'uploadScannedServiceDocument'])->name('services.documents.scan');
        Route::post('/{opening}/servicios', [AccountOpeningController::class, 'saveServices'])->name('services.save');
        Route::post('/{opening}/enviar-revision', [AccountOpeningController::class, 'submitReview'])->name('submit');
    });
    });
});

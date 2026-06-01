<?php

use App\Http\Controllers\AccountOpeningController;
use App\Http\Controllers\ProcessController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ProcessController::class, 'index'])->name('processes.index');

Route::prefix('aperturas')->name('accounts.')->group(function () {
    Route::get('/crear/{accountType?}', [AccountOpeningController::class, 'create'])->name('create');
    Route::post('/', [AccountOpeningController::class, 'store'])->name('store');
    Route::get('/{opening}', [AccountOpeningController::class, 'show'])->name('show');
    Route::get('/{opening}/consentimiento/ver', [AccountOpeningController::class, 'previewConsent'])->name('consent.preview');
    Route::get('/{opening}/consentimiento', fn ($opening) => redirect()->route('accounts.show', [$opening, 'paso' => 'consentimiento']));
    Route::get('/{opening}/documentos', fn ($opening) => redirect()->route('accounts.show', [$opening, 'paso' => 'requisitos']));
    Route::get('/{opening}/consulta-externa', fn ($opening) => redirect()->route('accounts.show', [$opening, 'paso' => 'externas']));
    Route::get('/{opening}/documentos-internos', fn ($opening) => redirect()->route('accounts.show', [$opening, 'paso' => 'internos']));
    Route::get('/{opening}/servicios', fn ($opening) => redirect()->route('accounts.show', [$opening, 'paso' => 'servicios']));
    Route::get('/{opening}/enviar-revision', fn ($opening) => redirect()->route('accounts.show', [$opening, 'paso' => 'resumen']));
    Route::post('/{opening}/socio', [AccountOpeningController::class, 'updateMember'])->name('member.update');
    Route::post('/{opening}/conyuge', [AccountOpeningController::class, 'updateSpouseRequirement'])->name('spouse.update');
    Route::post('/{opening}/consentimiento', [AccountOpeningController::class, 'uploadConsent'])->name('consent.upload');
    Route::post('/{opening}/documentos', [AccountOpeningController::class, 'uploadRequirement'])->name('requirements.upload');
    Route::post('/{opening}/consulta-externa', [AccountOpeningController::class, 'uploadExternalEvidence'])->name('external.upload');
    Route::post('/{opening}/documentos-internos/{template}/generar', [AccountOpeningController::class, 'generateInternalDocument'])->name('internal.generate');
    Route::post('/{opening}/documentos-internos', [AccountOpeningController::class, 'uploadInternalDocument'])->name('internal.upload');
    Route::post('/{opening}/servicios/documentos', [AccountOpeningController::class, 'uploadServiceDocument'])->name('services.documents.upload');
    Route::post('/{opening}/servicios', [AccountOpeningController::class, 'saveServices'])->name('services.save');
    Route::post('/{opening}/enviar-revision', [AccountOpeningController::class, 'submitReview'])->name('submit');
});

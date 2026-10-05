<?php

use App\Http\Controllers\WhatsAppAddonsController;
use Illuminate\Support\Facades\Route;


 //whatsapp-addons
    Route::group(['prefix' => 'whatsapp-addons', 'middleware' => 'permission:whatsapp_addons'], function () {
        Route::get('/', [WhatsAppAddonsController::class, 'index'])->name('whatsappAddons.index');
        Route::post('/verfication-submit', [WhatsAppAddonsController::class, 'verification_submit'])->name('whatsappAddons.verfication-submit');
        Route::post('/whatsapp-files', [WhatsAppAddonsController::class, 'whatsapp_files_uploads'])->name('whatsappAddons.whatsapp_files_uploads');
    });

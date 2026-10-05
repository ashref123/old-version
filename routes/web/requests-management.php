<?php

use App\Http\Controllers\TripRequestController;
use App\Http\Controllers\DeliveryRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', config('jetstream.auth_session')])->group(function () {

    //  trip ride  request
    Route::group(['prefix' => 'rides-request'], function () {
        Route::middleware(['permission:trip-request-view'])->get('/', [TripRequestController::class, 'ridesRequest'])->name('triprequest.ridesRequest');
        Route::middleware('remove_empty_query')->get('/list', [TripRequestController::class, 'list'])->name('triprequest.list');
        Route::post('/driver/{driver}', [TripRequestController::class, 'driverFind'])->name('triprequest.driverFind');
        Route::get('/view/{requestmodel}', [TripRequestController::class, 'viewDetails'])->name('triprequest.viewDetails');
        Route::get('/dossier/{requestmodel}', [TripRequestController::class, 'dossier'])->name('triprequest.dossier');
        Route::get('/report/{requestmodel}', [TripRequestController::class, 'downloadRideReport'])->name('triprequest.report');
        Route::get('/export-pricing/{requestmodel}', [TripRequestController::class, 'exportPricing'])->name('triprequest.exportPricing');
        Route::get('/export-logs/{requestmodel}', [TripRequestController::class, 'exportLogs'])->name('triprequest.exportLogs');
        Route::get('/cancel/{requestmodel}', [TripRequestController::class, 'cancelRide'])->name('triprequest.cancel');
        Route::get('/detail/{request}', [TripRequestController::class, 'sosDetail'])->name('triprequest.sosDetail');
        Route::get('/download-invoice/{requestmodel}', [TripRequestController::class, 'downloadInvoice']);

        // send invoice mail
        Route::post('/send-invoice-mail/{requestmodel}', [TripRequestController::class, 'sendInvoicemail']);
    });

    //  delivery ride request
    Route::group(['prefix' => 'delivery-rides-request'], function () {
        Route::middleware(['permission:manage-delivery-request'])->get('/', [DeliveryRequestController::class, 'ridesRequest'])->name('deliveryTriprequest.ridesRequest');
        Route::middleware('remove_empty_query')->get('/list', [DeliveryRequestController::class, 'list'])->name('deliveryTriprequest.list');
        Route::post('/driver/{driver}', [DeliveryRequestController::class, 'driverFind'])->name('deliveryTriprequest.driverFind');
        Route::get('/view/{requestmodel}', [DeliveryRequestController::class, 'viewDetails'])->name('deliveryTriprequest.viewDetails');
        Route::get('/cancel/{requestmodel}', [DeliveryRequestController::class, 'cancelRide'])->name('deliveryTriprequest.cancel');
        Route::get('/download-invoice/{requestmodel}', [DeliveryRequestController::class, 'downloadInvoice']);
    });



    // ongoing rides
    Route::group(['prefix' => 'ongoing-rides'], function () {
        Route::middleware(['permission:ongoing-request-view'])->get('/', [TripRequestController::class, 'ongoingRidesRequest'])->name('triprequest.ongoingRides');
        Route::get('/find-ride/{request}', [TripRequestController::class, 'ongoingRideDetail'])->name('triprequest.ongoingRideDetail');
        Route::get('/assign/{request}', [TripRequestController::class, 'assignView'])->name('triprequest.assignView');
        Route::post('/assign-driver/{requestmodel}', [TripRequestController::class, 'assignDriver'])->name('triprequest.assignDriver');
        Route::get('/reassign/{request}', [TripRequestController::class, 'reassignView'])->name('triprequest.reassignView');
        Route::post('/reassign-driver/{requestmodel}', [TripRequestController::class, 'reassignDriver'])->name('triprequest.reassignDriver');
        Route::post('/unassign-driver/{requestmodel}', [TripRequestController::class, 'unassignDriver'])->name('triprequest.unassignDriver');
    });

    // Dispatch monitor (searching rides debugger)
    Route::group(['prefix' => 'dispatch-monitor', 'middleware' => 'permission:dispatch-monitor-view'], function () {
        Route::get('/', [\App\Http\Controllers\DispatchMonitorController::class, 'index'])->name('dispatchMonitor.index');
        // Use /rides (not /searching) so it cannot collide with /{request} binding.
        Route::get('/rides', [\App\Http\Controllers\DispatchMonitorController::class, 'searching'])->name('dispatchMonitor.searching');
        Route::get('/searching', [\App\Http\Controllers\DispatchMonitorController::class, 'searching']); // legacy alias
        Route::get('/users', [\App\Http\Controllers\DispatchMonitorController::class, 'searchUsers'])->name('dispatchMonitor.users');
        Route::get('/{request}', [\App\Http\Controllers\DispatchMonitorController::class, 'show'])->name('dispatchMonitor.show');
        Route::post('/{request}/re-search', [\App\Http\Controllers\DispatchMonitorController::class, 'reSearch'])->name('dispatchMonitor.reSearch');
    });


    
});
    // Track Request
    Route::get('/track/request/{request}', [TripRequestController::class, 'trackRequest'])->name('triprequest.trackRequest');
    Route::get('/download-user-invoice/{requestmodel}', [TripRequestController::class, 'downloadUserInvoice']);
    Route::get('/download-driver-invoice/{requestmodel}', [TripRequestController::class, 'downloadDriverInvoice']);

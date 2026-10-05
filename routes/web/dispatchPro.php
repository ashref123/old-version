<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DispatcherProController;
use App\Http\Controllers\Web\Admin\DispatcherProCreateRequestController ;
use App\Http\Controllers\Api\V1\Request\EtaController;
use App\Http\Controllers\Web\Admin\SubscriptionController;
use App\Http\Controllers\DriverManagementController;
use App\Http\Controllers\Api\V1\Request\PromoCodeController;

Route::middleware(['auth:sanctum', config('jetstream.auth_session')])->group(function () {


Route::group(['prefix' => 'dispatcher-pro'], function () {
    Route::middleware(['permission:dispatcher-drivers'])->get('/godeye', [DispatcherProController::class, 'godseye'])->name('dispatcher-pro.godeye');
    Route::middleware(['permission:dispatcher-ride'])->get('/bookride', [DispatcherProController::class, 'bookride'])->name('dispatcher-pro.dashboard');
    Route::get('/advanced', [DispatcherProController::class, 'advanced'])->name('dispatcher-pro.advanced');

    //ride request
    Route::middleware(['permission:dispatcher-ride-request-view'])->get('/rides_request', [DispatcherProController::class, 'rideRequest'])->name('dispatcher-pro.rideRequest');
    Route::get('/rides_request/list', [DispatcherProController::class, 'list'])->name('dispatcher-pro.rideRequest.list');
    Route::post('/rides_request/driver/{driver}', [DispatcherProController::class, 'driverFind'])->name('dispatcher-pro.triprequest.driverFind');
    Route::middleware(['permission:dispatcher-ride-request-view'])->get('/rides_request/view/{requestmodel}', [DispatcherProController::class, 'viewDetails'])->name('dispatcher-pro.triprequest.viewDetails');
    Route::get('/rides_request/cancel/{requestmodel}', [DispatcherProController::class, 'cancelRide'])->name('dispatcher-pro.triprequest.cancel');
    Route::get('/rides_request/download-invoice/{requestmodel}', [DispatcherProController::class, 'downloadInvoice']);

    Route::middleware(['permission:dispatcher-ongoing-request-view'])->get('/ongoing_request', [DispatcherProController::class, 'ongoingRequest'])->name('dispatcher-pro.dispatch.ongoingRequest');
    Route::get('/ongoing_request/find-ride/{request}', [DispatcherProController::class, 'ongoingRideDetail'])->name('dispatcher-pro.dispatch.ongoingRideDetail');
    Route::middleware(['permission:dispatcher-ride-request-assign'])->get('/ongoing_request/assign/{request}', [DispatcherProController::class, 'assignView'])->name('dispatcher-pro.dispatch.assignView');
    Route::post('/ongoing_request/assign-driver/{requestmodel}', [DispatcherProController::class, 'assignDriver'])->name('dispatcher-pro.dispatch.assignDriver');
    Route::get('/schedule_rides', [DispatcherProController::class, 'scheduleRides'])->name('dispatcher-pro.dispatch.scheduleRides');
    Route::get('/schedule_rides/list', [DispatcherProController::class, 'scheduleRideslist'])->name('dispatcher-pro.rideRequest.scheduleRideslist');

    Route::post('/update-request/{requestmodel}',[DispatcherProController::class,'updateRequest']);
    Route::post('/update-trip-start-time/{requestmodel}',[DispatcherProController::class,'updateTripStartTime']);
     Route::get('/driver/view-profile/{driver}', [DispatcherProController::class, 'viewProfile'])->name('dispatcher-pro.driver.viewProfile');
     Route::middleware('remove_empty_query')->get('/withdrawal-list', [DispatcherProController::class, 'WithdrawalRequestDriversList'])->name('dispatcher-pro.withdrawalrequestdrivers.list');  
     Route::get('/drivers-rating/request-list/{driver}', [DispatcherProController::class, 'driverRatinghistory'])->name('dispatcher-pro.driversRequestRating.history'); 
     Route::middleware('remove_empty_query')->get('/driver-plan-list/{driver}',[SubscriptionController::class, 'driverSubscriptionList']);
    Route::get('/document/list/{driver}', [DriverManagementController::class, 'driverDocumentList'])->name('dispatcher-pros.approveddriver.driverDocumentList');

    Route::post('/end-request', [DispatcherProController::class, 'endRequest']);

    Route::middleware(['permission:dispatcher-dashboard'])->get('/dashboard', [DispatcherProController::class, 'dispatcherDashboard'])->name('dispatcherPro.dashboard');
    Route::get('/dashboard/today-earnings', [DispatcherProController::class, 'todayEarnings'])->name('dispatcher-pro.dashboard-todayEarnings');
    Route::get('/dashboard/overall-earnings', [DispatcherProController::class, 'overallEarnings'])->name('dispatcher-pro.dashboard-overallEarnings');
    Route::get('/dashboard/cancel-chart', [DispatcherProController::class, 'cancelChart'])->name('dispatcher-pro.dashboard-cancelChart');
    Route::get('/unassigned-rides', [DispatcherProController::class, 'unassignedRides'])->name('dispatcher-pro.unassignedRides');
    Route::get('/dashboard/request-enquires-chart', [DispatcherProController::class, 'requestEnquiriesChart'])->name('dispatcher-pro.requestEnquiriesChart');


    // request enquiries
    Route::middleware(['permission:dispatcher-request-enquiry'])->get('/request_enquiries', [DispatcherProController::class, 'requestEnquiries'])->name('dispatcher-pro.requestEnquiries');
    Route::get('/request_enquiries/list', [DispatcherProController::class, 'requestEnquiriesList'])->name('dispatcher-pro.request_enquiries.requestEnquiriesList');
    Route::get('/request_enquiries/delete/{requestEnquiry}', [DispatcherProController::class, 'deleteEnquiry'])->name('dispatcher-pro.request_enquiries.deleteEnquiry');


    // chat
    Route::middleware(['permission:dispatcher-chat'])->get('/chat', [DispatcherProController::class, 'chatIndex'])->name('dispatchProChat.index');     
    Route::get('/chat/messages/{conversationId}', [DispatcherProController::class, 'chatMessages'])->name('dispatchProChat.chatMessages');    
    Route::post('/chat/createChat', [DispatcherProController::class, 'chatcreate'])->name('dispatchProChat.chatcreate');
    Route::post('/chat/send-admin', [DispatcherProController::class, 'chatsendAdmin'])->name('dispatchProChat.chatsendAdmin');
    Route::post('/chat/close-chat', [DispatcherProController::class, 'chatclose'])->name('dispatchProChat.chatclose');
    Route::get('/chat/fetchChat', [DispatcherProController::class, 'chatsfetch'])->name('dispatchProChat.chatsfetch');
    Route::get('/chat/readAll', [DispatcherProController::class, 'readAllChat'])->name('dispatchProChat.readAllChat');
    Route::get('/chat/search-user', [DispatcherProController::class, 'chatsearchDriver'])->name('dispatchProChat.chatsearchDriver');
    Route::get('/chat/verify-chat', [DispatcherProController::class, 'chatVerify'])->name('dispatchProChat.chatVerify');    
    Route::get('/chat/fetch-driver', [DispatcherProController::class, 'chatfetchDriver'])->name('dispatchProChat.chatfetchDriver');


     //  delivery ride request
        Route::group(['prefix' => 'delivery-rides-request'], function () {
            Route::middleware(['permission:dispatcher-delivery-request'])->get('/', [DispatcherProController::class, 'ridesRequest'])->name('dispatch.ridesRequest');
            Route::middleware('remove_empty_query')->get('/list', [DispatcherProController::class, 'deliveryList'])->name('dispatch.list');
        });


});
});

Route::group(['prefix' => 'dispatch-pro'], function () {
    Route::get('/', [DispatcherProController::class, 'bookingRequest'])->name('dispatcher-pro.index');
    Route::post('/create-request',[DispatcherProCreateRequestController ::class,'createRequest']);
    Route::middleware('auth:sanctum')->post('request/eta',[EtaController::class,'eta']);
    Route::post('request/list_packages',[EtaController::class,'listPackages']);
    Route::post('serviceVerify', [EtaController::class,'serviceVerify']);
    Route::get('/dispatchLocations', [DispatcherProCreateRequestController ::class,'dispatchLocations']);
    
    Route::get('fetch-user-detail',[DispatcherProController::class,'fetchUserIfExists']);

    Route::post('/request-enquiry',[DispatcherProCreateRequestController ::class,'dispatcherEnquiries']);
    Route::get('promocode-list', [PromoCodeController::class, 'index']);
    Route::post('promocode-redeem', [PromoCodeController::class, 'redeem']);
    Route::post('promocode-clear', [PromoCodeController::class, 'clear']);

});
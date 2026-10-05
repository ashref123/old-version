<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\Web\Admin\AgentCreateRequestController;
use App\Http\Controllers\Api\V1\Request\EtaController;
use App\Http\Controllers\Web\Admin\SubscriptionController;
use App\Http\Controllers\DriverManagementController;
use App\Http\Controllers\Api\V1\Request\PromoCodeController;

Route::middleware(['auth:sanctum', config('jetstream.auth_session')])->group(function () {


    Route::group(['prefix' => 'agent', 'middleware' => ['agent_addons']], function () {
        Route::middleware(['permission:agent-ride'])->get('/bookride', [AgentController::class, 'bookride'])->name('agent.dashboard');

        //ride request
        Route::middleware(['permission:agent-ride-request-view'])->get('/rides_request', [AgentController::class, 'rideRequest'])->name('agent.rideRequest');
        Route::get('/rides_request/list', [AgentController::class, 'list'])->name('agent.rideRequest.list');
        Route::post('/rides_request/driver/{driver}', [AgentController::class, 'driverFind'])->name('agent.triprequest.driverFind');
        Route::middleware(['permission:agent-ride-request-view'])->get('/rides_request/view/{requestmodel}', [AgentController::class, 'viewDetails'])->name('agent.triprequest.viewDetails');
        Route::get('/rides_request/cancel/{requestmodel}', [AgentController::class, 'cancelRide'])->name('agent.triprequest.cancel');
        Route::get('/rides_request/download-invoice/{requestmodel}', [AgentController::class, 'downloadInvoice']);

        Route::middleware(['permission:agent-ongoing-request-view'])->get('/ongoing_request', [AgentController::class, 'ongoingRequest'])->name('agent.dispatch.ongoingRequest');
        Route::get('/ongoing_request/find-ride/{request}', [AgentController::class, 'ongoingRideDetail'])->name('agent.dispatch.ongoingRideDetail');
        Route::middleware(['permission:agent-ride-request-assign'])->get('/ongoing_request/assign/{request}', [AgentController::class, 'assignView'])->name('agent.dispatch.assignView');
        Route::post('/ongoing_request/assign-driver/{requestmodel}', [AgentController::class, 'assignDriver'])->name('agent.dispatch.assignDriver');
        Route::get('/schedule_rides', [AgentController::class, 'scheduleRides'])->name('agent.dispatch.scheduleRides');
        Route::get('/schedule_rides/list', [AgentController::class, 'scheduleRideslist'])->name('agent.rideRequest.scheduleRideslist');

        Route::post('/update-request/{requestmodel}',[AgentController::class,'updateRequest']);

        Route::middleware(['permission:agent-dashboard'])->get('/dashboard', [AgentController::class, 'agentDashboard'])->name('agent.dashboard');
        Route::get('/dashboard/today-earnings', [AgentController::class, 'todayEarnings'])->name('agent.dashboard-todayEarnings');
        Route::get('/dashboard/overall-earnings', [AgentController::class, 'overallEarnings'])->name('agent.dashboard-overallEarnings');
        Route::get('/dashboard/cancel-chart', [AgentController::class, 'cancelChart'])->name('agent.dashboard-cancelChart');
        Route::get('/unassigned-rides', [AgentController::class, 'unassignedRides'])->name('agent.unassignedRides');
        Route::get('/dashboard/request-enquires-chart', [AgentController::class, 'requestEnquiriesChart'])->name('agent.requestEnquiriesChart');
        //  delivery ride request
        Route::group(['prefix' => 'delivery-rides-request'], function () {
            Route::middleware(['permission:agent-delivery-request'])->get('/', [AgentController::class, 'ridesRequest'])->name('agent.ridesRequest');
            Route::middleware('remove_empty_query')->get('/list', [AgentController::class, 'deliveryList'])->name('agent.list');
        });

        // request enquiries
        Route::middleware(['permission:agent-request-enquiry'])->get('/request_enquiries', [AgentController::class, 'requestEnquiries'])->name('agent.requestEnquiries');
        Route::get('/request_enquiries/list', [AgentController::class, 'requestEnquiriesList'])->name('agent.request_enquiries.requestEnquiriesList');
        Route::get('/request_enquiries/delete/{requestEnquiry}', [AgentController::class, 'deleteEnquiry'])->name('agent.request_enquiries.deleteEnquiry');



        Route::group(['prefix' => 'withdrawal-request'], function () {
            Route::get('/', [AgentController::class, 'withdrawalRequest'])->name('agent.withdrawalRequest');
            Route::get('/list', [AgentController::class, 'withdrawalRequestList'])->name('agent.withdrawalRequestList');
            Route::get('/payment-method', [AgentController::class, 'listBankInfo'])->name('agent.listBankInfo');            
            Route::post('update/bankinfo', [AgentController::class,'updateBankinfoNewWeb']);            
            Route::post('/request-for-withdrawal',[AgentController::class,'requestForWithdrawalWeb']);
            Route::get('/wallet/history', [AgentController::class,'walletHistoryWeb']);
        });


    });
    

    Route::group(['prefix' => 'agent', 'middleware' => ['agent_addons']], function () {
        Route::get('/', [AgentController::class, 'bookingRequest'])->name('agent.index');
        Route::post('/create-request',[AgentCreateRequestController ::class,'createRequest']);
        Route::middleware('auth:sanctum')->post('request/eta',[EtaController::class,'eta']);
        Route::post('request/list_packages',[EtaController::class,'listPackages']);
        Route::post('serviceVerify', [EtaController::class,'serviceVerify']);
        Route::get('/dispatchLocations', [AgentCreateRequestController ::class,'dispatchLocations']);
        
        Route::get('fetch-user-detail',[AgentController::class,'fetchUserIfExists']);

        Route::post('/request-enquiry',[AgentCreateRequestController ::class,'dispatcherEnquiries']);
        
        Route::get('promocode-list', [PromoCodeController::class, 'index']);
        Route::post('promocode-redeem', [PromoCodeController::class, 'redeem']);
        Route::post('promocode-clear', [PromoCodeController::class, 'clear']);

    });
});
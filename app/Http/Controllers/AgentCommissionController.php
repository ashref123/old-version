<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Admin\AgentCommission;
use App\Models\Admin\Agents;
use App\Models\Admin\ServiceLocation;
use App\Models\Country;
use Illuminate\Http\Request;
use App\Base\Libraries\QueryFilter\QueryFilterContract;
use App\Base\Services\ImageUploader\ImageUploaderContract;
use App\Models\User;
use App\Base\Filters\Admin\AgentFilter;
use App\Transformers\BankInfoTransformer;
use App\Models\Method;
use App\Models\Payment\AgentBankInfo;
use App\Base\Constants\Auth\Role;
use App\Transformers\Payment\AgentWalletHistoryTransformer;
use App\Models\Payment\AgentWalletHistory;
use App\Models\Admin\AdminDetail;
use App\Models\Payment\AgentWallet;
use App\Jobs\Mails\SendDriverWithdrawalAcceptMailNotification;
use App\Jobs\Mails\SendDriverWithdrawalDeclineMailNotification;
use App\Models\Payment\WalletWithdrawalRequest;
use App\Transformers\Payment\WalletWithdrawalRequestsTransformer;
use App\Base\Filters\Admin\DriverFilter;
use App\Base\Constants\Masters\WalletRemarks;
use App\Jobs\Notifications\SendPushNotification;
use App\Models\Admin\Driver;
use Carbon\Carbon;
use App\Models\Request\Request as RequestModel;
use App\Models\Request\RequestBill;
use App\Base\Filters\Admin\RequestFilter;


class AgentCommissionController extends Controller
{
    protected $user;
    public function __construct(ImageUploaderContract $imageUploader, User $user)
    {
        $this->imageUploader = $imageUploader;
        $this->user = $user;
    }

    // AGENTS //
    public function agentView()
    {

        return Inertia::render('pages/agent_management/agents/index');
    }
    public function agentlist(QueryFilterContract $queryFilter, Request $request)
    {
        $query = Agents::where('created_by', auth()->user()->id);

        $results = $queryFilter->builder($query)->customFilter(new AgentFilter)->paginate();       

        return response()->json([
            'results' => $results->items(),
            'paginator' => $results,
        ]);
    }
    public function createAgent(){

        $serviceLocations = ServiceLocation::where('active', true)->get();

        $countries = Country::active()->get();

        return Inertia::render('pages/agent_management/agents/create', [
          'serviceLocations' => $serviceLocations,
          'countries' => $countries,
        ]);
    }
    public function storeAgent(request $request){
        $created_params =   $request->validate([
            'country'=>'required',
            'mobile' => 'required',
            'email' => 'required',
            'address' => 'required',
            'state' => 'required',
            'city' => 'required',
            'pincode' => 'required',
        ]);
            
        $created_params['first_name'] = $request->input('name');

        $created_params['created_by'] = auth()->user()->id;

        $created_params['active'] = true;

        if ($request->input('service_location_id')) {
            $created_params['service_location_id'] = $request->service_location_id;
            $timezone = ServiceLocation::where('id', $request->input('service_location_id'))->pluck('timezone')->first();
        } else {
            $timezone = config('app.timezone');
        }

        $user_params = ['name'=>$request->input('name'),
            'email'=>$request->input('email'),
            'mobile'=>$request->input('mobile'),
            'city'=>$request->input('city'),
            'timezone'=>$timezone,
            'country'=>$request->input('country'),
            'password' => bcrypt($request->input('password'))
        ];
        $user_params['password'] = bcrypt($request->input('password'));

        $validate_exists_mobile = $this->checkMobileExists($request->mobile)->getData()->exists;

        $errors = [];
       
        if ($validate_exists_mobile) {
            $errors['mobile'] ='Provided Mobile Number has already been taken';
        }
    
        if(count($errors)){
            return response()->json([ 'errors'=>$errors ], 422);
        }

        $user = $this->user->create($user_params);
      
        if ($uploadedFile = $request->file('profile_picture')) {
            $user['profile_picture'] = $this->imageUploader->file($uploadedFile)
                ->saveProfilePicture();
            $user->save();
        }
        $user->attachRole('agent');

        $user->agent()->create($created_params);

        $user->agent->agentWallet()->create(['amount_added'=>0]);
        return response()->json([
            'successMessage' => 'Agent created successfully.',
        ], 201);
    }  
    public function editAgent($id)
    {

        $agent = Agents::find($id);       
        
        $serviceLocations = get_user_locations(auth()->user());
        $countries = Country::active()->get();
        
        return Inertia::render('pages/agent_management/agents/create',[ 'serviceLocations'=> $serviceLocations,
        'countries'=> $countries,'agent'=> $agent,
        'app_for'=>env('APP_FOR'),
        ]);
    }
    public function Agentupdate(Request $request, $id)
    {
        $agent = Agents::findOrFail($id);
        $updatedParams = $request->validate([
            'country' => 'required',
            'mobile' => 'required',
            'email' => 'required',
            'address' => 'required',
            'state' => 'required',
            'city' => 'required',
            'pincode' => 'required',
        ]);

        $updatedParams['first_name'] = $request->input('name');

        // If service location included
        if ($request->filled('service_location_id')) {
            $updatedParams['service_location_id'] = $request->service_location_id;
            $timezone = ServiceLocation::where('id', $request->service_location_id)->value('timezone');
        } else {
            $timezone = config('app.timezone');
        }

        // Update user
        $user_params = [
            'name' => $request->name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'city' => $request->city,
            'timezone' => $timezone,
            'country' => $request->country,
        ];

        // Upload profile image
        if ($uploadedFile = $request->file('profile_picture')) {
            $user_params['profile_picture'] = $this->imageUploader
                ->file($uploadedFile)
                ->saveProfilePicture();
        }
        
        $agent->user->update($user_params);
        $agent->update($updatedParams);

        return response()->json([
            'successMessage' => 'Agent updated successfully.',
        ], 201);
    }
    public function destroyAgent($id)
    {
        $agents = Agents::findorfail($id);

        $agents->user->delete();

        $agents->delete();

        return response()->json([
            'successMessage' => 'Agent deleted successfully',
        ]);
    } 
    public function updateStatus(Request $request)
    {
        $status = $request->status; // Toggle the status
    
        $agent = Agents::where('id', $request->id)->first();

        $agent->user->update(['active' => $status]);
        $agent->update(['active' => $status]);

        return response()->json([
            'successMessage' => 'Agent Status updated successfully',
        ]);
    } 
    public function editPassword($id)
    {
        $agent = Agents::findorfail($id);
        $serviceLocations = ServiceLocation::active()->get();
        $countries = Country::active()->get();

        return Inertia::render('pages/agent_management/agents/edit',[ 'serviceLocations'=> $serviceLocations,
        'countries'=> $countries,'agent'=> $agent,
        'app_for'=>env('APP_FOR')
        ]);
    }

    public function updatePasswords(Request $request, $id)
    {
        $agent = Agents::findorfail($id);
        $updatedParams = $request->validate([
            'password' => 'required|min:8',  // Confirmed is for password_confirmation
            'confirm_password' => 'required|same:password',
        ]);
        
        $user_params = [
            'password'=>$request->input('password'),
            'confirm_password'=>$request->input('confirm_password'),         
        ];

        if($request->input('password')){
            $validated['password'] = bcrypt($request->input('password'));
        }
        if($request->input('password')){
            $user_params['password'] = $validated['password'];
        }

        $agent->user->update($user_params);

        $agent->update($updatedParams);

        // Optionally, return a response
        return response()->json([
            'successMessage' => 'Agent created successfully.',
        ], 201);
    }

    public function viewProfile(Agents $agent) 
    {
        
        // Preload only necessary relationships to reduce N+1 queries
        $agent->load([
            'user.countryDetail:id,currency_symbol',
            'agentWallet',
        ]);

        // Firebase settings — use a single helper call to reduce DB lookups
        $firebaseKeys = [
            'firebase_api_key', 'firebase_auth_domain', 'firebase_database_url', 
            'firebase_project_id', 'firebase_storage_bucket', 
            'firebase_messaging_sender_id', 'firebase_app_id'
        ];
        $firebaseSettings = [];
        foreach ($firebaseKeys as $key) {
            $firebaseSettings[$key] = get_firebase_settings($key);
        }

        $currency = $agent->user->countryDetail->currency_symbol ?? '';
        $agent_date = $agent->getConvertedCreatedAtAttribute();
        $agent_wallet = $agent->agentWallet;

        // Aggregate trip data in one SQL call (no loops)
        $today = Carbon::today()->toDateString();

        $today = now()->toDateString();
        // Fetch the data
        $tripQuery = RequestModel::selectRaw('
            IFNULL(SUM(CASE WHEN is_completed=1 THEN 1 ELSE 0 END), 0) AS completed,
            IFNULL(SUM(CASE WHEN is_completed=0 AND is_cancelled=0 THEN 1 ELSE 0 END), 0) AS scheduled,
            IFNULL(SUM(CASE WHEN is_cancelled=1 THEN 1 ELSE 0 END), 0) AS cancelled
        ');
            $overallTrips = $tripQuery->where('booked_by',$agent->user->id)->where('if_dispatch',1)->first();
            $todayTrips = $tripQuery->whereDate('created_at', $today)->where('booked_by',$agent->user->id)->where('if_dispatch',1)->first();

            $today_total_trip = RequestModel::whereDate('created_at', $today)->where('booked_by',$agent->user->id)->where('if_dispatch',1)->count();
            $overall_total_trip = RequestModel::where('booked_by',$agent->user->id)->where('if_dispatch',1)->count();
        
        

             // Fetch overall data


        //Today Earnings && today trips
        $cardEarningsQuery = "IFNULL(SUM(IF(requests.payment_opt=0,request_bills.agent_commision,0)),0)";
        $cashEarningsQuery = "IFNULL(SUM(IF(requests.payment_opt=1,request_bills.agent_commision,0)),0)";
        $walletEarningsQuery = "IFNULL(SUM(IF(requests.payment_opt=2,request_bills.agent_commision,0)),0)";
        $adminCommissionQuery = "IFNULL(SUM(request_bills.admin_commision_with_tax),0)";
        $driverCommissionQuery = "IFNULL(SUM(request_bills.driver_commision),0)";
        $totalEarningsQuery = "$cardEarningsQuery + $cashEarningsQuery + $walletEarningsQuery";

        $earningQuery = RequestModel::leftJoin('request_bills','requests.id','request_bills.request_id')
                            ->selectRaw("
                            {$cardEarningsQuery} AS card,
                            {$cashEarningsQuery} AS cash,
                            {$walletEarningsQuery} AS wallet,
                            {$totalEarningsQuery} AS total,
                            {$adminCommissionQuery} as admin_commision,
                            {$driverCommissionQuery} as driver_commision
                        ")
                        ->companyKey()
                        ->where('requests.is_completed',true);
        //Over All Earnings
            $overallEarnings = $earningQuery->where('requests.booked_by',$agent->user->id)->where('requests.if_dispatch',1)->first();

            $todayEarnings = $earningQuery->whereDate('requests.trip_start_time',date('Y-m-d'))
                        ->where('requests.booked_by',$agent->user->id)->where('requests.if_dispatch',1)->first();
        
       

        $todayEarningData=[
            "card"=> $todayEarnings->card,
            "cash"=> $todayEarnings->cash,
            "wallet"=> $todayEarnings->wallet,
            "total"=> round($todayEarnings->total,2),
            "admin_commision"=> $todayEarnings->admin_commision,
            "driver_commision"=> $todayEarnings->driver_commision,
        ];

        $overallEarningData=[
            "card"=> $overallEarnings->card,
            "cash"=> $overallEarnings->cash,
            "wallet"=> $overallEarnings->wallet,
            "total"=> round($overallEarnings->total,2),
            "admin_commision"=> $overallEarnings->admin_commision,
            "driver_commision"=> $overallEarnings->driver_commision,
        ];


        // Generate monthly chart data efficiently
        $startDate = Carbon::now()->startOfYear();
        $endDate = Carbon::now();
        $months = [];
        $earningsChartData = ['months' => [], 'values' => []];
        $tripsChartData = ['months' => [], 'completed' => [], 'cancelled' => [], 'upcoming' => []];

        while ($startDate->lte($endDate)) {
            $from = $startDate->copy()->startOfMonth();
            $to = $startDate->copy()->endOfMonth();
            $monthName = $startDate->monthName;
            $shortName = $startDate->shortEnglishMonth;

            $months[] = $shortName;
            $earningsChartData['months'][] = $monthName;

            // Aggregated sums instead of multiple per-row queries
            $monthlyEarnings = RequestBill::whereHas('requestDetail', function ($q) use ($from, $to, $agent) {
                    $q->where('booked_by', $agent->user->id)
                    ->whereBetween('trip_start_time', [$from, $to])
                    ->whereIsCompleted(true);
                })
                ->sum('agent_commision');

            $completedTrips = RequestModel::whereBetween('trip_start_time', [$from, $to])
                ->where('booked_by', $agent->user->id)
                ->whereIsCompleted(true)
                ->count();

            $cancelledTrips = RequestModel::whereBetween('trip_start_time', [$from, $to])
                ->where('booked_by', $agent->user->id)
                ->whereIsCancelled(true)
                ->count();

             $upcomingTrips = RequestModel::whereBetween('trip_start_time', [$from, $to])
                ->where('booked_by', $agent->user->id)
                ->where('is_completed', 0)
                ->where('is_cancelled', 0)
                ->count();

            $earningsChartData['values'][] = round($monthlyEarnings, 2);
            $tripsChartData['months'][] = $shortName;
            $tripsChartData['completed'][] = $completedTrips;
            $tripsChartData['cancelled'][] = $cancelledTrips;
            $tripsChartData['upcoming'][] = $upcomingTrips;

            $startDate->addMonth();
        }

        $data = [
            'agent' => $agent,
            'agent_date' => $agent_date,
            'currency' => $currency,
            'app_for' => env('APP_FOR'),
            'default_lat' => get_settings('default_latitude'),
            'default_lng' => get_settings('default_longitude'),
            'agent_wallet' => $agent_wallet,
            'firebaseSettings' => $firebaseSettings,
            'earningsChartData' => $earningsChartData,
            'tripsChartData' => $tripsChartData,
            'today' => [
                'completed' => (int) $todayTrips->completed,
                'scheduled' => (int) $todayTrips->scheduled,
                'cancelled' => (int) $todayTrips->cancelled,
                'earnings' => $todayEarningData,
                'today_total_trip' => $today_total_trip
            ],
            'overall' => [
                'completed' => (int) $overallTrips->completed,
                'scheduled' => (int) $overallTrips->scheduled,
                'cancelled' => (int) $overallTrips->cancelled,
                'earnings' => $overallEarningData,
                'overall_total_trip' => $overall_total_trip
            ],
        ];
        return Inertia::render('pages/agent_management/agents/view_profile', $data);
    }

    public function requestList( QueryFilterContract $queryFilter, Agents $agent)
    {
        static $cachedColumns = null;

        if (!$cachedColumns) {
            $cachedColumns = collect(\Schema::getColumnListing('requests'))
                ->reject(fn($col) => $col === 'poly_line')
                ->toArray();
        }

        $limit = min(request('limit', 15), 100);

        $query = RequestModel::select($cachedColumns)
            ->where('booked_by', $agent->user->id)
            ->latest('created_at');

        // Apply filters and paginate
        $requests = $queryFilter->builder($query)
            ->customFilter(new RequestFilter())
            ->paginate($limit);

        return response()->json([
            'requests' => $requests->items(),
            'paginator' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'total' => $requests->total(),
            ],
        ]);
    }  
    public function agentWithdrawalRequest(QueryFilterContract $queryFilter, Agents $agent)
    {

        $query = WalletWithdrawalRequest::where('agent_id', $agent->id)->whereHas('agentDetail.user',function($query){
            $query->companyKey();
            })->orderBy('created_at','desc')->with('agentDetail');

        $results =  $queryFilter->builder($query)->customFilter(new AgentFilter())->paginate();
        $items = fractal($results->items(), new WalletWithdrawalRequestsTransformer)->toArray();
        $results->setCollection(collect($items['data']));

        return response()->json([
            'results' => $results->items(),
            'paginator' => $results,
        ]);  
    } 
    public function listBankInfo(Agents $agent){

        $agent_wallet = $agent->agentWallet;

            if (!$agent_wallet) {

                $wallet_balance = 0;

            } else {                
                 $wallet_balance = $agent_wallet->amount_balance;

            }

        $methods = Method::with('fields')->get(); // Fetch all methods with their fields
        $bankInfos = $agent->bankInfo ?? collect();
    
    $formattedBankInfos = $methods->map(function ($method) use ($bankInfos) {

    $fields = $method->fields->map(function ($field) use ($bankInfos) {
        $info = $bankInfos->firstWhere('field_id', $field->id);

        return !empty($info?->value)
            ? [
                'field_name' => $field->input_field_name,
                'value'      => $info->value,
            ]
            : null;
    })->filter()->values(); // remove empty fields

    // Remove method if no fields have value
    if ($fields->isEmpty()) {
        return null;
    }

    return [
        'method_name' => $method->method_name,
        'fields'      => $fields,
    ];

})->filter()->values(); //

        return response()->json(['success' => true,
            'message' => 'wallet_history_listed',
            'wallet_balance' => $wallet_balance,
            'bank_info_exists' => $formattedBankInfos,
        ]);
    }

     public function WithdrawalRequestAgentsIndex()
    {
        return Inertia::render('pages/withdrawal_request_agent/index',['app_for'=>env("APP_FOR"),]);
    }
    public function WithdrawalRequestAgentsList(QueryFilterContract $queryFilter)
    {


        $query = WalletWithdrawalRequest::whereHas('agentDetail.user',function($query){
            $query->companyKey();
            })->orderBy('created_at','desc')->with('agentDetail');

        $results =  $queryFilter->builder($query)->customFilter(new AgentFilter())->paginate();
        $items = fractal($results->items(), new WalletWithdrawalRequestsTransformer)->toArray();
        $results->setCollection(collect($items['data']));

        return response()->json([
            'results' => $results->items(),
            'paginator' => $results,
        ]);  
    }   


    public function WithdrawalRequestAgentsViewDetails(Agents $agents)
    {
        $walletBalance = $agents->agentWallet ? $agents->agentWallet->amount_balance : 0;
    
        $bankDetails = [
            'account_holder_name' => $agents->name,
        ];
    
        $methods = Method::with('fields')->get(); // Fetch all methods with their fields
        $bankInfos = $agents->bankInfo;
    
        $formattedBankInfos = $methods->map(function ($method) use ($bankInfos) {
            $fields = $method->fields->map(function ($field) use ($bankInfos) {
                $info = $bankInfos->firstWhere('field_id', $field->id);
    
                return [
                    'field_name' => $field->input_field_name,
                    'value' => $info->value ?? null,
                ];
            });
    
            return [
                'method_name' => $method->method_name,
                'fields' => $fields,
            ];
        });
    
        return Inertia::render('pages/withdrawal_request_agent/view_in_detail', [
            'app_for' => env("APP_FOR"),
            'walletBalance' => $walletBalance,
            'bankDetails' => $bankDetails,
            'agent_id' => $agents->id,
            'formattedBankInfos' => $formattedBankInfos,
        ]);
    }

    //WithdrawalRequestAmount 
    public function WithdrawalRequestAmountAgents(QueryFilterContract $queryFilter, Agents $agent_id)
    {
        // Debugging driver_id for confirmation
    
        $query = WalletWithdrawalRequest::whereHas('agentDetail.user', function($query) {
            $query->companyKey();
        })
        ->where('agent_id', $agent_id->id) // Filter by driver_id
        ->orderBy('created_at', 'desc')
        ->with('agentDetail');
    
        $results = $queryFilter->builder($query)->customFilter(new DriverFilter())->paginate();
        $items = fractal($results->items(), new WalletWithdrawalRequestsTransformer)->toArray();
        $results->setCollection(collect($items['data']));
    
        return response()->json([
            'results' => $results->items(),
            'paginator' => $results,
        ]);
    }

    public function updateAgentPaymentStatus(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:wallet_withdrawal_requests,id',
            'status' => 'required|in:approved,declined',
        ]);
    
        $wallet_withdrawal_request = WalletWithdrawalRequest::findOrFail($request->id);
    
        if ($request->status === 'approved') {
            // Handle approval logic
            $agent_wallet = AgentWallet::firstOrCreate(['user_id' => $wallet_withdrawal_request->agent_id]);
            $agent_wallet->amount_spent += $wallet_withdrawal_request->requested_amount;
            $agent_wallet->amount_balance -= $wallet_withdrawal_request->requested_amount;
            $agent_wallet->save();

             // Generate transaction_id
                 $transaction_id = str_random(6); 
    
            $wallet_withdrawal_request->agentDetail->agentWalletHistory()->create([
                'amount' => $wallet_withdrawal_request->requested_amount,
                'transaction_id' => $transaction_id,
                'remarks' => WalletRemarks::WITHDRAWN_FROM_WALLET,
                'is_credit' => false,
            ]);
    
            $wallet_withdrawal_request->status = 1; // Approved

            $user = $agent_wallet->agent->user;

            $currency = $user->countryDetail()->pluck('currency_symbol')->first();


            $notification = \DB::table('notification_channels')
            ->where('topics', 'Driver Withdrawal Request Approval') // Match the correct topic
            ->first();

            //   send push notification 
                if ($notification && $notification->push_notification == 1) {

                     // Determine the user's language or default to 'en'
                    $userLang = $user->lang ?? 'en';
    
                    // Fetch the translation based on user language or fall back to 'en'
                    $translation = \DB::table('notification_channels_translations')
                        ->where('notification_channel_id', $notification->id)
                        ->where('locale', $userLang)
                        ->first();
    
                    // If no translation exists, fetch the default language (English)
                    if (!$translation) {
                        $translation = \DB::table('notification_channels_translations')
                            ->where('notification_channel_id', $notification->id)
                            ->where('locale', 'en')
                            ->first();
                    }

                    $title =  $translation->push_title ?? $notification->push_title;
                    $body = strip_tags($translation->push_body ?? $notification->push_body);
                    $push_data = ['notification_enum'=>"payment_credited"];
                    dispatch(new SendPushNotification($user, $title, $body,$push_data));
                }

                //   send email account approved
                if (!empty($user->email)) {
                SendDriverWithdrawalAcceptMailNotification::dispatch($user, $transaction_id, $currency, $wallet_withdrawal_request, $agent_wallet);
                }

          
           

        } elseif ($request->status === 'declined') {
            $wallet_withdrawal_request->status = 2; // Declined

            $agent_wallet = AgentWallet::firstOrCreate(['user_id' => $wallet_withdrawal_request->agent_id]);


            $user = $agent_wallet->agent->user;

            $notification = \DB::table('notification_channels')
            ->where('topics', 'Driver Withdrawal Request Decline') // Match the correct topic
            ->first();
            //   send push notification 
                if ($notification && $notification->push_notification == 1) {
                     // Determine the user's language or default to 'en'
                    $userLang = $user->lang ?? 'en';
                  
    
                    // Fetch the translation based on user language or fall back to 'en'
                    $translation = \DB::table('notification_channels_translations')
                        ->where('notification_channel_id', $notification->id)
                        ->where('locale', $userLang)
                        ->first();
    
                    // If no translation exists, fetch the default language (English)
                    if (!$translation) {
                        $translation = \DB::table('notification_channels_translations')
                            ->where('notification_channel_id', $notification->id)
                            ->where('locale', 'en')
                            ->first();
                    }
            
                    
                    $title =  $translation->push_title ?? $notification->push_title;
                    $body = strip_tags($translation->push_body ?? $notification->push_body);
                        $push_data = ['notification_enum'=>"payment_declained"];
                    dispatch(new SendPushNotification($user, $title, $body,$push_data));
                }
                
                // send the mail withdrawal decline
                if (!empty($user->email)) {
                SendDriverWithdrawalDeclineMailNotification::dispatch($user);
                }
           
        }
    
        $wallet_withdrawal_request->payment_status = $request->status;
        $wallet_withdrawal_request->save();
    
        return response()->json([
            'successMessage' => 'Agent payment status updated successfully.',
        ]);
    }
    public function checkMobileExists($mobile, $agentId = null)
    {
        $query = Agents::where('mobile', $mobile);
        if ($agentId !== null) {
            $query->where('id', '!=', $agentId);
        }
        $agentExists = $query->exists();
        return response()->json(['exists' => $agentExists]);
    }
}


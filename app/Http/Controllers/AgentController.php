<?php

namespace App\Http\Controllers;
use Inertia\Inertia;
use App\Models\Country;
use App\Transformers\CountryTransformer;
use App\Models\User;
use App\Models\Admin\Driver;
use App\Models\Request\Request as RequestModel;
use App\Models\Request\RequestBill;
use Carbon\Carbon;
use App\Models\Admin\GoodsType;
use App\Models\Admin\Zone;
use App\Models\Admin\VehicleType;
use App\Models\ThirdPartySetting;
use App\Base\Filters\Admin\RequestFilter;
use App\Base\Libraries\QueryFilter\QueryFilterContract;
use App\Transformers\Requests\TripRequestTransformer;
use App\Models\Admin\ServiceLocation;
use Illuminate\Http\Request;
use Dompdf\Dompdf;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Admin\Setting;
use App\Models\Admin\InvoiceConfiguration;
use Illuminate\Support\Facades\Mail;
use App\Mail\RideLaterMail;
use Kreait\Firebase\Contract\Database;
use App\Jobs\Notifications\SendPushNotification;
use App\Models\Admin\PackageType;
use App\Models\Admin\ZoneTypePackagePrice;
use App\Jobs\Mails\SendUserRideLaterMailNotification;
use App\Http\Controllers\Api\V1\Payment\Stripe\StripeController;
use App\Models\Master\MobileAppSetting;
use App\Models\Master\Preference;
use App\Models\Admin\ZoneType;
use App\Transformers\User\EtaTransformer;
use DB;
use Illuminate\Support\Facades\Log;
use App\Helpers\Rides\StoreEtaDetailForRideHelper;
use App\Models\Payment\WalletWithdrawalRequest;
use App\Transformers\Payment\WalletWithdrawalRequestsTransformer;
use App\Base\Filters\Admin\DriverFilter;
use App\Http\Requests\Request\DriverEndRequest;
use Illuminate\Http\Request as HttpRequest;
use App\Models\Request\RecentSearch;
use App\Models\Master\PreferencePrices;
use App\Models\Request\RequestEnquiry;
use App\Base\Filters\Admin\RequestEnquiryFilter;
use App\Helpers\Rides\RidePriceCalculationHelpers;
use App\Helpers\Rides\PaymentOptionCalculationHelper;
use App\Helpers\Rides\EndRequestHelper;
use App\Helpers\Payment\PaymentReferenceHelper;
use App\Models\Admin\Promo;
use App\Jobs\ValidateAndUpdateIncentivesJob;
use App\Models\Admin\Incentive;
use App\Models\Payment\DriverIncentiveHistory;
use App\Models\Payment\DriverWallet;
use App\Models\Admin\DriverLevelUp;
use App\Mail\UserInvoiceMail;
use App\Mail\DriverInvoiceMail;
use App\Jobs\Mails\SendUserInvoiceMailNotification;
use App\Jobs\Mails\SendDriverInvoiceMailNotification;
use App\Jobs\ValidateAndUpdateDriverLoyaltyJob;
use App\Jobs\NotifyViaMqtt;
use App\Jobs\NotifyViaSocket;
use App\Models\Admin\PromoUser;
use App\Base\Constants\Masters\UnitType;
use App\Base\Constants\Masters\PushEnums;
use App\Base\Constants\Masters\PaymentType;
use App\Base\Constants\Masters\WalletRemarks;
use App\Http\Controllers\Api\V1\BaseController;
use App\Jobs\Notifications\AndroidPushNotification;
use App\Models\Request\RequestCancellationFee;
use App\Base\Constants\Setting\Settings;
use App\Models\Master\MailTemplate;
use App\Mail\WelcomeMail;
use App\Jobs\Mails\SendMailNotification;
use App\Jobs\Mails\SendInvoiceMailNotification;
use App\Models\Request\Request as RequestRequest;
use App\Models\Request\RequestStop; 
use App\Models\Conversation;
use App\Models\Chat; 
use App\Models\ChatMessage;
use App\Models\Message;
use App\Base\Filters\Admin\UserFilter;
use App\Base\Filters\Admin\ServiceLocationFilter;
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

class AgentController extends StripeController
{

    use StoreEtaDetailForRideHelper, RidePriceCalculationHelpers,PaymentOptionCalculationHelper,EndRequestHelper,PaymentReferenceHelper;
    public function __construct(Database $database)
    {
        $this->database = $database;
    }
    function index() {
        $currency_code = get_settings('currency_code');
        $currency_symbol = get_settings('currency_symbol');


        $firebaseConfig = (object) [
            'apiKey' => get_firebase_settings('firebase_api_key'),
            'authDomain' => get_firebase_settings('firebase_auth_domain'),
            'databaseURL' => get_firebase_settings('firebase_database_url'),
            'projectId' => get_firebase_settings('firebase_project_id'),
            'storageBucket' => get_firebase_settings('firebase_storage_bucket'),
            'messagingSenderId' => get_firebase_settings('firebase_messaging_sender_id'),
            'appId' => get_firebase_settings('firebase_app_id'),
        ];
        return Inertia::render('agent/index', [
            'currency_code' => $currency_code,
            'currencySymbol' => $currency_symbol,
            'firebaseConfig' => $firebaseConfig,

        ]);
    }

    public function godseye() 
    {
        $service_location = ServiceLocation::where('active', true)->whereIn('id',get_user_location_ids(auth()->user()))->get(['id', 'name']);

        $vehicle_type = VehicleType::where('active', true)->get(['id', 'name']);

        $map_key = get_map_settings('google_map_key');

        $query = Country::active()->get();

        $countries = fractal($query, new CountryTransformer);

        $result = json_decode($countries->toJson(),true);
        
        $default_country = Country::active()->where('code',get_settings('default_country_code_for_mobile_app'))->first();
         $default_dial_code = $default_country->dial_code;
        $default_flag = $default_country->flag;


        $firebaseSettings = [
            'firebase_api_key' => get_firebase_settings('firebase_api_key'),
            'firebase_auth_domain' => get_firebase_settings('firebase_auth_domain'),
            'firebase_database_url' => get_firebase_settings('firebase_database_url'),
            'firebase_project_id' => get_firebase_settings('firebase_project_id'),
            'firebase_storage_bucket' => get_firebase_settings('firebase_storage_bucket'),
            'firebase_messaging_sender_id' => get_firebase_settings('firebase_messaging_sender_id'),
            'firebase_app_id' => get_firebase_settings('firebase_app_id'),
        ];

          $map_type = get_map_settings('map_type');

        $currency_symbol = get_settings('currency_symbol');
        // card Datas 
        $total_drivers = Driver::selectRaw('
                                        IFNULL(SUM(CASE WHEN approve=1 THEN 1 ELSE 0 END),0) AS approved,
                                        IFNULL((SUM(CASE WHEN approve=1 THEN 1 ELSE 0 END) / count(*)),0) * 100 AS approve_percentage,
                                        IFNULL((SUM(CASE WHEN approve=0 THEN 1 ELSE 0 END) / count(*)),0) * 100 AS decline_percentage,
                                        IFNULL(SUM(CASE WHEN approve=0 THEN 1 ELSE 0 END),0) AS declined,
                                        count(*) AS total
                                    ')
                                ->whereHas('user', function ($query) {
                                    $query->companyKey();
                                });
        $total_drivers = $total_drivers->whereIn('service_location_id',get_user_location_ids(auth()->user()));

        $total_drivers = $total_drivers->first();
        $total_drivers = [
        'approved' => $total_drivers->approved,
        'declined' => $total_drivers->declined,
        'approve_percentage' => round($total_drivers->approve_percentage),
        'decline_percentage' => round($total_drivers->decline_percentage),
        'total' => $total_drivers->total,
      ];
       
          if($map_type=="open_street_map")
          {
            return Inertia::render('agent/godseye-open',['firebaseSettings'=>$firebaseSettings,
            'app_for' => env('APP_FOR'),
            'default_lat'=>get_settings('default_latitude'),'default_lng'=>get_settings('default_longitude'),
            'service_location'=>$service_location,'vehicle_type'=>$vehicle_type,'total_drivers' => $total_drivers,'countries'=>$result['data'],
            'default_dial_code'=>$default_dial_code,'default_flag'=>$default_flag,]);    
          }else{
            $default_location = (object)[
                "lat"=> (float) get_settings('default_latitude'),
                "lng"=> (float) get_settings('default_longitude'),
            ];
            return Inertia::render('agent/godseye',['firebaseSettings'=>$firebaseSettings,
            'app_for' => env('APP_FOR'),
            'baseUrl'=>route('landing.index'),'default_location'=>$default_location,
            'service_location'=>$service_location,'vehicle_type'=>$vehicle_type,'map_key'=>$map_key,'total_drivers' => $total_drivers,'countries'=>$result['data'],
            'default_dial_code'=>$default_dial_code,'default_flag'=>$default_flag,]);
          }


    }

    public function bookride(Request $request) {

        $agent = auth()->user()->agent;
        $driver_id = $request->query('id'); 
        $type = [];
        if($driver_id){
            $driver = Driver::find($driver_id);
            $type = $driver->driverVehicleTypeDetail()->pluck('vehicle_type');
        } 
         $driver = Driver::find($driver_id);
        $trip_start_time = $request->query('date'); 

        $booking_details = $request->only([
                'pick_lat', 'pick_lng', 'drop_lat', 'drop_lng',
                'pick_address', 'drop_address', 'mobile', 'name'
            ]);

        $query = Country::active()->get();

        $countries = fractal($query, new CountryTransformer);

        $result = json_decode($countries->toJson(),true);
        
        $default_country = Country::active()->where('code',get_settings('default_country_code_for_mobile_app'))->first();

        $firebaseSettings = [
            'firebase_api_key' => get_firebase_settings('firebase_api_key'),
            'firebase_auth_domain' => get_firebase_settings('firebase_auth_domain'),
            'firebase_database_url' => get_firebase_settings('firebase_database_url'),
            'firebase_project_id' => get_firebase_settings('firebase_project_id'),
            'firebase_storage_bucket' => get_firebase_settings('firebase_storage_bucket'),
            'firebase_messaging_sender_id' => get_firebase_settings('firebase_messaging_sender_id'),
            'firebase_app_id' => get_firebase_settings('firebase_app_id'),
        ];

        $default_dial_code = $default_country->dial_code;
        $default_flag = $default_country->flag;

        // @TODO check if the rental is enabled or not
        $ride_type_for_ride = ['regular','rental'];

        $goods_types = GoodsType::active()->get();

        $transport_settings = Setting::where('category', 'trip_settings')
        ->pluck('value', 'name')
        ->toArray();

        $schedule_a_ride = (double) $transport_settings['user_can_make_a_ride_after_x_miniutes'];

        $settings = Setting::where('category', 'customization_settings')
        ->pluck('value', 'name')
        ->toArray(); 

        $preference = Preference::active()->get();

        $enabled_modules = $settings['enable_modules_for_applications'] ?? 'taxi';       
        
        $transport_type_regular = [];


        $package_taxi = ZoneTypePackagePrice::active()->whereHas('zoneType',function($query) {
            $query->where('transport_type','taxi')->orWhere('transport_type','both');
        })->exists();

        $package_delivery = ZoneTypePackagePrice::active()->whereHas('zoneType',function($query) {
            $query->where('transport_type','delivery')->orWhere('transport_type','both');
        })->exists();

        $rental_taxi = $settings['show_taxi_rental_ride_feature'];
        $rental_delivery = $settings['show_delivery_rental_ride_feature'];

        // Initialize the transport type array
        $transport_type_rental = [];

        

        $outstation_taxi = $settings['show_outstation_ride_feature'];
        $outstation_delivery = $settings['show_delivery_outstation_ride_feature'];

        $transport_type_outstation = [];

        // preference
        $pet_preference = $settings['enable_pet_preference_for_user'] == '1';
        $luggage_preference = $settings['enable_luggage_preference_for_user'] == '1';
        $map_type = get_map_settings('map_type');
        $enable_ride_without_destination = get_settings('show_ride_without_destination') == '1';
        $app_modules = MobileAppSetting::active()->get();
        $package = ZoneTypePackagePrice::active()->get();

        if($map_type=="open_street_map")
        {

            
            return Inertia::render('agent/open-dispatch',['countries'=>$result['data'],
            'default_dial_code'=>$default_dial_code,'default_flag'=>$default_flag,
            'default_lat'=>get_settings('default_latitude'),'default_lng'=>get_settings('default_longitude'),
            'firebaseSettings'=>$firebaseSettings,'enable_ride_without_destination'=>$enable_ride_without_destination,
            'ride_type_for_ride'=>$ride_type_for_ride,'goodsTypes'=>$goods_types,'type'=> $type,
            'transport_type_outstation' => $transport_type_outstation,'schedule_a_ride'=>$schedule_a_ride,
            'transport_type_rental' =>$transport_type_rental, 'transport_type_regular' =>$transport_type_regular,
            'is_pet_available' => $pet_preference, 'is_luggage_available' => $luggage_preference,'preference'=> $preference,'app_modules' => $app_modules,
            'package' => $package,'driver_id' => $driver_id,'trip_start_time' => $trip_start_time,'booking_details' => $booking_details,'driver' => $driver,
            'agent' =>$agent
            ]);   

        }else{
            $map_key = get_map_settings('google_map_key');

            $default_location = (object)[
                "lat"=> (float) get_settings('default_latitude'),
                "lng"=> (float) get_settings('default_longitude'),
            ];

            return Inertia::render('agent/dispatch',['countries'=>$result['data'],
            'default_dial_code'=>$default_dial_code,'default_flag'=>$default_flag,
            'baseUrl'=>route('landing.index'),'default_location'=>$default_location,
            'default_lat'=>get_settings('default_latitude'),'default_lng'=>get_settings('default_longitude'),
            'firebaseSettings'=>$firebaseSettings,'enable_ride_without_destination'=>$enable_ride_without_destination,
            'ride_type_for_ride'=>$ride_type_for_ride,'goodsTypes'=>$goods_types,'map_key'=>$map_key,
            'transport_type_outstation' => $transport_type_outstation,'schedule_a_ride'=>$schedule_a_ride,
            'transport_type_rental' =>$transport_type_rental, 'transport_type_regular' =>$transport_type_regular,
            'is_pet_available' => $pet_preference, 'is_luggage_available' => $luggage_preference, 'preference'=> $preference,
            'app_modules' => $app_modules,'package' => $package,'driver_id' => $driver_id,'trip_start_time' => $trip_start_time,
            'booking_details' => $booking_details,'type'=> $type,'driver' => $driver,'agent' =>$agent
            ]);

    
         }
    }

    /**
     * Fetch User Detail
     * 
     * 
     * */
    public function fetchUserIfExists()
    {
        $mobile = request()->mobile;

        $user = User::where('mobile',$mobile)->first();
        
        return $this->respondSuccess($user);


    }

    //ride request

    public function rideRequest() {
        $agent = auth()->user();
        $ongoing = RequestModel::with('userDetail','driverDetail')
            ->where('is_cancelled', false)
            ->where('is_completed', false)
            ->where('booked_by', $agent->id)
            ->orderBy('created_at','DESC')->pluck('id');
        $settings = ThirdPartySetting::where('module', 'firebase')->pluck('value', 'name')->toArray();

        $firebaseConfig = (object) [
            'apiKey' => $settings['firebase_api_key'],
            'authDomain' => $settings['firebase_auth_domain'],
            'databaseURL' => $settings['firebase_database_url'],
            'projectId' => $settings['firebase_project_id'],
            'storageBucket' => $settings['firebase_storage_bucket'],
            'messagingSenderId' => $settings['firebase_messaging_sender_id'],
            'appId' => $settings['firebase_app_id'],
        ];

        $service_location_id = get_user_location_ids(auth()->user());
        return Inertia::render('agent/rides_request/index',[
            'zones' => Zone::active()->get(),
            'types' => VehicleType::active()->get(),
            'ongoing_rides' => $ongoing,
            'firebaseConfig' => $firebaseConfig,
            'service_location_id' => $service_location_id[0],
            'enable_outstation' => get_settings('show_outstation_ride_feature') || get_settings('show_delivery_outstation_ride_feature'),
        ]);
    }

    //trip request list

    public function list(QueryFilterContract $queryFilter, Request $request)
    {
        $query = RequestModel::where('requests.transport_type', 'taxi')
                ->where('booked_by', auth()->user()->id)
                ->with('userDetail', 'driverDetail')
                ->orderBy('created_at', 'DESC');
        $results = $queryFilter->builder($query)
            ->customFilter(new RequestFilter)
            ->paginate();
    
        $data = [
            'results' => $results->items(),
            'paginator' => $results,
        ];
        
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        
        return response($json, 200)
            ->header('Content-Type', 'application/json');
    }

    //view details

    public function viewDetails(RequestModel $requestmodel) {
        $settings = ThirdPartySetting::where('module', 'firebase')->pluck('value', 'name')->toArray();
        $onsearch = $requestmodel->on_search;
        $requestmodel = fractal($requestmodel, new TripRequestTransformer)->parseIncludes(['userDetail','driverDetail','requestBill','rejectedDrivers'])->toArray();
        $requestmodel['data']['onSearch'] = $onsearch;
        $firebaseConfig = (object) [
            'apiKey' => $settings['firebase_api_key'],
            'authDomain' => $settings['firebase_auth_domain'],
            'databaseURL' => $settings['firebase_database_url'],
            'projectId' => $settings['firebase_project_id'],
            'storageBucket' => $settings['firebase_storage_bucket'],
            'messagingSenderId' => $settings['firebase_messaging_sender_id'],
            'appId' => $settings['firebase_app_id'],
        ];
        if(get_map_settings('map_type') == "open_street_map"){
            return Inertia::render('agent/rides_request/open-view',
                [
                    'request' => $requestmodel['data'],
                    'service_location'=>null,
                    'app_for' => env('APP_FOR'),
                    'pick_icon'=>asset('image/map/pickup.png'),
                    'drop_icon'=>asset('image/map/drop.png'),
                    'firebaseConfig'=>$firebaseConfig,
                ]);
        }
        $default_location = (object)[
            "lat"=> (float) get_settings('default_latitude'),
            "lng"=> (float) get_settings('default_longitude'),
        ];
        $googleMapKey = get_map_settings('google_map_key'); // Retrieve the Google Map API key
        return Inertia::render('agent/rides_request/view',
            [
                'request' => $requestmodel['data'],
                'service_location'=>null,
                'baseUrl'=>route('landing.index'),
                'app_for' => env('APP_FOR'),
                'googleMapKey'=>$googleMapKey,
                'firebaseConfig'=>$firebaseConfig,
                'default_location'=>$default_location,
            ]
        );
    }

    public function driverFind(Driver $driver,Request $request)
    {
        $request = RequestModel::find($request->request_id);
        return response()->json([
            'successMessage' => 'Driver Found successfully',
            'driver' => $driver,
            'current_time' => get_converted_time(now(),$request->timezone),
        ]);
    }
    public function cancelRide(RequestModel $requestmodel) {
        $update_parms['is_cancelled'] = true;
        $update_parms['cancelled_at'] = date('Y-m-d H:i:s');
        $update_parms['cancel_method'] = 0;
        if($requestmodel->driver_id){
            Driver::where('id',$requestmodel->driver_id)->update(['available'=>true,'occupied_seats'=>0]);
        }
        if($requestmodel->driverDetail)
        {
            $notifiable_driver = $requestmodel->driverDetail->user;

             $notification = \DB::table('notification_channels')
                ->where('topics', 'Trip Cancelled By System') // Match the correct topic
                ->first();

            //    send push notification 
                if ($notification && $notification->push_notification == 1) {
                     // Determine the user's language or default to 'en'
                    $userLang = $notifiable_driver->lang ?? 'en';
    
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
                    dispatch(new SendPushNotification($notifiable_driver, $title, $body));
                }
        }
        if($requestmodel->payment_intent_id){
            $this->cancel($requestmodel->payment_intent_id);
        }
        $this->database->getReference('requests/' . $requestmodel->id)->update(['is_cancelled' => true, 'cancelled_by_user' => true]);
        $this->database->getReference('requests/' . $requestmodel->id)->remove();
        $this->database->getReference('SOS/' . $requestmodel->id)->remove();
        $this->database->getReference('request-meta/' . $requestmodel->id)->remove();
        $requestmodel->update($update_parms);
        $requestmodel->requestMeta()->delete();

        return response()->json([
            'successMessage' => 'Trip Cancelled successfully',
            'request' => $requestmodel,
        ]);
    }
    public function sosDetail(RequestModel $request)
    {
        if($request->is_cancelled || $request->is_completed) {
            return response()->json(['message'=>'Invalid SOS'],422);
        }
        $result = json_decode(fractal($request, new TripRequestTransformer)->parseIncludes(['userDetail','driverDetail'])->toJson());
        return response()->json([
            'successMessage' => 'Ride Found successfully',
            'request' => $result->data,
            'current_time' => get_converted_time(now(),$request->timezone),
        ]);
    }

    //ongoing Request

    public function ongoingRequest()
    {
        $ongoing = RequestModel::with('userDetail','driverDetail')
            ->where('is_cancelled', false)
            ->where('is_completed', false)
            ->where('booked_by', auth()->user()->id)
            ->orderBy('created_at','DESC')->get();
        $settings = ThirdPartySetting::where('module', 'firebase')->pluck('value', 'name')->toArray();

        $firebaseConfig = (object) [
            'apiKey' => $settings['firebase_api_key'],
            'authDomain' => $settings['firebase_auth_domain'],
            'databaseURL' => $settings['firebase_database_url'],
            'projectId' => $settings['firebase_project_id'],
            'storageBucket' => $settings['firebase_storage_bucket'],
            'messagingSenderId' => $settings['firebase_messaging_sender_id'],
            'appId' => $settings['firebase_app_id'],
        ];
        
        $service_location_id = get_user_location_ids(auth()->user());

        return Inertia::render('agent/ongoing_rides/index',[
            'zones' => Zone::active()->get(),
            'types' => VehicleType::active()->get(),
            'service_location_id' => $service_location_id[0],
            'ongoing_rides' => $ongoing,
            'firebaseConfig' => $firebaseConfig,
        ]);
    }

    public function ongoingRideDetail(RequestModel $request)
    {
        $items = fractal($request, new TripRequestTransformer)->toArray();
        return response()->json([
            'result' => $items['data'],
            'current_time' => get_converted_time(now(),$request->timezone),
        ]);
    }
    public function assignView(RequestModel $request)
    {

        

        $preferenceIds = [];
        $ridePreferences = $request->preferenceDetail()->pluck('preference_price_id');

        if(count($ridePreferences) > 0){
            $preferenceIds = PreferencePrices::whereIn('id',$ridePreferences)->pluck('preference_id')->toArray();
            
        }
        if($request->is_cancelled || $request->driver_id){
            return redirect('rides-request/view/'.$request->id);
        }
        $firebaseSettings = [
            'firebase_api_key' => get_firebase_settings('firebase_api_key'),
            'firebase_auth_domain' => get_firebase_settings('firebase_auth_domain'),
            'firebase_database_url' => get_firebase_settings('firebase_database_url'),
            'firebase_project_id' => get_firebase_settings('firebase_project_id'),
            'firebase_storage_bucket' => get_firebase_settings('firebase_storage_bucket'),
            'firebase_messaging_sender_id' => get_firebase_settings('firebase_messaging_sender_id'),
            'firebase_app_id' => get_firebase_settings('firebase_app_id'),
        ];
        $item = fractal($request, new TripRequestTransformer)->parseIncludes(['userDetail','driverDetail'])->toJson();
        $request = json_decode($item);
        if(get_map_settings('map_type') == 'open_street_map'){
            return Inertia::render('agent/ongoing_rides/assign-open',[
                'result' => $request->data,
                'app_for' => env('APP_FOR'),
                'firebaseSettings' => $firebaseSettings,
                'preferenceIds' => $preferenceIds
            ]);
        }
        $map_key = get_map_settings('google_map_key');
        return Inertia::render('agent/ongoing_rides/assign',[
            'map_key' => $map_key,
            'app_for' => env('APP_FOR'),
            'baseUrl'=>route('landing.index'),
            'result' => $request->data,
            'firebaseSettings' => $firebaseSettings,
            'preferenceIds' => $preferenceIds
        ]);
    }
    public function assignDriver(RequestModel $requestmodel,Request $request) {
        $assigned = $requestmodel->is_cancelled || $requestmodel->is_completed || $requestmodel->driver_id || $requestmodel->requestMeta()->exists();
        if($assigned) {
            return response()->json(['status'=>false,'message'=>'Cannot Assign Request']);
        }
        $request->validate([
            'driver_id'  => 'required' 
        ]);
        $driver = Driver::find($request->driver_id);
        
        if(!$driver) {
            return response()->json(['status'=>false,'message'=>'Cannot Assign Driver']);
        }
        $selected_drivers["user_id"] = $requestmodel->user_id;
        $selected_drivers["driver_id"] = $driver->id;
        $selected_drivers["active"] = 1;
        $selected_drivers["assign_method"] = 1;
        $selected_drivers["created_at"] = date('Y-m-d H:i:s');
        $selected_drivers["updated_at"] = date('Y-m-d H:i:s');

        $requestmodel->requestMeta()->create($selected_drivers);
        $this->database->getReference('request-meta/'.$requestmodel->id)
                    ->set([
                            'driver_id'=>$driver->id,
                            'request_id'=>$requestmodel->id,
                            'user_id'=>$requestmodel->user_id,
                            'active'=>1,
                            'transport_type'=>"taxi",
                            'updated_at'=> Database::SERVER_TIMESTAMP
                        ]);
        $requestmodel->update(['assign_method'=>1, 'accepted_ride_fare'=>$requestmodel->offerred_ride_fare,'is_bid_ride'=>false]);


        $notifable_driver = $driver->user;

        $user = $requestmodel->userDetail;
        if ($requestmodel->is_later) {
            dispatch(new SendUserRideLaterMailNotification($user));
        }

        $notification = \DB::table('notification_channels')
            ->where('topics', 'User Ride Later') // Match the correct topic
            ->first();

        //   send push notification 
                if ($notification && $notification->push_notification == 1) {
                     // Determine the user's language or default to 'en'
                    $userLang = $notifable_driver->lang ?? 'en';
    
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
        $push_data = ['title' => $title,'message' => $body,'push_type'=>'meta-request'];
                    dispatch(new SendPushNotification($notifable_driver,$title,$body,$push_data));
                }
        return response()->json(['status'=>true,'message'=>'Assigned Successfully']);
    }

    //download invoice

    public function downloadInvoice(RequestModel $requestmodel, Request $request)
    {
        $requestmodel = fractal($requestmodel, new TripRequestTransformer)->parseIncludes(['userDetail', 'driverDetail', 'requestBill', 'rejectedDrivers'])->toArray();
        $logo = Setting::where('name', 'logo')->first();
        $invoice_configuration = ThirdPartySetting::where('module', 'mail_config')->pluck('value', 'name')->toArray();
        try {
            if ($request->invoice_type === "user") {
                $data = $requestmodel;
                $img = $logo;
                $invoice = $invoice_configuration;
                // Format completed_at for the view
                $data['formatted_completed_at'] = isset($requestmodel['completed_at']) 
                ? Carbon::parse($requestmodel['completed_at'])
                    ->setTimezone(config('app.timezone'))
                    ->format('M j, Y - h:i A') 
                : null;

                // Return user invoice Blade view
                return view('emails.invoice', compact('data','logo','invoice'));
            } elseif ($request->invoice_type === "driver") {
                $data = $requestmodel;
                $img = $logo;
                $invoice = $invoice_configuration;

                // Return driver invoice Blade view
                return view('emails.driver_invoice', compact('data','logo','invoice'));
            }

            // Handle invalid invoice type
            return response()->json(['error' => 'Invalid invoice type'], 400);
        } catch (\Exception $e) {
            // Handle exceptions
            return response()->json(['error' => 'Failed to generate invoice: ' . $e->getMessage()], 500);
        }
    }

     public function scheduleRides()
    {
         $ongoing = RequestModel::with('userDetail','driverDetail')
            ->where('is_cancelled', false)
            ->where('is_completed', false)
            ->where('is_later', true)
            ->orderBy('created_at','DESC')->pluck('id');
        $settings = ThirdPartySetting::where('module', 'firebase')->pluck('value', 'name')->toArray();
        $firebaseConfig = (object) [
            'apiKey' => get_firebase_settings('firebase_api_key'),
            'authDomain' => get_firebase_settings('firebase_auth_domain'),
            'databaseURL' => get_firebase_settings('firebase_database_url'),
            'projectId' => get_firebase_settings('firebase_project_id'),
            'storageBucket' => get_firebase_settings('firebase_storage_bucket'),
            'messagingSenderId' => get_firebase_settings('firebase_messaging_sender_id'),
            'appId' => get_firebase_settings('firebase_app_id'),
        ];
        $map_key = get_map_settings('google_map_key');
        return Inertia::render('agent/schedule_rides/index',[  'ongoing_rides' => $ongoing,'firebaseConfig' => $firebaseConfig,'map_key' => $map_key]);
    }

     public function scheduleRideslist(Request $request)
    {
        $query = RequestModel::with('userDetail','driverDetail')
            ->where('is_cancelled', false)
            ->where('is_completed', false)
            ->where('is_later', true)
            ->orderBy('created_at','DESC')->get();

            Log::info('query: ' . $query);
    
        if (auth()->user()->hasRole('owner')) {
            // Retrieve the specific owner associated with the authenticated user
            $owner = auth()->user()->owner;
            $query->where('owner_id', $owner->id)->orWhere('booked_by', auth()->user()->id);
        }

        $requestmodel = fractal($query, new TripRequestTransformer)->parseIncludes(['userDetail','driverDetail','requestBill','rejectedDrivers'])->toArray();
        
         return response()->json([
            'results' => $requestmodel, 
        ]);
    }

      public function updateTripStartTime(Request $request, RequestModel $requestmodel)
    {

        $returnDate = $request->retrun_time_with_date; 
        $returnTime = $request->cv_return_time;     
        $newTripStart = $request->newTripStartTime; 

        // Combine return date + time into a Carbon instance
        if($returnDate != null){
            $returnDateTime = Carbon::createFromFormat('d/m/Y h:i A', $returnDate . ' ' . $returnTime);            

            // Parse trip start
            $tripStart = Carbon::parse($newTripStart);

            // Compare
            if ($tripStart->greaterThanOrEqualTo($returnDateTime)) {
                return response()->json([
                    'error' => 'Trip start time must be before return time'
                ], 422);
            }
        }
        $zone_type_detail = ZoneType::where('id', $requestmodel->zone_type_id)->first();
         $service_location = $zone_type_detail->zone->serviceLocation;
        $secondcarbonDateTime = Carbon::parse($request->newTripStartTime, $service_location->timezone)->setTimezone('UTC')->toDateTimeString();
        $requestmodel->update(['trip_start_time' => $secondcarbonDateTime]);
         $this->database->getReference('requests/'.$requestmodel->id)->update(['date'=>$requestmodel->converted_trip_start_time,'updated_at'=> Database::SERVER_TIMESTAMP]);

        $request_result =  fractal($requestmodel, new TripRequestTransformer)->parseIncludes('userDetail');

         return $this->respondSuccess($request_result, 'Scheduled Time has been Changes Successfully');
    }


    public function updateRequest(Request $request, RequestModel $requestmodel)
    {

        $zone_type_detail = ZoneType::where('id', $request->zone_type_id)->first();
        $type_id = $zone_type_detail->type_id;

        // Get currency code of Request
        $service_location = $zone_type_detail->zone->serviceLocation;
        $currency_code = $service_location->currency_code;
        $currency_symbol = $service_location->currency_symbol;
        $trip_start_time = $request->trip_start_time;
        $secondcarbonDateTime = Carbon::parse($request->trip_start_time, $service_location->timezone)->setTimezone('UTC')->toDateTimeString();
        $now = Carbon::now($service_location->timezone)->addHour(); 

        $unit = $zone_type_detail->zone->unit;
        $eta_result = fractal($zone_type_detail, new EtaTransformer);

        $eta_result =json_decode($eta_result->toJson());

         // Calculate ETA
         $request_eta_params=[
            'base_price'=>$eta_result->data->base_price,
            'base_distance'=>$eta_result->data->base_distance,
            'total_distance'=>$eta_result->data->distance,
            'total_time'=>$eta_result->data->time,
            'price_per_distance'=>$eta_result->data->price_per_distance,
            'distance_price'=>$eta_result->data->distance_price,
            'price_per_time'=>$eta_result->data->price_per_time,
            'time_price'=>$eta_result->data->time_price,
            'service_tax'=>$eta_result->data->tax_amount,
            'service_tax_percentage'=>$eta_result->data->tax,
            'promo_discount'=>$eta_result->data->discount_amount,
            'admin_commision'=>$eta_result->data->without_discount_admin_commision,
            'admin_commision_with_tax'=>($eta_result->data->without_discount_admin_commision + $eta_result->data->tax_amount),
            'total_amount'=>$eta_result->data->total,
            'requested_currency_code'=>$currency_code
        ];
        $user_detail = auth()->user();

         $trip_start_time = $secondcarbonDateTime; 
          $timezone = auth()->user()->timezone?:config('app.timezone');
        $request_params = [
            'zone_type_id'=>$request->zone_type_id,
            'trip_start_time'=>$trip_start_time,
            'if_dispatch'=>true,
            'dispatcher_id'=>$user_detail->admin->id ?? null,
            'poly_line'=>$request->poly_line,
            'unit'=>$unit,
            'requested_currency_code'=>$currency_code,
            'requested_currency_symbol'=>$currency_symbol,
            'service_location_id'=>$service_location->id,
            'transport_type'=>$request->transport_type,
            'total_distance'=>$eta_result->data->distance,
            'total_time'=>$eta_result->data->time,
        ];


        if(!$request->drop_lat){
            $request_params['is_without_destination'] = true;
        }
            if($request->has('request_eta_amount') && $request->request_eta_amount){
 
                $request_params['request_eta_amount'] = round($request->request_eta_amount, 2);
     
             }    
     
             if($request->has('rental_package_id') && $request->rental_package_id){
     
                 $request_params['is_rental'] = true; 
     
                 $request_params['rental_package_id'] = $request->rental_package_id;
             }
             if($request->has('goods_type_id') && $request->goods_type_id){
                 $request_params['goods_type_id'] = $request->goods_type_id; 
                 $request_params['goods_type_quantity'] = $request->goods_type_quantity;
             }

             $request_params['is_parcel'] = 1;
            $request_params['paid_at'] = 'Sender';
            $request_params['parcel_type'] = 'Send Parcel';


             if($request->has('is_out_station') && $request->is_out_station){
                $request_params['is_out_station'] = $request->is_out_station;
                $request_params['offerred_ride_fare'] = $eta_result->data->total;
        
                if($request->has('is_round_trip'))
                {
        
                $return_time = Carbon::parse($request->return_time, $timezone)->setTimezone('UTC')->toDateTimeString();
        
                $request_params['return_time'] = $return_time;
                $request_params['is_round_trip'] = true;
        
        
                }
        
        
            }
        
             try {
            $requestmodel->update($request_params);
            // request place detail params

            if ($request->has('stopovers')) {
                $requestmodel->requestStops()->where('request_id', '=', $requestmodel->id)->delete();

                foreach (json_decode($request->stopovers)as $key => $stop) {
                    $requestmodel->requestStops()->create([
                    'address'=>$stop->address,
                    'latitude'=>$stop->latitude,
                    'longitude'=>$stop->longitude,
                    'order'=>$key+1]);

                }
            }
            else{
                  $requestmodel->requestStops()->where('request_id', '=', $requestmodel->id)->delete();
            }
            $request_place_params = [
            'pick_lat'=>$request->pick_lat,
            'pick_lng'=>$request->pick_lng,
            'drop_lat'=>$request->drop_lat,
            'drop_lng'=>$request->drop_lng,
            'pick_address'=>$request->pick_address,
            'drop_address'=>$request->drop_address];
            // store request place details
            $requestmodel->requestPlace()->where('request_id', '=', $requestmodel->id)->delete();
            $requestmodel->requestPlace()->create($request_place_params);

            $this->storeEta($requestmodel,$eta_result);
            
            $this->database->getReference('requests/'.$requestmodel->id)->update(['pick_address'=>$request->pick_address,'drop_address'=>$request->drop_address,'date'=>$requestmodel->converted_trip_start_time,'updated_at'=> Database::SERVER_TIMESTAMP]);




            $request_result =  fractal($requestmodel, new TripRequestTransformer)->parseIncludes('userDetail');
            // @TODO send sms & email to the user
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            Log::error('Error while Create new schedule request. Input params : ' . json_encode($request->all()));
            return $this->respondBadRequest('Unknown error occurred. Please try again later or contact us if it continues.');
        }
        DB::commit();

        return $this->respondSuccess($request_result, 'Request Scheduled Successfully');

    }
    public function agentDashboard() 
    {
        $firebaseSettings = (object) [
            'apiKey' => get_firebase_settings('firebase_api_key'),
            'authDomain' => get_firebase_settings('firebase_auth_domain'),
            'databaseURL' => get_firebase_settings('firebase_database_url'),
            'projectId' => get_firebase_settings('firebase_project_id'),
            'storageBucket' => get_firebase_settings('firebase_storage_bucket'),
            'messagingSenderId' => get_firebase_settings('firebase_messaging_sender_id'),
            'appId' => get_firebase_settings('firebase_app_id'),
        ];

        return Inertia::render('agent/dashboard/index', ['firebaseSettings' => $firebaseSettings, ]);
    }
    public function todayEarnings(HttpRequest $request)
    {
        $service_location_id = $request->service_location_id;
        // Assuming $today is defined or set to today's date
        $today = now()->toDateString();
        // Fetch the data
        $tripQuery = RequestModel::selectRaw('
            IFNULL(SUM(CASE WHEN is_completed=1 THEN 1 ELSE 0 END), 0) AS completed,
            IFNULL(SUM(CASE WHEN is_completed=0 AND is_cancelled=0 THEN 1 ELSE 0 END), 0) AS scheduled,
            IFNULL(SUM(CASE WHEN is_cancelled=1 THEN 1 ELSE 0 END), 0) AS cancelled
        ');
        $user = auth()->user();
        if($user->admin){
            $overallTrips = $tripQuery->where('dispatcher_id',$user->admin->id)->where('if_dispatch',1)->first();
            $todayTrips = $tripQuery->whereDate('created_at', $today)->where('dispatcher_id',$user->admin->id)->where('if_dispatch',1)->first();
        }
        else{
            $overallTrips = $tripQuery->where('booked_by',$user->id)->where('if_dispatch',1)->first();
            $todayTrips = $tripQuery->whereDate('created_at', $today)->where('booked_by',$user->id)->where('if_dispatch',1)->first();
        }
        

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
        if($user->admin){
             $overallEarnings = $earningQuery->where('requests.dispatcher_id',$user->admin->id)->where('requests.if_dispatch',1)->first();

            $todayEarnings = $earningQuery->whereDate('requests.trip_start_time',date('Y-m-d'))
                        ->where('requests.dispatcher_id',$user->admin->id)->where('requests.if_dispatch',1)->first();
        }
        else{
            $overallEarnings = $earningQuery->where('requests.booked_by',$user->id)->where('requests.if_dispatch',1)->first();

            $todayEarnings = $earningQuery->whereDate('requests.trip_start_time',date('Y-m-d'))
                        ->where('requests.booked_by',$user->id)->where('requests.if_dispatch',1)->first();
        }
       

        $todayEarningData=[
            "card"=> $todayEarnings->card,
            "cash"=> $todayEarnings->cash,
            "wallet"=> $todayEarnings->wallet,
            "total"=> $todayEarnings->total,
            "admin_commision"=> $todayEarnings->admin_commision,
            "driver_commision"=> $todayEarnings->driver_commision,
        ];

        $overallEarningData=[
            "card"=> $overallEarnings->card,
            "cash"=> $overallEarnings->cash,
            "wallet"=> $overallEarnings->wallet,
            "total"=> $overallEarnings->total,
            "admin_commision"=> $overallEarnings->admin_commision,
            "driver_commision"=> $overallEarnings->driver_commision,
        ];

        $data = [
            'today' => [
                'completed' => (int) $todayTrips->completed,
                'scheduled' => (int) $todayTrips->scheduled,
                'cancelled' => (int) $todayTrips->cancelled,
                'earnings' => $todayEarningData,
            ],
            'overall' => [
                'completed' => (int) $overallTrips->completed,
                'scheduled' => (int) $overallTrips->scheduled,
                'cancelled' => (int) $overallTrips->cancelled,
                'earnings' => $overallEarningData,
            ],
        ];
    
        // Return JSON response
        return response()->json($data);
    }
    public function overallEarnings(HttpRequest $request)
    {
        $service_location_id = $request->service_location_id;
        $startDate = Carbon::now()->startOfYear(); // Start of the current year (January 1st)
        $endDate = Carbon::now(); // End date is now (current date)
    
        // Initialize arrays for months and earnings
        $months = [];
        $values = [];
    
        // Loop through each month from the start of the year to the current date
        while ($startDate->lte($endDate)) {
            $from = Carbon::parse($startDate)->startOfMonth(); // Start of the month
            $to = Carbon::parse($startDate)->endOfMonth(); // End of the month
    
            // Add the short name of the month to the months array
            $months[] = $startDate->shortEnglishMonth;
            $user = auth()->user();
            if($user->admin){
                $totalEarnings = RequestBill::whereHas('requestDetail', function ($query) use ($from, $to, $service_location_id,$user) {
                    $query->companyKey()->whereBetween('trip_start_time', [$from, $to])->whereIsCompleted(true)->where('dispatcher_id',$user->admin->id)->where('if_dispatch',1);
                })->sum('agent_commision');
            }
            else{    
                // Sum up the earnings for the current month
                $totalEarnings = RequestBill::whereHas('requestDetail', function ($query) use ($from, $to, $service_location_id,$user) {
                    $query->companyKey()->whereBetween('trip_start_time', [$from, $to])->whereIsCompleted(true)->where('booked_by',$user->id)->where('if_dispatch',1);
                })->sum('agent_commision');
            }
    
            // Add the total earnings for the month to the values array
            $values[] = $totalEarnings;
    
            // Move to the next month
            $startDate->addMonth();
        }
    
        // Prepare the data to be returned
        $earningsData = [
            'earnings' => [
                'months' => $months,
                'values' => $values,
            ],
        ];
    
        // Return the data as a JSON response
        return response()->json($earningsData);
    }
    public function cancelChart(HttpRequest $request)
    {
        $service_location_id = $request->service_location_id;
        $startDate = Carbon::now()->startOfYear(); // Start of the current year (January 1st)
        $endDate = Carbon::now(); // End date is now (current date)

        // Initialize arrays for months and cancellation data
        $months = [];
        $a = []; // Cancelled by method '0'
        $u = []; // Cancelled by method '1'
        $d = []; // Cancelled by method '2'
    
        // Loop through each month from the start of the year to the current date
        while ($startDate->lte($endDate)) {
            $from = Carbon::parse($startDate)->startOfMonth(); // Start of the month
            $to = Carbon::parse($startDate)->endOfMonth(); // End of the month
    
            // Add the short name of the month to the months array
            $months[] = $startDate->shortEnglishMonth;
            $user = auth()->user();
    
            $cancelQuery = RequestModel::companyKey()->whereIsCancelled(true);
            // Collect cancellation data based on cancel method
            if($user->admin){
                $a[] = $cancelQuery->whereBetween('created_at', [$from, $to])
                ->where('cancel_method', "0")
                ->where('dispatcher_id',$user->admin->id)->where('if_dispatch',1)
                ->count();                
            }
            else{                
                $a[] = $cancelQuery->whereBetween('created_at', [$from, $to])
                    ->where('cancel_method', "0")
                    ->where('booked_by',$user->id)->where('if_dispatch',1)
                    ->count();
            }

            $cancelQuery = RequestModel::companyKey()->whereIsCancelled(true);
            if($user->admin){
                 $u[] = $cancelQuery->whereBetween('created_at', [$from, $to])
                ->where('cancel_method', '1')
                ->where('dispatcher_id',$user->admin->id)->where('if_dispatch', 1)
                ->count();
            }
            else{

                $u[] = $cancelQuery->whereBetween('created_at', [$from, $to])
                    ->where('cancel_method', '1')
                    ->where('booked_by',$user)->where('if_dispatch',1)
                    ->count();
            }
            

            $cancelQuery = RequestModel::companyKey()->whereIsCancelled(true);
            if($user->admin){
                $d[] = $cancelQuery->whereBetween('created_at', [$from, $to])
                ->where('cancel_method', '2')
                ->where('dispatcher_id',$user->admin->id)->where('if_dispatch',1)
                ->count();
            }
            else{            
                $d[] = $cancelQuery->whereBetween('created_at', [$from, $to])
                    ->where('cancel_method', '2')
                    ->where('booked_by',$user->id)->where('if_dispatch',1)
                    ->count();
            }
    
            // Move to the next month
            $startDate->addMonth();
        }
    
        // Prepare the data to be returned
        $cancelData = [
            'y' => $months,
            'a' => $a,
            'u' => $u,
            'd' => $d,
        ];
        if($user->admin){
            $tripQuery = RequestModel::companyKey()->where('created_at','>',Carbon::now()->startOfYear())->where('dispatcher_id',$user->admin->id)->where('if_dispatch',1);
        }
        else{
            $tripQuery = RequestModel::companyKey()->where('created_at','>',Carbon::now()->startOfYear())->where('booked_by',$user)->where('if_dispatch',1);
        }
        $cancelledtrips = $tripQuery->selectRaw('
            COUNT(CASE WHEN is_cancelled = 1 AND cancel_method = 0 THEN 1 END) AS auto_cancelled,
            COUNT(CASE WHEN is_cancelled = 1 AND cancel_method = 1 THEN 1 END) AS user_cancelled,
            COUNT(CASE WHEN is_cancelled = 1 AND cancel_method = 2 THEN 1 END) AS driver_cancelled,
            COUNT(CASE WHEN is_cancelled = 1 AND cancel_method = 3 THEN 1 END) AS dispatcher_cancelled,
            COUNT(CASE WHEN is_cancelled = 1 AND is_completed = 0  THEN 1 END) AS total_cancelled
        ')->first();


        $cancelData['data'] = $cancelledtrips;
        // Return the data as a JSON response
        return response()->json($cancelData);
    }

    public function unassignedRides(){
        $user = auth()->user();
        $today = Carbon::today()->toDateString();

        if($user->admin){
            $request = RequestModel::where('driver_id', null)
                ->where('is_completed', 0)
                ->where('is_cancelled', 0)
                ->where('dispatcher_id',$user->admin->id)
                ->where('if_dispatch', 1)
                ->whereDate('created_at', $today)
                ->get();

        }
        else{
            
            $request = RequestModel::where('driver_id', null)
                ->where('is_completed', 0)
                ->where('is_cancelled', 0)
                ->where('booked_by',$user->id)
                ->where('if_dispatch', 1)
                ->whereDate('created_at', $today)
                ->get();

        }

        $requestmodel = fractal($request, new TripRequestTransformer)->parseIncludes(['userDetail','driverDetail','requestBill','rejectedDrivers'])->toArray();
        return response()->json([
            'results' => $requestmodel,
        ]);
    }


    public function requestEnquiries() {
        $requestEnquiries = RequestEnquiry::with('userDetail')
            ->orderBy('created_at','DESC')->pluck('id');

        $service_location_id = get_user_location_ids(auth()->user());
        return Inertia::render('agent/request_enquires/index',[
            'request_enquiries' => $requestEnquiries,
        ]);
    }

    //trip request list

    public function requestEnquiriesList(QueryFilterContract $queryFilter, Request $request)
    {
        $agent = auth()->user();

        Log::info('agent '.$agent);
        Log::info('agent_id '.$agent->id);
        $query = RequestEnquiry::where('dispatcher_id', $agent->id)->with('userDetail')
            ->orderBy('created_at', 'DESC');
    
        $results = $queryFilter->builder($query)
            ->customFilter(new RequestEnquiryFilter)
            ->paginate();
    
        $data = [
            'results' => $results->items(),
            'paginator' => $results,
        ];
        
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        
        return response($json, 200)
            ->header('Content-Type', 'application/json');
    }

    public function deleteEnquiry($requestEnquiries)
    {
       RequestEnquiry::where('id', $requestEnquiries)->update(['is_cancelled'=> 1]);

        return response()->json([
            'successMessage' => 'Request enquiry cancelled successfully',
        ]);
    }


    public function requestEnquiriesChart(HttpRequest $request)
    {
        $service_location_id = $request->service_location_id;
        // Assuming $today is defined or set to today's date
        $today = now()->toDateString();
        // Fetch the data
        $tripQuery = RequestEnquiry::selectRaw('
            IFNULL(SUM(CASE WHEN converted_as_ride =1 THEN 1 ELSE 0 END), 0) AS converted_as_ride,
            IFNULL(SUM(CASE WHEN converted_as_ride=0 AND is_cancelled=0 THEN 1 ELSE 0 END), 0) AS enquiry_pending,
            IFNULL(SUM(CASE WHEN is_cancelled=1 THEN 1 ELSE 0 END), 0) AS cancelled
        ');

        $user = auth()->user();
        if($user->admin){
            $overallTrips = $tripQuery->where('dispatcher_id',$user->admin->id)->first();
        }
        else{
             $overallTrips = $tripQuery->where('dispatcher_id',$user->id)->first();
        }


        $data = [
            'overall' => [
                'converted_as_ride' => (int) $overallTrips->converted_as_ride,
                'enquiry_pending' => (int) $overallTrips->enquiry_pending,
                'cancelled' => (int) $overallTrips->cancelled,
            ],
        ];
    
        // Return JSON response
        return response()->json($data);
    }


    public function endRequest(DriverEndRequest $request)
    {   
        // Get Driver Detail
        $driver = Driver::find($request->driver_id);
        Log::info('Driver End');
        Log::info($request->all());

        // Get Request Detail
        $request_detail = $driver->requestDetail()->where('id', $request->request_id)->first();

        if (!$request_detail) {
            $this->throwAuthorizationException();
        }

        // Validate Trip request data
        if ($request_detail->is_completed) {

            $request_result = fractal($request_detail, new TripRequestTransformer)->parseIncludes('requestBill');
            return $this->respondSuccess($request_result, 'request_ended');
        }
        if ($request_detail->is_cancelled) {
            $this->throwCustomException('request cancelled');
        }


        // Collecting drop location detail & update to request_place table
        $firebase_request_detail = $this->database->getReference('requests/'.$request_detail->id)->getValue();

        $request_place_params = ['drop_lat'=>$request->drop_lat,'drop_lng'=>$request->drop_lng,'drop_address'=>$request->drop_address];

        if ($firebase_request_detail) {
            if(array_key_exists('lat_lng_array',$firebase_request_detail)){
                $locations = $firebase_request_detail['lat_lng_array'];
                $request_place_params['request_path'] = $locations;
            }
        }

        // Update Droped place details
        $request_detail->requestPlace->update($request_place_params);
        // Update Driver state as Available
        $occupied_seats = 0;
        if($request_detail->shared_ride){
            $occupied_seats = ($request_detail->driverDetail->occupied_seats - $request_detail->seats_taken) ?? 0;
        }
        $request_detail->driverDetail->update(['available'=>true,'occupied_seats'=>$occupied_seats]);

         // Get currency code of Request
        $service_location = $request_detail->zoneType->zone->serviceLocation;

        $currency_code = $service_location->currency_code;

        $requested_currency_symbol = $service_location->currency_symbol;


        // Get the Price Details

        $zone_type = $request_detail->zoneType;

        $zone_type_price = $zone_type->zoneTypePrice()->first();

        // Calulate Distance & duration
        $distance = (double)$request->distance;

        Log::info('app-distance for '.$request_detail->request_number);
        Log::info($distance);

        $distance_and_duration = $this->calculateDistanceAndDuration($distance,$request_detail);

        $distance = $distance_and_duration['distance'];
        $duration = $distance_and_duration['duration'];


        $request_params = [
            'is_completed'=>true,
            'completed_at'=>date('Y-m-d H:i:s'),
            'total_distance'=>$distance,
            'total_time'=>$duration,
            ];

        if($request->poly_line !=null){

            $request_params['poly_line'] = $request->poly_line;

        }
        $request_detail->update($request_params);

        // Calulate Waiting Time
        $before_trip_start_waiting_time = $request->input('before_trip_start_waiting_time');
        $after_trip_start_waiting_time = $request->input('after_trip_start_waiting_time');

        $waiting_time = $before_trip_start_waiting_time + $after_trip_start_waiting_time;

        // Get/Validate Coupon Detail
        $promo_detail =null;

        if ($request_detail->promo_id) {
            $user_id = $request_detail->userDetail->id;
            $service_location_id = $request_detail->service_location_id;
            $promo_detail = $this->validateAndGetPromoDetail($request_detail->promo_id,$user_id,$request_detail);
        }


        // Collect Request pickup & drop coords
        $pick_lat = $request_detail->pick_lat;
        $drop_lat = $request_detail->drop_lat;
        $pick_lng = $request_detail->pick_lng;
        $drop_lng = $request_detail->drop_lng;

        $timezone = $request_detail->serviceLocationDetail->timezone;

        $request_place = $request_detail->requestPlace;

        $airport_surge = find_airport($request_place->pick_lat,$request_place->pick_lng);
        if($airport_surge==null)
        {
            $airport_surge = find_airport($request_place->drop_lat,$request_place->drop_lng);
        }

        $airport_surge_fee = 0;

        if($airport_surge){

            $airport_surge_fee =  $zone_type->airport_surge ?? 0;

        }
        // Calculate Bill of a Ride
        $calculated_bill = $this->calculateBillForARide($pick_lat,$pick_lng,$drop_lat,$drop_lng,$distance, $duration, $zone_type, $zone_type_price, $promo_detail,$timezone,null,$waiting_time,$request_detail,$driver,$airport_surge_fee);


         if($request_detail->is_rental && $request_detail->rental_package_id){

            $zone_type_price = ZoneTypePackagePrice::where('zone_type_id',$request_detail->zone_type_id)->where('package_type_id',$request_detail->rental_package_id)->first();

            $calculated_bill =  $this->calculateRentalRideFares($zone_type_price, $distance, $duration, $waiting_time, $promo_detail,$request_detail,$airport_surge_fee);

        }


        $calculated_bill['before_trip_start_waiting_time'] = $before_trip_start_waiting_time;
        $calculated_bill['after_trip_start_waiting_time'] = $after_trip_start_waiting_time;
        $calculated_bill['calculated_waiting_time'] = $waiting_time;
        $calculated_bill['waiting_charge_per_min'] = $zone_type_price->waiting_charge ?? 0;
        $calculated_bill['requested_currency_code'] = $currency_code;
        $calculated_bill['requested_currency_symbol'] = $requested_currency_symbol;

        if($request_detail->additional_charges_amount > 0) {
            $calculated_bill['additional_charges_reason'] = $request_detail->additional_charges_reason;
            $calculated_bill['additional_charges_amount'] = $request_detail->additional_charges_amount;
        }
        // Store Bill detail
        $bill = $request_detail->requestBill()->create($calculated_bill);

        if ($request_detail->promo_id) {
            $promo_available_balance = $this->validatePromoAvailableBalance($request_detail->promo_id,$bill);
        }


        if($request_detail->transport_type=='delivery' && $request_detail->payment_opt==1){

            $this->handlePayment($request_detail);
             
        }

        // Incentives & Driver Rewards/Level
        $incentive_feature = get_settings('show_incentive_feature_for_driver');
       
        if($incentive_feature==1)
        {
            if($driver->owner_id==null)
            {
                dispatch(new ValidateAndUpdateIncentivesJob($request_detail));
            }

        }

        $driver_level_feature = get_settings('show_driver_level_feature');
       
        if($driver_level_feature==1 && !$driver->owner_id)
        {
            dispatch(new ValidateAndUpdateDriverLoyaltyJob($request_detail));
        }


        if ($request_detail->payment_opt == PaymentType::WALLET) {

            if ($this->handlePayment($request_detail) ) {
                $request_detail->update([ 'is_paid'=>true, ]);
            }else{
                $request_detail->update([ 'payment_opt' => PaymentType::CASH, ]);
            }
        }   

        $user = $request_detail->userDetail;


        // Collect Payment from card if ride has card token
        if($request_detail->payment_opt==PaymentType::CARD && $request_detail->card_token){

            if($request_detail->payment_intent_id){
                $requested_amount = $request_detail->requestBill->total_amount;
                
                if($this->updateAmount($request_detail->payment_intent_id,$requested_amount)){

                    if($this->capture($request_detail->payment_intent_id)){

                        $request_detail->is_paid = true;
            
                        $request_detail->save();
            
                        $request_detail->fresh();
                    }

                }else{
                    
                    $this->cancel($request_detail->payment_intent_id);

                    $requested_amount = $request_detail->requestBill->total_amount;

                    $requested_currency = $request_detail->requested_currency_code;
            
                    $conditional_description = 'for-ride-cost';
            
                    $description = $this->generatePaymentReference($user->id,$conditional_description);
            
                    $customer_id = $user->stripe_customer_id;
            
                    $payment_method = $request_detail->card_token;
            
                    $conditional_description = $request_detail->id;
            
                    $stripe = $this->makePaymentByStripe($user,$requested_amount,$requested_currency,$description,$customer_id,$payment_method,$conditional_description);
            
                    if(!$stripe){
            
                        $request_detail->payment_opt = 1;
            
                        $request_detail->save();
            
                        $request_detail->fresh();
                    }
                }

            }else{

                $requested_amount = $request_detail->requestBill->total_amount;

                $requested_currency = $request_detail->requested_currency_code;
        
                $conditional_description = 'for-ride-cost';
        
                $description = $this->generatePaymentReference($user->id,$conditional_description);
        
                $customer_id = $user->stripe_customer_id;
        
                $payment_method = $request_detail->card_token;
        
                $conditional_description = $request_detail->id;
        
                $stripe = $this->makePaymentByStripe($user,$requested_amount,$requested_currency,$description,$customer_id,$payment_method,$conditional_description);
        
                if(!$stripe){
        
                    $request_detail->payment_opt = 1;
        
                    $request_detail->save();
        
                    $request_detail->fresh();
                }
        
            }

        }

        $this->database->getReference('requests/' . $request_detail->id)->update(['is_completed' => true,]);

        
        // Send push notification to the user
        $request_result = fractal($request_detail, new TripRequestTransformer)->parseIncludes(['requestBill','userDetail','driverDetail']);

        if ($request_detail->if_dispatch || $request_detail->user_id==null ) {
            goto end;
        }
        // Send Push notification to the user
        
        if($user){
            $notification = \DB::table('notification_channels')
            ->where('topics', 'Invoice For End of the Ride User') // Match the correct topic
            ->first();

            if(!empty($user?->email)){

                $data = fractal($request_detail, new TripRequestTransformer)->parseIncludes(['userDetail', 'driverDetail', 'requestBill', 'rejectedDrivers'])->toArray(); 
                $logo = Setting::where('name', 'logo')->first();
                $invoice = ThirdPartySetting::where('module', 'mail_config')->pluck('value', 'name')->toArray();
                $data['formatted_completed_at'] = isset($request_detail['completed_at']) 
                ? Carbon::parse($request_detail['completed_at'])
                    ->setTimezone(config('app.timezone'))
                    ->format('M j, Y - h:i A') 
                : null;

                dispatch(new SendUserInvoiceMailNotification($user, $data, $logo, $invoice));

            } 


            //    send push notification 
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
                    dispatch(new SendPushNotification($user, $title, $body));
                }
        }

        
        $driver = $request_detail->driverDetail;


        if($driver && $driver->email){

            $data = fractal($request_detail, new TripRequestTransformer)->parseIncludes(['userDetail', 'driverDetail', 'requestBill', 'rejectedDrivers'])->toArray(); 
            $logo = Setting::where('name', 'logo')->first();
            $invoice = ThirdPartySetting::where('module', 'mail_config')->pluck('value', 'name')->toArray();
            $data['formatted_completed_at'] = isset($request_detail['completed_at']) 
            ? Carbon::parse($request_detail['completed_at'])
                ->setTimezone(config('app.timezone'))
                ->format('M j, Y - h:i A') 
            : null;

            dispatch(new SendDriverInvoiceMailNotification($driver, $data, $logo, $invoice));

        }

        end:
        
        return $this->respondSuccess($request_result, 'request_ended');
    }

    /**
    * Validate & Apply Promo code
    * @return \Illuminate\Http\JsonResponse
    *
    */
    public function validateAndGetPromoDetail($promo_code_id,$user_id,$request_detail)
    {
        $current_date = Carbon::today()->toDateTimeString();


            $transport_type = request()->transport_type;
            $expired = Promo::where('id', $promo_code_id)->where('service_location_id',$request_detail->service_location_id)->where(function($query)use($request_detail){
            $query->where('transport_type',$request_detail->transport_type)->orWhere('transport_type','both');
            })->where('to', '>', $current_date)->where('active',true)->first();

        if($expired)
        {

            if($expired->user_specific){
                $validate_promo_code = $expired->promoCodeUsers()->where('user_id',$request_detail->user_id)->first();
                if(!$validate_promo_code){
                    return null;
                }
            }
            $exceed_usage = PromoUser::where('promo_code_id', $expired->id)->where('user_id', $user_id)->count();

            if ($exceed_usage > $expired->uses_per_user) {
                return null;
            }
            else{
                return $expired;
            }
        }
        else{
            return null;
        }

    }

    /**
    * Validate & Apply Promo code
    * @return \Illuminate\Http\JsonResponse
    *
    */
    public function validatePromoAvailableBalance($promo_code_id,$bill)
    {
        $promo = Promo::where('id', $promo_code_id)->where('active',true)->first();

        if(!$promo){
            return null;
        }            
            
        if($promo->available_balance == 0){
            $total_amount = $promo->cummulative_maximum_discount_amount - $bill->promo_discount;     
        }  
        else{
            $total_amount = $promo->available_balance - $bill->promo_discount;
        }

        $new_balance = max(0, $total_amount);

        // Update balance and deactivate if exhausted
        $promo->update([
            'available_balance' => $new_balance,
            'active' => $new_balance > 0
        ]);

    }



    public function chatIndex() 
    {
        $firebaseConfig = (object) [
            'apiKey' => get_firebase_settings('firebase_api_key'),
            'authDomain' => get_firebase_settings('firebase_auth_domain'),
            'databaseURL' => get_firebase_settings('firebase_database_url'),
            'projectId' => get_firebase_settings('firebase_project_id'),
            'storageBucket' => get_firebase_settings('firebase_storage_bucket'),
            'messagingSenderId' => get_firebase_settings('firebase_messaging_sender_id'),
            'appId' => get_firebase_settings('firebase_app_id'),
        ];

        // Fetch the conversation data
        $conversations = Conversation::where('is_closed', false)
            ->orderBy('created_at','DESC')
            ->withSum('messages as unread', 'unseen_count')
            ->get();

        foreach ($conversations as $key => $chat) {
            if($chat->messages()->exists()){
                $chat->last_seen = $chat->messages[0]->converted_created_at;
            }
        }
        $conversationId = request()->conversation_id;
        $dispatcher = auth()->user();
        
        return Inertia::render('agent/chat/index', [
            'firebaseConfig' => $firebaseConfig,
            'selectedConversationId' => $conversationId,
            'conversations' => $conversations, // Pass the conversations to the view
            'dispatcher' =>$dispatcher,
        ]);
    }

    public function chatMessages(Conversation $conversationId)
    {
        // Get all messages for the specific conversation, ordered by created_at
        $messages = $conversationId->messages()->orderBy('created_at', 'Asc')->get();
    
        // Transform the messages to the desired structure
        $formattedMessages = $messages->map(function ($message) {
            return [
                'id' => $message->id,
                'align' => $message->sender_type === 'user' ? 'left' : 'right', // Assuming 'user' messages appear on the right
                'message' => $message->content,
                'profile_picture' => $message->profile_picture,
                'time' => $message->converted_created_at
            ];
        });
        foreach($messages as $key=> $message){
            $message->update(['unseen_count'=>0]);
        }
    
        return response()->json($formattedMessages); // Return JSON response
    }
    public function chatsendAdmin(Request $request)
    {
        $message = Message::create(['conversation_id'=>$request->conversationId,'sender_id'=>auth()->user()->id,
                'sender_type'=>'admin','content'=>$request->message, 'unseen_count'=>false]);


        $chat = [
            'conversation_id'=>$request->conversationId,'sender_id'=>auth()->user()->id,
            'message_id' => $message->id, 
            'created_at'=> Database::SERVER_TIMESTAMP,
            'sender_type'=>'admin','message'=>$request->message,
        ];
        
        $this->database->getReference('conversation/'.$request->conversationId)->set($chat);

        return response()->json([
            'successMessage' => 'message Sended successfully',
        ]);
   }
   public function chatclose(Request $request)
   {

    $conversations = Conversation::where('id', $request->conversationId)->update(['is_closed'=>true]);

        $this->database->getReference('conversation/'.$request->conversationId)->update(['is_closed'=>true]);
        $this->database->getReference('conversation/'.$request->conversationId)->remove();
        
        return response()->json([
            'successMessage' => 'Conversation Closed successfully',
        ]);
   }

   public function chatcreate(Request $request)
   {
        $request->validate(['user_id'=>'required']);
        $user = Driver::find($request->user_id);
        if(!$user){
            return response()->json([
                'message' => 'Conversation Closed successfully',
            ],403);
        }
        $user_id = $user->user_id;   
        $country = $user->country;
        $timezone = ServiceLocation::where('country',$country)->pluck('timezone')->first()?:'UTC';
        $check_data_exists =  Conversation::where('user_id', $user_id)->where('is_closed', false)->first();

        if(!$check_data_exists)
        {
            $conversation = new Conversation(); 
            $conversation->user_id = $user->user_id;
            $conversation->subject = $user->name;
            $conversation->save();
        }else{
            $conversation = $check_data_exists;

        }
        return response()->json($conversation);
   }
   public function chatfetchDriver(Request $request)
   {
        $request->validate(['user_id' => 'required']);
        $user = Driver::Find($request->user_id);

        return response()->json($user);
   }
   public function chatsFetch()
   {
        // Fetch the conversation data
        $conversations = Conversation::where('is_closed', false)->orderBy('created_at','DESC')
            ->withSum('messages as unread', 'unseen_count')
            ->having('unread','>',0)
            ->get();

        foreach ($conversations as $key => $chat) {
            $chat->last_seen = $chat->messages[0]->converted_created_at;
            $chat->last_message = $chat->messages[0]->content;
        }

        return response()->json($conversations);
   }
   public function readAllChat()
   {
        // Fetch the conversation data
        $conversations = Conversation::where('is_closed', false)
            ->withSum('messages as unread', 'unseen_count')
            ->having('unread','>',0)
            ->get();

        foreach ($conversations as $key => $chat) {
            $unread_messages = $chat->messages()->where('unseen_count',true)->get();
            foreach ($unread_messages as $key => $message) {
                $message->update(['unseen_count' => false]);
            }
        }
        $this->database->getReference('conversation')->update(['readMark'=>true]);
        $this->database->getReference('conversation/readMark')->remove();

        return response()->json([
            'successMessage' => 'Conversation Marked as Read successfully',
        ]);
   }

   public function chatsearchDriver(QueryFilterContract $queryFilter, Request $request)
   {
        $existingIds = Conversation::where('is_closed', false)
            ->pluck('user_id')->toArray();
         
       $query = Driver::orderBy('created_at','DESC')->whereNotIn('user_id',$existingIds);


       $results = $queryFilter->builder($query)->customFilter(new UserFilter)->paginate();

       return response()->json([
           'data' => $results->items(),
       ]);
   }
   public function chatVerify(Request $request)
   {
        $conversation = Conversation::where('is_closed',false)->where('id',$request->conversationId)->first();
        return response()->json([
            'data' => $conversation,
        ]);
   }


    public function withdrawalRequest (){
        return Inertia::render('agent/withdrawal_request/index');    
    }

     public function withdrawalRequestList (QueryFilterContract $queryFilter){
        
        $user = auth()->user()->agent;

        $query = WalletWithdrawalRequest::where('agent_id', $user->id);

        $results = $queryFilter->builder($query)->customFilter(new ServiceLocationFilter)->paginate();

        return response()->json([
            'results' => $results->items(),
            'paginator' => $results,
        ]);
    }

    public function listBankInfo()
    {
        $methods  = Method::where('active', true)->get();
        
      $result =  fractal($methods, new BankInfoTransformer);
      
      return $this->respondSuccess($result);
      
    }

    public function updateBankinfoNewWeb(Request $request)
    {

        if (auth()->user()->hasRole(Role::AGENT)) 
        {
        // Validate Request id
            $request->validate([
            'method_id' => 'required|exists:methods,id',
            ]);


            $method_detail = Method::where('id',$request->method_id)->first();

            $agentId = auth()->user()->agent->id;

            $request_params = $request->all();

            foreach ($request_params as $key => $request_param) {
                $field_data = $method_detail->fields()->where('input_field_name', $key)->first();

                if($field_data){

                    $agentBankInfo = AgentBankInfo::where('agent_id', $agentId)
                    ->where('method_id', $request->method_id)
                    ->where('field_id', $field_data->id)
                    ->first();

                if(!$agentBankInfo){

                    AgentBankInfo::create([
                        'agent_id' => $agentId,
                        'method_id' => $request->method_id,
                        'field_id' => $field_data->id,
                        'value' => $request->$key,
                    ]);


                }else{

                    $agentBankInfo->update([
                        'value' => $request->$key,
                    ]);
                }

                }

                    
            }
            return $this->respondSuccess(['message' => 'Agent Bank information updated successfully.']);          

        }
    }

    public function requestForWithdrawalWeb(Request $request)
    {

        $created_params = $request->all();
        $created_params['payment_status'] = "requested";

        if (access()->hasRole(Role::AGENT)) {

            $user_info = auth()->user()->agent;
            $currency_code = auth()->user()->countryDetail->currency_code;
            $currency_symbol = auth()->user()->countryDetail->currency_symbol;
 
            $created_params['requested_currency'] = $currency_code;
            $created_params['agent_id'] = auth()->user()->agent->id;

            $agent_wallet = auth()->user()->agent->agentWallet;

            $wallet_balance = $agent_wallet->amount_balance;


             $bank_info_exists = false;

            if (auth()->user()->agent->bankInfo()->exists()) {

                $bank_info_exists = true;
            }
            else{
                $this->throwCustomException('Update Your bank Details');
            }

            if ($wallet_balance <= 0) {

                $this->throwCustomException('Your wallet balance is too low');

            }

            if ($wallet_balance < $request->requested_amount) {

                $this->throwCustomException('Yout wallet balance is too low than your requested amount');

            }

            $exists_request = WalletWithdrawalRequest::where('agent_id', $user_info->id)->where('status', 0)->exists();

            if ($exists_request == true) {
                $this->throwCustomException('You cannot make multiple request. please wait for your existing request approval');
            }

        } 


        WalletWithdrawalRequest::create($created_params);

        return $this->respondSuccess(null, 'wallet_withdrawal_requested');


    }

    public function walletHistoryWeb()
    {
        if (access()->hasRole(Role::AGENT)) {
            $query = AgentWalletHistory::where('user_id', auth()->user()->agent->id)->orderBy('created_at', 'desc');
            $result = filter($query, new AgentWalletHistoryTransformer)->defaultSort('created_at')->paginate();
            

            $agent_wallet = auth()->user()->agent->agentWallet;

            if (!$agent_wallet) {

                $wallet_balance = 0;

            } else {                
                 $wallet_balance = $agent_wallet->amount_balance;

            }


            $currency_code = auth()->user()->countryDetail->currency_code;
            $currency_symbol = auth()->user()->countryDetail->currency_symbol;
        } 

        $methods = Method::with('fields')->get(); // Fetch all methods with their fields
       $bankInfos = auth()->user()->agent?->bankInfo ?? collect();

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
            'currency_code' => $currency_code,
            'currency_symbol' => $currency_symbol,
            'wallet_history' => $result,
            'bank_info_exists' => $formattedBankInfos,
        ]);
    }
     public function ridesRequest() {
        $ongoing = RequestModel::with('userDetail','driverDetail')
            ->where('is_cancelled', false)
            ->where('is_completed', false)
            ->orderBy('created_at','DESC')->pluck('id');
        $settings = ThirdPartySetting::where('module', 'firebase')->pluck('value', 'name')->toArray();

        $firebaseConfig = (object) [
            'apiKey' => $settings['firebase_api_key'],
            'authDomain' => $settings['firebase_auth_domain'],
            'databaseURL' => $settings['firebase_database_url'],
            'projectId' => $settings['firebase_project_id'],
            'storageBucket' => $settings['firebase_storage_bucket'],
            'messagingSenderId' => $settings['firebase_messaging_sender_id'],
            'appId' => $settings['firebase_app_id'],
        ];
        return Inertia::render('agent/delivery_rides_request/index',[
            'zones' => Zone::active()->get(),
            'types' => VehicleType::active()->get(),
            'ongoing_rides' => $ongoing,
            'firebaseConfig' => $firebaseConfig,
            'enable_outstation' => get_settings('show_outstation_ride_feature') || get_settings('show_delivery_outstation_ride_feature'),
        ]);
    }

    public function deliveryList(QueryFilterContract $queryFilter, Request $request)
    {
        $query = RequestModel::where('requests.transport_type', 'delivery')
            ->with('userDetail', 'driverDetail')
            ->orderBy('created_at', 'DESC');
    
        if (auth()->user()->hasRole('owner')) {
            // Retrieve the specific owner associated with the authenticated user
            $owner = auth()->user()->owner;
            $query = RequestModel::where('owner_id', $owner->id)
                ->orWhere('booked_by', auth()->user()->id)
                ->with('userDetail', 'driverDetail')
                ->orderBy('created_at', 'DESC');
        }
        if (auth()->user()->hasRole('franchise_owner')) {
            // Retrieve the specific owner associated with the authenticated user
            $owner = auth()->user()->franchise;
            $query = RequestModel::where('requests.transport_type', 'delivery')
                ->whereIn('requests.service_location_id',get_user_location_ids(auth()->user()))
                ->with('userDetail', 'driverDetail')
                ->orderBy('created_at', 'DESC');
        }
    
        $results = $queryFilter->builder($query)
            ->customFilter(new RequestFilter)
            ->paginate();
    
        $data = [
            'results' => $results->items(),
            'paginator' => $results,
        ];
        
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        
        return response($json, 200)
            ->header('Content-Type', 'application/json');
    }
     
}

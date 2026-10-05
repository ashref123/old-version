<?php

namespace App\Http\Controllers;
use Inertia\Inertia;
use App\Models\ThirdPartySetting;
use Illuminate\Http\Request;
use App\Models\Admin\VehicleType;
use App\Models\Admin\ServiceLocation;
use App\Models\Admin\Driver;
use App\Models\Request\Request as RequestModel;
use App\Models\Request\RequestPlace;
use App\Helpers\Rides\SyncDriverFirebaseAvailability;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Database;

class MapSettingController extends Controller
{
    protected Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function index() 
    {
        $settings = ThirdPartySetting::where('module', 'map')->pluck('value', 'name')->toArray();

        //   $map_type = get_map_settings('map_type');
    // dd($map_key);


        return Inertia::render('pages/map_settings/index', [
            'app_for'=>env('APP_FOR'),
            'settings' => $settings,
        ]);

    }

    public function mapShow() 
    {
        $settings = ThirdPartySetting::where('module', 'map')->pluck('value', 'name')->toArray();

        return Inertia::render('pages/map_show/index', [
            'app_for' => env('APP_FOR'),
            'settings' => $settings,
        ]);
    }

    public function mapShowUpdate(Request $request)
    {
        $request->validate([
            'map_show' => 'required|in:classic_layout,morden_layout',
        ]);

        ThirdPartySetting::updateOrCreate(
            ['module' => 'map', 'name' => 'map_show'],
            ['value' => $request->input('map_show')]
        );

        return response()->json(['message' => 'Map show setting updated successfully'], 201);
    }

     public function osmIndex()
    {
        $settings = ThirdPartySetting::where('module', 'map')->pluck('value', 'name')->toArray();

        $settings['enable_mapbox'] = filter_var($settings['enable_mapbox'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $settings['enable_thunderforest'] = filter_var($settings['enable_thunderforest'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $settings['enable_stadia'] = filter_var($settings['enable_stadia'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return Inertia::render('pages/osm_map_settings/index', [
            'app_for' => env('APP_FOR'),
            'settings' => $settings,
        ]);
    }

    public function osmUpdate(Request $request)
    {
        $request->validate([
            'enable_mapbox' => 'nullable|boolean',
            'enable_thunderforest' => 'nullable|boolean',
            'enable_stadia' => 'nullable|boolean',
            'mapbox_public_key' => 'nullable|string',
            'thunderforest_api_key' => 'nullable|string',
            'stadia_api_key' => 'nullable|string',
        ]);

        $enableMapbox = $request->boolean('enable_mapbox');
        $enableThunderforest = $request->boolean('enable_thunderforest');
        $enableStadia = $request->boolean('enable_stadia');

        if ($enableMapbox) {
            $enableThunderforest = false;
            $enableStadia = false;
        } elseif ($enableThunderforest) {
            $enableMapbox = false;
            $enableStadia = false;
        } elseif ($enableStadia) {
            $enableMapbox = false;
            $enableThunderforest = false;
        }

        $settings = [
            'enable_mapbox' => $enableMapbox ? '1' : '0',
            'mapbox_public_key' => $request->input('mapbox_public_key', ''),
            'enable_thunderforest' => $enableThunderforest ? '1' : '0',
            'thunderforest_api_key' => $request->input('thunderforest_api_key', ''),
            'enable_stadia' => $enableStadia ? '1' : '0',
            'stadia_api_key' => $request->input('stadia_api_key', ''),
        ];

        foreach ($settings as $key => $setting) {
            ThirdPartySetting::updateOrCreate(
                ['module' => 'map', 'name' => $key],
                ['value' => $setting]
            );
        }

        return response()->json(['message' => 'OSM map settings updated successfully'], 201);
    }
   
    public function update(Request $request) 
    {

        $settings = $request->only([
            'map_type',
            // 'enable_vase_map',
            'google_map_key_for_distance_matrix',
            // 'google_sheet_id',
            'google_map_key',]);
        
        foreach ($settings as $key => $setting) 
        {
            ThirdPartySetting::where('name' , $key )->update(['value' => $setting,'module'=>'map']);
        }
  
    
        return response()->json(['message' => 'Map  Details updated successfully'], 201);
    }    
    public function heatmap(Request $request) 
    {

        $map_key = get_map_settings('google_map_key');

        // dd($map_key);

        // Calculate the date one week ago
        $oneWeekAgo = Carbon::now()->subWeek();

        $requestData = RequestPlace::whereBetween('created_at', [$oneWeekAgo, Carbon::now()])
            ->whereHas('requestDetail',function($locationQuery){
                $locationQuery->whereIn('service_location_id',get_user_location_ids(auth()->user()));
            })->get();

                // dd($requestData);
        $map_type = get_map_settings('map_type');

        if($map_type=="open_street_map")
        {
        return Inertia::render('pages/map/openheatmap',[
        'default_lat'=>get_settings('default_latitude'),'default_lng'=>get_settings('default_longitude'),
        'requestData'=>$requestData, 'map_key'=>$map_key]);
        }else{
            return Inertia::render('pages/map/heatmap',[
                'default_lat'=>get_settings('default_latitude'),'default_lng'=>get_settings('default_longitude'),
                'requestData'=>$requestData, 'map_key'=>$map_key]);    
        }
    }

    public function godseye() 
    {

        $service_location = ServiceLocation::where('active', true)
            ->whereIn('id',get_user_location_ids(auth()->user()))
            ->get(['id', 'name']);
        $vehicle_type = VehicleType::where('active', true)->get(['id', 'name']);

        $map_key = get_map_settings('google_map_key');
        
        // dd($vehicle_type);


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
       
          if($map_type=="open_street_map")
          {
            return Inertia::render('pages/map/godseye-open',[
                'firebaseSettings'=>$firebaseSettings,
                'app_for' => env('APP_FOR'),
                'default_lat'=>get_settings('default_latitude'),
                'default_lng'=>get_settings('default_longitude'),
                'service_location'=>$service_location,
                'vehicle_type'=>$vehicle_type,
                // Places autocomplete (same Google Places API used on Google map Gods Eye).
                'map_key'=>$map_key,
            ]);
          }else{
            
            $default_location = (object)[
                "lat"=> (float) get_settings('default_latitude'),
                "lng"=> (float) get_settings('default_longitude'),
            ];
            return Inertia::render('pages/map/godseye',[
                'firebaseSettings'=>$firebaseSettings,
                'app_for' => env('APP_FOR'),
                'baseUrl'=>route('landing.index'),'default_location'=>$default_location,
                'service_location'=>$service_location,'vehicle_type'=>$vehicle_type,'map_key'=>$map_key
            ]);
          }


    }

    /**
     * Live eligibility diagnostics for a single driver (Gods Eye drawer).
     */
    public function driverEligibility(int $driver)
    {
        $driverModel = Driver::query()->findOrFail($driver);
        $this->assertDriverLocationAllowed($driverModel);

        $firebase = $this->database->getReference('drivers/driver_'.$driverModel->id)->getValue() ?: [];
        $openTrips = SyncDriverFirebaseAvailability::openTripsSummary((int) $driverModel->id);
        $payload = $this->buildEligibilityPayload($driverModel, $firebase, $openTrips);

        return response()->json($payload);
    }

    /**
     * Batch: which of the given driver IDs still have open trips (for stuck badges).
     */
    public function driversOpenTrips(Request $request)
    {
        $ids = collect($request->input('driver_ids', []))
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->unique()
            ->take(200)
            ->values();

        if ($ids->isEmpty()) {
            return response()->json(['open_trip_driver_ids' => []]);
        }

        $allowedLocations = collect(get_user_location_ids(auth()->user()))->filter()->map(static fn ($id) => (string) $id);

        $visibleIds = Driver::query()
            ->whereIn('id', $ids)
            ->when($allowedLocations->isNotEmpty(), function ($q) use ($allowedLocations) {
                $q->whereIn('service_location_id', $allowedLocations->all());
            })
            ->pluck('id');

        $openTripDriverIds = RequestModel::query()
            ->whereIn('driver_id', $visibleIds)
            ->where('is_completed', false)
            ->where('is_cancelled', false)
            ->distinct()
            ->pluck('driver_id')
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();

        return response()->json([
            'open_trip_driver_ids' => $openTripDriverIds,
        ]);
    }

    /**
     * Safe heal: MySQL + Firebase available when the driver has no open trips.
     */
    public function resetDriverAvailability(int $driver)
    {
        $driverModel = Driver::query()->findOrFail($driver);
        $this->assertDriverLocationAllowed($driverModel);

        if (SyncDriverFirebaseAvailability::hasOpenTrips((int) $driverModel->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Driver still has an open trip; availability was not reset.',
                'open_trips' => SyncDriverFirebaseAvailability::openTripsSummary((int) $driverModel->id),
            ], 422);
        }

        $driverModel->available = true;
        $driverModel->save();

        SyncDriverFirebaseAvailability::setAvailable((int) $driverModel->id, true, $this->database);

        Log::channel('activity')->info('Admin reset driver availability', [
            'driver_id' => $driverModel->id,
            'admin_user_id' => auth()->id(),
        ]);

        $firebase = $this->database->getReference('drivers/driver_'.$driverModel->id)->getValue() ?: [];
        $driverModel->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Driver availability reset.',
            'eligibility' => $this->buildEligibilityPayload(
                $driverModel,
                $firebase,
                []
            ),
        ]);
    }

    protected function assertDriverLocationAllowed(Driver $driver): void
    {
        $allowed = collect(get_user_location_ids(auth()->user()))
            ->filter()
            ->map(static fn ($id) => (string) $id);

        if ($allowed->isNotEmpty() && ! $allowed->contains((string) $driver->service_location_id)) {
            abort(403, 'Driver outside your service locations.');
        }
    }

    /**
     * @param  array<string,mixed>  $firebase
     * @param  array<int, array{id:string,request_number:?string,is_driver_started:bool}>  $openTrips
     * @return array<string,mixed>
     */
    protected function buildEligibilityPayload(Driver $driver, array $firebase, array $openTrips): array
    {
        $staleAfterMinutes = 7;
        $fbActive = isset($firebase['is_active']) ? (int) $firebase['is_active'] === 1 : false;
        $fbAvailable = $this->firebaseTruthy($firebase['is_available'] ?? null);

        $updatedAtMs = null;
        if (isset($firebase['updated_at']) && is_numeric($firebase['updated_at'])) {
            $updatedAtMs = (float) $firebase['updated_at'];
            if ($updatedAtMs < 1e12) {
                $updatedAtMs *= 1000;
            }
        }

        $ageSeconds = $updatedAtMs ? (int) max(0, (now()->getTimestampMs() - $updatedAtMs) / 1000) : null;
        $isFresh = $updatedAtMs !== null && $ageSeconds !== null && $ageSeconds <= ($staleAfterMinutes * 60);

        $mysqlActive = (bool) $driver->active;
        $mysqlAvailable = (bool) $driver->available;
        $mysqlApprove = (bool) $driver->approve;
        $openCount = count($openTrips);

        $reasons = [];
        if (! $fbActive) {
            $reasons[] = 'offline';
        }
        if ($fbActive && ! $fbAvailable && $openCount > 0) {
            $reasons[] = 'on_trip';
        }
        if ($fbActive && ! $fbAvailable && $openCount === 0) {
            $reasons[] = 'stuck_desync';
        }
        if ($fbActive && $fbAvailable && ! $isFresh) {
            $reasons[] = 'stale_location';
        }
        if (! $mysqlApprove) {
            $reasons[] = 'disapproved';
        }
        if ($mysqlAvailable !== $fbAvailable) {
            $reasons[] = 'mysql_firebase_mismatch';
        }
        if ($fbActive && $fbAvailable && $isFresh && $mysqlApprove && $mysqlActive) {
            $reasons[] = 'offer_ready';
        }

        $status = 'offline';
        if ($fbActive && $fbAvailable) {
            $status = 'online';
        } elseif ($fbActive && ! $fbAvailable && $openCount === 0) {
            $status = 'stuck';
        } elseif ($fbActive && ! $fbAvailable) {
            $status = 'onride';
        }

        $offerReady = $fbActive && $fbAvailable && $isFresh && $mysqlApprove && $mysqlActive;

        return [
            'driver_id' => (int) $driver->id,
            'name' => $driver->name,
            'mobile' => $driver->mobile,
            'status' => $status,
            'offer_ready' => $offerReady,
            'reasons' => $reasons,
            'stale_after_minutes' => $staleAfterMinutes,
            'firebase' => [
                'is_active' => $fbActive,
                'is_available' => $fbAvailable,
                'updated_at' => $firebase['updated_at'] ?? null,
                'updated_at_age_seconds' => $ageSeconds,
                'is_fresh' => $isFresh,
                'vehicle_types' => $firebase['vehicle_types'] ?? null,
                'approve' => $firebase['approve'] ?? null,
                'service_location_id' => $firebase['service_location_id'] ?? null,
            ],
            'mysql' => [
                'active' => $mysqlActive,
                'available' => $mysqlAvailable,
                'approve' => $mysqlApprove,
                'occupied_seats' => (int) ($driver->occupied_seats ?? 0),
                'service_location_id' => $driver->service_location_id,
            ],
            'open_trips' => $openTrips,
            'open_trip_count' => $openCount,
            'can_reset_availability' => $fbActive && ! $fbAvailable && $openCount === 0,
        ];
    }

    protected function firebaseTruthy($value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

}

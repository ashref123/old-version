<?php

namespace App\Http\Controllers;

use App\Base\Constants\Auth\Role;
use App\Helpers\Rides\FetchDriversFromFirebaseHelpers;
use App\Models\Request\DriverRejectedRequest;
use App\Models\Request\Request as RequestModel;
use App\Models\Request\RequestMeta;
use App\Models\ThirdPartySetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Kreait\Firebase\Contract\Database;

class DispatchMonitorController extends Controller
{
    use FetchDriversFromFirebaseHelpers;

    protected Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function index()
    {
        $settings = ThirdPartySetting::where('module', 'firebase')->pluck('value', 'name')->toArray();

        $firebaseConfig = (object) [
            'apiKey' => $settings['firebase_api_key'] ?? null,
            'authDomain' => $settings['firebase_auth_domain'] ?? null,
            'databaseURL' => $settings['firebase_database_url'] ?? null,
            'projectId' => $settings['firebase_project_id'] ?? null,
            'storageBucket' => $settings['firebase_storage_bucket'] ?? null,
            'messagingSenderId' => $settings['firebase_messaging_sender_id'] ?? null,
            'appId' => $settings['firebase_app_id'] ?? null,
        ];

        // Do not seed system-wide rides — client must choose a user first.
        return Inertia::render('pages/dispatch_monitor/index', [
            'firebaseConfig' => $firebaseConfig,
            'acceptDuration' => (int) (get_settings('trip_accept_reject_duration_for_driver') ?: 30),
            'serverTimeoutSeconds' => 60,
            'app_for' => env('APP_FOR'),
            'initialRides' => [],
            'initialMeta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 20,
                'total' => 0,
            ],
        ]);
    }

    /**
     * Lightweight user picker for dispatch monitor (name / mobile / email).
     */
    public function searchUsers(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        if (strlen($search) < 2) {
            return response()->json(['results' => []]);
        }

        $users = User::query()
            ->belongsTorole(Role::USER)
            ->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'mobile', 'email']);

        return response()->json([
            'results' => $users->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'mobile' => $u->mobile,
                'email' => $u->email,
                'label' => trim(($u->name ?? '').' '.($u->mobile ? "({$u->mobile})" : '').($u->email ? " · {$u->email}" : '')),
            ])->values(),
        ]);
    }

    /**
     * Paginated list of rides currently searching for drivers.
     * Requires user_id — empty until an admin picks a customer to debug.
     */
    public function searching(Request $request)
    {
        return response()->json($this->searchingPayload($request));
    }

    /**
     * Build searching-rides list payload (shared by Inertia seed + JSON poll).
     */
    protected function searchingPayload(Request $request): array
    {
        $perPage = min(50, max(10, (int) $request->get('per_page', 20)));
        $transportType = $request->get('transport_type', 'all');
        $search = trim((string) $request->get('search', ''));
        $userId = $request->get('user_id');
        $locationIds = collect(get_user_location_ids(auth()->user()))
            ->filter()
            ->values()
            ->all();

        $emptyMeta = [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => $perPage,
            'total' => 0,
        ];

        // Require a customer filter so we never load all system searching rides.
        if (!$userId) {
            return [
                'data' => collect(),
                'meta' => $emptyMeta,
                'server_time' => now()->toDateTimeString(),
                'requires_user' => true,
            ];
        }

        $query = RequestModel::query()
            ->with(['userDetail', 'requestPlace'])
            ->withCount([
                'requestMeta as active_offer_count' => function ($q) {
                    $q->where('active', true);
                },
                'requestMeta as total_meta_count',
                'driverRejectedRequestDetail as rejected_count',
            ])
            ->where('user_id', $userId)
            ->where('is_cancelled', false)
            ->where('is_completed', false)
            ->whereNull('driver_id')
            ->where(function ($q) {
                $q->where('on_search', true)
                    ->orWhereHas('requestMeta', function ($meta) {
                        $meta->where('active', true);
                    });
            })
            ->where(function ($q) {
                $q->where('is_bid_ride', false)
                    ->orWhereNull('is_bid_ride');
            })
            ->when(
                $locationIds !== [],
                fn ($q) => $q->whereIn('service_location_id', $locationIds),
                fn ($q) => $q->whereRaw('1 = 0')
            )
            ->orderByDesc('created_at');

        if ($transportType !== 'all' && $transportType !== '') {
            $query->where('transport_type', $transportType);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                    ->orWhereHas('userDetail', function ($user) use ($search) {
                        $user->where('name', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%");
                    });
            });
        }

        $paginator = $query->paginate($perPage);
        $acceptDuration = (int) (get_settings('trip_accept_reject_duration_for_driver') ?: 30);
        $tripDispatchType = (int) (get_settings('trip_dispatch_type') ?? 1);

        $data = collect($paginator->items())->map(function (RequestModel $ride) use ($acceptDuration, $tripDispatchType) {
            $activeCount = (int) ($ride->active_offer_count ?? 0);
            $status = 'searching';
            if ($activeCount > 0) {
                $status = 'offered';
            } elseif ((int) ($ride->total_meta_count ?? 0) === 0 && (int) ($ride->attempt_for_schedule ?? 0) === 0) {
                $status = 'no_meta_yet';
            } elseif ($activeCount === 0) {
                $status = 'waiting_next';
            }

            $assignMethod = (int) ($ride->assign_method ?? 0);

            return [
                'id' => $ride->id,
                'request_number' => $ride->request_number,
                'transport_type' => $ride->transport_type ?? 'taxi',
                'is_later' => (bool) $ride->is_later,
                'on_search' => (bool) $ride->on_search,
                'pick_address' => $ride->pick_address,
                'drop_address' => $ride->drop_address,
                'pick_lat' => $ride->pick_lat,
                'pick_lng' => $ride->pick_lng,
                'user_name' => optional($ride->userDetail)->name,
                'user_mobile' => optional($ride->userDetail)->mobile,
                'attempt_for_schedule' => (int) ($ride->attempt_for_schedule ?? 0),
                'assign_method' => $assignMethod,
                'assign_method_label' => $this->assignMethodLabel($assignMethod),
                'trip_dispatch_type' => $tripDispatchType,
                'trip_dispatch_type_label' => $this->tripDispatchTypeLabel($tripDispatchType),
                'active_offer_count' => $activeCount,
                'rejected_count' => (int) ($ride->rejected_count ?? 0),
                'created_at' => optional($ride->created_at)?->toIso8601String(),
                'created_at_display' => optional($ride->created_at)?->toDateTimeString(),
                'search_age_seconds' => $ride->created_at
                    ? Carbon::parse($ride->created_at)->diffInSeconds(now())
                    : 0,
                'status' => $status,
                'status_label' => match ($status) {
                    'offered' => 'Offered to '.$activeCount.' driver'.($activeCount === 1 ? '' : 's'),
                    'no_meta_yet' => 'No meta yet',
                    'waiting_next' => 'Waiting for next driver',
                    default => 'Searching',
                },
                'accept_duration' => $acceptDuration,
            ];
        })->values();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'server_time' => now()->toDateTimeString(),
        ];
    }

    /**
     * Full dispatch debug payload for one searching ride.
     */
    public function show(RequestModel $request)
    {
        $request->load([
            'userDetail',
            'requestPlace',
            'requestMeta.driver.user',
            'driverRejectedRequestDetail.drivers.user',
            'zoneType.vehicleType',
        ]);

        $acceptDuration = (int) (get_settings('trip_accept_reject_duration_for_driver') ?: 30);
        $serverTimeout = 60;
        $tripDispatchType = (int) (get_settings('trip_dispatch_type') ?? 1);
        $assignMethod = (int) ($request->assign_method ?? 0);

        $activeMetas = $request->requestMeta
            ->where('active', true)
            ->values()
            ->map(function (RequestMeta $meta) use ($acceptDuration, $serverTimeout) {
                $offeredAt = $meta->created_at ? Carbon::parse($meta->created_at) : null;
                $elapsed = $offeredAt ? $offeredAt->diffInSeconds(now()) : 0;
                $driver = $meta->driver;
                $metaAssign = (int) ($meta->assign_method ?? 1);

                return [
                    'meta_id' => $meta->id,
                    'driver_id' => $meta->driver_id,
                    'driver_name' => optional(optional($driver)->user)->name
                        ?? optional($driver)->name
                        ?? ('Driver #'.$meta->driver_id),
                    'driver_mobile' => optional(optional($driver)->user)->mobile,
                    'distance_to_pickup' => $meta->distance_to_pickup,
                    'assign_method' => $metaAssign,
                    'assign_method_label' => $this->metaAssignMethodLabel($metaAssign),
                    'active' => (bool) $meta->active,
                    'offered_at' => optional($offeredAt)?->toIso8601String(),
                    'offered_at_display' => optional($offeredAt)?->toDateTimeString(),
                    'elapsed_seconds' => $elapsed,
                    'accept_window_seconds' => $acceptDuration,
                    'server_timeout_seconds' => $serverTimeout,
                    'accept_remaining_seconds' => max(0, $acceptDuration - $elapsed),
                    'server_remaining_seconds' => max(0, $serverTimeout - $elapsed),
                    'state' => $elapsed >= $serverTimeout
                        ? 'likely_timed_out'
                        : ($elapsed >= $acceptDuration ? 'past_app_timer' : 'waiting'),
                    'state_label' => $elapsed >= $serverTimeout
                        ? 'Timed out'
                        : ($elapsed >= $acceptDuration ? 'Past app timer' : 'Waiting'),
                ];
            });

        $rejected = $request->driverRejectedRequestDetail->map(function (DriverRejectedRequest $row) {
            $driver = $row->drivers;

            return [
                'id' => $row->id,
                'driver_id' => $row->driver_id,
                'driver_name' => optional(optional($driver)->user)->name
                    ?? optional($driver)->name
                    ?? ('Driver #'.$row->driver_id),
                'reason' => $row->reason,
                'custom_reason' => $row->custom_reason,
                'is_after_accept' => (bool) $row->is_after_accept,
                'created_at' => optional($row->created_at)?->toDateTimeString(),
            ];
        })->values();

        $timeline = $this->buildTimeline($request, $activeMetas, $rejected);

        $firebaseMeta = null;
        try {
            $snapshot = $this->database->getReference('request-meta/'.$request->id)->getValue();
            $firebaseMeta = $snapshot;
        } catch (\Throwable $e) {
            Log::warning('Dispatch monitor firebase read failed', [
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'ride' => [
                'id' => $request->id,
                'request_number' => $request->request_number,
                'transport_type' => $request->transport_type ?? 'taxi',
                'is_later' => (bool) $request->is_later,
                'is_bid_ride' => (bool) $request->is_bid_ride,
                'on_search' => (bool) $request->on_search,
                'pick_address' => $request->pick_address,
                'drop_address' => $request->drop_address,
                'pick_lat' => $request->pick_lat,
                'pick_lng' => $request->pick_lng,
                'drop_lat' => $request->drop_lat,
                'drop_lng' => $request->drop_lng,
                'user_name' => optional($request->userDetail)->name,
                'user_mobile' => optional($request->userDetail)->mobile,
                'vehicle_type_name' => $request->vehicle_type_name,
                'attempt_for_schedule' => (int) ($request->attempt_for_schedule ?? 0),
                'assign_method' => $assignMethod,
                'assign_method_label' => $this->assignMethodLabel($assignMethod),
                'trip_dispatch_type' => $tripDispatchType,
                'trip_dispatch_type_label' => $this->tripDispatchTypeLabel($tripDispatchType),
                'created_at' => optional($request->created_at)?->toIso8601String(),
                'created_at_display' => optional($request->created_at)?->toDateTimeString(),
                'trip_start_time' => $request->trip_start_time,
                'search_age_seconds' => $request->created_at
                    ? Carbon::parse($request->created_at)->diffInSeconds(now())
                    : 0,
                'driver_search_radius' => get_settings('driver_search_radius'),
                'driver_id' => $request->driver_id,
                'is_cancelled' => (bool) $request->is_cancelled,
                'is_completed' => (bool) $request->is_completed,
            ],
            'still_searching' => ! $request->driver_id
                && ! $request->is_cancelled
                && ! $request->is_completed,
            'closed_reason' => $request->driver_id
                ? 'accepted'
                : ($request->is_cancelled
                    ? 'cancelled'
                    : ($request->is_completed ? 'completed' : null)),
            'active_offers' => $activeMetas,
            'rejected' => $rejected,
            'timeline' => $timeline,
            'firebase_meta' => $firebaseMeta,
            'driver_eligibility' => $this->buildDriverEligibility($request),
            'accept_duration' => $acceptDuration,
            'server_timeout_seconds' => $serverTimeout,
            'server_time' => now()->toIso8601String(),
            'server_time_display' => now()->toDateTimeString(),
            'links' => [
                'assign' => url('ongoing-rides/assign/'.$request->id),
                'view' => url('rides-request/view/'.$request->id),
                'cancel' => url('rides-request/cancel/'.$request->id),
            ],
        ]);
    }

    /**
     * Rules used by the admin UI to filter Firebase drivers to dispatch-eligible ones.
     */
    private function buildDriverEligibility(RequestModel $request): array
    {
        $radius = get_settings('driver_search_radius') ?: 30;

        $vehicleTypeId = optional(optional($request->zoneType)->vehicleType)->id
            ?? optional($request->zoneType)->type_id
            ?? null;

        // zoneType.type_id is what FetchDriversFromFirebaseHelpers uses.
        $dispatchVehicleTypeId = optional($request->zoneType)->type_id ?: $vehicleTypeId;

        $rejectedIds = $request->driverRejectedRequestDetail
            ->pluck('driver_id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        return [
            'vehicle_type_id' => $dispatchVehicleTypeId !== null ? (string) $dispatchVehicleTypeId : null,
            'vehicle_type_name' => $request->vehicle_type_name,
            'search_radius_km' => (float) $radius,
            'rejected_driver_ids' => $rejectedIds,
            'require_online' => true,
            'stale_after_minutes' => 7,
        ];
    }

    /**
     * Request.assign_method: booking assign mode (manual vs automatic).
     */
    private function assignMethodLabel(int $method): string
    {
        return $method === 1 ? 'Manual Assign' : 'Automatic Assign';
    }

    /**
     * Settings trip_dispatch_type: how drivers are offered the ride.
     * 1 => one-by-one, 0 => to all drivers.
     */
    private function tripDispatchTypeLabel(int $type): string
    {
        return $type === 0 ? 'To All Drivers' : 'One By One';
    }

    /**
     * RequestMeta.assign_method: 1 => one-by-one, 0/2 => to all.
     */
    private function metaAssignMethodLabel(int $method): string
    {
        return $method === 1 ? 'One By One' : 'To All Drivers';
    }

    /**
     * Force another driver search for a still-unassigned ride.
     */
    public function reSearch(RequestModel $request)
    {
        if ($request->is_cancelled || $request->is_completed || $request->driver_id) {
            return response()->json([
                'success' => false,
                'message' => 'Ride is no longer searching (already assigned, completed, or cancelled).',
            ], 422);
        }

        if ($request->is_bid_ride) {
            return response()->json([
                'success' => false,
                'message' => 'Bidding rides are not handled by this dispatcher search.',
            ], 422);
        }

        // Clear stale metas so fetchDriversFromFirebase can run (it early-exits if any meta exists).
        $hadMetas = $request->requestMeta()->exists();
        if ($hadMetas) {
            $request->requestMeta()->delete();
            try {
                $this->database->getReference('request-meta/'.$request->id)->remove();
            } catch (\Throwable $e) {
                Log::warning('Dispatch monitor clear firebase meta failed', [
                    'request_id' => $request->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $request->update(['on_search' => true]);

        $result = $this->fetchDriversFromFirebase($request->fresh(), $this->database);

        Log::channel('activity')->info('Dispatch monitor force re-search', [
            'request_id' => $request->id,
            'admin_id' => auth()->id(),
            'result' => $result,
            'cleared_metas' => $hadMetas,
        ]);

        return response()->json([
            'success' => true,
            'message' => $result === 'success'
                ? 'Drivers offered successfully.'
                : 'Re-search ran; no eligible drivers found right now.',
            'result' => $result,
        ]);
    }

    protected function buildTimeline(RequestModel $request, $activeMetas, $rejected): array
    {
        $events = [];

        $events[] = [
            'type' => 'created',
            'label' => 'Ride created',
            'at' => optional($request->created_at)?->toDateTimeString(),
            'tone' => 'grey',
        ];

        if ($request->on_search) {
            $events[] = [
                'type' => 'searching',
                'label' => 'Searching for drivers (on_search=1)',
                'at' => optional($request->updated_at)?->toDateTimeString(),
                'tone' => 'amber',
            ];
        }

        foreach ($request->requestMeta->sortBy('created_at') as $meta) {
            $driverName = optional(optional($meta->driver)->user)->name
                ?? optional($meta->driver)->name
                ?? ('Driver #'.$meta->driver_id);
            $events[] = [
                'type' => $meta->active ? 'offered' : 'offer_cleared',
                'label' => ($meta->active ? 'Offered to ' : 'Offer cleared for ').$driverName,
                'at' => optional($meta->created_at)?->toDateTimeString(),
                'driver_id' => $meta->driver_id,
                'tone' => $meta->active ? 'green' : 'grey',
            ];
        }

        foreach ($rejected as $row) {
            $events[] = [
                'type' => 'rejected',
                'label' => 'Rejected by '.$row['driver_name'],
                'at' => $row['created_at'],
                'driver_id' => $row['driver_id'],
                'tone' => 'red',
            ];
        }

        usort($events, function ($a, $b) {
            return strcmp($a['at'] ?? '', $b['at'] ?? '');
        });

        return $events;
    }
}

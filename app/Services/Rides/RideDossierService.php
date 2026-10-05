<?php

namespace App\Services\Rides;

use App\Models\Admin\Driver;
use App\Models\Payment\DriverWalletHistory;
use App\Models\Payment\PaymentRequest;
use App\Models\Payment\RewardHistory;
use App\Models\Payment\UserWalletHistory;
use App\Models\Request\Request as RequestModel;
use App\Models\Request\RequestEvent;
use App\Models\Request\RequestPricingAudit;
use App\Models\Request\DriverRejectedRequest; // same namespace as Request model relation
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class RideDossierService
{
    public function build(RequestModel $request): array
    {
        $request->loadMissing([
            'userDetail.userWallet',
            'userDetail.rewardPoint',
            'driverDetail.user',
            'driverDetail.driverWallet',
            'driverDetail.driverDocument',
            'requestPlace',
            'requestBill',
            'requestEtaDetail',
            'requestStops',
            'requestProofs',
            'requestRating',
            'requestMeta.driver.user',
            'driverRejectedRequestDetail.drivers.user',
            'requestCancellationFee',
            'preferenceDetail',
            'zoneType.zone',
            'zoneType.vehicleType',
            'zoneType.zoneTypePrice',
            'serviceLocationDetail',
            'promo',
            'cancelReason',
            'rentalPackage',
            'fleetDetail',
        ]);

        $timezone = auth()->user()->timezone ?? $request->timezone ?? config('app.timezone');

        $overview = $this->overview($request, $timezone);
        $estimated = $this->fareFromEta($request);
        $final = $this->fareFromBill($request);
        $pricingAudits = $this->pricingAudits($request);
        $reconstructed = null;
        if (empty($pricingAudits['eta']) && empty($pricingAudits['final'])) {
            $reconstructed = $this->reconstructPricing($request, $estimated, $final);
        }

        return [
            'overview' => $overview,
            'customer' => $this->customer($request),
            'driver' => $this->driver($request),
            'locations' => $this->locations($request),
            'estimated_fare' => $estimated,
            'final_fare' => $final,
            'fare_diff' => $this->fareDiff($estimated, $final),
            'pricing_audit' => $pricingAudits,
            'pricing_reconstructed' => $reconstructed,
            'timeline' => $this->timeline($request, $timezone),
            'payment' => $this->payment($request, $timezone),
            'logs' => $this->logs($request, $timezone),
            'applied_settings' => $this->appliedSettings($request, $pricingAudits, $reconstructed),
            'ratings' => $this->ratings($request),
            'proofs' => $request->requestProofs->map(fn ($p) => [
                'id' => $p->id,
                'proof_image' => $p->proof_image ?? $p->image ?? null,
            ])->values(),
            'preferences' => $request->preferenceDetail->map(fn ($p) => [
                'id' => $p->id,
                'price' => $p->price,
                'preference_price_id' => $p->preference_price_id,
            ])->values(),
            'rejected_drivers' => $this->rejectedDrivers($request, $timezone),
            'actions' => $this->actions($request),
            'raw' => [
                'request_id' => $request->id,
                'request_number' => $request->request_number,
                'flags' => [
                    'is_later' => (bool) $request->is_later,
                    'on_search' => (bool) $request->on_search,
                    'is_driver_started' => (bool) $request->is_driver_started,
                    'is_driver_arrived' => (bool) $request->is_driver_arrived,
                    'is_trip_start' => (bool) $request->is_trip_start,
                    'is_completed' => (bool) $request->is_completed,
                    'is_cancelled' => (bool) $request->is_cancelled,
                    'is_paid' => (bool) $request->is_paid,
                    'is_surge_applied' => (bool) $request->is_surge_applied,
                    'is_bid_ride' => (bool) $request->is_bid_ride,
                    'is_rental' => (bool) $request->is_rental,
                    'is_out_station' => (bool) $request->is_out_station,
                    'shared_ride' => (bool) $request->shared_ride,
                    'if_dispatch' => (bool) $request->if_dispatch,
                    'is_airport' => (bool) $request->is_airport,
                ],
                'eta' => $request->requestEtaDetail,
                'bill' => $request->requestBill,
            ],
        ];
    }

    protected function fmt($value, string $timezone): ?string
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        // Delivery rides store paid_at as "Sender" / "Receiver" (who pays), not a timestamp.
        if (is_string($value) && in_array(ucfirst(strtolower($value)), ['Sender', 'Receiver'], true)) {
            return null;
        }

        try {
            return Carbon::parse($value)->setTimezone($timezone)->format('jS M Y, h:i:s A');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * For delivery, paid_at may mean payment party (Sender/Receiver). Otherwise a paid timestamp.
     */
    protected function paidAtPayload($request, string $timezone): array
    {
        $raw = $request->paid_at;
        $party = null;
        if (is_string($raw) && in_array(ucfirst(strtolower($raw)), ['Sender', 'Receiver'], true)) {
            $party = ucfirst(strtolower($raw));
        }

        return [
            'paid_at' => $party ? null : $this->fmt($raw, $timezone),
            'payment_party' => $party,
            'paid_at_raw' => $raw,
        ];
    }

    /**
     * Round numeric values for API/UI so IEEE-754 artifacts like 1.6800000000000002 are not shown.
     */
    protected function num($value, int $precision = 2): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, $precision);
    }

    /**
     * Duration/waiting in minutes — always whole minutes (no decimals).
     */
    protected function mins($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) round((float) $value);
    }

    protected function overview(RequestModel $request, string $timezone): array
    {
        $status = 'searching';
        if ($request->is_cancelled) {
            $status = 'cancelled';
        } elseif ($request->is_completed) {
            $status = 'completed';
        } elseif ($request->is_trip_start) {
            $status = 'started';
        } elseif ($request->is_driver_arrived) {
            $status = 'arrived';
        } elseif ($request->accepted_at || $request->driver_id) {
            $status = 'accepted';
        } elseif ($request->on_search) {
            $status = 'searching';
        } elseif ($request->is_later) {
            $status = 'scheduled';
        }

        $durationMins = $this->mins($request->total_time ?: (optional($request->requestBill)->total_time ?? 0));
        $waitingMins = $this->mins(optional($request->requestBill)->calculated_waiting_time ?? 0);

        $rideType = 'normal';
        if ($request->is_bid_ride) {
            $rideType = 'bidding';
        } elseif ($request->is_rental) {
            $rideType = 'rental';
        } elseif ($request->is_out_station) {
            $rideType = 'outstation';
        } elseif ($request->shared_ride) {
            $rideType = 'shared';
        } elseif ($request->is_later) {
            $rideType = 'scheduled';
        }

        $paidPayload = $this->paidAtPayload($request, $timezone);

        return [
            'id' => $request->id,
            'request_number' => $request->request_number,
            'booking_type' => $request->is_later ? 'scheduled' : 'now',
            'status' => $status,
            'ride_type' => $rideType,
            'service_type' => $request->transport_type,
            'zone' => optional(optional($request->zoneType)->zone)->name,
            'vehicle_type' => $request->vehicle_type_name,
            'ride_category' => $request->rentalPackage->name ?? ($request->is_parcel ? 'parcel' : 'taxi'),
            'created_at' => $this->fmt($request->created_at, $timezone),
            'accepted_at' => $this->fmt($request->accepted_at, $timezone),
            'arrived_at' => $this->fmt($request->arrived_at, $timezone),
            'started_at' => $request->is_trip_start ? $this->fmt($request->trip_start_time, $timezone) : null,
            'completed_at' => $this->fmt($request->completed_at, $timezone),
            'cancelled_at' => $this->fmt($request->cancelled_at, $timezone),
            'paid_at' => $paidPayload['paid_at'],
            'payment_party' => $paidPayload['payment_party'],
            'total_duration_mins' => $durationMins,
            'waiting_time_mins' => $waitingMins,
            // Hours may keep decimals; minutes above are whole numbers.
            'total_duration_hours' => $durationMins !== null ? $this->num($durationMins / 60, 2) : null,
            'total_distance' => $this->num($request->total_distance ?: (optional($request->requestBill)->total_distance ?? 0)),
            'unit' => ((int) $request->unit === 2) ? 'MILES' : 'KM',
            'driver_rating' => $request->driver_rating,
            'customer_rating' => $request->user_rating,
            'ride_otp' => $request->ride_otp,
            'cancel_method' => $request->cancel_method,
            'cancel_reason' => $request->custom_reason
                ?: (optional($request->cancelReason)->reason ?? $request->reason),
            'currency_symbol' => $request->requested_currency_symbol,
            'currency_code' => $request->requested_currency_code,
            'request_eta_amount' => $request->request_eta_amount,
            'offerred_ride_fare' => $request->offerred_ride_fare,
            'accepted_ride_fare' => $request->accepted_ride_fare,
            'is_surge_applied' => (bool) $request->is_surge_applied,
            'timezone' => $timezone,
            'book_for_other' => (bool) $request->book_for_other,
            'book_for_other_contact' => $request->book_for_other_contact,
            'book_for_other_contact_name' => $request->book_for_other_contact_name,
            'goods_type_quantity' => $request->goods_type_quantity,
            'parcel_type' => $request->parcel_type,
            'seats_taken' => $request->seats_taken,
            'rental_package_name' => optional($request->rentalPackage)->name,
        ];
    }

    protected function customer(RequestModel $request): ?array
    {
        $user = $request->userDetail;
        if (!$user) {
            return null;
        }

        $completed = $user->requestDetail()->where('is_completed', true)->count();
        $cancelled = $user->requestDetail()->where('is_cancelled', true)->count();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'profile_picture' => $user->profile_picture ?? null,
            'wallet_balance' => optional($user->userWallet)->amount_balance
                ?? optional($user->userWallet)->amount_added
                ?? null,
            'reward_points' => optional($user->rewardPoint)->balance_reward_points ?? 0,
            'previous_ride_count' => $completed,
            'cancellation_count' => $cancelled,
            'loyalty_status' => (optional($user->rewardPoint)->balance_reward_points ?? 0) > 0 ? 'member' : 'none',
            'customer_type' => $user->user_type ?? ($request->if_dispatch ? 'dispatch' : 'app'),
        ];
    }

    protected function driver(RequestModel $request): ?array
    {
        /** @var Driver|null $driver */
        $driver = $request->driverDetail;
        if (!$driver) {
            return null;
        }

        $accepted = $driver->requestDetail()->count();
        $rejected = DriverRejectedRequest::where('driver_id', $driver->id)->count();
        $offers = max(1, $accepted + $rejected);
        $acceptanceRate = round(($accepted / $offers) * 100, 1);
        $rejectRate = round(($rejected / $offers) * 100, 1);

        $docs = $driver->driverDocument ?? collect();
        $docStatus = [
            'total' => $docs->count(),
            'approved' => $docs->where('document_status', 1)->count()
                + $docs->where('is_approved', 1)->count(),
            'items' => $docs->take(10)->map(fn ($d) => [
                'name' => $d->document_name ?? $d->name ?? 'Document',
                'status' => $d->document_status ?? $d->is_approved ?? null,
            ])->values(),
        ];

        $bill = $request->requestBill;

        return [
            'id' => $driver->id,
            'name' => optional($driver->user)->name ?? $driver->name,
            'email' => optional($driver->user)->email,
            'mobile' => optional($driver->user)->mobile ?? $driver->mobile,
            'profile_picture' => optional($driver->user)->profile_picture,
            'status' => [
                'active' => (bool) $driver->active,
                'approve' => (bool) $driver->approve,
                'available' => (bool) $driver->available,
                'online' => (bool) ($driver->is_active ?? $driver->active),
            ],
            'vehicle' => [
                'car_number' => $driver->car_number ?? $request->car_number ?? null,
                'car_make' => $driver->car_make_name ?? null,
                'car_model' => $driver->car_model_name ?? null,
                'car_color' => $driver->car_color ?? null,
            ],
            'wallet_balance' => optional($driver->driverWallet)->amount_balance ?? null,
            'earnings_this_ride' => $bill->driver_commision ?? null,
            'admin_commission_this_ride' => $bill->admin_commision ?? null,
            'commission_percentage' => optional($request->zoneType)->admin_commision,
            'commission_type' => optional($request->zoneType)->admin_commision_type,
            'documents' => $docStatus,
            'acceptance_rate' => $acceptanceRate,
            'cancellation_rate' => $rejectRate,
            'rating' => $driver->rating ?? null,
        ];
    }

    protected function locations(RequestModel $request): array
    {
        $place = $request->requestPlace;
        $eta = $request->requestEtaDetail;
        $bill = $request->requestBill;

        return [
            'pickup' => [
                'address' => $request->pick_address,
                'lat' => $request->pick_lat,
                'lng' => $request->pick_lng,
                'poc_name' => optional($place)->pickup_poc_name,
                'poc_mobile' => optional($place)->pickup_poc_mobile,
            ],
            'drop' => [
                'address' => $request->drop_address,
                'lat' => $request->drop_lat,
                'lng' => $request->drop_lng,
                'poc_name' => optional($place)->drop_poc_name,
                'poc_mobile' => optional($place)->drop_poc_mobile,
            ],
            'stops' => $request->requestStops->sortBy('order')->values()->map(fn ($s) => [
                'address' => $s->address,
                'lat' => $s->latitude ?? $s->lat,
                'lng' => $s->longitude ?? $s->lng,
                'order' => $s->order,
                'completed_at' => optional($s->completed_at)?->toDateTimeString(),
            ]),
            'poly_line' => $request->poly_line,
            'request_path' => optional($place)->request_path,
            'estimated_distance' => optional($eta)->total_distance,
            'actual_distance' => optional($bill)->total_distance ?? $request->total_distance,
            'route_distance' => $request->total_distance,
            'instructions' => optional($place)->instructions,
        ];
    }

    protected function mapFareRow($row): ?array
    {
        if (!$row) {
            return null;
        }

        $baseDistance = $this->num($row->base_distance);
        $totalDistance = $this->num($row->total_distance);
        $billable = $this->num(max(0, ($totalDistance ?? 0) - ($baseDistance ?? 0)));

        return [
            'base_price' => $this->num($row->base_price),
            'base_distance' => $baseDistance,
            'price_per_distance' => $this->num($row->price_per_distance),
            'distance_price' => $this->num($row->distance_price),
            'price_per_time' => $this->num($row->price_per_time),
            'time_price' => $this->num($row->time_price),
            'waiting_charge' => $this->num($row->waiting_charge ?? 0),
            'calculated_waiting_time' => $this->mins($row->calculated_waiting_time ?? 0),
            'waiting_charge_per_min' => $this->num($row->waiting_charge_per_min ?? 0),
            'before_trip_start_waiting_time' => $this->mins($row->before_trip_start_waiting_time ?? 0),
            'after_trip_start_waiting_time' => $this->mins($row->after_trip_start_waiting_time ?? 0),
            'cancellation_fee' => $this->num($row->cancellation_fee ?? 0),
            'airport_surge_fee' => $this->num($row->airport_surge_fee ?? 0),
            'service_tax' => $this->num($row->service_tax ?? 0),
            'service_tax_percentage' => $this->num($row->service_tax_percentage ?? 0),
            'promo_discount' => $this->num($row->promo_discount ?? 0),
            'admin_commision' => $this->num($row->admin_commision ?? $row->admin_commission ?? 0),
            'admin_commision_with_tax' => $this->num($row->admin_commision_with_tax ?? $row->admin_commission_with_tax ?? 0),
            'admin_commission_from_driver' => $this->num($row->admin_commission_from_driver ?? 0),
            'driver_commision' => $this->num($row->driver_commision ?? $row->driver_commission ?? 0),
            'driver_tips' => $this->num($row->tips ?? 0),
            'preference_price_total' => $this->num($row->preference_price_total ?? 0),
            'additional_charges_amount' => $this->num($row->additional_charges_amount ?? 0),
            'additional_charges_reason' => $row->additional_charges_reason ?? null,
            'agent_commision' => $this->num($row->agent_commision ?? 0),
            'franchise_owner_commision' => $this->num($row->franchise_owner_commision ?? 0),
            'total_amount' => $this->num($row->total_amount),
            'total_distance' => $totalDistance,
            'total_time' => $this->mins($row->total_time),
            'billable_distance' => $billable,
            'currency_code' => $row->requested_currency_code ?? null,
            'currency_symbol' => $row->requested_currency_symbol ?? null,
        ];
    }

    protected function fareFromEta(RequestModel $request): ?array
    {
        return $this->mapFareRow($request->requestEtaDetail);
    }

    protected function fareFromBill(RequestModel $request): ?array
    {
        return $this->mapFareRow($request->requestBill);
    }

    protected function fareDiff(?array $estimated, ?array $final): array
    {
        if (!$estimated || !$final) {
            return [
                'has_both' => false,
                'total_delta' => null,
                'reasons' => [],
                'lines' => [],
            ];
        }

        $keys = [
            'total_distance' => 'Distance',
            'total_time' => 'Duration',
            'waiting_charge' => 'Waiting charge',
            'distance_price' => 'Distance charge',
            'time_price' => 'Time charge',
            'airport_surge_fee' => 'Airport fee',
            'promo_discount' => 'Promo discount',
            'service_tax' => 'Tax',
            'total_amount' => 'Total',
            'price_per_distance' => 'Distance rate',
            'price_per_time' => 'Time rate',
        ];

        $lines = [];
        $reasons = [];
        foreach ($keys as $key => $label) {
            $e = $this->num($estimated[$key] ?? 0);
            $f = $this->num($final[$key] ?? 0);
            $delta = $this->num(($f ?? 0) - ($e ?? 0));
            if (abs($delta ?? 0) < 0.0001) {
                continue;
            }
            $lines[] = [
                'key' => $key,
                'label' => $label,
                'estimated' => $e,
                'final' => $f,
                'delta' => $delta,
            ];
            $reasons[] = "{$label} changed from {$e} to {$f} (Δ {$delta})";
        }

        return [
            'has_both' => true,
            'total_delta' => $this->num(($final['total_amount'] ?? 0) - ($estimated['total_amount'] ?? 0)),
            'reasons' => $reasons,
            'lines' => $lines,
        ];
    }

    protected function pricingAudits(RequestModel $request): array
    {
        if (!Schema::hasTable('request_pricing_audits')) {
            return ['eta' => null, 'final' => null];
        }

        $rows = RequestPricingAudit::where('request_id', $request->id)->get()->keyBy('phase');

        return [
            'eta' => $rows->get('eta')?->audit,
            'final' => $rows->get('final')?->audit,
        ];
    }

    protected function reconstructPricing(RequestModel $request, ?array $estimated, ?array $final): array
    {
        $source = $final ?: $estimated;
        if (!$source) {
            return [
                'available' => false,
                'message' => 'No ETA or bill stored for this ride.',
            ];
        }

        $phase = $final ? 'final' : 'eta';
        $audit = RidePricingAuditWriter::buildAudit($request, $phase, $source, [
            'surge_skip_reason' => 'Historical ride — surge rule details were not persisted',
            'peak_skip_reason' => 'Historical ride — peak zone match was not persisted',
            'eta_rates_locked' => (bool) $request->requestEtaDetail,
        ]);

        return [
            'available' => true,
            'historical' => true,
            'message' => 'Pricing audit was not captured for this ride. Values reconstructed from stored amounts and current zone configuration may differ from ride-time settings.',
            'audit' => $audit,
        ];
    }

    protected function timeline(RequestModel $request, string $timezone): array
    {
        $persisted = [];
        if (Schema::hasTable('request_events')) {
            $persisted = RequestEvent::where('request_id', $request->id)
                ->orderBy('occurred_at')
                ->get()
                ->map(fn ($e) => [
                    'event' => $e->event,
                    'actor_type' => $e->actor_type,
                    'actor_id' => $e->actor_id,
                    'payload' => $e->payload,
                    'occurred_at' => $this->fmt($e->occurred_at, $timezone),
                    'occurred_at_raw' => optional($e->occurred_at)->toIso8601String(),
                    'source' => 'persisted',
                ])
                ->all();
        }

        if (count($persisted)) {
            return $persisted;
        }

        return collect(RideEventLogger::syntheticTimeline($request))->map(function ($e) use ($timezone) {
            $e['occurred_at_raw'] = $e['occurred_at'];
            $e['occurred_at'] = $this->fmt($e['occurred_at'], $timezone);

            return $e;
        })->all();
    }

    protected function payment(RequestModel $request, string $timezone): array
    {
        $payments = PaymentRequest::where('request_id', $request->id)->get()->map(fn ($p) => [
            'id' => $p->id,
            'amount' => $p->amount,
            'status' => $p->status,
            'is_paid' => (bool) $p->is_paid,
            'currency' => $p->currency,
            'payment_for' => $p->payment_for,
            'created_at' => $this->fmt($p->created_at, $timezone),
        ]);

        $userWallet = UserWalletHistory::where('request_id', $request->id)->get()->map(fn ($w) => [
            'amount' => $w->amount,
            'is_credit' => (bool) $w->is_credit,
            'remarks' => $w->remarks,
            'created_at' => $this->fmt($w->created_at, $timezone),
        ]);

        $driverWallet = DriverWalletHistory::where('request_id', $request->id)->get()->map(fn ($w) => [
            'amount' => $w->amount,
            'is_credit' => (bool) $w->is_credit,
            'remarks' => $w->remarks,
            'created_at' => $this->fmt($w->created_at, $timezone),
        ]);

        $rewards = RewardHistory::where('request_id', $request->id)->get()->map(fn ($r) => [
            'reward_points' => $r->reward_points,
            'amount' => $r->amount,
            'is_credit' => (bool) $r->is_credit,
            'remarks' => $r->remarks,
            'created_at' => $this->fmt($r->created_at, $timezone),
        ]);

        $bill = $request->requestBill;
        $paid = $this->paidAtPayload($request, $timezone);

        return [
            'payment_method' => $request->payment_option,
            'payment_opt' => $request->payment_opt,
            'payment_status' => $request->is_paid ? 'paid' : 'unpaid',
            'is_paid' => (bool) $request->is_paid,
            'paid_at' => $paid['paid_at'],
            'payment_party' => $paid['payment_party'],
            'payment_intent_id' => $request->payment_intent_id,
            'customer_paid' => $bill->total_amount ?? $request->request_eta_amount,
            'driver_earnings' => $bill->driver_commision ?? null,
            'admin_commission' => $bill->admin_commision ?? null,
            'driver_tips' => $bill->tips ?? null,
            'transactions' => $payments,
            'user_wallet_history' => $userWallet,
            'driver_wallet_history' => $driverWallet,
            'reward_history' => $rewards,
            'cancellation_fee' => optional($request->requestCancellationFee)->cancellation_fee,
        ];
    }

    protected function logs(RequestModel $request, string $timezone): array
    {
        $timeline = $this->timeline($request, $timezone);

        $dispatch = $request->requestMeta->map(fn ($m) => [
            'type' => 'dispatch',
            'label' => ($m->active ? 'Offered to driver #' : 'Offer cleared for driver #') . $m->driver_id,
            'at' => $this->fmt($m->created_at, $timezone),
            'actor_type' => 'system',
            'actor_id' => $m->driver_id,
        ]);

        return [
            'events' => $timeline,
            'dispatch' => $dispatch,
        ];
    }

    protected function appliedSettings(RequestModel $request, array $audits, ?array $reconstructed): array
    {
        $audit = $audits['final'] ?? $audits['eta'] ?? ($reconstructed['audit'] ?? null);
        $historical = empty($audits['final']) && empty($audits['eta']);

        $zoneType = $request->zoneType;
        $price = $zoneType?->zoneTypePrice?->first();

        $items = [];
        $add = function ($name, $value, $module, $applied, $why) use (&$items, $historical) {
            $items[] = [
                'name' => $name,
                'value' => $value,
                'source_module' => $module,
                'applied' => $applied,
                'why' => $why,
                'is_current_config' => $historical,
            ];
        };

        if ($audit && !empty($audit['settings_snapshot']['zone_type'])) {
            $zt = $audit['settings_snapshot']['zone_type'];
            $add('Service Tax %', $zt['service_tax'] ?? null, 'Zone Type', true, 'Applied from ride audit snapshot');
            $add('Admin Commission', $zt['admin_commision'] ?? null, 'Zone Type', true, 'Type: ' . ($zt['admin_commision_type'] ?? '?'));
            $add('Airport Surge Fee Config', $zt['airport_surge'] ?? null, 'Zone Type', ((float) (optional($request->requestBill)->airport_surge_fee ?? 0)) > 0, 'Applied when pick/drop in airport');
        } elseif ($zoneType) {
            $add('Service Tax %', $zoneType->service_tax, 'Zone Type (current)', true, 'Current config — may differ from ride-time');
            $add('Admin Commission', $zoneType->admin_commision, 'Zone Type (current)', true, 'Current config — may differ from ride-time');
            $add('Airport Surge Fee Config', $zoneType->airport_surge, 'Zone Type (current)', ((float) (optional($request->requestBill)->airport_surge_fee ?? 0)) > 0, 'Current config');
        }

        if ($price) {
            $add('Base Price', $price->base_price, 'Vehicle Pricing (current)', true, $historical ? 'Current config — may differ from ride-time' : 'Zone type price');
            $add('Base Distance', $price->base_distance, 'Vehicle Pricing (current)', true, $historical ? 'Current config — may differ from ride-time' : 'Zone type price');
            $add('Price / Distance', $price->price_per_distance, 'Vehicle Pricing (current)', true, $historical ? 'Current config — may differ from ride-time' : 'Zone type price');
            $add('Price / Time', $price->price_per_time, 'Vehicle Pricing (current)', true, $historical ? 'Current config — may differ from ride-time' : 'Zone type price');
            $add('Waiting Charge / Min', $price->waiting_charge, 'Waiting Charge Settings (current)', ((float) (optional($request->requestBill)->waiting_charge ?? 0)) > 0, 'Applied when waiting exceeds free minutes');
        }

        if ($audit && !empty($audit['rates'])) {
            foreach ($audit['rates'] as $k => $v) {
                $add('Applied rate: ' . $k, $v, 'Pricing Audit', true, 'Captured at ' . ($audit['phase'] ?? 'ride'));
            }
        }

        $add('Round Bill Values', get_settings('can_round_the_bill_values'), 'Global Settings', (int) get_settings('can_round_the_bill_values') === 1, 'Global setting');
        $add('ETA Total Lock', get_settings('enable_eta_total_update'), 'Global Settings', (bool) $request->requestEtaDetail, 'When enabled, final may reuse ETA distance/time');
        $add('Peak Zone Feature', get_settings('enable_peak_zone_feature'), 'Peak Hour Rules', (bool) get_settings('enable_peak_zone_feature'), 'Feature flag');
        $add('Surge Applied Flag', $request->is_surge_applied ? 'yes' : 'no', 'Surge Settings', (bool) $request->is_surge_applied, 'Stored on request');
        $add('Promo', $request->promo_id ? ('#' . $request->promo_id) : 'none', 'Coupon Rules', (bool) $request->promo_id, $request->promo_id ? 'Promo linked to request' : 'Skipped — no promo');

        return [
            'historical' => $historical,
            'notice' => $historical
                ? 'Some values reflect current admin configuration and may differ from what applied at ride time.'
                : 'Values taken from the pricing audit captured during the ride.',
            'items' => $items,
            'rules' => $audit['rules'] ?? [],
        ];
    }

    protected function ratings(RequestModel $request): array
    {
        return $request->requestRating->map(fn ($r) => [
            'rating' => $r->rating,
            'comment' => $r->comment ?? $r->feedback,
            'user_rating' => (bool) $r->user_rating,
            'driver_rating' => (bool) $r->driver_rating,
        ])->values()->all();
    }

    protected function rejectedDrivers(RequestModel $request, string $timezone): array
    {
        return $request->driverRejectedRequestDetail->map(function ($r) use ($timezone) {
            return [
                'driver_id' => $r->driver_id,
                'driver_name' => optional(optional($r->drivers)->user)->name ?? optional($r->drivers)->name,
                'is_after_accept' => (bool) $r->is_after_accept,
                'reason' => $r->custom_reason ?: $r->reason,
                'created_at' => $this->fmt($r->created_at, $timezone),
            ];
        })->values()->all();
    }

    protected function actions(RequestModel $request): array
    {
        return [
            'can_cancel' => !$request->is_completed && !$request->is_cancelled,
            'can_download_invoice' => (bool) $request->is_completed,
            'can_assign' => !$request->is_completed && !$request->is_cancelled && !$request->driver_id,
            'can_track' => !$request->is_completed && !$request->is_cancelled,
            'invoice_url' => $request->is_completed ? url('/rides-request/download-invoice/' . $request->id) : null,
            'user_invoice_url' => $request->is_completed ? url('/download-user-invoice/' . $request->id) : null,
            'driver_invoice_url' => $request->is_completed ? url('/download-driver-invoice/' . $request->id) : null,
            'track_url' => url('/track/request/' . $request->id),
            'assign_url' => url('/ongoing-rides/assign/' . $request->id),
            'cancel_url' => url('/rides-request/cancel/' . $request->id),
            'report_url' => url('/rides-request/report/' . $request->id),
            'export_pricing_url' => url('/rides-request/export-pricing/' . $request->id),
            'export_logs_url' => url('/rides-request/export-logs/' . $request->id),
        ];
    }
}

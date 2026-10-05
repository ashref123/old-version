<?php

namespace App\Services\Rides;

use App\Models\Request\Request as RequestModel;
use App\Models\Request\RequestPricingAudit;
use Illuminate\Support\Facades\Log;
use Throwable;

class RidePricingAuditWriter
{
    /**
     * Persist a pricing audit snapshot. Never throws to callers.
     *
     * @param  array|object  $billResult
     */
    public static function write(RequestModel $request, string $phase, $billResult, array $context = []): void
    {
        try {
            $result = is_object($billResult) ? (array) $billResult : (array) $billResult;
            $audit = self::buildAudit($request, $phase, $result, $context);

            RequestPricingAudit::updateOrCreate(
                [
                    'request_id' => $request->id,
                    'phase' => $phase,
                ],
                [
                    'audit' => $audit,
                ]
            );
        } catch (Throwable $e) {
            Log::warning('RidePricingAuditWriter failed', [
                'request_id' => $request->id,
                'phase' => $phase,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function buildAudit(RequestModel $request, string $phase, array $result, array $context = []): array
    {
        $zoneType = $request->zoneType;
        $zone = $zoneType?->zone ?? null;
        $vehicleType = $zoneType?->vehicleType ?? $zoneType?->vehicle_type_name ?? null;
        $symbol = $request->requested_currency_symbol ?: ($result['requested_currency_symbol'] ?? '');
        $unit = ((int) $request->unit === 2) ? 'MILES' : 'KM';

        $basePrice = (float) ($result['base_price'] ?? 0);
        $baseDistance = (float) ($result['base_distance'] ?? 0);
        $totalDistance = (float) ($result['total_distance'] ?? $result['distance'] ?? 0);
        $pricePerDistance = (float) ($result['price_per_distance'] ?? 0);
        $distancePrice = (float) ($result['distance_price'] ?? 0);
        $pricePerTime = (float) ($result['price_per_time'] ?? 0);
        $timePrice = (float) ($result['time_price'] ?? 0);
        $totalTime = (float) ($result['total_time'] ?? $result['duration'] ?? 0);
        $waitingCharge = (float) ($result['waiting_charge'] ?? 0);
        $waitingTime = (float) ($result['calculated_waiting_time'] ?? $context['waiting_time'] ?? 0);
        $waitingPerMin = (float) ($result['waiting_charge_per_min'] ?? $context['waiting_charge_per_min'] ?? 0);
        $airportFee = (float) ($result['airport_surge_fee'] ?? 0);
        $taxAmount = (float) ($result['service_tax'] ?? $result['tax_amount'] ?? 0);
        $taxPercent = (float) ($result['service_tax_percentage'] ?? $result['tax_percent'] ?? $result['tax'] ?? 0);
        $promo = (float) ($result['promo_discount'] ?? $result['discount_amount'] ?? 0);
        $adminComm = (float) ($result['admin_commision'] ?? $result['without_discount_admin_commision'] ?? 0);
        $driverComm = (float) ($result['driver_commision'] ?? 0);
        $total = (float) ($result['total_amount'] ?? $result['total_price'] ?? $result['discounted_total_price'] ?? 0);
        $cancellation = (float) ($result['cancellation_fee'] ?? 0);
        $preference = (float) ($result['preference_price_total'] ?? 0);
        $calculatableDistance = max(0, $totalDistance - $baseDistance);

        $surgePercent = (float) ($context['surge_percent'] ?? 0);
        $surgeMatched = $context['surge'] ?? null;
        $peakMatched = $context['peak'] ?? null;
        $etaLocked = (bool) ($context['eta_rates_locked'] ?? false);

        $lines = [];

        $lines[] = self::line(
            'base_fare',
            'Base Fare',
            true,
            ['base_price' => $basePrice, 'base_distance' => $baseDistance, 'unit' => $unit],
            "Configured base fare for first {$baseDistance} {$unit}",
            "{$symbol}{$basePrice}",
            $basePrice,
            $etaLocked ? 'Rates locked from request_eta' : 'From zone_type_price'
        );

        $lines[] = self::line(
            'distance_charge',
            'Distance Charge',
            $distancePrice > 0 || $calculatableDistance > 0,
            [
                'price_per_distance' => $pricePerDistance,
                'total_distance' => $totalDistance,
                'base_distance' => $baseDistance,
                'billable_distance' => $calculatableDistance,
                'unit' => $unit,
            ],
            "max({$totalDistance} - {$baseDistance}, 0) × {$symbol}{$pricePerDistance}",
            "{$calculatableDistance} × {$symbol}{$pricePerDistance} = {$symbol}{$distancePrice}",
            $distancePrice,
            $distancePrice > 0 ? null : 'No billable distance beyond base'
        );

        $lines[] = self::line(
            'time_charge',
            'Time Charge',
            $timePrice != 0,
            [
                'price_per_time' => $pricePerTime,
                'total_time' => $totalTime,
            ],
            "{$totalTime} min × {$symbol}{$pricePerTime}",
            "{$symbol}{$timePrice}",
            $timePrice,
            $timePrice == 0 ? 'Zero duration or zero rate' : null
        );

        $lines[] = self::line(
            'waiting_charge',
            'Waiting Charge',
            $waitingCharge > 0,
            [
                'waiting_time' => $waitingTime,
                'waiting_charge_per_min' => $waitingPerMin,
            ],
            "{$waitingTime} min × {$symbol}{$waitingPerMin}",
            "{$symbol}{$waitingCharge}",
            $waitingCharge,
            $waitingCharge <= 0 ? 'No waiting charge applied' : null
        );

        $lines[] = self::line(
            'surge_pricing',
            'Surge Pricing',
            $surgePercent > 0 || !empty($result['is_surge_applied']) || (bool) $request->is_surge_applied,
            [
                'surge_percent' => $surgePercent,
                'matched_rule' => $surgeMatched,
                'folded_into' => 'price_per_distance',
            ],
            $surgePercent > 0
                ? "price_per_distance × (1 + {$surgePercent}/100)"
                : 'Surge window match on zone_surge_prices',
            $surgePercent > 0 ? "+{$surgePercent}% on distance rate" : (($request->is_surge_applied || !empty($result['is_surge_applied'])) ? 'Applied (amount folded into rate)' : 'Not applied'),
            null,
            ($surgePercent <= 0 && !$request->is_surge_applied && empty($result['is_surge_applied']))
                ? ($context['surge_skip_reason'] ?? 'No matching surge window')
                : null
        );

        $lines[] = self::line(
            'peak_zone',
            'Peak Zone Multiplier',
            !empty($peakMatched),
            ['matched_rule' => $peakMatched],
            'Added to surge % on distance rate',
            $peakMatched
                ? ('+' . ($peakMatched['distance_price_percentage'] ?? '?') . '%')
                : 'Not applied',
            null,
            empty($peakMatched) ? ($context['peak_skip_reason'] ?? 'Pickup not in active peak zone') : null
        );

        $lines[] = self::line(
            'airport_charge',
            'Airport Charge',
            $airportFee > 0,
            ['airport_surge_fee' => $airportFee, 'source' => 'zone_types.airport_surge'],
            'Flat fee when pick/drop in airport polygon',
            "{$symbol}{$airportFee}",
            $airportFee,
            $airportFee <= 0 ? 'Pick/drop not in airport polygon' : null
        );

        $lines[] = self::line(
            'preference_charges',
            'Preference Charges',
            $preference > 0,
            ['preference_price_total' => $preference],
            'Sum of selected preference prices',
            "{$symbol}{$preference}",
            $preference,
            $preference <= 0 ? 'No preferences selected' : null
        );

        $lines[] = self::line(
            'cancellation_fee',
            'Cancellation Charges',
            $cancellation > 0,
            ['cancellation_fee' => $cancellation],
            'Unpaid prior cancellation fees rolled into ride',
            "{$symbol}{$cancellation}",
            $cancellation,
            $cancellation <= 0 ? 'No outstanding cancellation fee' : null
        );

        $lines[] = self::line(
            'service_tax',
            'Service Tax / GST / VAT',
            $taxAmount > 0,
            [
                'service_tax_percentage' => $taxPercent,
                'source' => 'zone_types.service_tax',
            ],
            "subtotal × {$taxPercent}%",
            "{$symbol}{$taxAmount}",
            $taxAmount,
            $taxAmount <= 0 ? 'Tax percentage zero or not configured' : null
        );

        $lines[] = self::line(
            'admin_commission',
            'Admin Commission',
            $adminComm > 0,
            [
                'admin_commision' => $adminComm,
                'admin_commision_type' => $zoneType->admin_commision_type ?? null,
                'admin_commision_value' => $zoneType->admin_commision ?? null,
            ],
            'Fixed or % from zone_types commission settings',
            "{$symbol}{$adminComm}",
            $adminComm
        );

        $lines[] = self::line(
            'driver_commission',
            'Driver Earnings',
            $driverComm != 0,
            ['driver_commision' => $driverComm],
            'Subtotal minus driver-side admin commission',
            "{$symbol}{$driverComm}",
            $driverComm
        );

        $lines[] = self::line(
            'promo_discount',
            'Promo / Coupon Discount',
            $promo > 0,
            [
                'promo_id' => $request->promo_id,
                'franchise_promo_id' => $request->franchise_promo_id,
                'promo_discount' => $promo,
            ],
            'Coupon % with min trip / max discount caps',
            "-{$symbol}{$promo}",
            $promo,
            $promo <= 0 ? 'No promo applied' : null
        );

        $lines[] = self::line(
            'total',
            $phase === 'eta' ? 'Estimated Total' : 'Final Total',
            true,
            ['total' => $total],
            'Sum of fare components + tax/commission − promo',
            "{$symbol}{$total}",
            $total
        );

        $rules = [
            [
                'rule' => 'zone_surge_prices',
                'matched' => (bool) $surgeMatched || $surgePercent > 0,
                'detail' => $surgeMatched,
                'skip_reason' => ($surgeMatched || $surgePercent > 0) ? null : ($context['surge_skip_reason'] ?? 'No matching day/time window'),
            ],
            [
                'rule' => 'peak_zones',
                'matched' => (bool) $peakMatched,
                'detail' => $peakMatched,
                'skip_reason' => $peakMatched ? null : ($context['peak_skip_reason'] ?? 'Not in peak polygon/window'),
            ],
            [
                'rule' => 'airport_fee',
                'matched' => $airportFee > 0,
                'detail' => ['amount' => $airportFee],
                'skip_reason' => $airportFee > 0 ? null : 'Not airport ride',
            ],
            [
                'rule' => 'eta_rate_lock',
                'matched' => $etaLocked,
                'detail' => ['enable_eta_total_update' => get_settings('enable_eta_total_update')],
                'skip_reason' => $etaLocked ? null : 'ETA rates not locked for this ride',
            ],
            [
                'rule' => 'promo',
                'matched' => $promo > 0,
                'detail' => [
                    'promo_id' => $request->promo_id,
                    'franchise_promo_id' => $request->franchise_promo_id,
                ],
                'skip_reason' => $promo > 0 ? null : 'No promo on request',
            ],
            [
                'rule' => 'bill_rounding',
                'matched' => (int) get_settings('can_round_the_bill_values') === 1,
                'detail' => ['can_round_the_bill_values' => get_settings('can_round_the_bill_values')],
                'skip_reason' => (int) get_settings('can_round_the_bill_values') === 1 ? null : 'Rounding disabled',
            ],
        ];

        return [
            'phase' => $phase,
            'captured_at' => now()->toIso8601String(),
            'currency' => [
                'code' => $request->requested_currency_code,
                'symbol' => $symbol,
            ],
            'context' => [
                'zone_id' => $zone->id ?? null,
                'zone_name' => $zone->name ?? null,
                'zone_type_id' => $request->zone_type_id,
                'service_location_id' => $request->service_location_id,
                'vehicle_type' => is_object($vehicleType)
                    ? ($vehicleType->name ?? null)
                    : ($request->vehicle_type_name ?? $vehicleType),
                'is_rental' => (bool) $request->is_rental,
                'is_out_station' => (bool) $request->is_out_station,
                'is_bid_ride' => (bool) $request->is_bid_ride,
                'shared_ride' => (bool) $request->shared_ride,
                'is_surge_applied' => (bool) ($result['is_surge_applied'] ?? $request->is_surge_applied),
                'eta_rates_locked' => $etaLocked,
                'multiplier_priority' => ['zone_surge', 'peak_zone', 'airport_flat_fee'],
            ],
            'rates' => [
                'base_price' => $basePrice,
                'base_distance' => $baseDistance,
                'price_per_distance' => $pricePerDistance,
                'price_per_time' => $pricePerTime,
                'waiting_charge_per_min' => $waitingPerMin,
                'service_tax_percentage' => $taxPercent,
            ],
            'lines' => $lines,
            'rules' => $rules,
            'settings_snapshot' => [
                'can_round_the_bill_values' => get_settings('can_round_the_bill_values'),
                'enable_eta_total_update' => get_settings('enable_eta_total_update'),
                'enable_peak_zone_feature' => get_settings('enable_peak_zone_feature'),
                'enable_driver_tips_feature' => get_settings('enable_driver_tips_feature'),
                'zone_type' => $zoneType ? [
                    'admin_commision_type' => $zoneType->admin_commision_type ?? null,
                    'admin_commision' => $zoneType->admin_commision ?? null,
                    'service_tax' => $zoneType->service_tax ?? null,
                    'admin_commission_from_driver' => $zoneType->admin_commission_from_driver ?? null,
                    'admin_commission_type_from_driver' => $zoneType->admin_commission_type_from_driver ?? null,
                    'airport_surge' => $zoneType->airport_surge ?? null,
                ] : null,
            ],
            'raw_result' => $result,
        ];
    }

    protected static function line(
        string $key,
        string $name,
        bool $applied,
        array $configured,
        string $formula,
        string $calculation,
        $result,
        ?string $skippedReason = null
    ): array {
        return [
            'key' => $key,
            'name' => $name,
            'applied' => $applied && !$skippedReason,
            'configured' => $configured,
            'formula' => $formula,
            'calculation' => $calculation,
            'result' => $result,
            'skipped_reason' => $skippedReason,
            'source_module' => 'Set Prices / Zone Type',
        ];
    }
}

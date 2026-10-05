<?php

namespace App\Helpers\Rides;

use Kreait\Firebase\Contract\Database;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Rides\RidePricingAuditWriter;
use App\Services\Rides\RideEventLogger;

trait StoreEtaDetailForRideHelper
{

    protected function storeEta($request_detail,$eta_result)
    {
        // Log::info("eta-distance");
        // Log::info($eta_result->data->distance);

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
            'total_amount'=>$request_detail->request_eta_amount,
            'requested_currency_code'=>$request_detail->requested_currency_code
        ];

        $requesteta = $request_detail->requestEtaDetail()->first();
        if ($requesteta) {
            $requesteta->update($request_eta_params);
        } else {
            $request_detail->requestEtaDetail()->create($request_eta_params);
        }

        $auditContext = [];
        if (isset($eta_result->data->_audit_context) && is_array($eta_result->data->_audit_context)) {
            $auditContext = $eta_result->data->_audit_context;
        }

        RidePricingAuditWriter::write($request_detail, 'eta', array_merge($request_eta_params, [
            'airport_surge_fee' => $eta_result->data->airport_surge_fee ?? 0,
            'is_surge_applied' => $eta_result->data->is_surge_applied ?? false,
            'waiting_charge' => 0,
        ]), $auditContext);

        RideEventLogger::log(
            $request_detail->id,
            'requested',
            'user',
            $request_detail->user_id,
            [
                'request_number' => $request_detail->request_number,
                'is_later' => (bool) $request_detail->is_later,
            ],
            $request_detail->created_at
        );

        if ($request_detail->on_search) {
            RideEventLogger::log($request_detail->id, 'search_started', 'system', null);
        }
    }
    
    protected function storePreference($request_detail,$preferences)
    {
        if(!isset($preferences) || !count($preferences)){
            return true;
        }
    
        $request_detail->preferenceDetail()->delete();

        $preferences = $request_detail->zoneType->preference()->whereIn('preference_id',$preferences)->get();

        foreach ($preferences as $key => $preference) {
            $request_detail->preferenceDetail()->create([
                "preference_price_id" => $preference->id,
                "price" => $preference->price,
            ]);
        }

        return true;

    }
    
}

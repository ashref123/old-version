<?php

namespace App\Http\Controllers\Api\V1\Common;

use App\Http\Controllers\Controller;
use App\Models\Admin\PopularCity;
use Illuminate\Http\Request;

class PopularCityController extends Controller
{
    public function index(Request $request)
    {
        $query = PopularCity::query()
            ->where('status', 1)
            ->orderBy('city_search');

        if ($request->filled('zone_id')) {
            $query->where('zone_id', $request->zone_id);
        } elseif ($request->filled('service_location_id')) {
            $query->whereHas('zone', function ($zoneQuery) use ($request) {
                $zoneQuery->where('service_location_id', $request->service_location_id);
            });
        }

        if ($request->filled('ride_type')) {
            $query->where('ride_type', $request->ride_type);

            return response()->json([
                'results' => $query->get()->values(),
            ]);
        }

        $cities = $query->get();

        return response()->json([
            'normal' => $cities->where('ride_type', 'normal')->values(),
            'outstation' => $cities->where('ride_type', 'outstation')->values(),
            'results' => $cities->values(),
        ]);
    }
}

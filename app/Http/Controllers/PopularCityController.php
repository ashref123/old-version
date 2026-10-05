<?php

namespace App\Http\Controllers;

use App\Base\Services\ImageUploader\ImageUploaderContract;
use App\Models\Admin\PopularCity;
use App\Models\Admin\Zone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PopularCityController extends Controller
{
    protected $imageUploader;

    public function __construct(ImageUploaderContract $imageUploader)
    {
        $this->imageUploader = $imageUploader;
    }

    public function index(Zone $zone)
    {
        return inertia('pages/zone/cities-index', [
            'zone' => $zone,
            'app_for' => env('APP_FOR'),
        ]);
    }

    public function fetch(Zone $zone, Request $request)
    {
        $query = PopularCity::query()
            ->where('zone_id', $zone->id)
            ->orderBy('city_search');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($innerQuery) use ($search) {
                $innerQuery->where('city_search', 'like', "%{$search}%")
                    ->orWhere('shortcode', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('ride_type', 'like', "%{$search}%");
            });
        }

        $results = $query->paginate($request->input('limit', 10));

        return response()->json([
            'results' => $results->items(),
            'paginator' => $results,
        ]);
    }

    public function create(Zone $zone)
    {
        return inertia('pages/zone/city-create', [
            'zone' => $zone,
            'selectedZoneId' => $zone->id,
            'zones' => Zone::select('id', 'name')->orderBy('name')->get(),
            'popularCity' => null,
            'googleMapKey' => get_map_settings('google_map_key'),
            'app_for' => env('APP_FOR'),
        ]);
    }

    public function edit(PopularCity $popularCity)
    {
        return inertia('pages/zone/city-create', [
            'zone' => $popularCity->zone,
            'selectedZoneId' => $popularCity->zone_id,
            'zones' => Zone::select('id', 'name')->orderBy('name')->get(),
            'popularCity' => $popularCity,
            'googleMapKey' => get_map_settings('google_map_key'),
            'app_for' => env('APP_FOR'),
        ]);
    }

    public function store(Request $request)
    {
        if (env('APP_FOR') === 'demo') {
            return response()->json(['alertMessage' => 'You are not Authorized'], 403);
        }

        $validated = $request->validate([
            'zone_id' => ['required', 'exists:zones,id'],
            'ride_type' => ['required', Rule::in(['normal', 'outstation'])],
            'city_search' => ['required', 'string', 'max:255'],
            'lat' => ['required', 'numeric'],
            'lng' => ['required', 'numeric'],
            'image' => ['nullable', 'image', 'max:5120'],
            'shortcode' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $validated['status'] = 1;
        if ($request->hasFile('image')) {
            $validated['image'] = $this->imageUploader
                ->file($request->file('image'))
                ->savePopularCityImage();
        }

        $popularCity = PopularCity::create($validated);

        return response()->json(['popularCity' => $popularCity], 201);
    }

    public function update(Request $request, PopularCity $popularCity)
    {
        if (env('APP_FOR') === 'demo') {
            return response()->json(['alertMessage' => 'You are not Authorized'], 403);
        }

        $rules = [
            'zone_id' => ['required', 'exists:zones,id'],
            'ride_type' => ['required', Rule::in(['normal', 'outstation'])],
            'city_search' => ['required', 'string', 'max:255'],
            'lat' => ['required', 'numeric'],
            'lng' => ['required', 'numeric'],
            'shortcode' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ];

        if ($request->hasFile('image')) {
            $rules['image'] = ['required', 'image', 'max:5120'];
        }

        if ($request->has('image_removed')) {
            $rules['image_removed'] = ['boolean'];
        }

        $validated = $request->validate($rules);

        if ($request->hasFile('image')) {
            $validated['image'] = $this->imageUploader
                ->file($request->file('image'))
                ->savePopularCityImage();
        } elseif ($request->boolean('image_removed')) {
            $validated['image'] = null;
        }

        unset($validated['image_removed']);

        $popularCity->update($validated);

        return response()->json(['popularCity' => $popularCity], 200);
    }

    public function updateStatus(Request $request)
    {
        if (env('APP_FOR') === 'demo') {
            return response()->json(['alertMessage' => 'You are not Authorized'], 403);
        }

        PopularCity::where('id', $request->id)->update(['status' => $request->status ? 1 : 0]);

        return response()->json(['successMessage' => 'Popular city status updated successfully']);
    }

    public function destroy(PopularCity $popularCity)
    {
        if (env('APP_FOR') === 'demo') {
            return response()->json(['alertMessage' => 'You are not Authorized'], 403);
        }

        $popularCity->delete();

        return response()->json(['success' => true], 200);
    }
}

<?php

namespace App\Services\Maps;

use App\Services\Maps\DTO\PlaceResult;
use App\Services\Maps\DTO\RouteResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Minimal geocode/reverse-geocode shim. This project has no server-side geocoding anywhere else
 * — every other booking path (app, web) receives an already-resolved address from the client's
 * own map picker. The WhatsApp bot is the first caller that needs the server to resolve a raw
 * WhatsApp location pin or free-typed text itself, hence this class.
 *
 * Intentionally narrower than a full multi-provider abstraction: branches on the same
 * `map_type` setting (`google_map` vs anything else -> OpenStreetMap/Nominatim, free, no key)
 * already used elsewhere in this app (see get_line_string() in app/Helpers/helpers.php), rather
 * than introducing a new provider-selection concept.
 */
class MapProviderFactory
{
    public static function active(): self
    {
        return new self;
    }

    public function geocode(string $text): ?PlaceResult
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        return $this->usesGoogle()
            ? $this->googleGeocode($text)
            : $this->osmGeocode($text);
    }

    public function reverseGeocode(float $lat, float $lng): ?PlaceResult
    {
        return $this->usesGoogle()
            ? $this->googleReverseGeocode($lat, $lng)
            : $this->osmReverseGeocode($lat, $lng);
    }

    /**
     * Distance/duration between two points, used for pricing when a ride is booked without a
     * client-side map picker (i.e. the WhatsApp flow). Mirrors this project's own existing
     * get_line_string() routing calls in app/Helpers/helpers.php (same Google Routes API /
     * same public OSRM instance) — just requesting distance+duration in the field mask/response
     * in addition to the polyline that helper already pulls.
     */
    public function directions(float $originLat, float $originLng, float $destLat, float $destLng): ?RouteResult
    {
        return $this->usesGoogle()
            ? $this->googleDirections($originLat, $originLng, $destLat, $destLng)
            : $this->osrmDirections($originLat, $originLng, $destLat, $destLng);
    }

    protected function usesGoogle(): bool
    {
        return get_map_settings('map_type') === 'google_map';
    }

    protected function googleDirections(float $originLat, float $originLng, float $destLat, float $destLng): ?RouteResult
    {
        $key = (string) get_map_settings('google_map_key_for_distance_matrix');
        if ($key === '') {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Goog-Api-Key' => $key,
                'X-Goog-FieldMask' => 'routes.distanceMeters,routes.duration,routes.polyline.encodedPolyline',
            ])->timeout(10)->post('https://routes.googleapis.com/directions/v2:computeRoutes', [
                'origin' => ['location' => ['latLng' => ['latitude' => $originLat, 'longitude' => $originLng]]],
                'destination' => ['location' => ['latLng' => ['latitude' => $destLat, 'longitude' => $destLng]]],
                'travelMode' => 'DRIVE',
                'routingPreference' => 'TRAFFIC_AWARE',
                'computeAlternativeRoutes' => false,
                'routeModifiers' => ['avoidTolls' => false, 'avoidHighways' => false, 'avoidFerries' => false],
                'languageCode' => 'en-US',
                'units' => 'METRIC',
            ]);

            $route = data_get($response->json(), 'routes.0');
            if (! $route) {
                return null;
            }

            return new RouteResult(
                distanceMeters: (float) data_get($route, 'distanceMeters'),
                durationSeconds: $this->parseGoogleDuration(data_get($route, 'duration')),
                encodedPolyline: data_get($route, 'polyline.encodedPolyline'),
                raw: $route,
            );
        } catch (Throwable $e) {
            Log::warning('MapProviderFactory::googleDirections failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    protected function osrmDirections(float $originLat, float $originLng, float $destLat, float $destLng): ?RouteResult
    {
        try {
            $url = "https://routing.openstreetmap.de/routed-car/route/v1/driving/{$originLng},{$originLat};{$destLng},{$destLat}";
            $response = Http::timeout(10)->get($url, [
                'overview' => 'false',
                'alternatives' => 'false',
                'steps' => 'false',
            ]);

            $route = data_get($response->json(), 'routes.0');
            if (! $route) {
                return null;
            }

            return new RouteResult(
                distanceMeters: (float) data_get($route, 'distance'),
                durationSeconds: (int) data_get($route, 'duration'),
                raw: $route,
            );
        } catch (Throwable $e) {
            Log::warning('MapProviderFactory::osrmDirections failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Google Routes API returns duration as a string like "1234s".
     */
    protected function parseGoogleDuration(mixed $duration): ?int
    {
        if (! is_string($duration) || $duration === '') {
            return null;
        }

        return (int) rtrim($duration, 's');
    }

    protected function googleGeocode(string $text): ?PlaceResult
    {
        $key = (string) get_map_settings('google_map_key');
        if ($key === '') {
            return null;
        }

        try {
            $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $text,
                'key' => $key,
            ]);

            $result = data_get($response->json(), 'results.0');
            if (! $result) {
                return null;
            }

            return new PlaceResult(
                lat: (float) data_get($result, 'geometry.location.lat'),
                lng: (float) data_get($result, 'geometry.location.lng'),
                address: (string) data_get($result, 'formatted_address'),
                placeId: data_get($result, 'place_id'),
                raw: $result,
            );
        } catch (Throwable $e) {
            Log::warning('MapProviderFactory::googleGeocode failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    protected function googleReverseGeocode(float $lat, float $lng): ?PlaceResult
    {
        $key = (string) get_map_settings('google_map_key');
        if ($key === '') {
            return null;
        }

        try {
            $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'latlng' => "{$lat},{$lng}",
                'key' => $key,
            ]);

            $result = data_get($response->json(), 'results.0');
            if (! $result) {
                return null;
            }

            return new PlaceResult(
                lat: $lat,
                lng: $lng,
                address: (string) data_get($result, 'formatted_address'),
                placeId: data_get($result, 'place_id'),
                raw: $result,
            );
        } catch (Throwable $e) {
            Log::warning('MapProviderFactory::googleReverseGeocode failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    protected function osmGeocode(string $text): ?PlaceResult
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => $this->nominatimUserAgent()])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $text,
                    'format' => 'json',
                    'limit' => 1,
                ]);

            $result = data_get($response->json(), '0');
            if (! $result) {
                return null;
            }

            return new PlaceResult(
                lat: (float) data_get($result, 'lat'),
                lng: (float) data_get($result, 'lon'),
                address: (string) data_get($result, 'display_name'),
                raw: $result,
            );
        } catch (Throwable $e) {
            Log::warning('MapProviderFactory::osmGeocode failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    protected function osmReverseGeocode(float $lat, float $lng): ?PlaceResult
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => $this->nominatimUserAgent()])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $lat,
                    'lon' => $lng,
                    'format' => 'json',
                ]);

            $json = $response->json();
            if (! $json || data_get($json, 'error')) {
                return null;
            }

            return new PlaceResult(
                lat: $lat,
                lng: $lng,
                address: (string) data_get($json, 'display_name'),
                raw: $json,
            );
        } catch (Throwable $e) {
            Log::warning('MapProviderFactory::osmReverseGeocode failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    protected function nominatimUserAgent(): string
    {
        return 'RestartAdminPanel/1.0 ('.rtrim((string) config('app.url'), '/').')';
    }
}

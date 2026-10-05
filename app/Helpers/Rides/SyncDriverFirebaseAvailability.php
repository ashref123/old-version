<?php

namespace App\Helpers\Rides;

use App\Models\Request\Request as RideRequest;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Database;

/**
 * Keep Firebase drivers/driver_{id}.is_available aligned with MySQL free state.
 * Regular dispatch requires Firebase is_available == 1; trip-end APIs historically
 * only flipped MySQL available, leaving Firebase stuck false.
 */
class SyncDriverFirebaseAvailability
{
    /**
     * After a driver is freed in MySQL, set Firebase is_available=true only when
     * they have no remaining open trips (supports second-ride / waiting ride).
     *
     * @param  string|null  $excludeRequestId  Request being ended/cancelled (still open in DB at call time)
     */
    public static function syncWhenFreed(int $driverId, ?string $excludeRequestId = null, ?Database $database = null): bool
    {
        if ($driverId <= 0) {
            return false;
        }

        if (self::hasOpenTrips($driverId, $excludeRequestId)) {
            Log::channel('activity')->info('Skip Firebase is_available=true; driver still has open trips', [
                'driver_id' => $driverId,
                'exclude_request_id' => $excludeRequestId,
            ]);

            return false;
        }

        return self::setAvailable($driverId, true, $database);
    }

    public static function setAvailable(int $driverId, bool $available, ?Database $database = null): bool
    {
        if ($driverId <= 0) {
            return false;
        }

        try {
            $database = $database ?? app(Database::class);
            $database->getReference('drivers/driver_'.$driverId)->update([
                'is_available' => $available,
                'updated_at' => Database::SERVER_TIMESTAMP,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to sync Firebase is_available', [
                'driver_id' => $driverId,
                'is_available' => $available,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public static function hasOpenTrips(int $driverId, ?string $excludeRequestId = null): bool
    {
        $query = RideRequest::query()
            ->where('driver_id', $driverId)
            ->where('is_completed', false)
            ->where('is_cancelled', false);

        if ($excludeRequestId) {
            $query->where('id', '!=', $excludeRequestId);
        }

        return $query->exists();
    }

    /**
     * @return array<int, array{id:string,request_number:?string,is_driver_started:bool}>
     */
    public static function openTripsSummary(int $driverId): array
    {
        return RideRequest::query()
            ->where('driver_id', $driverId)
            ->where('is_completed', false)
            ->where('is_cancelled', false)
            ->orderByDesc('created_at')
            ->get(['id', 'request_number', 'is_driver_started'])
            ->map(static function ($ride) {
                return [
                    'id' => (string) $ride->id,
                    'request_number' => $ride->request_number,
                    'is_driver_started' => (bool) $ride->is_driver_started,
                ];
            })
            ->values()
            ->all();
    }
}

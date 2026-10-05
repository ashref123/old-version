<?php

namespace App\Helpers\Rides;

use App\Jobs\Notifications\SendPushNotification;
use App\Models\Admin\Driver;
use App\Models\Request\Request as RequestModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Database;

trait UnassignDriverFromRideHelper
{
    protected function unassignDriverFromRide(
        RequestModel $requestmodel,
        Database $database,
        string $portal,
        string $action = 'unassign'
    ): array {
        if ($requestmodel->is_cancelled || $requestmodel->is_completed) {
            return ['status' => false, 'message' => 'Cannot Unassign Request'];
        }

        if (!$requestmodel->driver_id && !$requestmodel->requestMeta()->exists()) {
            return ['status' => false, 'message' => 'No driver assigned to this ride'];
        }

        $oldDriverId = $requestmodel->driver_id;
        $metaDriverIds = $requestmodel->requestMeta()->pluck('driver_id')->toArray();
        $driverIdsToNotify = array_unique(array_filter(array_merge(
            $oldDriverId ? [$oldDriverId] : [],
            $metaDriverIds
        )));

        if ($oldDriverId) {
            $driver = Driver::find($oldDriverId);
            if ($driver) {
                $occupiedSeats = 0;
                if ($requestmodel->shared_ride) {
                    $occupiedSeats = max(0, ($driver->occupied_seats ?? 0) - ($requestmodel->seats_taken ?? 0));
                }
                $driver->update(['available' => true, 'occupied_seats' => $occupiedSeats]);
                SyncDriverFirebaseAvailability::syncWhenFreed(
                    (int) $oldDriverId,
                    (string) $requestmodel->id,
                    $database
                );
            }
        }

        $requestmodel->requestMeta()->delete();

        $requestmodel->forceFill([
            'driver_id' => null,
            'accepted_at' => null,
            'is_driver_started' => false,
            'is_driver_arrived' => false,
            'is_trip_start' => false,
            'arrived_at' => null,
            'owner_id' => null,
            'fleet_id' => null,
            'franchise_owner_id' => null,
            'on_search' => true,
        ])->save();

        try {
            $database->getReference('requests/'.$requestmodel->id)->update([
                'driver_id' => '',
                'is_accept' => 0,
                'trip_arrived' => 0,
                'trip_start' => 0,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to update Firebase request on unassign', [
                'request_id' => $requestmodel->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $database->getReference('request-meta/'.$requestmodel->id)->remove();
        } catch (\Throwable $e) {
            Log::warning('Failed to remove Firebase request-meta on unassign', [
                'request_id' => $requestmodel->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->notifyUnassignedDrivers($driverIdsToNotify);

        Log::channel('activity')->info('Ride driver unassigned', [
            'request_id' => $requestmodel->id,
            'request_number' => $requestmodel->request_number,
            'old_driver_id' => $oldDriverId,
            'action' => $action,
            'portal' => $portal,
            'performed_by_user_id' => auth()->id(),
            'performed_by_name' => auth()->user()?->name,
            'performed_at' => now()->toDateTimeString(),
        ]);

        return ['status' => true, 'message' => 'Driver unassigned successfully'];
    }

    protected function notifyUnassignedDrivers(array $driverIds): void
    {
        if (empty($driverIds)) {
            return;
        }

        $notification = DB::table('notification_channels')
            ->where('topics', 'Trip Cancelled By System')
            ->first();

        if (!$notification || !$notification->push_notification) {
            return;
        }

        foreach ($driverIds as $driverId) {
            $driver = Driver::with('user')->find($driverId);
            if (!$driver || !$driver->user) {
                continue;
            }

            $notifiableDriver = $driver->user;
            $userLang = $notifiableDriver->lang ?? 'en';

            $translation = DB::table('notification_channels_translations')
                ->where('notification_channel_id', $notification->id)
                ->where('locale', $userLang)
                ->first();

            if (!$translation) {
                $translation = DB::table('notification_channels_translations')
                    ->where('notification_channel_id', $notification->id)
                    ->where('locale', 'en')
                    ->first();
            }

            $title = $translation->push_title ?? $notification->push_title;
            $body = strip_tags($translation->push_body ?? $notification->push_body);
            dispatch(new SendPushNotification($notifiableDriver, $title, $body));
        }
    }
}

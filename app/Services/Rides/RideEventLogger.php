<?php

namespace App\Services\Rides;

use App\Models\Request\Request as RequestModel;
use App\Models\Request\RequestEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class RideEventLogger
{
    /**
     * Persist a lifecycle event. Never throws to callers.
     */
    public static function log(
        $requestId,
        string $event,
        ?string $actorType = null,
        $actorId = null,
        array $payload = [],
        $occurredAt = null
    ): void {
        try {
            if (!$requestId) {
                return;
            }

            RequestEvent::create([
                'request_id' => $requestId,
                'event' => $event,
                'actor_type' => $actorType,
                'actor_id' => $actorId !== null ? (string) $actorId : null,
                'payload' => $payload ?: null,
                'occurred_at' => $occurredAt
                    ? Carbon::parse($occurredAt)
                    : Carbon::now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('RideEventLogger failed', [
                'request_id' => $requestId,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build a synthetic timeline for rides that pre-date request_events.
     */
    public static function syntheticTimeline(RequestModel $request): array
    {
        $events = [];

        $push = function (string $event, $at, ?string $actorType = null, $actorId = null, array $payload = []) use (&$events) {
            if (!$at) {
                return;
            }
            $events[] = [
                'event' => $event,
                'actor_type' => $actorType,
                'actor_id' => $actorId !== null ? (string) $actorId : null,
                'payload' => $payload,
                'occurred_at' => Carbon::parse($at)->toIso8601String(),
                'source' => 'synthetic',
            ];
        };

        $push('requested', $request->created_at, 'user', $request->user_id);

        if ($request->on_search) {
            $push('search_started', $request->created_at, 'system', null);
        }

        foreach ($request->requestMeta->sortBy('created_at') as $meta) {
            $push(
                $meta->active ? 'driver_offered' : 'offer_cleared',
                $meta->created_at,
                'system',
                $meta->driver_id,
                ['driver_id' => $meta->driver_id, 'active' => (bool) $meta->active]
            );
        }

        foreach ($request->driverRejectedRequestDetail ?? [] as $reject) {
            $push(
                $reject->is_after_accept ? 'driver_cancelled_after_accept' : 'driver_rejected',
                $reject->created_at,
                'driver',
                $reject->driver_id,
                [
                    'reason' => $reject->reason,
                    'custom_reason' => $reject->custom_reason,
                ]
            );
        }

        $push('accepted', $request->accepted_at, 'driver', $request->driver_id);

        if ($request->is_driver_started && $request->accepted_at) {
            $push('driver_started_to_pickup', $request->accepted_at, 'driver', $request->driver_id);
        }

        $push('arrived', $request->arrived_at, 'driver', $request->driver_id);

        if ($request->is_trip_start && $request->trip_start_time && !$request->is_later) {
            $push('started', $request->trip_start_time, 'driver', $request->driver_id, [
                'otp' => $request->ride_otp ? 'verified' : null,
            ]);
        } elseif ($request->is_trip_start && $request->trip_start_time) {
            $push('started', $request->trip_start_time, 'driver', $request->driver_id);
        }

        $push('completed', $request->completed_at, 'driver', $request->driver_id);
        $push('cancelled', $request->cancelled_at, self::cancelActorType($request), self::cancelActorId($request), [
            'cancel_method' => $request->cancel_method,
            'reason' => $request->reason,
            'custom_reason' => $request->custom_reason,
        ]);
        // Delivery stores paid_at as Sender/Receiver (who pays) — not a payment timestamp.
        $paidAt = $request->paid_at;
        $isPaymentParty = is_string($paidAt)
            && in_array(ucfirst(strtolower($paidAt)), ['Sender', 'Receiver'], true);
        if ($request->is_paid && !$isPaymentParty) {
            $push('payment_completed', $paidAt, 'system', null, [
                'payment_opt' => $request->payment_opt,
                'is_paid' => true,
            ]);
        } elseif ($isPaymentParty) {
            $push('payment_party_set', $request->created_at, 'system', null, [
                'payment_party' => ucfirst(strtolower($paidAt)),
                'payment_opt' => $request->payment_opt,
                'is_paid' => (bool) $request->is_paid,
            ]);
        }

        usort($events, function ($a, $b) {
            return strcmp($a['occurred_at'] ?? '', $b['occurred_at'] ?? '');
        });

        return $events;
    }

    protected static function cancelActorType(RequestModel $request): string
    {
        return match ((string) $request->cancel_method) {
            '1' => 'user',
            '2' => 'driver',
            '3' => 'admin',
            default => 'system',
        };
    }

    protected static function cancelActorId(RequestModel $request)
    {
        return match ((string) $request->cancel_method) {
            '1' => $request->user_id,
            '2' => $request->driver_id,
            '3' => auth()->id(),
            default => null,
        };
    }
}

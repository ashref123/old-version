<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ride Report {{ $overview['request_number'] ?? '' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        h2 { font-size: 14px; margin-top: 18px; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; }
        .muted { color: #666; }
    </style>
</head>
<body>
    <h1>Ride Report — {{ $overview['request_number'] ?? $overview['id'] }}</h1>
    <p class="muted">Generated for admin review</p>

    <h2>Overview</h2>
    <table>
        <tr><th>Status</th><td>{{ $overview['status'] ?? '' }}</td></tr>
        <tr><th>Booking Type</th><td>{{ $overview['booking_type'] ?? '' }}</td></tr>
        <tr><th>Ride Type</th><td>{{ $overview['ride_type'] ?? '' }}</td></tr>
        <tr><th>Zone</th><td>{{ $overview['zone'] ?? '' }}</td></tr>
        <tr><th>Vehicle</th><td>{{ $overview['vehicle_type'] ?? '' }}</td></tr>
        <tr><th>Created</th><td>{{ $overview['created_at'] ?? '' }}</td></tr>
        <tr><th>Accepted</th><td>{{ $overview['accepted_at'] ?? '' }}</td></tr>
        <tr><th>Arrived</th><td>{{ $overview['arrived_at'] ?? '' }}</td></tr>
        <tr><th>Started</th><td>{{ $overview['started_at'] ?? '' }}</td></tr>
        <tr><th>Completed</th><td>{{ $overview['completed_at'] ?? '' }}</td></tr>
        <tr><th>Cancelled</th><td>{{ $overview['cancelled_at'] ?? '' }}</td></tr>
        <tr><th>Distance</th><td>{{ $overview['total_distance'] ?? '' }} {{ $overview['unit'] ?? '' }}</td></tr>
        <tr><th>Duration</th><td>
            @php
                $mins = isset($overview['total_duration_mins']) ? (int) round($overview['total_duration_mins']) : null;
            @endphp
            @if($mins !== null && $mins >= 60)
                {{ number_format($mins / 60, 2) }} hrs ({{ $mins }} min)
            @elseif($mins !== null)
                {{ $mins }} min
            @endif
        </td></tr>
        <tr><th>Waiting</th><td>{{ isset($overview['waiting_time_mins']) ? (int) round($overview['waiting_time_mins']) : '' }} min</td></tr>
    </table>

    @if(!empty($dossier['customer']))
    <h2>Customer</h2>
    <table>
        <tr><th>Name</th><td>{{ $dossier['customer']['name'] ?? '' }}</td></tr>
        <tr><th>Phone</th><td>{{ $dossier['customer']['mobile'] ?? '' }}</td></tr>
        <tr><th>Email</th><td>{{ $dossier['customer']['email'] ?? '' }}</td></tr>
        <tr><th>Previous rides</th><td>{{ $dossier['customer']['previous_ride_count'] ?? 0 }}</td></tr>
        <tr><th>Cancellations</th><td>{{ $dossier['customer']['cancellation_count'] ?? 0 }}</td></tr>
    </table>
    @endif

    @if(!empty($dossier['driver']))
    <h2>Driver</h2>
    <table>
        <tr><th>Name</th><td>{{ $dossier['driver']['name'] ?? '' }}</td></tr>
        <tr><th>Phone</th><td>{{ $dossier['driver']['mobile'] ?? '' }}</td></tr>
        <tr><th>Vehicle</th><td>{{ $dossier['driver']['vehicle']['car_number'] ?? '' }}</td></tr>
        <tr><th>Earnings</th><td>{{ $dossier['driver']['earnings_this_ride'] ?? '' }}</td></tr>
    </table>
    @endif

    <h2>Locations</h2>
    <table>
        <tr><th>Pickup</th><td>{{ $dossier['locations']['pickup']['address'] ?? '' }}</td></tr>
        <tr><th>Drop</th><td>{{ $dossier['locations']['drop']['address'] ?? '' }}</td></tr>
    </table>

    @if(!empty($dossier['final_fare']) || !empty($dossier['estimated_fare']))
    <h2>Fare</h2>
    <table>
        <tr>
            <th>Component</th>
            <th>Estimated</th>
            <th>Final</th>
        </tr>
        @php
            $est = $dossier['estimated_fare'] ?? [];
            $fin = $dossier['final_fare'] ?? [];
            $keys = ['base_price','distance_price','time_price','waiting_charge','airport_surge_fee','service_tax','promo_discount','total_amount'];
        @endphp
        @foreach($keys as $key)
            <tr>
                <td>{{ $key }}</td>
                <td>{{ $est[$key] ?? '-' }}</td>
                <td>{{ $fin[$key] ?? '-' }}</td>
            </tr>
        @endforeach
    </table>
    @endif

    <h2>Timeline</h2>
    <table>
        <tr><th>Event</th><th>When</th><th>Actor</th></tr>
        @foreach(($dossier['timeline'] ?? []) as $event)
            <tr>
                <td>{{ $event['event'] ?? '' }}</td>
                <td>{{ $event['occurred_at'] ?? '' }}</td>
                <td>{{ ($event['actor_type'] ?? '') . ' ' . ($event['actor_id'] ?? '') }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>

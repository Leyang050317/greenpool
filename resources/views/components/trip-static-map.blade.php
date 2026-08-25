@props(['trip', 'bookings' => collect(), 'route' => null, 'liveLocation' => null, 'liveTracking' => false])

@php
    $validCoordinate = fn ($latitude, $longitude) => is_numeric($latitude) && is_numeric($longitude)
        && (float) $latitude >= -90 && (float) $latitude <= 90
        && (float) $longitude >= -180 && (float) $longitude <= 180;
    $hasTripCoordinates = $validCoordinate($trip->departure_latitude, $trip->departure_longitude)
        && $validCoordinate($trip->destination_latitude, $trip->destination_longitude);
    $browserKey = config('services.google_maps.browser_key');
    $mapId = 'trip-map-'.$trip->trip_id.'-'.Str::random(8);
    $driverLocation = $liveLocation ? [
        'latitude' => (float) $liveLocation->latitude,
        'longitude' => (float) $liveLocation->longitude,
        'accuracy_meters' => $liveLocation->accuracy_meters === null ? null : (float) $liveLocation->accuracy_meters,
        'heading' => $liveLocation->heading === null ? null : (float) $liveLocation->heading,
        'recorded_at' => $liveLocation->recorded_at?->toIso8601String(),
    ] : null;
    $markers = $hasTripCoordinates ? [
        ['position' => ['lat' => (float) $trip->departure_latitude, 'lng' => (float) $trip->departure_longitude], 'label' => 'D', 'title' => 'Departure: '.$trip->departure_location],
        ...$bookings->filter(fn ($booking) => $validCoordinate($booking->pickup_latitude, $booking->pickup_longitude))->values()->map(fn ($booking, $index) => [
            'position' => ['lat' => (float) $booking->pickup_latitude, 'lng' => (float) $booking->pickup_longitude],
            'label' => 'P'.($index + 1), 'title' => 'Pickup '.($index + 1),
        ])->all(),
        ['position' => ['lat' => (float) $trip->destination_latitude, 'lng' => (float) $trip->destination_longitude], 'label' => 'F', 'title' => 'Destination: '.$trip->destination],
    ] : [];
@endphp

<section class="overflow-hidden rounded-2xl border border-gray-100 bg-white p-5">
    <div class="mb-4 flex items-start justify-between gap-4">
        <div><h2 class="text-sm font-semibold text-gray-900">Trip map</h2><p class="mt-1 text-xs text-gray-500">{{ $liveTracking ? 'Live driver location is shown when available.' : 'Static route plan.' }}</p><p @class(['mt-1 text-xs text-green-700' => $liveTracking, 'hidden' => ! $liveTracking]) data-live-location-status-for="{{ $mapId }}">{{ $driverLocation ? 'Live location updated '.\Carbon\Carbon::parse($driverLocation['recorded_at'])->format('g:i A').'.' : 'Waiting for the driver’s live location.' }}</p></div>
        <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $liveTracking ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-600' }}">{{ $liveTracking ? 'Live' : 'Static' }}</span>
    </div>

    @if(! $hasTripCoordinates)
        <div class="flex min-h-56 items-center justify-center rounded-xl bg-slate-50 px-5 text-center text-sm text-slate-500">Map is currently unavailable for this trip.</div>
    @elseif(blank($browserKey))
        <div class="flex min-h-56 items-center justify-center rounded-xl bg-slate-50 px-5 text-center text-sm text-slate-500">Map will be available once Google Maps is configured.</div>
    @else
        <div id="{{ $mapId }}" data-trip-static-map data-trip-id="{{ $trip->trip_id }}" data-live-tracking="{{ $liveTracking ? 'true' : 'false' }}" data-live-location='@json($driverLocation)' data-markers='@json($markers)' data-polyline='@json(data_get($route, "encoded_polyline"))' class="h-72 rounded-xl bg-slate-100 sm:h-96" aria-label="Map for {{ $trip->departure_location }} to {{ $trip->destination }}"></div>
        <p data-map-error-for="{{ $mapId }}" class="mt-3 hidden text-sm text-slate-500">Map is currently unavailable for this trip.</p>
    @endif
</section>

@if($hasTripCoordinates && filled($browserKey))
    @once
        <script>
            window.GreenPoolStaticMaps = window.GreenPoolStaticMaps || {
                instances: {},
                pendingLocations: {},
                load() {
                    if (window.google?.maps) return Promise.resolve();
                    if (this.promise) return this.promise;
                    this.promise = new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = 'https://maps.googleapis.com/maps/api/js?key={{ urlencode($browserKey) }}&libraries=geometry';
                        script.async = true;
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });
                    return this.promise;
                },
                init(element) {
                    const markers = JSON.parse(element.dataset.markers || '[]');
                    if (!markers.length) throw new Error('No map markers available.');
                    const map = new google.maps.Map(element, { mapTypeControl: false, streetViewControl: false, fullscreenControl: false });
                    const bounds = new google.maps.LatLngBounds();
                    markers.forEach((marker) => {
                        new google.maps.Marker({ map, position: marker.position, label: marker.label, title: marker.title });
                        bounds.extend(marker.position);
                    });
                    const encodedPolyline = JSON.parse(element.dataset.polyline || 'null');
                    if (encodedPolyline && google.maps.geometry?.encoding) {
                        new google.maps.Polyline({ map, path: google.maps.geometry.encoding.decodePath(encodedPolyline), strokeColor: '#16A34A', strokeOpacity: 0.9, strokeWeight: 5 });
                    }
                    map.fitBounds(bounds, 48);
                    if (markers.length === 1) map.setZoom(14);
                    const instance = { map, tripId: String(element.dataset.tripId), driverMarker: null, status: document.querySelector(`[data-live-location-status-for="${element.id}"]`) };
                    this.instances[element.id] = instance;
                    const liveLocation = this.pendingLocations[instance.tripId] || JSON.parse(element.dataset.liveLocation || 'null');
                    if (liveLocation) this.updateDriver(instance.tripId, liveLocation);
                },
                updateDriver(tripId, location) {
                    const latitude = Number(location.latitude);
                    const longitude = Number(location.longitude);
                    if (!Number.isFinite(latitude) || latitude < -90 || latitude > 90 || !Number.isFinite(longitude) || longitude < -180 || longitude > 180) return;
                    this.pendingLocations[String(tripId)] = location;
                    Object.values(this.instances).filter((instance) => instance.tripId === String(tripId)).forEach((instance) => {
                        const position = { lat: latitude, lng: longitude };
                        if (instance.driverMarker) instance.driverMarker.setPosition(position);
                        else instance.driverMarker = new google.maps.Marker({ map: instance.map, position, label: 'D', title: 'Live driver location', zIndex: 10 });
                        if (instance.status) instance.status.textContent = `Live location updated ${new Date(location.recorded_at || Date.now()).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}.`;
                    });
                },
            };
            document.addEventListener('DOMContentLoaded', () => {
                const maps = [...document.querySelectorAll('[data-trip-static-map]')];
                if (!maps.length) return;
                window.GreenPoolStaticMaps.load().then(() => maps.forEach((map) => {
                    try { window.GreenPoolStaticMaps.init(map); } catch (_) { document.querySelector(`[data-map-error-for="${map.id}"]`)?.classList.remove('hidden'); }
                })).catch(() => maps.forEach((map) => document.querySelector(`[data-map-error-for="${map.id}"]`)?.classList.remove('hidden')));
            });
            document.addEventListener('greenpool:trip-location', (event) => window.GreenPoolStaticMaps.updateDriver(event.detail.trip_id, event.detail));
        </script>
    @endonce
@endif

/**
 * Shared Gods Eye helpers: status classification, filter/search matching,
 * lat/lng resolution, and status-aware map icon stems.
 */

export function isFirebaseAvailable(value) {
    return value === true || value === 1 || value === '1' || value === 'true';
}

/**
 * @param {object} driver Firebase driver node
 * @param {{ hasOpenTrip?: boolean|null }} [hints]
 *   hasOpenTrip === false with active+unavailable → stuck (desync)
 *   hasOpenTrip === true → onride
 *   hasOpenTrip == null → treat as onride (unknown)
 */
export function classifyDriverStatus(driver, hints = {}) {
    if (!driver || driver.is_active == null || driver.is_available == null) {
        return 'offline';
    }

    const active = Number(driver.is_active) === 1;
    const available = isFirebaseAvailable(driver.is_available);

    if (active && available) {
        return 'online';
    }
    if (active && !available) {
        if (hints.hasOpenTrip === false) {
            return 'stuck';
        }
        return 'onride';
    }
    return 'offline';
}

export function firebaseUpdatedAtAgeSeconds(updatedAt) {
    if (updatedAt == null || updatedAt === '') {
        return null;
    }
    let ms = Number(updatedAt);
    if (!Number.isFinite(ms)) {
        return null;
    }
    if (ms < 1e12) {
        ms *= 1000;
    }
    return Math.max(0, Math.floor((Date.now() - ms) / 1000));
}

/**
 * Human-readable age, e.g. "45s ago", "12m ago", "3h 20m ago", "15d 3h ago".
 */
export function formatAgeLabel(ageSeconds) {
    if (ageSeconds == null || !Number.isFinite(ageSeconds)) {
        return null;
    }
    const sec = Math.max(0, Math.floor(ageSeconds));
    if (sec < 60) {
        return `${sec}s ago`;
    }
    const mins = Math.floor(sec / 60);
    if (mins < 60) {
        return `${mins}m ago`;
    }
    const hours = Math.floor(mins / 60);
    const remMins = mins % 60;
    if (hours < 24) {
        return remMins > 0 ? `${hours}h ${remMins}m ago` : `${hours}h ago`;
    }
    const days = Math.floor(hours / 24);
    const remHours = hours % 24;
    if (days < 30) {
        return remHours > 0 ? `${days}d ${remHours}h ago` : `${days}d ago`;
    }
    const months = Math.floor(days / 30);
    const remDays = days % 30;
    return remDays > 0 ? `${months}mo ${remDays}d ago` : `${months}mo ago`;
}

/**
 * Resolve Firebase vehicle_types / vehicle_type to display names.
 * @param {object} driver
 * @param {Record<string,string>|Map|Array<{value:any,label:string}>|null} vehicleTypeLookup
 */
export function resolveDriverVehicleTypeLabels(driver, vehicleTypeLookup = null) {
    const lookup = new Map();
    if (vehicleTypeLookup instanceof Map) {
        vehicleTypeLookup.forEach((label, id) => lookup.set(String(id), label));
    } else if (Array.isArray(vehicleTypeLookup)) {
        vehicleTypeLookup.forEach((item) => {
            if (item == null) return;
            const id = item.value ?? item.id;
            const label = item.label ?? item.name;
            if (id != null && label) {
                lookup.set(String(id), String(label));
            }
        });
    } else if (vehicleTypeLookup && typeof vehicleTypeLookup === 'object') {
        Object.keys(vehicleTypeLookup).forEach((id) => {
            lookup.set(String(id), String(vehicleTypeLookup[id]));
        });
    }

    const ids = [];
    if (Array.isArray(driver?.vehicle_types)) {
        driver.vehicle_types.forEach((id) => {
            if (id != null && id !== '') ids.push(String(id));
        });
    }
    if (driver?.vehicle_type != null && driver.vehicle_type !== '') {
        const single = String(driver.vehicle_type);
        if (!ids.includes(single)) ids.push(single);
    }

    const names = ids
        .map((id) => lookup.get(id) || null)
        .filter(Boolean);

    // Firebase sometimes stores a display string separately.
    if (names.length === 0 && driver?.vehicle_type_name) {
        names.push(String(driver.vehicle_type_name));
    }

    return {
        ids,
        names,
        label: names.length ? names.join(', ') : (ids.length ? ids.join(', ') : null),
    };
}

export function normalizeIdList(value) {
    if (value == null || value === '' || value === 'all') {
        return [];
    }
    if (Array.isArray(value)) {
        return value.map((v) => String(v)).filter(Boolean);
    }
    return [String(value)];
}

export function matchesSearch(driver, searchQuery, firebaseKey = null) {
    const q = String(searchQuery || '').trim().toLowerCase();
    if (!q) {
        return true;
    }

    const haystacks = [
        driver?.name,
        driver?.mobile,
        driver?.id,
        firebaseKey,
    ]
        .filter((v) => v != null && v !== '')
        .map((v) => String(v).toLowerCase());

    return haystacks.some((h) => h.includes(q));
}

export function matchesVehicleType(driver, selectedVehicleTypes) {
    const selected = normalizeIdList(selectedVehicleTypes);
    if (selected.length === 0) {
        return true;
    }

    const driverTypes = [];
    if (Array.isArray(driver?.vehicle_types)) {
        driverTypes.push(...driver.vehicle_types.map((t) => String(t)));
    }
    if (driver?.vehicle_type != null && driver.vehicle_type !== '') {
        driverTypes.push(String(driver.vehicle_type));
    }

    if (driverTypes.length === 0) {
        return false;
    }

    return selected.some((id) => driverTypes.includes(id));
}

export function matchesServiceLocation(driver, serviceLocationId, allowedLocationIds = []) {
    const driverLoc = driver?.service_location_id;
    if (driverLoc == null || driverLoc === '') {
        return false;
    }

    const allowed = normalizeIdList(allowedLocationIds);
    if (allowed.length > 0 && !allowed.includes(String(driverLoc))) {
        return false;
    }

    const selected = normalizeIdList(serviceLocationId);
    if (selected.length === 0) {
        return true;
    }

    return selected.includes(String(driverLoc));
}

export function matchesModes(driver, modes, statusHints = {}) {
    const selected = Array.isArray(modes) ? modes.filter(Boolean) : [];
    if (selected.length === 0) {
        return true;
    }
    const status = classifyDriverStatus(driver, statusHints);
    return selected.includes(status);
}

/**
 * Great-circle distance in km.
 */
export function haversineKm(lat1, lon1, lat2, lon2) {
    const toRad = (deg) => (deg * Math.PI) / 180;
    const r = 6371;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a = Math.sin(dLat / 2) ** 2
        + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
    return 2 * r * Math.asin(Math.min(1, Math.sqrt(a)));
}

/**
 * @param {{lat:number,lng:number}|null} latLng
 * @param {{lat:number,lng:number}|null} center
 * @param {number|null} radiusKm
 */
export function matchesRadius(latLng, center, radiusKm) {
    if (!center || !Number.isFinite(center.lat) || !Number.isFinite(center.lng)) {
        return false;
    }
    if (!latLng || !Number.isFinite(latLng.lat) || !Number.isFinite(latLng.lng)) {
        return false;
    }
    const radius = Number(radiusKm);
    if (!Number.isFinite(radius) || radius <= 0) {
        return false;
    }
    return haversineKm(center.lat, center.lng, latLng.lat, latLng.lng) <= radius;
}

/**
 * @param {object} driver
 * @param {object} filters
 * @param {string[]} filters.modes
 * @param {string|string[]|null} filters.vehicleTypeIds
 * @param {string|null} filters.serviceLocationId
 * @param {Array} filters.allowedLocationIds
 * @param {string} filters.searchQuery
 * @param {{lat:number,lng:number}|null} filters.center
 * @param {number|null} filters.radiusKm
 * @param {string|null} firebaseKey
 * @param {{lat:number,lng:number}|null} latLng pre-resolved position
 */
export function matchesFilters(driver, filters = {}, firebaseKey = null, latLng = null, statusHints = {}) {
    const {
        modes = [],
        vehicleTypeIds = null,
        serviceLocationId = null,
        allowedLocationIds = [],
        searchQuery = '',
        center = null,
        radiusKm = null,
    } = filters;

    const position = latLng || resolveDriverLatLng(driver);

    return matchesRadius(position, center, radiusKm)
        && matchesModes(driver, modes, statusHints)
        && matchesVehicleType(driver, vehicleTypeIds)
        && matchesServiceLocation(driver, serviceLocationId, allowedLocationIds)
        && matchesSearch(driver, searchQuery, firebaseKey);
}

export function decodeGeohash(geohash) {
    if (!geohash || typeof geohash !== 'string') {
        return null;
    }

    const BASE32 = '0123456789bcdefghjkmnpqrstuvwxyz';
    const BITS = [16, 8, 4, 2, 1];
    let isEven = true;
    let latMin = -90;
    let latMax = 90;
    let lonMin = -180;
    let lonMax = 180;

    for (let i = 0; i < geohash.length; i++) {
        const cd = BASE32.indexOf(geohash.charAt(i));
        if (cd < 0) {
            return null;
        }
        for (let j = 0; j < 5; j++) {
            const mask = BITS[j];
            if (isEven) {
                const lonMid = (lonMin + lonMax) / 2;
                if (cd & mask) {
                    lonMin = lonMid;
                } else {
                    lonMax = lonMid;
                }
            } else {
                const latMid = (latMin + latMax) / 2;
                if (cd & mask) {
                    latMin = latMid;
                } else {
                    latMax = latMid;
                }
            }
            isEven = !isEven;
        }
    }

    return {
        lat: (latMin + latMax) / 2,
        lon: (lonMin + lonMax) / 2,
    };
}

/**
 * Prefer geohash `g`, then Firebase `l` (array or RTDB object {0,1}), then lat/lng fields.
 */
export function resolveDriverLatLng(driver) {
    const fromHash = decodeGeohash(driver?.g);
    if (fromHash && Number.isFinite(fromHash.lat) && Number.isFinite(fromHash.lon)) {
        return { lat: fromHash.lat, lng: fromHash.lon };
    }

    // Driver app writes l as { "0": lat, "1": lng }; older data may be a real array.
    const loc = driver?.l;
    if (loc != null) {
        let lat;
        let lng;
        if (Array.isArray(loc) && loc.length >= 2) {
            lat = parseFloat(loc[0]);
            lng = parseFloat(loc[1]);
        } else if (typeof loc === 'object') {
            lat = parseFloat(loc[0] ?? loc['0']);
            lng = parseFloat(loc[1] ?? loc['1']);
        }
        if (Number.isFinite(lat) && Number.isFinite(lng)) {
            return { lat, lng };
        }
    }

    const lat = parseFloat(driver?.lat ?? driver?.latitude);
    const lng = parseFloat(driver?.lng ?? driver?.longitude ?? driver?.lon);
    if (Number.isFinite(lat) && Number.isFinite(lng)) {
        return { lat, lng };
    }

    return null;
}

/**
 * Returns icon stem without .png (e.g. bike-online) for /image/map/{stem}.png
 */
export function statusIconStem(baseIcon, status) {
    const base = String(baseIcon || 'car').replace(/\.png$/i, '');
    if (status === 'online') {
        return `${base}-online`;
    }
    if (status === 'onride' || status === 'stuck') {
        // Reuse onride icon; list badge distinguishes stuck
        return `${base}-onride`;
    }
    return base;
}

export function statusIconUrl(baseIcon, status, baseUrl = '') {
    const stem = statusIconStem(baseIcon, status);
    const prefix = baseUrl ? String(baseUrl).replace(/\/$/, '') : '';
    return `${prefix}/image/map/${stem}.png`;
}

export function formatDriverUpdatedAt(updatedAt) {
    if (updatedAt == null || updatedAt === '') {
        return null;
    }
    // Firebase SERVER_TIMESTAMP is ms; sometimes seconds
    let ms = Number(updatedAt);
    if (!Number.isFinite(ms)) {
        return null;
    }
    if (ms < 1e12) {
        ms *= 1000;
    }
    try {
        return new Date(ms).toLocaleString();
    } catch (_) {
        return null;
    }
}

/** Max rows shown in the side list (map still shows all in-radius matches up to mapCap). */
export const GODS_EYE_LIST_CAP = 100;

/** Soft cap for map markers inside radius (protects the browser). */
export const GODS_EYE_MAP_CAP = 500;

/**
 * Build a list of visible driver rows + status counts from a Firebase drivers snapshot object.
 * Requires filters.center + filters.radiusKm — otherwise returns empty (no full-fleet dump).
 */
/**
 * @param {object} driversObj
 * @param {object} filters
 * @param {{
 *   appFor?: string|null,
 *   baseUrl?: string,
 *   listCap?: number,
 *   mapCap?: number,
 *   openTripDriverIds?: Set<number|string>|number[]|string[]|null,
 *   vehicleTypeLookup?: Record<string,string>|Array<{value:any,label:string}>|null,
 * }} [options]
 * openTripDriverIds: when provided, active+unavailable drivers not in the set are "stuck".
 */
export function processDriversSnapshot(driversObj, filters, options = {}) {
    const {
        appFor = null,
        baseUrl = '',
        listCap = GODS_EYE_LIST_CAP,
        mapCap = GODS_EYE_MAP_CAP,
        openTripDriverIds = null,
        vehicleTypeLookup = null,
    } = options;
    const list = [];
    const counts = { online: 0, onride: 0, stuck: 0, offline: 0, total: 0, truncated: false };

    if (!driversObj || typeof driversObj !== 'object') {
        return { list, counts, markers: {} };
    }

    // Require an address center + radius before showing anyone.
    if (!filters?.center || !Number.isFinite(Number(filters.radiusKm))) {
        return { list, counts, markers: {} };
    }

    const openTripSet = openTripDriverIds == null
        ? null
        : new Set(
            (openTripDriverIds instanceof Set
                ? [...openTripDriverIds]
                : openTripDriverIds
            ).map((id) => String(id))
        );

    const markers = {};
    const matched = [];

    Object.keys(driversObj).forEach((firebaseKey) => {
        const driver = driversObj[firebaseKey];
        if (!driver) {
            return;
        }

        const latLng = resolveDriverLatLng(driver);
        if (!latLng) {
            return;
        }

        const driverId = driver.id != null ? String(driver.id) : null;
        let hasOpenTrip;
        if (openTripSet == null || driverId == null) {
            hasOpenTrip = null;
        } else {
            hasOpenTrip = openTripSet.has(driverId);
        }
        const statusHints = { hasOpenTrip };

        if (!matchesFilters(driver, filters, firebaseKey, latLng, statusHints)) {
            return;
        }

        const status = classifyDriverStatus(driver, statusHints);
        const distanceKm = haversineKm(
            filters.center.lat,
            filters.center.lng,
            latLng.lat,
            latLng.lng
        );
        const ageSeconds = firebaseUpdatedAtAgeSeconds(driver.updated_at);

        matched.push({
            driver,
            firebaseKey,
            latLng,
            status,
            distanceKm,
            ageSeconds,
        });
    });

    matched.sort((a, b) => a.distanceKm - b.distanceKm);

    matched.forEach((item, index) => {
        counts[item.status] = (counts[item.status] || 0) + 1;
        counts.total += 1;

        const { driver, firebaseKey, latLng, status, distanceKm, ageSeconds } = item;
        const id = driver.id != null ? driver.id : firebaseKey;
        const mobileDisplay = appFor === 'demo' ? '*********' : (driver.mobile || '');
        const iconStem = statusIconStem(driver.vehicle_type_icon || 'car', status);
        const vehicleTypes = resolveDriverVehicleTypeLabels(driver, vehicleTypeLookup);

        const row = {
            id,
            firebaseKey,
            name: driver.name || '—',
            mobile: mobileDisplay,
            mobileRaw: driver.mobile || '',
            status,
            vehicleTypeIcon: driver.vehicle_type_icon || 'car',
            vehicleTypeIds: vehicleTypes.ids,
            vehicleTypeNames: vehicleTypes.names,
            vehicleTypeLabel: vehicleTypes.label,
            type_icon: iconStem,
            lat: latLng.lat,
            lng: latLng.lng,
            distanceKm,
            service_location_id: driver.service_location_id,
            updated_at: driver.updated_at ?? null,
            updatedAtLabel: formatDriverUpdatedAt(driver.updated_at),
            ageSeconds,
            ageLabel: formatAgeLabel(ageSeconds),
            is_active: driver.is_active,
            is_available: driver.is_available,
            iconUrl: statusIconUrl(driver.vehicle_type_icon || 'car', status, baseUrl),
        };

        if (index < listCap) {
            list.push(row);
        }
        if (index < mapCap) {
            markers[id] = {
                lat: row.lat,
                lng: row.lng,
                type_icon: row.type_icon,
                name: row.name,
                mobile: row.mobile,
                status: row.status,
                id: row.id,
            };
        }
    });

    if (matched.length > listCap || matched.length > mapCap) {
        counts.truncated = true;
    }

    return { list, counts, markers };
}

export function useGodsEyeDrivers() {
    return {
        classifyDriverStatus,
        isFirebaseAvailable,
        firebaseUpdatedAtAgeSeconds,
        formatAgeLabel,
        resolveDriverVehicleTypeLabels,
        matchesFilters,
        matchesSearch,
        matchesVehicleType,
        matchesServiceLocation,
        matchesModes,
        matchesRadius,
        haversineKm,
        resolveDriverLatLng,
        decodeGeohash,
        statusIconStem,
        statusIconUrl,
        formatDriverUpdatedAt,
        processDriversSnapshot,
        normalizeIdList,
        GODS_EYE_LIST_CAP,
        GODS_EYE_MAP_CAP,
    };
}

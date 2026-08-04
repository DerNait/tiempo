/**
 * Duration and clock formatting. Kept free of Vue so it can be unit tested and
 * reused by any component.
 */

export function formatDuration(seconds: number): string {
    const total = Math.max(0, Math.floor(seconds));
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);

    if (hours === 0) {
        return `${minutes}m`;
    }

    return minutes === 0 ? `${hours}h` : `${hours}h ${minutes}m`;
}

/**
 * `H:MM:SS` for the live timer, where the running hour count may exceed 24.
 */
export function formatStopwatch(seconds: number): string {
    const total = Math.max(0, Math.floor(seconds));
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const secs = total % 60;

    return `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

export function minutesToLabel(minutes: number): string {
    return formatDuration(Math.max(0, Math.round(minutes)) * 60);
}

export function formatPercent(ratio: number | null): string {
    if (ratio === null || Number.isNaN(ratio)) {
        return '—';
    }

    return `${Math.round(ratio * 100)}%`;
}

/**
 * Wall clock time of an instant in a given IANA timezone.
 */
export function formatTimeInZone(iso: string, timezone: string): string {
    return new Intl.DateTimeFormat('es-GT', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        timeZone: timezone,
    }).format(new Date(iso));
}

export function formatDateInZone(iso: string, timezone: string): string {
    return new Intl.DateTimeFormat('es-GT', {
        weekday: 'short',
        day: '2-digit',
        month: 'short',
        timeZone: timezone,
    }).format(new Date(iso));
}

/**
 * `YYYY-MM-DDTHH:mm` for `<input type="datetime-local">`, rendered in the
 * user's timezone rather than the browser's.
 */
export function toLocalInputValue(iso: string, timezone: string): string {
    const parts = new Intl.DateTimeFormat('en-CA', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        timeZone: timezone,
    }).formatToParts(new Date(iso));

    const get = (type: string) => parts.find((part) => part.type === type)?.value ?? '00';

    return `${get('year')}-${get('month')}-${get('day')}T${get('hour')}:${get('minute')}`;
}

/**
 * Inverse of `toLocalInputValue`: interpret the wall clock the user typed as
 * being in their timezone and return the matching UTC instant.
 */
export function fromLocalInputValue(value: string, timezone: string): string {
    // Start from the same wall clock read as UTC, then correct by the offset
    // that timezone had at that moment.
    const naive = new Date(`${value}:00Z`);
    const offset = zoneOffsetMs(naive, timezone);

    return new Date(naive.getTime() - offset).toISOString();
}

/**
 * Offset of a timezone at a given instant, in milliseconds.
 */
function zoneOffsetMs(instant: Date, timezone: string): number {
    const parts = new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
        timeZone: timezone,
    }).formatToParts(instant);

    const get = (type: string) => Number(parts.find((part) => part.type === type)?.value ?? 0);
    const asUtc = Date.UTC(
        get('year'),
        get('month') - 1,
        get('day'),
        get('hour') % 24,
        get('minute'),
        get('second'),
    );

    return asUtc - instant.getTime();
}

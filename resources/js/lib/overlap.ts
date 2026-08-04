/**
 * Client-side overlap detection. The server is authoritative — it rejects
 * overlaps too — but catching them before saving turns a 422 into inline
 * feedback while the user is still editing.
 */

export interface Interval {
    id?: number;
    started_at: string;
    ended_at: string | null;
}

const FOREVER = 8.64e15;

function bounds(interval: Interval): [number, number] {
    const start = new Date(interval.started_at).getTime();
    const end = interval.ended_at === null ? FOREVER : new Date(interval.ended_at).getTime();

    return [start, end];
}

export function overlaps(a: Interval, b: Interval): boolean {
    const [aStart, aEnd] = bounds(a);
    const [bStart, bEnd] = bounds(b);

    // Half-open ranges: touching at a boundary is not an overlap.
    return aStart < bEnd && bStart < aEnd;
}

/**
 * First existing entry the candidate collides with, ignoring itself.
 */
export function findOverlap(candidate: Interval, existing: Interval[]): Interval | null {
    for (const entry of existing) {
        if (candidate.id !== undefined && entry.id === candidate.id) {
            continue;
        }

        if (overlaps(candidate, entry)) {
            return entry;
        }
    }

    return null;
}

export function isValidRange(candidate: Interval): boolean {
    if (candidate.ended_at === null) {
        return true;
    }

    return new Date(candidate.ended_at).getTime() > new Date(candidate.started_at).getTime();
}

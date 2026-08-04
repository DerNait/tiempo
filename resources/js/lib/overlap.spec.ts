import { describe, expect, it } from 'vitest';
import { findOverlap, isValidRange, overlaps } from './overlap';

const entry = (id: number, start: string, end: string | null) => ({
    id,
    started_at: start,
    ended_at: end,
});

describe('overlaps', () => {
    it('detects a partial collision', () => {
        expect(
            overlaps(
                entry(1, '2026-08-04T10:00:00Z', '2026-08-04T11:00:00Z'),
                entry(2, '2026-08-04T10:30:00Z', '2026-08-04T11:30:00Z'),
            ),
        ).toBe(true);
    });

    it('allows ranges that only touch at the boundary', () => {
        expect(
            overlaps(
                entry(1, '2026-08-04T10:00:00Z', '2026-08-04T11:00:00Z'),
                entry(2, '2026-08-04T11:00:00Z', '2026-08-04T12:00:00Z'),
            ),
        ).toBe(false);
    });

    it('treats an open entry as running forever', () => {
        expect(
            overlaps(
                entry(1, '2026-08-04T10:00:00Z', null),
                entry(2, '2026-08-04T23:00:00Z', '2026-08-05T01:00:00Z'),
            ),
        ).toBe(true);
    });
});

describe('findOverlap', () => {
    const existing = [
        entry(1, '2026-08-04T08:00:00Z', '2026-08-04T09:00:00Z'),
        entry(2, '2026-08-04T12:00:00Z', '2026-08-04T13:00:00Z'),
    ];

    it('returns the colliding entry', () => {
        const conflict = findOverlap(entry(99, '2026-08-04T12:30:00Z', '2026-08-04T14:00:00Z'), existing);

        expect(conflict?.id).toBe(2);
    });

    it('ignores the entry being edited', () => {
        const conflict = findOverlap(entry(2, '2026-08-04T12:00:00Z', '2026-08-04T13:30:00Z'), existing);

        expect(conflict).toBeNull();
    });

    it('returns null when the slot is free', () => {
        expect(findOverlap(entry(99, '2026-08-04T09:00:00Z', '2026-08-04T12:00:00Z'), existing)).toBeNull();
    });
});

describe('isValidRange', () => {
    it('rejects an end before its start', () => {
        expect(isValidRange(entry(1, '2026-08-04T10:00:00Z', '2026-08-04T09:00:00Z'))).toBe(false);
    });

    it('rejects a zero length range', () => {
        expect(isValidRange(entry(1, '2026-08-04T10:00:00Z', '2026-08-04T10:00:00Z'))).toBe(false);
    });

    it('accepts an open range', () => {
        expect(isValidRange(entry(1, '2026-08-04T10:00:00Z', null))).toBe(true);
    });
});

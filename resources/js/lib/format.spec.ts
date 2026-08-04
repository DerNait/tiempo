import { describe, expect, it } from 'vitest';
import {
    formatDuration,
    formatPercent,
    formatStopwatch,
    formatTimeInZone,
    fromLocalInputValue,
    minutesToLabel,
    toLocalInputValue,
} from './format';

describe('formatDuration', () => {
    it('drops the hour part below an hour', () => {
        expect(formatDuration(0)).toBe('0m');
        expect(formatDuration(59)).toBe('0m');
        expect(formatDuration(90)).toBe('1m');
        expect(formatDuration(3540)).toBe('59m');
    });

    it('omits zero minutes on whole hours', () => {
        expect(formatDuration(3600)).toBe('1h');
        expect(formatDuration(7200)).toBe('2h');
    });

    it('combines hours and minutes', () => {
        expect(formatDuration(3660)).toBe('1h 1m');
        expect(formatDuration(31_320)).toBe('8h 42m');
    });

    it('never reports negative time', () => {
        expect(formatDuration(-500)).toBe('0m');
    });
});

describe('formatStopwatch', () => {
    it('pads minutes and seconds', () => {
        expect(formatStopwatch(0)).toBe('0:00:00');
        expect(formatStopwatch(5010)).toBe('1:23:30');
    });

    it('lets the hour count grow past 24', () => {
        expect(formatStopwatch(90_000)).toBe('25:00:00');
    });
});

describe('minutesToLabel', () => {
    it('renders budget minutes as readable durations', () => {
        expect(minutesToLabel(600)).toBe('10h');
        expect(minutesToLabel(120)).toBe('2h');
        expect(minutesToLabel(95)).toBe('1h 35m');
    });
});

describe('formatPercent', () => {
    it('renders a dash when there is nothing to compare', () => {
        expect(formatPercent(null)).toBe('—');
    });

    it('rounds to whole percent', () => {
        expect(formatPercent(0.7612)).toBe('76%');
    });
});

describe('timezone conversion', () => {
    const zone = 'America/Guatemala'; // UTC-6 year round.

    it('renders an instant as local wall clock', () => {
        expect(formatTimeInZone('2026-08-04T16:02:00Z', zone)).toBe('10:02');
    });

    it('round-trips a datetime-local value through the user timezone', () => {
        const iso = '2026-08-04T16:02:00Z';
        const local = toLocalInputValue(iso, zone);

        expect(local).toBe('2026-08-04T10:02');
        expect(fromLocalInputValue(local, zone)).toBe(iso.replace('Z', '.000Z'));
    });

    it('treats typed wall clock as local, not browser time', () => {
        expect(fromLocalInputValue('2026-08-04T00:00', zone)).toBe('2026-08-04T06:00:00.000Z');
    });
});

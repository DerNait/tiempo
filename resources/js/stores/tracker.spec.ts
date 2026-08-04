import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useTrackerStore } from './tracker';
import type { Category, Status } from '@/lib/types';

const category: Category = {
    id: 7,
    name: 'Proyecto de Unity',
    slug: 'proyecto-de-unity',
    group_name: 'Proyectos y aprendizaje',
    icon: '🎮',
    color: '#a855f7',
    sort_order: 6,
    is_active: true,
    is_favorite: true,
};

function statusFixture(overrides: Partial<Status> = {}): Status {
    return {
        server_time: '2026-08-04T16:00:00+00:00',
        server_time_unix: 1_785_859_200,
        timezone: 'America/Guatemala',
        current_entry: null,
        today: { date: '2026-08-04', tracked_seconds: 3600, elapsed_seconds: 7200, coverage: 0.5 },
        week: { week_start: '2026-08-03', tracked_seconds: 7200, elapsed_seconds: 14_400, coverage: 0.5 },
        audit: null,
        favorites: [category],
        ...overrides,
    };
}

function jsonResponse(body: unknown, status = 200): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        headers: { get: () => 'application/json' },
        json: async () => body,
    } as unknown as Response;
}

describe('tracker store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-08-04T16:00:00Z'));
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('counts elapsed seconds of the open entry from the clock tick', () => {
        const tracker = useTrackerStore();

        tracker.status = statusFixture({
            current_entry: {
                id: 1,
                category_id: category.id,
                category,
                description: null,
                started_at: '2026-08-04T15:00:00Z',
                ended_at: null,
                is_open: true,
                duration_seconds: 3600,
                source: 'web',
            },
        });

        tracker.advanceClock(new Date('2026-08-04T16:00:00Z').getTime());
        expect(tracker.elapsedSeconds).toBe(3600);

        tracker.advanceClock(new Date('2026-08-04T16:00:30Z').getTime());
        expect(tracker.elapsedSeconds).toBe(3630);
    });

    it('reports zero elapsed time when nothing is running', () => {
        const tracker = useTrackerStore();
        tracker.status = statusFixture();

        expect(tracker.elapsedSeconds).toBe(0);
    });

    it('shows the new activity before the server answers', async () => {
        const tracker = useTrackerStore();
        tracker.status = statusFixture();

        let resolve: (value: Response) => void = () => undefined;
        const pendingResponse = new Promise<Response>((r) => {
            resolve = r;
        });
        vi.stubGlobal('fetch', vi.fn().mockReturnValue(pendingResponse));

        const promise = tracker.startActivity(category);

        expect(tracker.currentEntry?.category_id).toBe(category.id);
        expect(tracker.pending).toBe(true);

        resolve(
            jsonResponse(
                statusFixture({
                    current_entry: {
                        id: 42,
                        category_id: category.id,
                        category,
                        description: null,
                        started_at: '2026-08-04T16:00:00Z',
                        ended_at: null,
                        is_open: true,
                        duration_seconds: 0,
                        source: 'web',
                    },
                }),
            ),
        );

        await promise;

        expect(tracker.currentEntry?.id).toBe(42);
        expect(tracker.pending).toBe(false);
        expect(tracker.error).toBeNull();
    });

    it('rolls back and surfaces the message when starting fails', async () => {
        const tracker = useTrackerStore();
        const original = statusFixture();
        tracker.status = original;

        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(jsonResponse({ message: 'El rango se solapa con otra actividad.' }, 422)),
        );

        await expect(tracker.startActivity(category)).rejects.toThrow();

        expect(tracker.currentEntry).toBeNull();
        expect(tracker.error).toBe('El rango se solapa con otra actividad.');
        expect(tracker.pending).toBe(false);
    });

    it('clears the running activity when stopping succeeds', async () => {
        const tracker = useTrackerStore();
        tracker.status = statusFixture({
            current_entry: {
                id: 1,
                category_id: category.id,
                category,
                description: null,
                started_at: '2026-08-04T15:00:00Z',
                ended_at: null,
                is_open: true,
                duration_seconds: 3600,
                source: 'web',
            },
        });

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(statusFixture())));

        await tracker.stopActivity();

        expect(tracker.currentEntry).toBeNull();
    });

    it('restores the running activity when stopping fails', async () => {
        const tracker = useTrackerStore();
        const running = statusFixture({
            current_entry: {
                id: 1,
                category_id: category.id,
                category,
                description: null,
                started_at: '2026-08-04T15:00:00Z',
                ended_at: null,
                is_open: true,
                duration_seconds: 3600,
                source: 'web',
            },
        });
        tracker.status = running;

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ message: 'Falló' }, 500)));

        await expect(tracker.stopActivity()).rejects.toThrow();

        expect(tracker.currentEntry?.id).toBe(1);
        expect(tracker.error).toBe('Falló');
    });

    it('groups categories for the expandable picker', () => {
        const tracker = useTrackerStore();
        tracker.categories = [
            category,
            { ...category, id: 8, name: 'Aprendizaje', slug: 'aprendizaje' },
            { ...category, id: 9, name: 'Sueño', slug: 'sueno', group_name: 'Salud y mantenimiento' },
            { ...category, id: 10, name: 'Vieja', slug: 'vieja', is_active: false },
        ];

        expect(tracker.groupedCategories).toEqual([
            { name: 'Proyectos y aprendizaje', items: [tracker.categories[0], tracker.categories[1]] },
            { name: 'Salud y mantenimiento', items: [tracker.categories[2]] },
        ]);
    });
});

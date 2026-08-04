import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { ApiError, api } from '@/lib/api';
import type { Category, Settings, Status, TimeEntry } from '@/lib/types';

/**
 * Owns the "what am I doing right now" state. Starting an activity updates the
 * UI immediately and rolls back to the previous status if the server refuses.
 */
export const useTrackerStore = defineStore('tracker', () => {
    const status = ref<Status | null>(null);
    const categories = ref<Category[]>([]);
    const settings = ref<Settings | null>(null);
    const loading = ref(false);
    const pending = ref(false);
    const error = ref<string | null>(null);
    const tick = ref(Date.now());

    const timezone = computed(() => status.value?.timezone ?? settings.value?.timezone ?? 'America/Guatemala');
    const currentEntry = computed(() => status.value?.current_entry ?? null);

    const favorites = computed(() =>
        status.value?.favorites ?? categories.value.filter((category) => category.is_favorite && category.is_active),
    );

    const activeCategories = computed(() => categories.value.filter((category) => category.is_active));

    const groupedCategories = computed(() => {
        const groups = new Map<string, Category[]>();

        for (const category of activeCategories.value) {
            const key = category.group_name ?? 'Sin grupo';
            groups.set(key, [...(groups.get(key) ?? []), category]);
        }

        return [...groups.entries()].map(([name, items]) => ({ name, items }));
    });

    /**
     * Seconds the current activity has been running, recomputed from `tick`
     * so the timer stays live without re-fetching.
     */
    const elapsedSeconds = computed(() => {
        const entry = currentEntry.value;

        if (!entry) {
            return 0;
        }

        return Math.max(0, Math.floor((tick.value - new Date(entry.started_at).getTime()) / 1000));
    });

    function advanceClock(now = Date.now()): void {
        tick.value = now;
    }

    async function loadStatus(): Promise<void> {
        status.value = await api.get<Status>('/api/status');
        advanceClock();
    }

    async function loadCategories(): Promise<void> {
        const response = await api.get<{ data: Category[] }>('/api/categories?include_archived=1');
        categories.value = response.data;
    }

    async function loadSettings(): Promise<void> {
        const response = await api.get<{ user: Settings }>('/api/settings');
        settings.value = response.user;
        applyAccent(response.user.accent_color);
    }

    async function bootstrap(): Promise<void> {
        loading.value = true;
        error.value = null;

        try {
            await Promise.all([loadStatus(), loadCategories(), loadSettings()]);
        } catch (caught) {
            error.value = messageFrom(caught);
            throw caught;
        } finally {
            loading.value = false;
        }
    }

    /**
     * One tap: show the new activity right away, then reconcile with the
     * server. On failure the previous status is restored and the error is
     * surfaced instead of leaving a phantom activity on screen.
     */
    async function startActivity(category: Category, description: string | null = null): Promise<void> {
        const previous = status.value;
        error.value = null;
        pending.value = true;

        if (previous) {
            const optimistic: TimeEntry = {
                id: -1,
                category_id: category.id,
                category,
                description,
                started_at: new Date().toISOString(),
                ended_at: null,
                is_open: true,
                duration_seconds: 0,
                source: 'web',
            };

            status.value = { ...previous, current_entry: optimistic };
            advanceClock();
        }

        try {
            status.value = await api.post<Status>('/api/tracking/start', {
                category_id: category.id,
                description,
                source: 'web',
            });
            advanceClock();
        } catch (caught) {
            status.value = previous;
            error.value = messageFrom(caught);
            throw caught;
        } finally {
            pending.value = false;
        }
    }

    async function stopActivity(): Promise<void> {
        const previous = status.value;
        error.value = null;
        pending.value = true;

        if (previous) {
            status.value = { ...previous, current_entry: null };
        }

        try {
            status.value = await api.post<Status>('/api/tracking/stop');
            advanceClock();
        } catch (caught) {
            status.value = previous;
            error.value = messageFrom(caught);
            throw caught;
        } finally {
            pending.value = false;
        }
    }

    function applyAccent(color: string): void {
        if (typeof document !== 'undefined') {
            document.documentElement.style.setProperty('--accent', color);
        }
    }

    async function updateSettings(payload: Partial<Settings> & Record<string, unknown>): Promise<void> {
        const response = await api.patch<{ user: Settings }>('/api/settings', payload);
        settings.value = response.user;
        applyAccent(response.user.accent_color);
    }

    function clearError(): void {
        error.value = null;
    }

    return {
        status,
        categories,
        settings,
        loading,
        pending,
        error,
        tick,
        timezone,
        currentEntry,
        favorites,
        activeCategories,
        groupedCategories,
        elapsedSeconds,
        advanceClock,
        bootstrap,
        loadStatus,
        loadCategories,
        loadSettings,
        startActivity,
        stopActivity,
        updateSettings,
        applyAccent,
        clearError,
    };
});

export function messageFrom(caught: unknown): string {
    if (caught instanceof ApiError) {
        return caught.message;
    }

    return 'No se pudo conectar con el servidor. Revisa tu conexión.';
}

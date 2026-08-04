<script setup lang="ts">
import { computed } from 'vue';
import { formatDuration, formatTimeInZone, fromLocalInputValue } from '@/lib/format';
import type { Gap, TimeEntry } from '@/lib/types';

const props = defineProps<{
    date: string;
    entries: TimeEntry[];
    gaps: Gap[];
    timezone: string;
}>();

interface Segment {
    key: string;
    kind: 'entry' | 'gap';
    label: string;
    color: string;
    offset: number;
    size: number;
    title: string;
}

const DAY_SECONDS = 86_400;

function dayStartMs(): number {
    // Midnight of the rendered date in the user's timezone, as an instant.
    return new Date(fromLocalInputValue(`${props.date}T00:00`, props.timezone)).getTime();
}

const segments = computed<Segment[]>(() => {
    const start = dayStartMs();

    const fromRange = (
        kind: Segment['kind'],
        key: string,
        fromIso: string,
        toIso: string | null,
        color: string,
        label: string,
    ): Segment => {
        const from = new Date(fromIso).getTime();
        const to = toIso === null ? Date.now() : new Date(toIso).getTime();
        const offset = Math.max(0, (from - start) / 1000);
        const size = Math.max(0, Math.min(DAY_SECONDS - offset, (to - from) / 1000));

        return {
            key,
            kind,
            label,
            color,
            offset: (offset / DAY_SECONDS) * 100,
            size: (size / DAY_SECONDS) * 100,
            title: `${label} · ${formatTimeInZone(fromIso, props.timezone)}–${
                toIso === null ? 'ahora' : formatTimeInZone(toIso, props.timezone)
            } · ${formatDuration(size)}`,
        };
    };

    return [
        ...props.entries.map((entry) =>
            fromRange(
                'entry',
                `entry-${entry.id}`,
                entry.started_at,
                entry.ended_at,
                entry.category?.color ?? '#8b8b9e',
                entry.category?.name ?? 'Sin categoría',
            ),
        ),
        ...props.gaps.map((gap, index) =>
            fromRange('gap', `gap-${index}`, gap.start, gap.end, 'transparent', 'Sin registrar'),
        ),
    ].filter((segment) => segment.size > 0);
});

const hours = [0, 6, 12, 18, 24];
</script>

<template>
    <div class="card">
        <h2 class="text-sm font-medium text-ink-200">Línea del día</h2>

        <div class="relative mt-4 h-10 overflow-hidden rounded-lg bg-ink-900 ring-1 ring-ink-700">
            <!-- Untracked stretches are drawn as hatching, not just empty space. -->
            <div v-for="segment in segments" :key="segment.key" class="absolute inset-y-0" :title="segment.title"
                :style="{
                    left: `${segment.offset}%`,
                    width: `${Math.max(segment.size, 0.4)}%`,
                    backgroundColor: segment.kind === 'entry' ? segment.color : undefined,
                    backgroundImage:
                        segment.kind === 'gap'
                            ? 'repeating-linear-gradient(45deg, #22222f 0 4px, transparent 4px 8px)'
                            : undefined,
                }"></div>
        </div>

        <div class="mt-1.5 flex justify-between text-[11px] tabular-nums text-ink-400">
            <span v-for="hour in hours" :key="hour">{{ String(hour).padStart(2, '0') }}:00</span>
        </div>

        <p class="mt-3 text-xs text-ink-400">
            Las franjas rayadas son tiempo sin registrar. No son un error: solo muestran lo que aún no observaste.
        </p>
    </div>
</template>

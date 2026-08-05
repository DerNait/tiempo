<script setup lang="ts">
import FaIcon from '@/components/FaIcon.vue';
import { computed } from 'vue';
import { formatStopwatch, formatTimeInZone } from '@/lib/format';
import { useTrackerStore } from '@/stores/tracker';

const tracker = useTrackerStore();

const entry = computed(() => tracker.currentEntry);
const startedLabel = computed(() =>
    entry.value ? formatTimeInZone(entry.value.started_at, tracker.timezone) : '',
);

defineEmits<{ (event: 'stop'): void }>();
</script>

<template>
    <section class="card" aria-live="polite">
        <template v-if="entry">
            <div class="flex items-start gap-3">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl text-xl"
                    :style="{
                        backgroundColor: (entry.category?.color ?? '#8b8b9e') + '22',
                        border: `1px solid ${(entry.category?.color ?? '#8b8b9e')}55`,
                    }" aria-hidden="true">{{ entry.category?.icon ?? '⏱️' }}</span>

                <div class="min-w-0 flex-1">
                    <p class="text-xs tracking-wide text-ink-400 uppercase">En curso desde {{ startedLabel }}</p>
                    <h1 class="truncate text-lg font-semibold text-ink-100">{{ entry.category?.name }}</h1>
                    <p v-if="entry.description" class="truncate text-sm text-ink-300">{{ entry.description }}</p>
                </div>
            </div>

            <p class="mt-4 font-mono text-4xl tabular-nums text-[var(--accent)]">
                {{ formatStopwatch(tracker.elapsedSeconds) }}
            </p>

            <button type="button" class="btn-ghost mt-4 w-full" :disabled="tracker.pending" @click="$emit('stop')">
                <FaIcon icon="stop" />
                Detener
            </button>
        </template>

        <template v-else>
            <p class="text-xs tracking-wide text-ink-400 uppercase">Sin actividad</p>
            <p class="mt-1 text-sm text-ink-300">
                Toca una categoría para empezar a registrar. Un toque basta.
            </p>
        </template>
    </section>
</template>

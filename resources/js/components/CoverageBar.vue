<script setup lang="ts">
import { computed } from 'vue';
import { formatDuration } from '@/lib/format';

const props = defineProps<{
    label: string;
    trackedSeconds: number;
    elapsedSeconds: number;
    coverage: number;
}>();

const percent = computed(() => Math.round(Math.min(1, Math.max(0, props.coverage)) * 100));
</script>

<template>
    <div class="card">
        <div class="flex items-baseline justify-between gap-2">
            <h2 class="text-sm font-medium text-ink-200">{{ label }}</h2>
            <p class="text-lg font-semibold tabular-nums text-ink-100">{{ formatDuration(trackedSeconds) }}</p>
        </div>

        <div class="mt-3 h-2 overflow-hidden rounded-full bg-ink-700" role="progressbar" :aria-valuenow="percent"
            aria-valuemin="0" aria-valuemax="100" :aria-label="`Cobertura de ${label}`">
            <div class="h-full rounded-full bg-[var(--accent)] transition-[width] duration-500"
                :style="{ width: `${percent}%` }"></div>
        </div>

        <p class="mt-2 text-xs text-ink-400">
            {{ percent }}% de {{ formatDuration(elapsedSeconds) }} transcurridos están registrados
        </p>
    </div>
</template>

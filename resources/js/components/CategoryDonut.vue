<script setup lang="ts">
import { Chart, type ChartConfiguration, DoughnutController, ArcElement, Tooltip } from 'chart.js';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { formatDuration } from '@/lib/format';
import type { CategoryTotal } from '@/lib/types';

Chart.register(DoughnutController, ArcElement, Tooltip);

const props = defineProps<{ totals: CategoryTotal[] }>();

const canvas = ref<HTMLCanvasElement | null>(null);
let chart: Chart | null = null;

function config(): ChartConfiguration<'doughnut'> {
    return {
        type: 'doughnut',
        data: {
            labels: props.totals.map((total) => total.name),
            datasets: [
                {
                    data: props.totals.map((total) => total.seconds),
                    backgroundColor: props.totals.map((total) => total.color),
                    borderColor: '#12121b',
                    borderWidth: 2,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (item) => ` ${item.label}: ${formatDuration(Number(item.raw))}`,
                    },
                },
            },
        },
    };
}

function render(): void {
    if (!canvas.value) {
        return;
    }

    chart?.destroy();
    chart = new Chart(canvas.value, config());
}

onMounted(render);
watch(() => props.totals, render, { deep: true });
onBeforeUnmount(() => chart?.destroy());
</script>

<template>
    <div>
        <div v-if="totals.length > 0" class="relative h-56">
            <canvas ref="canvas" role="img" aria-label="Distribución de tiempo por categoría"></canvas>
        </div>

        <!-- The chart is decorative on its own; this list carries the data. -->
        <ul class="mt-4 space-y-1.5">
            <li v-for="total in totals" :key="total.category_id" class="flex items-center gap-2 text-sm">
                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: total.color }"
                    aria-hidden="true"></span>
                <span aria-hidden="true">{{ total.icon }}</span>
                <span class="min-w-0 flex-1 truncate text-ink-200">{{ total.name }}</span>
                <span class="tabular-nums text-ink-100">{{ formatDuration(total.seconds) }}</span>
                <span class="w-12 text-right tabular-nums text-ink-400">{{ Math.round(total.share * 100) }}%</span>
            </li>
        </ul>

        <p v-if="totals.length === 0" class="text-sm text-ink-400">
            Todavía no hay tiempo registrado en este periodo.
        </p>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import BudgetTable from '@/components/BudgetTable.vue';
import CategoryDonut from '@/components/CategoryDonut.vue';
import CoverageBar from '@/components/CoverageBar.vue';
import { api, query } from '@/lib/api';
import { formatDuration } from '@/lib/format';
import { messageFrom, useTrackerStore } from '@/stores/tracker';
import { useToasts } from '@/composables/useToasts';
import type { WeekReport } from '@/lib/types';

const tracker = useTrackerStore();
const toasts = useToasts();

const weekStart = ref<string>('');
const report = ref<WeekReport | null>(null);
const loading = ref(false);

const difference = computed(() => {
    if (!report.value) {
        return 0;
    }

    return report.value.tracked_seconds - report.value.previous_week.tracked_seconds;
});

const dayNames = ['lun', 'mar', 'mié', 'jue', 'vie', 'sáb', 'dom'];

const maxDaySeconds = computed(() =>
    Math.max(1, ...(report.value?.daily ?? []).map((day) => day.seconds)),
);

async function load(): Promise<void> {
    loading.value = true;

    try {
        report.value = await api.get<WeekReport>(`/api/reports/week${query({ week_start: weekStart.value })}`);
        weekStart.value = report.value.week_start;
    } catch (caught) {
        toasts.fail(messageFrom(caught));
    } finally {
        loading.value = false;
    }
}

function shiftWeek(weeks: number): void {
    const current = new Date(`${weekStart.value || report.value?.week_start}T00:00:00Z`);
    current.setUTCDate(current.getUTCDate() + weeks * 7);
    weekStart.value = current.toISOString().slice(0, 10);
}

watch(weekStart, load);
onMounted(() => {
    weekStart.value = tracker.status?.week.week_start ?? '';

    if (weekStart.value === '') {
        load();
    }
});
</script>

<template>
    <div class="space-y-4">
        <header class="flex items-center justify-between gap-2">
            <button type="button" class="btn-ghost !px-3" aria-label="Semana anterior" @click="shiftWeek(-1)">←</button>
            <div class="text-center">
                <h1 class="text-sm font-semibold text-ink-100">Semana</h1>
                <p class="text-xs text-ink-400">
                    {{ report ? `${report.week_start} → ${report.week_end}` : '…' }}
                </p>
            </div>
            <button type="button" class="btn-ghost !px-3" aria-label="Semana siguiente" @click="shiftWeek(1)">→</button>
        </header>

        <CoverageBar v-if="report" label="Registrado esta semana" :tracked-seconds="report.tracked_seconds"
            :elapsed-seconds="report.elapsed_seconds" :coverage="report.coverage" />

        <section v-if="report" class="card">
            <h2 class="text-sm font-medium text-ink-200">Por día</h2>
            <ul class="mt-4 flex h-32 items-end gap-2">
                <li v-for="(day, index) in report.daily" :key="day.date" class="flex flex-1 flex-col items-center gap-1.5">
                    <span class="w-full rounded-t bg-[var(--accent)] transition-[height]"
                        :style="{ height: `${Math.round((day.seconds / maxDaySeconds) * 100)}%` }"
                        :title="`${day.date}: ${formatDuration(day.seconds)}`"></span>
                    <span class="text-[11px] text-ink-400">{{ dayNames[index] ?? '' }}</span>
                </li>
            </ul>
        </section>

        <section v-if="report" class="card">
            <h2 class="text-sm font-medium text-ink-200">Distribución por categoría</h2>
            <CategoryDonut class="mt-3" :totals="report.by_category" />
        </section>

        <section v-if="report" class="card">
            <h2 class="text-sm font-medium text-ink-200">Presupuesto contra realidad</h2>
            <BudgetTable class="mt-3" :rows="report.budget.rows" />
        </section>

        <section v-if="report" class="grid gap-3 sm:grid-cols-2">
            <div class="card">
                <h2 class="text-xs tracking-wide text-ink-400 uppercase">Prioridad más descuidada</h2>
                <p v-if="report.budget.most_neglected" class="mt-1.5 text-sm text-ink-100">
                    {{ report.budget.most_neglected.icon }} {{ report.budget.most_neglected.category }} ·
                    faltan {{ formatDuration(Math.abs(report.budget.most_neglected.difference_minutes) * 60) }}
                </p>
                <p v-else class="mt-1.5 text-sm text-ink-400">Ninguna meta mínima quedó corta.</p>
            </div>

            <div class="card">
                <h2 class="text-xs tracking-wide text-ink-400 uppercase">Límite más excedido</h2>
                <p v-if="report.budget.biggest_overrun" class="mt-1.5 text-sm text-ink-100">
                    {{ report.budget.biggest_overrun.icon }} {{ report.budget.biggest_overrun.category }} ·
                    {{ formatDuration(report.budget.biggest_overrun.difference_minutes * 60) }} de más
                </p>
                <p v-else class="mt-1.5 text-sm text-ink-400">Ningún límite máximo se superó.</p>
            </div>
        </section>

        <section v-if="report" class="card">
            <h2 class="text-xs tracking-wide text-ink-400 uppercase">Contra la semana anterior</h2>
            <p class="mt-1.5 text-sm text-ink-100">
                {{ difference >= 0 ? '+' : '−' }}{{ formatDuration(Math.abs(difference)) }} registrados
                <span class="text-ink-400">
                    ({{ formatDuration(report.previous_week.tracked_seconds) }} la semana del
                    {{ report.previous_week.week_start }})
                </span>
            </p>
        </section>

        <RouterLink :to="{ name: 'review' }" class="btn-ghost w-full">Hacer la revisión semanal</RouterLink>
    </div>
</template>

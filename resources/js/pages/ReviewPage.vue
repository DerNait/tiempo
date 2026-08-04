<script setup lang="ts">
import { onMounted, ref } from 'vue';
import BudgetTable from '@/components/BudgetTable.vue';
import { api, query } from '@/lib/api';
import { formatDuration, formatPercent } from '@/lib/format';
import { messageFrom } from '@/stores/tracker';
import { useToasts } from '@/composables/useToasts';
import type { BudgetReport, WeeklyReview } from '@/lib/types';

const toasts = useToasts();

const weekStart = ref('');
const context = ref<{ tracked_seconds: number; coverage: number; budget: BudgetReport } | null>(null);
const form = ref<WeeklyReview>({
    week_start: '',
    biggest_time_leak: '',
    most_neglected_priority: '',
    what_worked: '',
    what_did_not_work: '',
    next_week_adjustment: '',
    notes: '',
});
const busy = ref(false);

const questions: { key: keyof WeeklyReview; label: string; help: string }[] = [
    {
        key: 'biggest_time_leak',
        label: '¿Qué categoría consumió más tiempo del esperado?',
        help: 'Describe el patrón, no el juicio.',
    },
    {
        key: 'most_neglected_priority',
        label: '¿Qué prioridad recibió menos tiempo del planeado?',
        help: 'Piensa en lo que elegiste priorizar, no en lo que “deberías” hacer.',
    },
    { key: 'what_worked', label: '¿Qué funcionó?', help: 'Vale la pena repetir lo que sí sostuviste.' },
    { key: 'what_did_not_work', label: '¿Qué no funcionó?', help: 'Sin culpa: solo qué falló como sistema.' },
    {
        key: 'next_week_adjustment',
        label: '¿Qué ajuste concreto harás la próxima semana?',
        help: 'Un solo cambio, medible en minutos.',
    },
];

async function load(): Promise<void> {
    busy.value = true;

    try {
        const response = await api.get<{
            week_start: string;
            review: WeeklyReview | null;
            context: { tracked_seconds: number; coverage: number; budget: BudgetReport };
        }>(`/api/weekly-review${query({ week_start: weekStart.value })}`);

        weekStart.value = response.week_start;
        context.value = response.context;
        form.value = {
            week_start: response.week_start,
            biggest_time_leak: response.review?.biggest_time_leak ?? '',
            most_neglected_priority: response.review?.most_neglected_priority ?? '',
            what_worked: response.review?.what_worked ?? '',
            what_did_not_work: response.review?.what_did_not_work ?? '',
            next_week_adjustment: response.review?.next_week_adjustment ?? '',
            notes: response.review?.notes ?? '',
        };
    } catch (caught) {
        toasts.fail(messageFrom(caught));
    } finally {
        busy.value = false;
    }
}

/** Prefill the two factual questions from the week's own numbers. */
function suggestFromData(): void {
    const overrun = context.value?.budget.biggest_overrun;
    const neglected = context.value?.budget.most_neglected;

    if (overrun && !form.value.biggest_time_leak) {
        form.value.biggest_time_leak =
            `${overrun.category}: ${overrun.actual_minutes} min contra un límite de ${overrun.target_minutes} min.`;
    }

    if (neglected && !form.value.most_neglected_priority) {
        form.value.most_neglected_priority =
            `${neglected.category}: ${neglected.actual_minutes} min contra una meta de ${neglected.target_minutes} min.`;
    }
}

async function save(): Promise<void> {
    busy.value = true;

    try {
        await api.post('/api/weekly-review', { ...form.value, week_start: weekStart.value });
        toasts.success('Revisión guardada.');
    } catch (caught) {
        toasts.fail(messageFrom(caught));
    } finally {
        busy.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <header>
            <h1 class="text-sm font-semibold text-ink-100">Revisión semanal</h1>
            <p class="mt-1 text-xs text-ink-400">
                Unos 20 minutos. Los datos están al lado para que no dependas de la memoria.
            </p>
        </header>

        <section v-if="context" class="card">
            <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                <p class="text-ink-300">
                    Registrado: <span class="tabular-nums text-ink-100">{{ formatDuration(context.tracked_seconds) }}</span>
                </p>
                <p class="text-ink-300">
                    Cobertura: <span class="tabular-nums text-ink-100">{{ formatPercent(context.coverage) }}</span>
                </p>
            </div>
            <BudgetTable class="mt-3" :rows="context.budget.rows" />
            <button type="button" class="btn-ghost mt-3 w-full text-xs" @click="suggestFromData">
                Rellenar las dos primeras respuestas con los datos
            </button>
        </section>

        <form class="space-y-3" @submit.prevent="save">
            <label v-for="question in questions" :key="question.key" class="card block">
                <span class="block text-sm font-medium text-ink-100">{{ question.label }}</span>
                <span class="mt-0.5 block text-xs text-ink-400">{{ question.help }}</span>
                <textarea v-model="form[question.key] as string" rows="3" class="input mt-2.5"></textarea>
            </label>

            <label class="card block">
                <span class="block text-sm font-medium text-ink-100">Notas</span>
                <textarea v-model="form.notes as string" rows="3" class="input mt-2.5"></textarea>
            </label>

            <button type="submit" class="btn-primary w-full" :disabled="busy">Guardar revisión</button>
        </form>
    </div>
</template>

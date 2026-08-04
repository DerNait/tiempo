<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { api, query } from '@/lib/api';
import { minutesToLabel } from '@/lib/format';
import { messageFrom, useTrackerStore } from '@/stores/tracker';
import { useToasts } from '@/composables/useToasts';
import type { BudgetReport, BudgetType } from '@/lib/types';

const tracker = useTrackerStore();
const toasts = useToasts();

interface Draft {
    category_id: number;
    budget_type: BudgetType;
    target_minutes: number;
}

const weekStart = ref('');
const report = ref<BudgetReport | null>(null);
const drafts = ref<Draft[]>([]);
const saveAsTemplate = ref(false);
const busy = ref(false);

const typeOptions: { value: BudgetType; label: string; hint: string }[] = [
    { value: 'minimum', label: 'Meta mínima', hint: 'Conviene alcanzarla' },
    { value: 'maximum', label: 'Límite máximo', hint: 'Conviene no superarlo' },
    { value: 'reference', label: 'Referencia', hint: 'Solo informativo' },
];

async function load(): Promise<void> {
    busy.value = true;

    try {
        const response = await api.get<{ week_start: string; budget: BudgetReport }>(
            `/api/budgets${query({ week_start: weekStart.value })}`,
        );
        weekStart.value = response.week_start;
        report.value = response.budget;
        drafts.value = response.budget.rows.map((row) => ({
            category_id: row.category_id,
            budget_type: row.budget_type,
            target_minutes: row.target_minutes,
        }));
    } catch (caught) {
        toasts.fail(messageFrom(caught));
    } finally {
        busy.value = false;
    }
}

async function save(): Promise<void> {
    busy.value = true;

    try {
        const response = await api.post<{ budget: BudgetReport }>('/api/budgets', {
            week_start: weekStart.value,
            budgets: drafts.value,
            save_as_template: saveAsTemplate.value,
        });
        report.value = response.budget;
        toasts.success('Presupuesto guardado.');
    } catch (caught) {
        toasts.fail(messageFrom(caught));
    } finally {
        busy.value = false;
    }
}

async function copyPrevious(): Promise<void> {
    busy.value = true;

    try {
        await api.post('/api/budgets/copy-previous', { week_start: weekStart.value, overwrite: true });
        await load();
        toasts.success('Copiado desde la semana anterior.');
    } catch (caught) {
        toasts.fail(messageFrom(caught));
    } finally {
        busy.value = false;
    }
}

async function applyTemplate(): Promise<void> {
    busy.value = true;

    try {
        await api.post('/api/budgets/apply-template', { week_start: weekStart.value });
        await load();
        toasts.success('Plantilla aplicada.');
    } catch (caught) {
        toasts.fail(messageFrom(caught));
    } finally {
        busy.value = false;
    }
}

function shiftWeek(weeks: number): void {
    const current = new Date(`${weekStart.value}T00:00:00Z`);
    current.setUTCDate(current.getUTCDate() + weeks * 7);
    weekStart.value = current.toISOString().slice(0, 10);
}

watch(weekStart, (value, previous) => {
    if (previous !== '' && value !== previous) {
        load();
    }
});

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <header class="flex items-center justify-between gap-2">
            <button type="button" class="btn-ghost !px-3" aria-label="Semana anterior" @click="shiftWeek(-1)">←</button>
            <div class="text-center">
                <h1 class="text-sm font-semibold text-ink-100">Presupuesto semanal</h1>
                <p class="text-xs text-ink-400">Semana del {{ weekStart || '…' }}</p>
            </div>
            <button type="button" class="btn-ghost !px-3" aria-label="Semana siguiente" @click="shiftWeek(1)">→</button>
        </header>

        <p class="text-xs text-ink-400">
            Los minutos son la unidad. Una meta mínima marca lo que quieres proteger; un límite máximo, lo que quieres
            contener. El descanso y el sueño pueden quedarse como referencia: no son tiempo perdido.
        </p>

        <div class="flex flex-wrap gap-2">
            <button type="button" class="btn-ghost flex-1 text-xs" :disabled="busy" @click="copyPrevious">
                Copiar semana anterior
            </button>
            <button type="button" class="btn-ghost flex-1 text-xs" :disabled="busy" @click="applyTemplate">
                Aplicar plantilla
            </button>
        </div>

        <form class="space-y-2" @submit.prevent="save">
            <div v-for="(draft, index) in drafts" :key="draft.category_id" class="card !p-3">
                <div class="flex items-center gap-2">
                    <span aria-hidden="true">{{ report?.rows[index]?.icon }}</span>
                    <h2 class="min-w-0 flex-1 truncate text-sm text-ink-100">{{ report?.rows[index]?.category }}</h2>
                    <span class="text-xs tabular-nums text-ink-400">
                        real {{ minutesToLabel(report?.rows[index]?.actual_minutes ?? 0) }}
                    </span>
                </div>

                <div class="mt-2.5 grid gap-2 sm:grid-cols-[1fr_9rem]">
                    <label class="block">
                        <span class="sr-only">Tipo de presupuesto para {{ report?.rows[index]?.category }}</span>
                        <select v-model="draft.budget_type" class="input !py-2 text-xs">
                            <option v-for="option in typeOptions" :key="option.value" :value="option.value">
                                {{ option.label }} — {{ option.hint }}
                            </option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="sr-only">Minutos para {{ report?.rows[index]?.category }}</span>
                        <div class="flex items-center gap-2">
                            <input v-model.number="draft.target_minutes" type="number" min="0" max="10080" step="15"
                                class="input !py-2 text-xs" inputmode="numeric">
                            <span class="w-14 shrink-0 text-right text-xs tabular-nums text-ink-400">
                                {{ minutesToLabel(draft.target_minutes) }}
                            </span>
                        </div>
                    </label>
                </div>
            </div>

            <label class="flex items-center gap-2 py-2 text-sm text-ink-300">
                <input v-model="saveAsTemplate" type="checkbox" class="h-4 w-4 accent-[var(--accent)]">
                Guardar también como plantilla recurrente
            </label>

            <button type="submit" class="btn-primary w-full" :disabled="busy">Guardar presupuesto</button>
        </form>
    </div>
</template>

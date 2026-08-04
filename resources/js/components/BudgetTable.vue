<script setup lang="ts">
import { formatDuration, minutesToLabel } from '@/lib/format';
import type { BudgetRow } from '@/lib/types';

defineProps<{ rows: BudgetRow[] }>();

const typeLabels: Record<string, string> = {
    minimum: 'Meta mínima',
    maximum: 'Límite máximo',
    reference: 'Referencia',
};

const statusLabels: Record<string, string> = {
    on_track: 'Dentro',
    pending: 'Falta',
    exceeded: 'Excedido',
    reference: '—',
};

const statusClasses: Record<string, string> = {
    on_track: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200',
    pending: 'border-amber-500/40 bg-amber-500/10 text-amber-200',
    exceeded: 'border-red-500/40 bg-red-500/10 text-red-200',
    reference: 'border-ink-600 bg-ink-800 text-ink-300',
};

/** Minimums fill up toward their goal; maximums fill up toward their ceiling. */
function barColor(row: BudgetRow): string {
    if (row.status === 'exceeded') {
        return '#f87171';
    }

    if (row.status === 'on_track') {
        return '#34d399';
    }

    return 'var(--accent)';
}
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[34rem] border-collapse text-sm">
            <caption class="sr-only">Presupuesto semanal contra tiempo real</caption>
            <thead>
                <tr class="text-left text-xs tracking-wide text-ink-400 uppercase">
                    <th scope="col" class="py-2 pr-3">Categoría</th>
                    <th scope="col" class="py-2 pr-3">Tipo</th>
                    <th scope="col" class="py-2 pr-3 text-right">Real</th>
                    <th scope="col" class="py-2 pr-3 text-right">Meta</th>
                    <th scope="col" class="py-2 pr-3 text-right">Dif.</th>
                    <th scope="col" class="py-2 text-right">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ink-700">
                <tr v-for="row in rows" :key="row.category_id">
                    <th scope="row" class="py-2.5 pr-3 text-left font-normal">
                        <span class="flex items-center gap-2">
                            <span aria-hidden="true">{{ row.icon }}</span>
                            <span class="text-ink-100">{{ row.category }}</span>
                        </span>
                        <span v-if="row.target_minutes > 0"
                            class="mt-1.5 block h-1.5 w-full max-w-[10rem] overflow-hidden rounded-full bg-ink-700">
                            <span class="block h-full rounded-full"
                                :style="{
                                    width: `${Math.min(100, Math.round(((row.actual_minutes / row.target_minutes) || 0) * 100))}%`,
                                    backgroundColor: barColor(row),
                                }"></span>
                        </span>
                    </th>
                    <td class="py-2.5 pr-3 text-ink-300">{{ typeLabels[row.budget_type] }}</td>
                    <td class="py-2.5 pr-3 text-right tabular-nums text-ink-100">
                        {{ minutesToLabel(row.actual_minutes) }}
                    </td>
                    <td class="py-2.5 pr-3 text-right tabular-nums text-ink-300">
                        {{ row.target_minutes > 0 ? minutesToLabel(row.target_minutes) : '—' }}
                    </td>
                    <td class="py-2.5 pr-3 text-right tabular-nums"
                        :class="row.difference_minutes >= 0 ? 'text-ink-200' : 'text-ink-400'">
                        {{ row.difference_minutes >= 0 ? '+' : '−' }}{{ formatDuration(Math.abs(row.difference_minutes) * 60) }}
                    </td>
                    <td class="py-2.5 text-right">
                        <span class="chip" :class="statusClasses[row.status]">{{ statusLabels[row.status] }}</span>
                    </td>
                </tr>
            </tbody>
        </table>

        <p v-if="rows.length === 0" class="py-4 text-sm text-ink-400">
            Todavía no hay presupuesto ni registros para esta semana.
        </p>
    </div>
</template>

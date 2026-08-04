<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import CategoryButton from '@/components/CategoryButton.vue';
import CategoryPicker from '@/components/CategoryPicker.vue';
import CategoryDonut from '@/components/CategoryDonut.vue';
import CoverageBar from '@/components/CoverageBar.vue';
import CurrentActivity from '@/components/CurrentActivity.vue';
import DayTimeline from '@/components/DayTimeline.vue';
import EntryEditor from '@/components/EntryEditor.vue';
import { api } from '@/lib/api';
import { formatDuration, formatTimeInZone } from '@/lib/format';
import { messageFrom, useTrackerStore } from '@/stores/tracker';
import { useToasts } from '@/composables/useToasts';
import type { Category, DayReport, TimeEntry } from '@/lib/types';

const tracker = useTrackerStore();
const toasts = useToasts();

const report = ref<DayReport | null>(null);
const editing = ref<TimeEntry | null>(null);
const editorOpen = ref(false);

const audit = computed(() => tracker.status?.audit ?? null);

const recent = computed(() => (report.value?.timeline ?? []).slice().reverse().slice(0, 12));

async function loadReport(): Promise<void> {
    report.value = await api.get<DayReport>('/api/reports/day');
}

async function start(category: Category): Promise<void> {
    try {
        await tracker.startActivity(category);
        await loadReport();
    } catch (caught) {
        toasts.fail(messageFrom(caught));
    }
}

async function stop(): Promise<void> {
    try {
        await tracker.stopActivity();
        await loadReport();
    } catch (caught) {
        toasts.fail(messageFrom(caught));
    }
}

function edit(entry: TimeEntry | null): void {
    editing.value = entry;
    editorOpen.value = true;
}

async function refreshAll(): Promise<void> {
    await Promise.all([tracker.loadStatus(), loadReport()]);
}

onMounted(loadReport);
</script>

<template>
    <div class="space-y-4">
        <section v-if="audit && !audit.finished"
            class="rounded-2xl border border-[var(--accent)]/40 bg-[color-mix(in_srgb,var(--accent)_12%,transparent)] p-4">
            <p class="text-sm font-medium text-ink-100">
                Auditoría inicial · día {{ audit.day_number }} de {{ audit.total_days }}
            </p>
            <p class="mt-1 text-xs text-ink-300">
                Esta semana solo mides con honestidad. Aún no hay metas que cumplir ni nada que reprocharte.
            </p>
        </section>

        <CurrentActivity @stop="stop" />

        <section class="space-y-2">
            <h2 class="text-sm font-semibold text-ink-200">Favoritas</h2>
            <div class="grid gap-2 sm:grid-cols-2">
                <CategoryButton v-for="category in tracker.favorites" :key="category.id" :category="category"
                    :active="tracker.currentEntry?.category_id === category.id" :disabled="tracker.pending"
                    @select="start" />
            </div>
            <p v-if="tracker.favorites.length === 0" class="text-sm text-ink-400">
                Marca categorías como favoritas en Ajustes para tenerlas a un toque.
            </p>
        </section>

        <CategoryPicker :active-id="tracker.currentEntry?.category_id ?? null" :disabled="tracker.pending"
            @select="start" />

        <CoverageBar v-if="report" label="Hoy" :tracked-seconds="report.tracked_seconds"
            :elapsed-seconds="report.elapsed_seconds" :coverage="report.coverage" />

        <DayTimeline v-if="report" :date="report.date" :entries="report.timeline" :gaps="report.gaps"
            :timezone="tracker.timezone" />

        <section v-if="report" class="card">
            <h2 class="text-sm font-medium text-ink-200">Distribución de hoy</h2>
            <CategoryDonut class="mt-3" :totals="report.by_category" />
        </section>

        <section class="card">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-medium text-ink-200">Últimos registros</h2>
                <button type="button" class="btn-ghost !px-3 !py-1.5 text-xs" @click="edit(null)">
                    Añadir registro
                </button>
            </div>

            <ul class="mt-3 divide-y divide-ink-700">
                <li v-for="entry in recent" :key="entry.id">
                    <button type="button" class="flex w-full items-center gap-3 py-2.5 text-left"
                        @click="edit(entry)">
                        <span aria-hidden="true">{{ entry.category?.icon ?? '⏱️' }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm text-ink-100">{{ entry.category?.name }}</span>
                            <span class="block text-xs text-ink-400">
                                {{ formatTimeInZone(entry.started_at, tracker.timezone) }}–{{
                                    entry.ended_at ? formatTimeInZone(entry.ended_at, tracker.timezone) : 'ahora'
                                }}
                            </span>
                        </span>
                        <span class="text-sm tabular-nums text-ink-200">
                            {{ formatDuration(entry.duration_seconds) }}
                        </span>
                    </button>
                </li>
            </ul>

            <p v-if="recent.length === 0" class="mt-3 text-sm text-ink-400">
                Aún no hay registros hoy. Toca una categoría para empezar.
            </p>
        </section>

        <EntryEditor v-if="editorOpen" :entry="editing" :siblings="report?.timeline ?? []"
            @close="editorOpen = false" @saved="refreshAll" />
    </div>
</template>

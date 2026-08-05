<script setup lang="ts">
import FaIcon from '@/components/FaIcon.vue';
import { onMounted, ref } from 'vue';
import EntryEditor from '@/components/EntryEditor.vue';
import { api, query } from '@/lib/api';
import { formatDateInZone, formatDuration, formatTimeInZone } from '@/lib/format';
import { messageFrom, useTrackerStore } from '@/stores/tracker';
import { useToasts } from '@/composables/useToasts';
import type { TimeEntry } from '@/lib/types';

const tracker = useTrackerStore();
const toasts = useToasts();

const filters = ref({ from: '', to: '', category_id: '', search: '' });
const entries = ref<TimeEntry[]>([]);
const loading = ref(false);
const editing = ref<TimeEntry | null>(null);
const editorOpen = ref(false);

async function load(): Promise<void> {
    loading.value = true;

    try {
        const response = await api.get<{ data: TimeEntry[] }>(
            `/api/time-entries${query({ ...filters.value, per_page: 100 })}`,
        );
        entries.value = response.data;
    } catch (caught) {
        toasts.fail(messageFrom(caught));
    } finally {
        loading.value = false;
    }
}

function exportCsv(): void {
    window.location.href = `/api/time-entries/export${query(filters.value)}`;
}

function edit(entry: TimeEntry): void {
    editing.value = entry;
    editorOpen.value = true;
}

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <h1 class="text-sm font-semibold text-ink-100">Historial</h1>

        <form class="card space-y-3" @submit.prevent="load">
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block">
                    <span class="label">Desde</span>
                    <input v-model="filters.from" type="date" class="input">
                </label>
                <label class="block">
                    <span class="label">Hasta</span>
                    <input v-model="filters.to" type="date" class="input">
                </label>
            </div>

            <label class="block">
                <span class="label">Categoría</span>
                <select v-model="filters.category_id" class="input">
                    <option value="">Todas</option>
                    <option v-for="category in tracker.categories" :key="category.id" :value="category.id">
                        {{ category.icon }} {{ category.name }}
                    </option>
                </select>
            </label>

            <label class="block">
                <span class="label">Texto</span>
                <input v-model="filters.search" type="search" class="input" placeholder="Descripción o categoría">
            </label>

            <div class="flex gap-2">
                <button type="submit" class="btn-primary flex-1" :disabled="loading">
                    <FaIcon icon="filter" />
                    Filtrar
                </button>
                <button type="button" class="btn-ghost" @click="exportCsv">
                    <FaIcon icon="download" />
                    Exportar CSV
                </button>
            </div>
        </form>

        <section class="card">
            <ul class="divide-y divide-ink-700">
                <li v-for="entry in entries" :key="entry.id">
                    <button type="button" class="flex w-full items-center gap-3 py-2.5 text-left" @click="edit(entry)">
                        <span aria-hidden="true">{{ entry.category?.icon ?? '⏱️' }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm text-ink-100">
                                {{ entry.category?.name }}
                                <span v-if="entry.description" class="text-ink-400">· {{ entry.description }}</span>
                            </span>
                            <span class="block text-xs text-ink-400">
                                {{ formatDateInZone(entry.started_at, tracker.timezone) }} ·
                                {{ formatTimeInZone(entry.started_at, tracker.timezone) }}–{{
                                    entry.ended_at ? formatTimeInZone(entry.ended_at, tracker.timezone) : 'ahora'
                                }}
                            </span>
                        </span>
                        <span class="text-sm tabular-nums text-ink-200">{{ formatDuration(entry.duration_seconds) }}</span>
                    </button>
                </li>
            </ul>

            <p v-if="entries.length === 0 && !loading" class="text-sm text-ink-400">
                No hay registros con esos filtros.
            </p>
        </section>

        <EntryEditor v-if="editorOpen" :entry="editing" :siblings="entries" @close="editorOpen = false"
            @saved="load" />
    </div>
</template>

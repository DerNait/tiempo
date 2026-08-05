<script setup lang="ts">
import FaIcon from '@/components/FaIcon.vue';
import { computed, ref, watch } from 'vue';
import { ApiError, api } from '@/lib/api';
import { findOverlap, isValidRange } from '@/lib/overlap';
import { fromLocalInputValue, toLocalInputValue } from '@/lib/format';
import { useTrackerStore } from '@/stores/tracker';
import { useToasts } from '@/composables/useToasts';
import type { TimeEntry } from '@/lib/types';

const props = defineProps<{
    /** null means "create a new past entry". */
    entry: TimeEntry | null;
    /** Same-day entries used for instant overlap feedback. */
    siblings: TimeEntry[];
}>();

const emit = defineEmits<{ (event: 'saved'): void; (event: 'close'): void }>();

const tracker = useTrackerStore();
const toasts = useToasts();

const categoryId = ref<number>(0);
const description = ref('');
const startedAt = ref('');
const endedAt = ref('');
const keepOpen = ref(false);
const saving = ref(false);
const serverError = ref<string | null>(null);

watch(
    () => props.entry,
    (entry) => {
        const now = new Date().toISOString();
        categoryId.value = entry?.category_id ?? tracker.activeCategories[0]?.id ?? 0;
        description.value = entry?.description ?? '';
        startedAt.value = toLocalInputValue(entry?.started_at ?? now, tracker.timezone);
        endedAt.value = entry?.ended_at ? toLocalInputValue(entry.ended_at, tracker.timezone) : '';
        keepOpen.value = entry !== null && entry.ended_at === null;
        serverError.value = null;
    },
    { immediate: true },
);

const candidate = computed(() => ({
    id: props.entry?.id,
    started_at: startedAt.value ? fromLocalInputValue(startedAt.value, tracker.timezone) : '',
    ended_at: keepOpen.value || endedAt.value === '' ? null : fromLocalInputValue(endedAt.value, tracker.timezone),
}));

const rangeError = computed(() => {
    if (candidate.value.started_at === '') {
        return 'Indica la hora de inicio.';
    }

    if (!keepOpen.value && endedAt.value === '') {
        return 'Indica la hora de fin, o marca la actividad como abierta.';
    }

    if (!isValidRange(candidate.value)) {
        return 'La hora de fin debe ser posterior a la de inicio.';
    }

    const conflict = findOverlap(candidate.value, props.siblings);

    if (conflict) {
        const name = props.siblings.find((sibling) => sibling.id === conflict.id)?.category?.name ?? 'otra actividad';

        return `Se solapa con ${name}. Ajusta las horas antes de guardar.`;
    }

    return null;
});

async function save(): Promise<void> {
    if (rangeError.value !== null || categoryId.value === 0) {
        return;
    }

    saving.value = true;
    serverError.value = null;

    const payload = {
        category_id: categoryId.value,
        description: description.value.trim() === '' ? null : description.value.trim(),
        started_at: candidate.value.started_at,
        ended_at: candidate.value.ended_at,
    };

    try {
        if (props.entry) {
            await api.patch(`/api/time-entries/${props.entry.id}`, payload);
        } else {
            await api.post('/api/time-entries', { ...payload, source: 'manual' });
        }

        toasts.success('Registro guardado.');
        emit('saved');
        emit('close');
    } catch (caught) {
        serverError.value = caught instanceof ApiError ? caught.message : 'No se pudo guardar el registro.';
    } finally {
        saving.value = false;
    }
}

async function remove(): Promise<void> {
    if (!props.entry || !window.confirm('¿Eliminar este registro? No se puede deshacer.')) {
        return;
    }

    saving.value = true;

    try {
        await api.delete(`/api/time-entries/${props.entry.id}`);
        toasts.success('Registro eliminado.');
        emit('saved');
        emit('close');
    } catch (caught) {
        serverError.value = caught instanceof ApiError ? caught.message : 'No se pudo eliminar el registro.';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-0 sm:items-center sm:p-4"
        role="dialog" aria-modal="true" aria-label="Editar registro" @click.self="emit('close')">
        <form
            class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-t-3xl border border-ink-700 bg-ink-850 p-5 sm:rounded-3xl"
            @submit.prevent="save">
            <h2 class="text-base font-semibold text-ink-100">
                {{ entry ? 'Editar registro' : 'Nuevo registro' }}
            </h2>

            <div class="mt-4 space-y-4">
                <label class="block">
                    <span class="label">Categoría</span>
                    <select v-model.number="categoryId" class="input" required>
                        <option v-for="category in tracker.activeCategories" :key="category.id" :value="category.id">
                            {{ category.icon }} {{ category.name }}
                        </option>
                    </select>
                </label>

                <label class="block">
                    <span class="label">Descripción (opcional)</span>
                    <input v-model="description" type="text" class="input" maxlength="255" placeholder="Sin descripción">
                </label>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="label">Inicio</span>
                        <input v-model="startedAt" type="datetime-local" class="input" required>
                    </label>

                    <label class="block">
                        <span class="label">Fin</span>
                        <input v-model="endedAt" type="datetime-local" class="input" :disabled="keepOpen">
                    </label>
                </div>

                <label class="flex items-center gap-2 text-sm text-ink-300">
                    <input v-model="keepOpen" type="checkbox" class="h-4 w-4 accent-[var(--accent)]">
                    Dejar la actividad abierta (en curso)
                </label>

                <p v-if="rangeError" class="rounded-xl border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm text-amber-200">
                    {{ rangeError }}
                </p>

                <p v-if="serverError" class="rounded-xl border border-red-500/40 bg-red-500/10 px-3 py-2 text-sm text-red-200">
                    {{ serverError }}
                </p>
            </div>

            <div class="mt-5 flex flex-wrap gap-2">
                <button type="submit" class="btn-primary flex-1" :disabled="saving || rangeError !== null">
                    <FaIcon icon="check" />
                    Guardar
                </button>
                <button type="button" class="btn-ghost" :disabled="saving" @click="emit('close')">
                    <FaIcon icon="cancel" />
                    Cancelar
                </button>
                <button v-if="entry" type="button" class="btn-danger" :disabled="saving" @click="remove">
                    <FaIcon icon="trash" />
                    Eliminar
                </button>
            </div>
        </form>
    </div>
</template>

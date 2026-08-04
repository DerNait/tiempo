<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '@/lib/api';
import { messageFrom, useTrackerStore } from '@/stores/tracker';
import { useToasts } from '@/composables/useToasts';
import type { Category } from '@/lib/types';

const tracker = useTrackerStore();
const toasts = useToasts();
const router = useRouter();

const step = ref(1);
const timezone = ref(tracker.settings?.timezone ?? 'America/Guatemala');
const startAudit = ref(true);
const busy = ref(false);

const timezones = ['America/Guatemala', 'America/Mexico_City', 'America/Bogota', 'America/New_York', 'Europe/Madrid', 'UTC'];

async function toggleFavorite(category: Category): Promise<void> {
    try {
        await api.patch(`/api/categories/${category.id}`, { is_favorite: !category.is_favorite });
        await tracker.loadCategories();
    } catch (caught) {
        toasts.fail(messageFrom(caught));
    }
}

async function finish(): Promise<void> {
    busy.value = true;

    try {
        await tracker.updateSettings({
            timezone: timezone.value,
            audit_mode_enabled: startAudit.value,
            onboarded: true,
        });
        await Promise.all([tracker.loadStatus(), tracker.loadCategories()]);
        await router.replace({ name: 'today' });
    } catch (caught) {
        toasts.fail(messageFrom(caught));
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="space-y-4">
        <section v-if="step === 1" class="card space-y-3">
            <h1 class="text-lg font-semibold text-ink-100">Cómo funciona esto</h1>
            <p class="text-sm text-ink-300">
                La meta no es exprimir cada minuto. Es comprobar si tus minutos reflejan las prioridades que elegiste
                conscientemente.
            </p>
            <ul class="space-y-2 text-sm text-ink-300">
                <li>· Registras con un toque cada vez que cambias de actividad.</li>
                <li>· Al final de la semana comparas lo real contra lo que presupuestaste en minutos.</li>
                <li>· El descanso, el sueño y el ocio cuentan como tiempo válido, no como fracaso.</li>
            </ul>
            <button type="button" class="btn-primary w-full" @click="step = 2">Continuar</button>
        </section>

        <section v-else-if="step === 2" class="card space-y-3">
            <h1 class="text-lg font-semibold text-ink-100">Tu zona horaria</h1>
            <p class="text-sm text-ink-400">Define cuándo empieza tu día y tu semana. La semana empieza el lunes.</p>

            <label class="block">
                <span class="label">Zona horaria</span>
                <select v-model="timezone" class="input">
                    <option v-for="zone in timezones" :key="zone" :value="zone">{{ zone }}</option>
                </select>
            </label>

            <label class="flex items-start gap-2 text-sm text-ink-300">
                <input v-model="startAudit" type="checkbox" class="mt-0.5 h-4 w-4 accent-[var(--accent)]">
                <span>
                    Iniciar una auditoría de 7 días
                    <span class="block text-xs text-ink-400">
                        Durante esos días solo observas: sin metas ni evaluaciones.
                    </span>
                </span>
            </label>

            <div class="flex gap-2">
                <button type="button" class="btn-ghost" @click="step = 1">Atrás</button>
                <button type="button" class="btn-primary flex-1" @click="step = 3">Continuar</button>
            </div>
        </section>

        <section v-else class="card space-y-3">
            <h1 class="text-lg font-semibold text-ink-100">Elige tus favoritas</h1>
            <p class="text-sm text-ink-400">
                Aparecerán primero en la pantalla de inicio, a un solo toque.
            </p>

            <ul class="max-h-80 divide-y divide-ink-700 overflow-y-auto">
                <li v-for="category in tracker.activeCategories" :key="category.id"
                    class="flex items-center gap-2 py-2">
                    <span aria-hidden="true">{{ category.icon }}</span>
                    <span class="min-w-0 flex-1 truncate text-sm text-ink-100">{{ category.name }}</span>
                    <button type="button" class="btn-ghost !px-2.5 !py-1 text-xs" :aria-pressed="category.is_favorite"
                        @click="toggleFavorite(category)">
                        {{ category.is_favorite ? '★' : '☆' }}
                    </button>
                </li>
            </ul>

            <div class="flex gap-2">
                <button type="button" class="btn-ghost" @click="step = 2">Atrás</button>
                <button type="button" class="btn-primary flex-1" :disabled="busy" @click="finish">
                    Empezar
                </button>
            </div>
        </section>
    </div>
</template>

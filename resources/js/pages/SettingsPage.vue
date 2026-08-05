<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '@/lib/api';
import { messageFrom, useTrackerStore } from '@/stores/tracker';
import { useToasts } from '@/composables/useToasts';
import type { ApiToken, Category } from '@/lib/types';

const tracker = useTrackerStore();
const toasts = useToasts();

const tokens = ref<ApiToken[]>([]);
const newTokenName = ref('Rainmeter');
const freshToken = ref<string | null>(null);
const busy = ref(false);

const newCategory = ref({ name: '', group_name: '', icon: '⏱️', color: '#a855f7' });

const timezones = [
    'America/Guatemala',
    'America/Mexico_City',
    'America/Bogota',
    'America/New_York',
    'Europe/Madrid',
    'UTC',
];

async function loadTokens(): Promise<void> {
    const response = await api.get<{ tokens: ApiToken[] }>('/api/tokens');
    tokens.value = response.tokens;
}

async function createToken(): Promise<void> {
    busy.value = true;

    try {
        const response = await api.post<{ token: string }>('/api/tokens', { name: newTokenName.value });
        freshToken.value = response.token;
        await loadTokens();
    } catch (caught) {
        toasts.fail(messageFrom(caught));
    } finally {
        busy.value = false;
    }
}

async function revokeToken(token: ApiToken): Promise<void> {
    if (!window.confirm(`¿Revocar el token «${token.name}»? Rainmeter dejará de actualizarse.`)) {
        return;
    }

    try {
        await api.delete(`/api/tokens/${token.id}`);
        await loadTokens();
        toasts.success('Token revocado.');
    } catch (caught) {
        toasts.fail(messageFrom(caught));
    }
}

async function saveSetting(payload: Record<string, unknown>): Promise<void> {
    try {
        await tracker.updateSettings(payload);
        toasts.success('Ajustes guardados.');
    } catch (caught) {
        toasts.fail(messageFrom(caught));
    }
}

async function toggleFavorite(category: Category): Promise<void> {
    try {
        await api.patch(`/api/categories/${category.id}`, { is_favorite: !category.is_favorite });
        await Promise.all([tracker.loadCategories(), tracker.loadStatus()]);
    } catch (caught) {
        toasts.fail(messageFrom(caught));
    }
}

async function toggleActive(category: Category): Promise<void> {
    try {
        await api.patch(`/api/categories/${category.id}`, { is_active: !category.is_active });
        await Promise.all([tracker.loadCategories(), tracker.loadStatus()]);
    } catch (caught) {
        toasts.fail(messageFrom(caught));
    }
}

async function createCategory(): Promise<void> {
    if (newCategory.value.name.trim() === '') {
        return;
    }

    try {
        await api.post('/api/categories', {
            ...newCategory.value,
            group_name: newCategory.value.group_name || null,
        });
        newCategory.value = { name: '', group_name: '', icon: '⏱️', color: '#a855f7' };
        await tracker.loadCategories();
        toasts.success('Categoría creada.');
    } catch (caught) {
        toasts.fail(messageFrom(caught));
    }
}

async function logout(): Promise<void> {
    await api.post('/api/logout');
    window.location.href = '/';
}

onMounted(loadTokens);
</script>

<template>
    <div v-if="tracker.settings" class="space-y-4">
        <h1 class="text-sm font-semibold text-ink-100">Ajustes</h1>

        <section class="card space-y-3">
            <h2 class="text-sm font-medium text-ink-200">Perfil</h2>

            <label class="block">
                <span class="label">Nombre</span>
                <input v-model="tracker.settings.name" type="text" class="input"
                    @change="saveSetting({ name: tracker.settings!.name })">
            </label>

            <label class="block">
                <span class="label">Zona horaria</span>
                <select v-model="tracker.settings.timezone" class="input"
                    @change="saveSetting({ timezone: tracker.settings!.timezone })">
                    <option v-for="zone in timezones" :key="zone" :value="zone">{{ zone }}</option>
                </select>
            </label>

            <label class="block">
                <span class="label">Color de acento</span>
                <input v-model="tracker.settings.accent_color" type="color" class="h-10 w-20 rounded-lg bg-ink-900"
                    @change="saveSetting({ accent_color: tracker.settings!.accent_color })">
            </label>
        </section>

        <section class="card space-y-3">
            <h2 class="text-sm font-medium text-ink-200">Auditoría</h2>
            <p class="text-xs text-ink-400">
                Durante la auditoría no hay metas: solo mides con honestidad para ver tus patrones reales.
            </p>

            <label class="flex items-center gap-2 text-sm text-ink-300">
                <input v-model="tracker.settings.audit_mode_enabled" type="checkbox"
                    class="h-4 w-4 accent-[var(--accent)]"
                    @change="saveSetting({ audit_mode_enabled: tracker.settings!.audit_mode_enabled })">
                Auditoría activa
            </label>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block">
                    <span class="label">Empieza el</span>
                    <input v-model="tracker.settings.audit_start_date" type="date" class="input"
                        @change="saveSetting({ audit_start_date: tracker.settings!.audit_start_date })">
                </label>

                <label class="block">
                    <span class="label">Duración (días)</span>
                    <input v-model.number="tracker.settings.audit_days" type="number" min="1" max="60" class="input"
                        @change="saveSetting({ audit_days: tracker.settings!.audit_days })">
                </label>
            </div>

            <p class="text-xs text-ink-400">
                Puedes ponerla a partir de mañana si hoy ya perdiste horas sin registrar: los días se cuentan
                completos, de medianoche a medianoche.
            </p>
        </section>

        <section class="card space-y-3">
            <h2 class="text-sm font-medium text-ink-200">Rainmeter</h2>
            <p class="text-xs text-ink-400">
                La skin muestra una categoría prioritaria y una de fuga. Toma su meta mínima y su límite máximo de la
                semana en curso.
            </p>

            <label class="block">
                <span class="label">Categoría prioritaria</span>
                <select v-model.number="tracker.settings.rainmeter_priority_category_id" class="input"
                    @change="saveSetting({ rainmeter_priority_category_id: tracker.settings!.rainmeter_priority_category_id })">
                    <option :value="null">Ninguna</option>
                    <option v-for="category in tracker.activeCategories" :key="category.id" :value="category.id">
                        {{ category.icon }} {{ category.name }}
                    </option>
                </select>
            </label>

            <label class="block">
                <span class="label">Categoría de fuga</span>
                <select v-model.number="tracker.settings.rainmeter_leak_category_id" class="input"
                    @change="saveSetting({ rainmeter_leak_category_id: tracker.settings!.rainmeter_leak_category_id })">
                    <option :value="null">Ninguna</option>
                    <option v-for="category in tracker.activeCategories" :key="category.id" :value="category.id">
                        {{ category.icon }} {{ category.name }}
                    </option>
                </select>
            </label>

            <div class="border-t border-ink-700 pt-3">
                <h3 class="text-xs tracking-wide text-ink-400 uppercase">Tokens de solo lectura</h3>

                <div class="mt-2 flex gap-2">
                    <input v-model="newTokenName" type="text" class="input" placeholder="Nombre del token">
                    <button type="button" class="btn-primary shrink-0" :disabled="busy" @click="createToken">
                        Crear
                    </button>
                </div>

                <div v-if="freshToken"
                    class="mt-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-3 text-xs text-amber-100">
                    <p class="font-medium">Cópialo ahora: no se vuelve a mostrar.</p>
                    <code class="mt-1.5 block break-all font-mono text-[11px]">{{ freshToken }}</code>
                </div>

                <ul class="mt-3 divide-y divide-ink-700">
                    <li v-for="token in tokens" :key="token.id" class="flex items-center gap-3 py-2">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm text-ink-100">{{ token.name }}</span>
                            <span class="block text-xs text-ink-400">
                                {{ token.abilities.join(', ') }} ·
                                {{ token.last_used_at ? `usado ${token.last_used_at.slice(0, 10)}` : 'sin usar' }}
                            </span>
                        </span>
                        <button type="button" class="btn-danger !px-3 !py-1.5 text-xs" @click="revokeToken(token)">
                            Revocar
                        </button>
                    </li>
                </ul>
            </div>
        </section>

        <section class="card space-y-3">
            <h2 class="text-sm font-medium text-ink-200">Categorías</h2>

            <ul class="divide-y divide-ink-700">
                <li v-for="category in tracker.categories" :key="category.id" class="flex items-center gap-2 py-2">
                    <span aria-hidden="true">{{ category.icon }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm" :class="category.is_active ? 'text-ink-100' : 'text-ink-400'">
                            {{ category.name }}
                        </span>
                        <span class="block text-xs text-ink-400">{{ category.group_name }}</span>
                    </span>
                    <button type="button" class="btn-ghost !px-2.5 !py-1 text-xs"
                        :aria-pressed="category.is_favorite" @click="toggleFavorite(category)">
                        {{ category.is_favorite ? '★ Favorita' : '☆ Favorita' }}
                    </button>
                    <button type="button" class="btn-ghost !px-2.5 !py-1 text-xs" @click="toggleActive(category)">
                        {{ category.is_active ? 'Archivar' : 'Restaurar' }}
                    </button>
                </li>
            </ul>

            <form class="grid gap-2 border-t border-ink-700 pt-3 sm:grid-cols-[1fr_1fr_4rem_4rem_auto]"
                @submit.prevent="createCategory">
                <input v-model="newCategory.name" type="text" class="input" placeholder="Nombre" required>
                <input v-model="newCategory.group_name" type="text" class="input" placeholder="Grupo">
                <input v-model="newCategory.icon" type="text" class="input text-center" maxlength="4" aria-label="Icono">
                <input v-model="newCategory.color" type="color" class="h-full w-full rounded-xl bg-ink-900"
                    aria-label="Color">
                <button type="submit" class="btn-primary">Añadir</button>
            </form>
        </section>

        <button type="button" class="btn-ghost w-full" @click="logout">Cerrar sesión</button>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { ApiError, api } from '@/lib/api';

const emit = defineEmits<{ (event: 'authenticated'): void }>();

const email = ref('');
const password = ref('');
const remember = ref(true);
const error = ref<string | null>(null);
const busy = ref(false);

async function submit(): Promise<void> {
    busy.value = true;
    error.value = null;

    try {
        await api.post('/api/login', {
            email: email.value,
            password: password.value,
            remember: remember.value,
        });
        emit('authenticated');
    } catch (caught) {
        error.value =
            caught instanceof ApiError
                ? (caught.fieldErrors.email ?? caught.message)
                : 'No se pudo conectar con el servidor.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <main class="flex min-h-screen items-center justify-center px-4">
        <form class="card w-full max-w-sm" @submit.prevent="submit">
            <h1 class="text-xl font-semibold text-ink-100">Tiempo</h1>
            <p class="mt-1 text-sm text-ink-400">
                Registra en qué se va tu tiempo y compáralo con lo que decidiste priorizar.
            </p>

            <div class="mt-6 space-y-4">
                <label class="block">
                    <span class="label">Correo</span>
                    <input v-model="email" type="email" class="input" autocomplete="username" required autofocus>
                </label>

                <label class="block">
                    <span class="label">Contraseña</span>
                    <input v-model="password" type="password" class="input" autocomplete="current-password" required>
                </label>

                <label class="flex items-center gap-2 text-sm text-ink-300">
                    <input v-model="remember" type="checkbox" class="h-4 w-4 accent-[var(--accent)]">
                    Mantener la sesión abierta
                </label>

                <p v-if="error" class="rounded-xl border border-red-500/40 bg-red-500/10 px-3 py-2 text-sm text-red-200"
                    role="alert">
                    {{ error }}
                </p>

                <button type="submit" class="btn-primary w-full" :disabled="busy">
                    {{ busy ? 'Entrando…' : 'Entrar' }}
                </button>
            </div>
        </form>
    </main>
</template>

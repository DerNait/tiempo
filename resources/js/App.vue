<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import AppNav from '@/components/AppNav.vue';
import LoginPage from '@/pages/LoginPage.vue';
import ToastStack from '@/components/ToastStack.vue';
import { useTrackerStore } from '@/stores/tracker';
import { ApiError } from '@/lib/api';

const tracker = useTrackerStore();
const router = useRouter();

const authenticated = ref(document.getElementById('app')?.dataset.authenticated === '1');
const booted = ref(false);
let timer: number | undefined;
let refresher: number | undefined;

const ready = computed(() => authenticated.value && booted.value);

async function boot(): Promise<void> {
    try {
        await tracker.bootstrap();
        booted.value = true;

        if (tracker.settings && !tracker.settings.onboarded) {
            await router.replace({ name: 'onboarding' });
        }
    } catch (caught) {
        if (caught instanceof ApiError && caught.status === 401) {
            authenticated.value = false;
        }
    }
}

async function onAuthenticated(): Promise<void> {
    authenticated.value = true;
    await boot();
}

onMounted(async () => {
    if (authenticated.value) {
        await boot();
    }

    // The stopwatch ticks locally; the status is re-synced far less often so a
    // phone left open does not hammer the API.
    timer = window.setInterval(() => tracker.advanceClock(), 1000);
    refresher = window.setInterval(() => {
        if (authenticated.value && document.visibilityState === 'visible') {
            tracker.loadStatus().catch(() => undefined);
        }
    }, 60_000);
});

onUnmounted(() => {
    window.clearInterval(timer);
    window.clearInterval(refresher);
});
</script>

<template>
    <LoginPage v-if="!authenticated" @authenticated="onAuthenticated" />

    <div v-else class="mx-auto flex min-h-screen w-full max-w-3xl flex-col">
        <main class="flex-1 px-4 pt-4 pb-28">
            <div v-if="!ready" class="space-y-3" aria-busy="true" aria-live="polite">
                <div class="h-24 animate-pulse rounded-2xl bg-ink-850"></div>
                <div class="h-40 animate-pulse rounded-2xl bg-ink-850"></div>
                <span class="sr-only">Cargando</span>
            </div>

            <RouterView v-else v-slot="{ Component }">
                <component :is="Component" />
            </RouterView>
        </main>

        <AppNav v-if="ready" />
        <ToastStack />
    </div>
</template>

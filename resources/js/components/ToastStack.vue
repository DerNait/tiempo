<script setup lang="ts">
import { useToasts } from '@/composables/useToasts';

const { toasts, dismiss } = useToasts();

const tones: Record<string, string> = {
    info: 'border-ink-600 bg-ink-800 text-ink-100',
    success: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200',
    error: 'border-red-500/40 bg-red-500/10 text-red-200',
};
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 bottom-24 z-50 flex flex-col items-center gap-2 px-4"
        role="status" aria-live="polite">
        <TransitionGroup name="toast">
            <button v-for="toast in toasts" :key="toast.id" type="button"
                class="pointer-events-auto w-full max-w-md rounded-xl border px-4 py-3 text-left text-sm shadow-lg"
                :class="tones[toast.tone]" @click="dismiss(toast.id)">
                {{ toast.message }}
            </button>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.18s ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(6px);
}
</style>

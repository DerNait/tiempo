<script setup lang="ts">
import FaIcon from '@/components/FaIcon.vue';
import type { IconName } from '@/lib/icons';

const links: { name: string; label: string; icon: IconName }[] = [
    { name: 'today', label: 'Hoy', icon: 'today' },
    { name: 'week', label: 'Semana', icon: 'week' },
    { name: 'budgets', label: 'Presupuesto', icon: 'budget' },
    { name: 'history', label: 'Historial', icon: 'history' },
    { name: 'settings', label: 'Ajustes', icon: 'settings' },
];
</script>

<template>
    <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-ink-700 bg-ink-900/95 backdrop-blur"
        aria-label="Navegación principal">
        <ul class="mx-auto flex w-full max-w-3xl items-stretch justify-between px-2 pb-[env(safe-area-inset-bottom)]">
            <li v-for="link in links" :key="link.name" class="flex-1">
                <RouterLink :to="{ name: link.name }"
                    class="flex flex-col items-center gap-0.5 rounded-xl px-1 py-2.5 text-[11px] font-medium text-ink-400 transition"
                    active-class="text-[var(--accent)]">
                    <FaIcon :icon="link.icon" class="text-base leading-none" />
                    {{ link.label }}
                </RouterLink>
            </li>
        </ul>
    </nav>
</template>

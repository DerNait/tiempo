<script setup lang="ts">
import type { Category } from '@/lib/types';

defineProps<{
    category: Category;
    active?: boolean;
    disabled?: boolean;
    compact?: boolean;
}>();

defineEmits<{ (event: 'select', category: Category): void }>();
</script>

<template>
    <button type="button"
        class="flex w-full items-center gap-3 rounded-2xl border px-3 text-left transition active:scale-[0.98]
        disabled:cursor-not-allowed disabled:opacity-60"
        :class="[
            compact ? 'py-2.5' : 'py-4',
            active
                ? 'border-[var(--accent)] bg-[color-mix(in_srgb,var(--accent)_18%,transparent)]'
                : 'border-ink-700 bg-ink-850 hover:border-ink-600 hover:bg-ink-800',
        ]" :disabled="disabled" :aria-pressed="active ? 'true' : 'false'"
        @click="$emit('select', category)">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl text-lg"
            :style="{ backgroundColor: category.color + '22', border: `1px solid ${category.color}55` }"
            aria-hidden="true">{{ category.icon }}</span>

        <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium text-ink-100">{{ category.name }}</span>
            <span v-if="category.group_name && !compact" class="block truncate text-xs text-ink-400">
                {{ category.group_name }}
            </span>
        </span>

        <span v-if="active" class="text-xs font-semibold text-[var(--accent)]">En curso</span>
    </button>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import CategoryButton from '@/components/CategoryButton.vue';
import { useTrackerStore } from '@/stores/tracker';
import type { Category } from '@/lib/types';

defineProps<{
    activeId?: number | null;
    disabled?: boolean;
}>();

const emit = defineEmits<{ (event: 'select', category: Category): void }>();

const tracker = useTrackerStore();
const expanded = ref(false);
const search = ref('');

const groups = computed(() => {
    const term = search.value.trim().toLowerCase();

    return tracker.groupedCategories
        .map((group) => ({
            name: group.name,
            items: term === ''
                ? group.items
                : group.items.filter((category) => category.name.toLowerCase().includes(term)),
        }))
        .filter((group) => group.items.length > 0);
});

function select(category: Category): void {
    emit('select', category);
}
</script>

<template>
    <section class="space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-ink-200">Todas las categorías</h2>
            <button type="button" class="btn-ghost !px-3 !py-1.5 text-xs" :aria-expanded="expanded"
                @click="expanded = !expanded">
                {{ expanded ? 'Ocultar' : 'Mostrar' }}
            </button>
        </div>

        <div v-if="expanded" class="space-y-4">
            <label class="block">
                <span class="sr-only">Buscar categoría</span>
                <input v-model="search" type="search" class="input" placeholder="Buscar categoría…">
            </label>

            <div v-for="group in groups" :key="group.name" class="space-y-2">
                <h3 class="text-xs font-semibold tracking-wide text-ink-400 uppercase">{{ group.name }}</h3>
                <div class="grid gap-2 sm:grid-cols-2">
                    <CategoryButton v-for="category in group.items" :key="category.id" :category="category" compact
                        :active="category.id === activeId" :disabled="disabled" @select="select" />
                </div>
            </div>

            <p v-if="groups.length === 0" class="text-sm text-ink-400">
                Ninguna categoría coincide con «{{ search }}».
            </p>
        </div>
    </section>
</template>

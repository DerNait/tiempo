<script setup lang="ts">
import { computed } from 'vue';
import { icons, type IconName } from '@/lib/icons';

/**
 * Renders a Font Awesome icon straight from its definition.
 *
 * The official `@fortawesome/vue-fontawesome` component needs the SVG core
 * runtime, which added ~30 KB gzipped to the bundle — more than the icons it
 * draws. These are static, decorative glyphs, so the path data is all we need.
 *
 * Sized in `em` so an icon follows the font size of the button it sits in, and
 * `aria-hidden` because every icon here accompanies a text label or a button
 * with its own `aria-label`.
 */
const props = defineProps<{ icon: IconName }>();

const definition = computed(() => icons[props.icon]);
const viewBox = computed(() => `0 0 ${definition.value.icon[0]} ${definition.value.icon[1]}`);
const path = computed(() => definition.value.icon[4] as string);
</script>

<template>
    <svg :viewBox="viewBox" class="inline-block h-[1em] w-[1em] shrink-0 fill-current" role="img"
        aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
        <path :d="path" />
    </svg>
</template>

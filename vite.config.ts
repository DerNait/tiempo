import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
        VitePWA({
            registerType: 'autoUpdate',
            injectRegister: null,
            manifest: {
                name: 'Tiempo — presupuesto de tiempo',
                short_name: 'Tiempo',
                description: 'Registro y auditoría de cómo uso mi tiempo frente a un presupuesto semanal.',
                theme_color: '#0b0b12',
                background_color: '#0b0b12',
                display: 'standalone',
                start_url: '/',
                scope: '/',
                lang: 'es',
                icons: [
                    { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
                    { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png' },
                    { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
                ],
            },
            workbox: {
                // The app is useless with stale numbers, so only the shell and
                // static assets are precached; API calls always hit the network.
                globPatterns: ['**/*.{js,css,ico,png,svg,woff2}'],
                navigateFallback: null,
            },
        }),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: { host: 'localhost' },
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.spec.ts'],
        globals: true,
    },
});

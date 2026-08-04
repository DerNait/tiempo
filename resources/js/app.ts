import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from '@/App.vue';
import { router } from '@/router';
import { registerSW } from 'virtual:pwa-register';

// Registered here rather than injected into HTML: the shell is a Blade view,
// so the plugin has no index.html to write into.
registerSW({ immediate: true });

const app = createApp(App);

app.use(createPinia());
app.use(router);
app.mount('#app');

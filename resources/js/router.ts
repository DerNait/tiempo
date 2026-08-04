import { createRouter, createWebHistory } from 'vue-router';

export const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', name: 'today', component: () => import('@/pages/TodayPage.vue') },
        { path: '/semana', name: 'week', component: () => import('@/pages/WeekPage.vue') },
        { path: '/presupuestos', name: 'budgets', component: () => import('@/pages/BudgetsPage.vue') },
        { path: '/historial', name: 'history', component: () => import('@/pages/HistoryPage.vue') },
        { path: '/revision', name: 'review', component: () => import('@/pages/ReviewPage.vue') },
        { path: '/ajustes', name: 'settings', component: () => import('@/pages/SettingsPage.vue') },
        { path: '/bienvenida', name: 'onboarding', component: () => import('@/pages/OnboardingPage.vue') },
        { path: '/:pathMatch(.*)*', redirect: { name: 'today' } },
    ],
    scrollBehavior: () => ({ top: 0 }),
});

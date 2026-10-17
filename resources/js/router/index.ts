import { createRouter, createMemoryHistory, type RouteRecordRaw } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const routes: RouteRecordRaw[] = [
    {
        path: '/',
        redirect: '/dashboard',
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('@/views/auth/LoginView.vue'),
        meta: { guestOnly: true, title: 'Login' },
    },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: () => import('@/views/dashboard/DashboardView.vue'),
        meta: { requiresAuth: true, title: 'Dashboard' },
    },
    {
        path: '/routers',
        name: 'routers',
        component: () => import('@/views/routers/RouterListView.vue'),
        meta: { requiresAuth: true, title: 'Routers Fleet' },
    },
    {
        path: '/audit-logs',
        name: 'audit-logs',
        component: () => import('@/views/safe-mode/AuditLogsView.vue'),
        meta: { requiresAuth: true, title: 'Safe Mode Audit Logs' },
    },
    {
        path: '/vouchers',
        name: 'vouchers',
        component: () => import('@/views/hotspot/VouchersIndexView.vue'),
        meta: { requiresAuth: true, title: 'Hotspot Vouchers' },
    },
    {
        path: '/dedicated-users',
        name: 'dedicated-users',
        component: () => import('@/views/ppp/DedicatedUsersView.vue'),
        meta: { requiresAuth: true, title: 'Dedicated Subscribers & PPP' },
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/dashboard',
    },
];

// Restore last active route from sessionStorage on browser refresh
const savedRoute = sessionStorage.getItem('tikpulse_memory_route') || '/';

const router = createRouter({
    history: createMemoryHistory(),
    routes,
});

if (savedRoute && savedRoute !== '/' && savedRoute !== '/login') {
    router.replace(savedRoute);
}

router.afterEach((to) => {
    if (to.fullPath && to.name !== 'login') {
        sessionStorage.setItem('tikpulse_memory_route', to.fullPath);
    }
    const pageTitle = to.meta.title ? `${to.meta.title} | TikPulse` : 'TikPulse - Enterprise MikroTik & Hotspot Management';
    document.title = pageTitle;
});

router.beforeEach(async (to) => {
    const authStore = useAuthStore();

    // If token exists in localStorage but user state is empty, fetch user info
    if (authStore.token && !authStore.user) {
        await authStore.fetchMe();
    }

    const isAuthenticated = authStore.isAuthenticated;

    if (to.meta.requiresAuth && !isAuthenticated) {
        return { name: 'login' };
    }

    if (to.meta.guestOnly && isAuthenticated) {
        return { name: 'dashboard' };
    }
});

export default router;

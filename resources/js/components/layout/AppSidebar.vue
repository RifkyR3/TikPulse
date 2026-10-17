<script setup lang="ts">
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useRouterContextStore } from '@/stores/routerContext';
import { useLocaleStore } from '@/stores/locale';
import {
    LayoutDashboard,
    Server,
    Ticket,
    ShieldAlert,
    Activity,
    ChevronLeft,
    ChevronRight,
    Wifi,
    Radio
} from 'lucide-vue-next';

const props = defineProps<{
    isCollapsed: boolean;
    isMobileOpen: boolean;
}>();

const emit = defineEmits<{
    (e: 'toggle-collapse'): void;
    (e: 'close-mobile'): void;
}>();

const route = useRoute();
const router = useRouter();
const routerStore = useRouterContextStore();
const localeStore = useLocaleStore();

const navItems = computed(() => [
    {
        name: localeStore.t('nav.dashboard'),
        path: '/dashboard',
        icon: LayoutDashboard,
    },
    {
        name: localeStore.t('nav.routers'),
        path: '/routers',
        icon: Server,
    },
    {
        name: localeStore.t('nav.vouchers'),
        path: '/vouchers',
        icon: Ticket,
    },
    {
        name: localeStore.t('nav.dedicatedUsers'),
        path: '/dedicated-users',
        icon: Radio,
    },
    {
        name: localeStore.t('nav.auditLogs'),
        path: '/audit-logs',
        icon: ShieldAlert,
    },
]);

const navigateTo = (path: string) => {
    router.push(path);
    emit('close-mobile');
};
</script>

<template>
    <!-- Mobile Backdrop -->
    <div
        v-if="isMobileOpen"
        @click="emit('close-mobile')"
        class="fixed inset-0 z-40 bg-black/60 lg:hidden animate-in fade-in duration-150"
    ></div>

    <!-- Sidebar Container -->
    <aside
        :class="[
            'fixed top-0 bottom-0 left-0 z-40 flex flex-col border-r border-border bg-card transition-all duration-300 ease-in-out',
            // Desktop width
            isCollapsed ? 'lg:w-20' : 'lg:w-64',
            // Mobile visibility
            isMobileOpen ? 'translate-x-0 w-64 shadow-2xl' : '-translate-x-full lg:translate-x-0'
        ]"
    >
        <!-- Brand Header -->
        <div class="h-16 flex items-center justify-between px-4 border-b border-border">
            <div class="flex items-center space-x-3 overflow-hidden cursor-pointer" @click="navigateTo('/dashboard')">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-sky-500 via-indigo-500 to-primary text-white flex items-center justify-center shrink-0 shadow-md shadow-sky-500/20">
                    <Activity class="w-5 h-5" />
                </div>
                <div v-if="!isCollapsed" class="flex flex-col overflow-hidden transition-opacity duration-200">
                    <span class="font-extrabold text-base tracking-tight leading-none text-foreground">
                        Tik<span class="text-sky-500">Pulse</span>
                    </span>
                    <span class="text-[10px] text-muted-foreground font-mono mt-0.5">Enterprise v1.0</span>
                </div>
            </div>

            <!-- Desktop Collapse Button -->
            <button
                type="button"
                @click="emit('toggle-collapse')"
                class="hidden lg:flex p-1.5 rounded-lg border border-border text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer"
                title="Toggle Sidebar Width"
            >
                <ChevronLeft v-if="!isCollapsed" class="w-4 h-4" />
                <ChevronRight v-else class="w-4 h-4" />
            </button>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 px-3 py-4 space-y-1.5 overflow-y-auto">
            <button
                v-for="item in navItems"
                :key="item.path"
                type="button"
                @click="navigateTo(item.path)"
                :class="[
                    'w-full flex items-center rounded-xl transition-all cursor-pointer font-medium text-xs',
                    isCollapsed ? 'justify-center p-3' : 'space-x-3 px-3.5 py-2.5',
                    route.path.startsWith(item.path)
                        ? 'bg-gradient-to-r from-sky-500/15 to-indigo-500/10 text-sky-600 dark:text-sky-400 font-bold border border-sky-500/20 shadow-xs'
                        : 'text-muted-foreground hover:text-foreground hover:bg-muted/80'
                ]"
                :title="item.name"
            >
                <component
                    :is="item.icon"
                    :class="[
                        'shrink-0',
                        isCollapsed ? 'w-5 h-5' : 'w-4 h-4',
                        route.path.startsWith(item.path) ? 'text-sky-500' : 'text-muted-foreground'
                    ]"
                />
                <span v-if="!isCollapsed" class="truncate">{{ item.name }}</span>
            </button>
        </nav>

        <!-- Router Health Quick Badge (Bottom of Sidebar) -->
        <div class="p-3 border-t border-border">
            <div
                v-if="!isCollapsed"
                class="rounded-xl border border-border/70 bg-background/60 p-3 space-y-1.5"
            >
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-muted-foreground uppercase tracking-wider">{{ localeStore.t('nav.routerStatus') }}</span>
                    <div class="flex items-center space-x-1.5">
                        <span
                            class="w-2 h-2 rounded-full"
                            :class="[
                                routerStore.isOffline ? 'bg-amber-500' :
                                routerStore.activeRouter?.status === 'online' ? 'bg-emerald-500 shadow-xs shadow-emerald-500/50' : 'bg-slate-400'
                            ]"
                        ></span>
                        <span class="text-[10px] font-mono font-bold" :class="routerStore.isOffline ? 'text-amber-500' : 'text-emerald-500'">
                            {{ routerStore.isOffline ? localeStore.t('common.offline') : localeStore.t('common.online') }}
                        </span>
                    </div>
                </div>
                <div class="text-xs font-semibold text-foreground truncate flex items-center justify-between">
                    <span class="truncate">{{ routerStore.activeRouter?.name || localeStore.t('nav.noRouter') }}</span>
                    <button
                        v-if="routerStore.isOffline"
                        type="button"
                        @click.stop="routerStore.reconnectRouter()"
                        :disabled="routerStore.isReconnecting"
                        class="text-[10px] font-bold text-sky-500 hover:text-sky-400 cursor-pointer ml-1"
                        title="Retry Connection"
                    >
                        {{ routerStore.isReconnecting ? '...' : localeStore.t('common.retry') }}
                    </button>
                </div>
                <div class="flex items-center justify-between text-[10px] text-muted-foreground font-mono">
                    <span>{{ routerStore.activeRouter?.protocol || 'socket' }}</span>
                    <span v-if="routerStore.isOffline && routerStore.consecutiveFailures > 0" class="text-amber-500">
                        {{ routerStore.consecutiveFailures }}/{{ routerStore.maxRetries }} retries
                    </span>
                    <span v-else>{{ routerStore.activeRouter?.ros_version || 'v7.x' }}</span>
                </div>
            </div>

            <div v-else class="flex justify-center">
                <span
                    class="w-2.5 h-2.5 rounded-full"
                    :class="routerStore.activeRouter?.status === 'online' ? 'bg-emerald-500' : 'bg-slate-400'"
                    :title="routerStore.activeRouter?.name"
                ></span>
            </div>
        </div>
    </aside>
</template>

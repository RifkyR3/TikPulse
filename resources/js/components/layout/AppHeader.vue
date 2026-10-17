<script setup lang="ts">
import RouterSwitcher from '@/components/layout/RouterSwitcher.vue';
import SafeModeBadge from '@/components/layout/SafeModeBadge.vue';
import ThemeToggle from '@/components/layout/ThemeToggle.vue';
import LanguageSwitcher from '@/components/layout/LanguageSwitcher.vue';
import UserDropdown from '@/components/layout/UserDropdown.vue';
import { useRouterContextStore } from '@/stores/routerContext';
import { Menu, Cpu, HardDrive, Clock, Activity, RefreshCw } from 'lucide-vue-next';

const emit = defineEmits<{
    (e: 'toggle-mobile'): void;
    (e: 'open-unlock-modal'): void;
    (e: 'add-router'): void;
}>();

const routerStore = useRouterContextStore();
</script>

<template>
    <header class="h-16 border-b border-border bg-card sticky top-0 z-30 px-4 sm:px-6 flex items-center justify-between">
        <!-- Left: Mobile Menu & Router Switcher -->
        <div class="flex items-center space-x-3">
            <button
                type="button"
                @click="emit('toggle-mobile')"
                class="lg:hidden p-2 rounded-xl border border-border text-foreground hover:bg-muted transition-colors cursor-pointer"
                aria-label="Open navigation drawer"
            >
                <Menu class="w-5 h-5" />
            </button>

            <!-- Global Router Switcher -->
            <RouterSwitcher @add-router="emit('add-router')" />

            <!-- Quick Telemetry (CPU, RAM & Uptime) on Tablet / Desktop -->
            <div class="hidden xl:flex items-center space-x-3 pl-3 border-l border-border text-xs text-muted-foreground font-medium">
                <div class="flex items-center space-x-1.5" title="Router CPU Load">
                    <Cpu class="w-3.5 h-3.5 text-sky-500" />
                    <span>CPU: <strong class="text-foreground font-mono">{{ routerStore.activeRouter?.cpu_load !== null && routerStore.activeRouter?.cpu_load !== undefined ? routerStore.activeRouter.cpu_load + '%' : '-' }}</strong></span>
                </div>
                <div class="flex items-center space-x-1.5" title="Router Memory Usage">
                    <HardDrive class="w-3.5 h-3.5 text-indigo-500" />
                    <span>RAM: <strong class="text-foreground font-mono">{{ routerStore.activeRouter?.memory_usage_percent !== null && routerStore.activeRouter?.memory_usage_percent !== undefined ? routerStore.activeRouter.memory_usage_percent + '%' : '-' }}</strong></span>
                </div>
                <div class="flex items-center space-x-1.5" title="Router Uptime">
                    <Clock class="w-3.5 h-3.5 text-emerald-500" />
                    <span>Uptime: <strong class="text-foreground font-mono">{{ routerStore.activeRouter?.uptime || '-' }}</strong></span>
                </div>
            </div>
        </div>

        <!-- Right: Auto-Refresh Rate Selector, Safe Mode Badge, Theme Toggle, User Menu -->
        <div class="flex items-center space-x-2 sm:space-x-3">
            <!-- Global Refresh Rate Selector -->
            <div class="hidden md:flex items-center space-x-1.5 px-2.5 py-1.5 rounded-xl border border-border bg-background text-xs font-medium text-muted-foreground shadow-2xs">
                <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': routerStore.isLoading || routerStore.isReconnecting }" />
                <select
                    :value="routerStore.refreshIntervalSeconds"
                    @change="(e) => routerStore.setRefreshInterval(Number((e.target as HTMLSelectElement).value))"
                    class="bg-transparent text-xs font-semibold text-foreground focus:outline-none cursor-pointer"
                    title="Auto-refresh rate across all views"
                >
                    <option :value="0">Pause</option>
                    <option :value="5">5s</option>
                    <option :value="10">10s</option>
                    <option :value="15">15s (Default)</option>
                    <option :value="30">30s</option>
                    <option :value="60">60s</option>
                </select>
            </div>

            <!-- Safe Mode Badge Button -->
            <SafeModeBadge @open-unlock-modal="emit('open-unlock-modal')" />

            <!-- Language Switcher -->
            <LanguageSwitcher />

            <!-- Theme Switcher -->
            <ThemeToggle />

            <!-- User Menu & Logout -->
            <UserDropdown />
        </div>
    </header>
</template>

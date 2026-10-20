<script lang="ts">
    import { routerContextStore } from '@/stores/routerContext.svelte';
    import { localeStore } from '@/stores/locale.svelte';
    import { router } from '@inertiajs/svelte';
    import {
        LayoutDashboard,
        Server,
        Ticket,
        ShieldAlert,
        Activity,
        ChevronLeft,
        ChevronRight,
        Radio,
    } from '@lucide/svelte';

    let {
        isCollapsed = false,
        isMobileOpen = false,
        onToggleCollapse,
        onCloseMobile,
    }: {
        isCollapsed?: boolean;
        isMobileOpen?: boolean;
        onToggleCollapse?: () => void;
        onCloseMobile?: () => void;
    } = $props();

    const navItems = $derived([
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

    function currentPath(): string {
        return typeof window !== 'undefined' ? window.location.pathname : '/dashboard';
    }

    function navigateTo(path: string) {
        router.visit(path);
        onCloseMobile?.();
    }
</script>

<!-- Mobile Backdrop -->
{#if isMobileOpen}
    <button
        type="button"
        onclick={() => onCloseMobile?.()}
        class="fixed inset-0 z-40 bg-black/60 lg:hidden animate-in fade-in duration-150 border-0 cursor-default w-full h-full"
        aria-label="Close mobile sidebar"
    ></button>
{/if}

<!-- Sidebar Container -->
<aside
    class="fixed top-0 bottom-0 left-0 z-40 flex flex-col border-r border-border bg-card transition-all duration-300 ease-in-out {isCollapsed
        ? 'lg:w-20'
        : 'lg:w-64'} {isMobileOpen ? 'translate-x-0 w-64 shadow-2xl' : '-translate-x-full lg:translate-x-0'}"
>
    <!-- Brand Header -->
    <div class="h-16 flex items-center justify-between px-4 border-b border-border">
        <button
            type="button"
            class="flex items-center space-x-3 overflow-hidden cursor-pointer bg-transparent border-0 text-left p-0"
            onclick={() => navigateTo('/dashboard')}
        >
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-sky-500 via-indigo-500 to-primary text-white flex items-center justify-center shrink-0 shadow-md shadow-sky-500/20">
                <Activity class="w-5 h-5" />
            </div>
            {#if !isCollapsed}
                <div class="flex flex-col overflow-hidden transition-opacity duration-200">
                    <span class="font-extrabold text-base tracking-tight leading-none text-foreground">
                        Tik<span class="text-sky-500">Pulse</span>
                    </span>
                    <span class="text-[10px] text-muted-foreground font-mono mt-0.5">Enterprise v1.0</span>
                </div>
            {/if}
        </button>

        <!-- Desktop Collapse Button -->
        <button
            type="button"
            onclick={() => onToggleCollapse?.()}
            class="hidden lg:flex p-1.5 rounded-lg border border-border text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer"
            title="Toggle Sidebar Width"
        >
            {#if !isCollapsed}
                <ChevronLeft class="w-4 h-4" />
            {:else}
                <ChevronRight class="w-4 h-4" />
            {/if}
        </button>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 px-3 py-4 space-y-1.5 overflow-y-auto">
        {#each navItems as item (item.path)}
            {@const active = currentPath().startsWith(item.path)}
            <button
                type="button"
                onclick={() => navigateTo(item.path)}
                class="w-full flex items-center rounded-xl transition-all cursor-pointer font-medium text-xs {isCollapsed
                    ? 'justify-center p-3'
                    : 'space-x-3 px-3.5 py-2.5'} {active
                    ? 'bg-gradient-to-r from-sky-500/15 to-indigo-500/10 text-sky-600 dark:text-sky-400 font-bold border border-sky-500/20 shadow-2xs'
                    : 'text-muted-foreground hover:text-foreground hover:bg-muted/80'}"
                title={item.name}
            >
                <item.icon
                    class="shrink-0 {isCollapsed ? 'w-5 h-5' : 'w-4 h-4'} {active ? 'text-sky-500' : 'text-muted-foreground'}"
                />
                {#if !isCollapsed}
                    <span class="truncate">{item.name}</span>
                {/if}
            </button>
        {/each}
    </nav>

    <!-- Router Health Quick Badge (Bottom of Sidebar) -->
    <div class="p-3 border-t border-border">
        {#if !isCollapsed}
            <div class="rounded-xl border border-border/70 bg-background/60 p-3 space-y-1.5">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-muted-foreground uppercase tracking-wider">{localeStore.t('nav.routerStatus')}</span>
                    <div class="flex items-center space-x-1.5">
                        <span
                            class="w-2 h-2 rounded-full {routerContextStore.isOffline
                                ? 'bg-amber-500'
                                : routerContextStore.activeRouter?.status === 'online'
                                  ? 'bg-emerald-500 shadow-xs shadow-emerald-500/50'
                                  : 'bg-slate-400'}"
                        ></span>
                        <span class="text-[10px] font-mono font-bold {routerContextStore.isOffline ? 'text-amber-500' : 'text-emerald-500'}">
                            {routerContextStore.isOffline ? localeStore.t('common.offline') : localeStore.t('common.online')}
                        </span>
                    </div>
                </div>
                <div class="text-xs font-semibold text-foreground truncate flex items-center justify-between">
                    <span class="truncate">{routerContextStore.activeRouter?.name || localeStore.t('nav.noRouter')}</span>
                    {#if routerContextStore.isOffline}
                        <button
                            type="button"
                            onclick={(e) => {
                                e.stopPropagation();
                                routerContextStore.reconnectRouter();
                            }}
                            disabled={routerContextStore.isReconnecting}
                            class="text-[10px] font-bold text-sky-500 hover:text-sky-400 cursor-pointer ml-1"
                            title="Retry Connection"
                        >
                            {routerContextStore.isReconnecting ? '...' : localeStore.t('common.retry')}
                        </button>
                    {/if}
                </div>
                <div class="flex items-center justify-between text-[10px] text-muted-foreground font-mono">
                    <span>{routerContextStore.activeRouter?.protocol || 'socket'}</span>
                    {#if routerContextStore.isOffline && routerContextStore.consecutiveFailures > 0}
                        <span class="text-amber-500">
                            {routerContextStore.consecutiveFailures}/{routerContextStore.maxRetries} retries
                        </span>
                    {:else}
                        <span>{routerContextStore.activeRouter?.ros_version || 'v7.x'}</span>
                    {/if}
                </div>
            </div>
        {:else}
            <div class="flex justify-center">
                <span
                    class="w-2.5 h-2.5 rounded-full {routerContextStore.activeRouter?.status === 'online' ? 'bg-emerald-500' : 'bg-slate-400'}"
                    title={routerContextStore.activeRouter?.name}
                ></span>
            </div>
        {/if}
    </div>
</aside>

<script lang="ts">
    import * as Sidebar from '@/components/ui/sidebar';
    import * as Breadcrumb from '@/components/ui/breadcrumb';
    import { Separator } from '@/components/ui/separator';
    import RouterSwitcher from '@/components/layout/RouterSwitcher.svelte';
    import SafeModeBadge from '@/components/layout/SafeModeBadge.svelte';
    import ThemeToggle from '@/components/layout/ThemeToggle.svelte';
    import LanguageSwitcher from '@/components/layout/LanguageSwitcher.svelte';
    import UserDropdown from '@/components/layout/UserDropdown.svelte';
    import { routerContextStore } from '@/stores/routerContext.svelte';
    import { localeStore } from '@/stores/locale.svelte';
    import { Cpu, HardDrive, Clock, RefreshCw } from '@lucide/svelte';

    let {
        onOpenUnlockModal,
        onAddRouter,
    }: {
        onOpenUnlockModal?: () => void;
        onAddRouter?: () => void;
    } = $props();

    function getBreadcrumbs() {
        const path = typeof window !== 'undefined' ? window.location.pathname : '/dashboard';
        if (path === '/' || path === '/dashboard') {
            return [{ label: localeStore.t('nav.dashboard'), href: '/dashboard' }];
        }

        const segments = path.split('/').filter(Boolean);
        const routeLabels: Record<string, string> = {
            dashboard: localeStore.t('nav.dashboard'),
            routers: localeStore.t('nav.routers'),
            vouchers: localeStore.t('nav.vouchers'),
            'dedicated-users': localeStore.t('nav.dedicatedUsers'),
            'audit-logs': localeStore.t('nav.auditLogs'),
            users: localeStore.t('nav.users'),
        };

        return segments.map((seg, i) => {
            const href = '/' + segments.slice(0, i + 1).join('/');
            const label = routeLabels[seg] || seg.charAt(0).toUpperCase() + seg.slice(1);
            return { label, href };
        });
    }

    const breadcrumbs = $derived(getBreadcrumbs());
</script>

<header class="bg-background sticky top-0 z-30 flex h-14 shrink-0 items-center justify-between gap-2 border-b border-border px-4 sm:px-6">
    <!-- Left: Trigger, Separator, Breadcrumbs & Router Info -->
    <div class="flex items-center gap-2 min-w-0">
        <Sidebar.Trigger class="-ml-1" />
        <Separator orientation="vertical" class="mr-1 !h-4" />

        <!-- Breadcrumbs Navigation -->
        <div class="hidden md:flex items-center">
            <Breadcrumb.Root>
                <Breadcrumb.List>
                    {#each breadcrumbs as crumb, i (crumb.href)}
                        {#if i > 0}
                            <Breadcrumb.Separator />
                        {/if}
                        <Breadcrumb.Item>
                            {#if i === breadcrumbs.length - 1}
                                <Breadcrumb.Page>{crumb.label}</Breadcrumb.Page>
                            {:else}
                                <Breadcrumb.Link href={crumb.href}>{crumb.label}</Breadcrumb.Link>
                            {/if}
                        </Breadcrumb.Item>
                    {/each}
                </Breadcrumb.List>
            </Breadcrumb.Root>
            <Separator orientation="vertical" class="mx-3 !h-4 hidden lg:block" />
        </div>

        <!-- Global Router Switcher -->
        <RouterSwitcher {onAddRouter} />

        <!-- Router Telemetry Pill (CPU, RAM, Uptime) -->
        <div class="hidden xl:flex items-center gap-3 pl-2.5 border-l border-border text-xs text-muted-foreground font-medium">
            <div class="flex items-center gap-1.5" title="Router CPU Load">
                <Cpu class="size-3.5 text-sky-500" />
                <span>CPU: <strong class="text-foreground font-mono">{routerContextStore.activeRouter?.cpu_load !== null && routerContextStore.activeRouter?.cpu_load !== undefined ? routerContextStore.activeRouter.cpu_load + '%' : '-'}</strong></span>
            </div>
            <div class="flex items-center gap-1.5" title="Router Memory Usage">
                <HardDrive class="size-3.5 text-indigo-500" />
                <span>RAM: <strong class="text-foreground font-mono">{routerContextStore.activeRouter?.memory_usage_percent !== null && routerContextStore.activeRouter?.memory_usage_percent !== undefined ? routerContextStore.activeRouter.memory_usage_percent + '%' : '-'}</strong></span>
            </div>
            <div class="flex items-center gap-1.5" title="Router Uptime">
                <Clock class="size-3.5 text-emerald-500" />
                <span>Uptime: <strong class="text-foreground font-mono">{routerContextStore.activeRouter?.uptime || '-'}</strong></span>
            </div>
        </div>
    </div>

    <!-- Right Actions: Refresh Rate, Safe Mode, Language, Theme, User -->
    <div class="flex items-center gap-1.5 sm:gap-2">
        <!-- Global Refresh Rate Selector -->
        <div class="hidden sm:flex items-center gap-1.5 px-2 h-8 rounded-md border border-border bg-background text-xs font-medium text-muted-foreground shadow-2xs">
            <RefreshCw class="size-3.5 {routerContextStore.isLoading || routerContextStore.isReconnecting ? 'animate-spin' : ''}" />
            <select
                value={routerContextStore.refreshIntervalSeconds}
                onchange={(e) => routerContextStore.setRefreshInterval(Number((e.target as HTMLSelectElement).value))}
                class="bg-transparent text-xs font-semibold text-foreground focus:outline-none cursor-pointer"
                title="Auto-refresh rate across all views"
            >
                <option value={0}>Pause</option>
                <option value={5}>5s</option>
                <option value={10}>10s</option>
                <option value={15}>15s</option>
                <option value={30}>30s</option>
                <option value={60}>60s</option>
            </select>
        </div>

        <!-- Safe Mode Badge Button -->
        <SafeModeBadge {onOpenUnlockModal} />

        <!-- Language Switcher -->
        <LanguageSwitcher />

        <!-- Theme Toggle -->
        <ThemeToggle />

        <!-- Separator -->
        <Separator orientation="vertical" class="mx-0.5 !h-4 hidden sm:block" />

        <!-- User Menu & Logout -->
        <UserDropdown />
    </div>
</header>

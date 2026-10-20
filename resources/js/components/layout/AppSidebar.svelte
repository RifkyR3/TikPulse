<script lang="ts">
    import * as Sidebar from '@/components/ui/sidebar';
    import { routerContextStore } from '@/stores/routerContext.svelte';
    import { localeStore } from '@/stores/locale.svelte';
    import { authStore } from '@/stores/auth.svelte';
    import * as Avatar from '@/components/ui/avatar';
    import { Badge } from '@/components/ui/badge';
    import { router } from '@inertiajs/svelte';
    import {
        Activity,
        LayoutDashboard,
        Server,
        Ticket,
        Radio,
        ShieldAlert,
        LogOut,
        ChevronDown,
    } from '@lucide/svelte';

    const sidebar = Sidebar.useSidebar();

    function handleNavigate(path: string) {
        if (sidebar.isMobile) {
            sidebar.setOpenMobile(false);
        }
        router.visit(path);
    }

    function currentPath(): string {
        return typeof window !== 'undefined' ? window.location.pathname : '/dashboard';
    }

    function getInitials(name?: string) {
        if (!name) return 'A';
        return name
            .split(' ')
            .map((n) => n[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    }

    type NavItem = {
        title: string;
        url: string;
        icon: typeof LayoutDashboard;
        badge?: string;
    };

    type NavGroup = {
        label: string;
        items: NavItem[];
    };

    const navigation: NavGroup[] = $derived([
        {
            label: 'Overview',
            items: [
                { title: localeStore.t('nav.dashboard'), url: '/dashboard', icon: LayoutDashboard },
            ],
        },
        {
            label: 'Network',
            items: [
                { title: localeStore.t('nav.routers'), url: '/routers', icon: Server },
            ],
        },
        {
            label: 'Services',
            items: [
                { title: localeStore.t('nav.vouchers'), url: '/vouchers', icon: Ticket },
                { title: localeStore.t('nav.dedicatedUsers'), url: '/dedicated-users', icon: Radio },
            ],
        },
        {
            label: 'System & Security',
            items: [
                { title: localeStore.t('nav.auditLogs'), url: '/audit-logs', icon: ShieldAlert },
            ],
        },
    ]);
</script>

<Sidebar.Root collapsible="icon">
    <!-- Brand Header -->
    <Sidebar.Header>
        <Sidebar.Menu>
            <Sidebar.MenuItem>
                <Sidebar.MenuButton size="lg" onclick={() => handleNavigate('/dashboard')}>
                    {#snippet child({ props })}
                        <a href="/dashboard" {...props} onclick={(e) => { e.preventDefault(); handleNavigate('/dashboard'); }}>
                            <div class="size-8 rounded-lg bg-gradient-to-tr from-sky-500 via-indigo-500 to-primary text-white flex items-center justify-center shrink-0 shadow-md shadow-sky-500/20">
                                <Activity class="size-4" />
                            </div>
                            <div class="flex flex-col gap-0.5 leading-none">
                                <span class="font-bold text-sm tracking-tight text-foreground">
                                    Tik<span class="text-sky-500">Pulse</span>
                                </span>
                                <span class="text-[10px] text-muted-foreground font-mono">Enterprise v1.0</span>
                            </div>
                        </a>
                    {/snippet}
                </Sidebar.MenuButton>
            </Sidebar.MenuItem>
        </Sidebar.Menu>
    </Sidebar.Header>

    <!-- Nav Items -->
    <Sidebar.Content>
        {#each navigation as group (group.label)}
            <Sidebar.Group>
                <Sidebar.GroupLabel>{group.label}</Sidebar.GroupLabel>
                <Sidebar.GroupContent>
                    <Sidebar.Menu>
                        {#each group.items as item (item.title)}
                            {@const active =
                                item.url === '/dashboard'
                                    ? currentPath() === '/dashboard' || currentPath() === '/'
                                    : currentPath().startsWith(item.url)}
                            <Sidebar.MenuItem>
                                <Sidebar.MenuButton
                                    isActive={active}
                                    onclick={() => handleNavigate(item.url)}
                                    title={item.title}
                                >
                                    {#snippet child({ props })}
                                        <a
                                            href={item.url}
                                            {...props}
                                            aria-current={active ? 'page' : undefined}
                                            onclick={(e) => { e.preventDefault(); handleNavigate(item.url); }}
                                        >
                                            <item.icon class="size-4" />
                                            <span>{item.title}</span>
                                        </a>
                                    {/snippet}
                                </Sidebar.MenuButton>
                                {#if item.badge}
                                    <Sidebar.MenuBadge>{item.badge}</Sidebar.MenuBadge>
                                {/if}
                            </Sidebar.MenuItem>
                        {/each}
                    </Sidebar.Menu>
                </Sidebar.GroupContent>
            </Sidebar.Group>
        {/each}
    </Sidebar.Content>

    <!-- Router Health Quick Badge (Bottom of Sidebar) -->
    <div class="px-3 py-2 group-data-[collapsible=icon]:hidden">
        <div class="rounded-lg border border-border bg-card/60 p-2.5 space-y-1.5 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">{localeStore.t('nav.routerStatus')}</span>
                <div class="flex items-center gap-1.5">
                    <span
                        class="size-2 rounded-full {routerContextStore.isOffline
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
                <span>{routerContextStore.activeRouter?.ros_version || 'v7.x'}</span>
            </div>
        </div>
    </div>

    <!-- Sidebar Footer with User Card -->
    <Sidebar.Footer>
        <Sidebar.Menu>
            <Sidebar.MenuItem>
                <Sidebar.MenuButton size="lg" class="data-[state=open]:bg-sidebar-accent">
                    {#snippet child({ props })}
                        <div {...props} class="flex items-center gap-2.5 w-full">
                            <Avatar.Root class="size-8 rounded-lg shrink-0">
                                <Avatar.Fallback class="rounded-lg bg-gradient-to-tr from-sky-600 to-indigo-600 text-white font-bold text-xs">
                                    {getInitials(authStore.user?.name)}
                                </Avatar.Fallback>
                            </Avatar.Root>
                            <div class="grid flex-1 text-left text-xs leading-tight group-data-[collapsible=icon]:hidden">
                                <span class="truncate font-semibold text-foreground">{authStore.user?.name || 'Administrator'}</span>
                                <span class="text-muted-foreground truncate text-[10px]">{authStore.user?.email || 'admin@tikpulse.test'}</span>
                            </div>
                            <button
                                type="button"
                                onclick={() => authStore.logout()}
                                class="size-7 rounded-md hover:bg-destructive/10 hover:text-destructive flex items-center justify-center text-muted-foreground transition-colors ml-auto group-data-[collapsible=icon]:hidden cursor-pointer"
                                title={localeStore.t('nav.signOut')}
                            >
                                <LogOut class="size-3.5" />
                            </button>
                        </div>
                    {/snippet}
                </Sidebar.MenuButton>
            </Sidebar.MenuItem>
        </Sidebar.Menu>
    </Sidebar.Footer>

    <Sidebar.Rail />
</Sidebar.Root>

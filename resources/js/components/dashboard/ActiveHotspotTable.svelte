<script lang="ts">
    import { onMount, onDestroy } from 'svelte';
    import { routerContextStore } from '@/stores/routerContext.svelte';
    import { localeStore } from '@/stores/locale.svelte';
    import { usePageVisibility } from '@/utils/pageVisibility';
    import { debounce } from '@/utils/debounce';
    import { toast } from 'svelte-sonner';
    import apiClient from '@/services/api';
    import type { ApiResponse } from '@/types/api';
    import DataTable, { type Column } from '@/components/common/DataTable.svelte';
    import {
        Users,
        Search,
        RefreshCw,
        UserX,
        Clock,
        AlertCircle,
    } from '@lucide/svelte';

    export interface ActiveHotspotUser {
        id: string;
        server: string;
        user: string;
        address: string;
        mac_address: string;
        uptime: string;
        bytes_in: number;
        bytes_out: number;
        bytes_in_formatted: string;
        bytes_out_formatted: string;
        login_by: string;
        comment: string | null;
    }

    const { isPageVisible, onVisibilityChange } = usePageVisibility();

    const columns: Column<ActiveHotspotUser>[] = [
        {
            id: 'user',
            header: localeStore.t('dashboard.userVoucher'),
            sortable: true,
        },
        {
            id: 'address',
            header: localeStore.t('dashboard.ipAddress'),
            sortable: true,
        },
        {
            id: 'mac_address',
            header: localeStore.t('dashboard.macAddress'),
        },
        {
            id: 'uptime',
            header: localeStore.t('dashboard.uptime'),
            sortable: true,
        },
        {
            id: 'bytes_out',
            header: localeStore.t('dashboard.downloaded'),
            sortable: true,
        },
        {
            id: 'bytes_in',
            header: localeStore.t('dashboard.uploaded'),
            sortable: true,
        },
        {
            id: 'actions',
            header: localeStore.t('common.actions'),
            align: 'right',
        },
    ];

    let users = $state<ActiveHotspotUser[]>([]);
    let searchQuery = $state('');
    let debouncedSearch = $state('');
    let isLoading = $state(false);
    let isKicking = $state<string | null>(null);
    let errorMessage = $state<string | null>(null);
    let autoRefresh = $state(true);
    let refreshInterval: any = null;

    const onSearchInput = debounce((val: string) => {
        debouncedSearch = val;
    }, 200);

    const filteredUsers = $derived.by(() => {
        if (!debouncedSearch.trim()) return users;
        const q = debouncedSearch.toLowerCase().trim();
        return users.filter((u) =>
            u.user.toLowerCase().includes(q) ||
            u.address.toLowerCase().includes(q) ||
            u.mac_address.toLowerCase().includes(q)
        );
    });

    function formatBytes(bytes: number): string {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`;
    }

    async function fetchActiveUsers(showLoading = true) {
        if (!routerContextStore.activeRouter) {
            users = [];
            return;
        }
        if (
            (routerContextStore.isPollingPaused ||
                routerContextStore.isOffline ||
                routerContextStore.consecutiveFailures >= routerContextStore.maxRetries) &&
            !routerContextStore.isReconnecting
        ) {
            return;
        }
        if (!isPageVisible.value) {
            return;
        }

        if (showLoading) isLoading = true;
        errorMessage = null;

        try {
            const response = await apiClient.get<ApiResponse<ActiveHotspotUser[]>>(
                `/routers/${routerContextStore.activeRouter.id}/hotspot/active`
            );
            if (response.data.status === 'success' && Array.isArray(response.data.data)) {
                users = response.data.data;
            } else if ((response.data as any).is_polling_paused) {
                routerContextStore.recordFailure('Router offline');
            }
        } catch (err: unknown) {
            const axiosErr = err as { response?: { data?: { message?: string } } };
            errorMessage = axiosErr.response?.data?.message || 'Failed to fetch active hotspot users';
            routerContextStore.recordFailure(errorMessage);
        } finally {
            if (showLoading) isLoading = false;
        }
    }

    async function handleKick(user: ActiveHotspotUser) {
        if (!routerContextStore.activeRouter) return;
        if (!confirm(`Are you sure you want to terminate session for user "${user.user}" (${user.address})?`)) {
            return;
        }

        isKicking = user.id;
        errorMessage = null;

        try {
            const response = await apiClient.post<ApiResponse<null>>(
                `/routers/${routerContextStore.activeRouter.id}/hotspot/active/${encodeURIComponent(user.id)}/kick`
            );
            const msg = response.data.message || `User "${user.user}" disconnected successfully.`;
            toast.success(msg);
            users = users.filter((u) => u.id !== user.id);
        } catch (err: unknown) {
            const axiosErr = err as { response?: { data?: { message?: string } } };
            const msg = axiosErr.response?.data?.message || 'Failed to disconnect user';
            errorMessage = msg;
            toast.error(msg);
        } finally {
            isKicking = null;
        }
    }

    function resetInterval() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
        if (
            autoRefresh &&
            !routerContextStore.isPollingPaused &&
            !routerContextStore.isOffline &&
            routerContextStore.consecutiveFailures < routerContextStore.maxRetries &&
            isPageVisible.value
        ) {
            refreshInterval = setInterval(() => {
                if (
                    autoRefresh &&
                    routerContextStore.activeRouter &&
                    !routerContextStore.isPollingPaused &&
                    !routerContextStore.isOffline &&
                    routerContextStore.consecutiveFailures < routerContextStore.maxRetries &&
                    isPageVisible.value
                ) {
                    fetchActiveUsers(false);
                }
            }, 10000);
        }
    }

    onMount(() => {
        if (!routerContextStore.isOffline && !routerContextStore.isPollingPaused && routerContextStore.consecutiveFailures < routerContextStore.maxRetries) {
            fetchActiveUsers(true);
        }
        resetInterval();

        onVisibilityChange((visible) => {
            if (visible && autoRefresh && routerContextStore.activeRouter && !routerContextStore.isPollingPaused && !routerContextStore.isOffline && routerContextStore.consecutiveFailures < routerContextStore.maxRetries) {
                fetchActiveUsers(false);
                resetInterval();
            } else {
                if (refreshInterval) {
                    clearInterval(refreshInterval);
                    refreshInterval = null;
                }
            }
        });
    });

    onDestroy(() => {
        if (refreshInterval) clearInterval(refreshInterval);
    });

    let currentTableRouterId: number | null = null;

    $effect(() => {
        const id = routerContextStore.activeRouterId;
        if (id && id !== currentTableRouterId) {
            currentTableRouterId = id;
            if (!routerContextStore.isOffline && !routerContextStore.isPollingPaused && routerContextStore.consecutiveFailures < routerContextStore.maxRetries) {
                fetchActiveUsers(true);
            }
            resetInterval();
        }
    });
</script>

{#snippet userCell(row: ActiveHotspotUser)}
    <div class="font-bold text-foreground font-mono">{row.user}</div>
    {#if row.comment}
        <div class="text-[10px] text-muted-foreground truncate max-w-xs">
            {row.comment}
        </div>
    {/if}
{/snippet}

{#snippet addressCell(row: ActiveHotspotUser)}
    <span class="font-mono text-muted-foreground">{row.address}</span>
{/snippet}

{#snippet macCell(row: ActiveHotspotUser)}
    <span class="font-mono text-muted-foreground uppercase text-[11px]">{row.mac_address || '-'}</span>
{/snippet}

{#snippet uptimeCell(row: ActiveHotspotUser)}
    <div class="flex items-center space-x-1.5 font-mono text-muted-foreground">
        <Clock class="w-3 h-3 text-muted-foreground/60 shrink-0" />
        <span>{row.uptime || '-'}</span>
    </div>
{/snippet}

{#snippet bytesOutCell(row: ActiveHotspotUser)}
    <span class="font-mono text-emerald-600 dark:text-emerald-400 font-semibold">
        {row.bytes_out_formatted || formatBytes(row.bytes_out)}
    </span>
{/snippet}

{#snippet bytesInCell(row: ActiveHotspotUser)}
    <span class="font-mono text-sky-600 dark:text-sky-400 font-semibold">
        {row.bytes_in_formatted || formatBytes(row.bytes_in)}
    </span>
{/snippet}

{#snippet actionsCell(row: ActiveHotspotUser)}
    <div class="text-right">
        <button
            type="button"
            onclick={() => handleKick(row)}
            disabled={isKicking === row.id}
            class="inline-flex items-center space-x-1 px-2.5 py-1 rounded-md bg-destructive/10 hover:bg-destructive/20 text-destructive text-[11px] font-semibold border border-destructive/20 transition-colors cursor-pointer disabled:opacity-50"
            title="Disconnect user session"
        >
            {#if isKicking === row.id}
                <RefreshCw class="w-3 h-3 animate-spin" />
            {:else}
                <UserX class="w-3 h-3" />
            {/if}
            <span>Kick</span>
        </button>
    </div>
{/snippet}

<div class="rounded-2xl border border-border bg-card shadow-xs overflow-hidden flex flex-col">
    <!-- Table Header & Controls -->
    <div class="p-4 sm:p-5 border-b border-border flex flex-col md:flex-row md:items-center justify-between gap-4 bg-card">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-xl bg-sky-500/10 text-sky-500 flex items-center justify-center shrink-0">
                <Users class="w-5 h-5" />
            </div>
            <div>
                <div class="flex items-center space-x-2">
                    <h3 class="text-sm font-bold text-foreground tracking-tight">{localeStore.t('dashboard.activeHotspotUsers')}</h3>
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">
                        {users.length} {localeStore.t('common.online')}
                    </span>
                </div>
                <p class="text-xs text-muted-foreground mt-0.5">
                    {localeStore.t('dashboard.activeSessionsSubtitle')} {routerContextStore.activeRouter?.name || 'Selected Router'}
                </p>
            </div>
        </div>

        <!-- Actions Bar -->
        <div class="flex flex-wrap items-center gap-2.5">
            <!-- Search input -->
            <div class="relative w-full sm:w-60">
                <Search class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                <input
                    bind:value={searchQuery}
                    oninput={(e) => onSearchInput((e.target as HTMLInputElement).value)}
                    type="text"
                    placeholder="{localeStore.t('common.search')}..."
                    class="w-full pl-8 pr-3 py-1.5 text-xs rounded-lg border border-border bg-background focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 text-foreground transition-all"
                />
            </div>

            <!-- Auto Refresh Switcher -->
            <button
                type="button"
                onclick={() => (autoRefresh = !autoRefresh)}
                class="px-2.5 py-1.5 rounded-lg border text-xs font-medium inline-flex items-center space-x-1.5 transition-colors cursor-pointer {autoRefresh
                    ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-600 dark:text-emerald-400'
                    : 'bg-muted border-border text-muted-foreground'}"
                title={autoRefresh ? 'Auto-refresh every 10s (Click to pause)' : 'Auto-refresh paused (Click to resume)'}
            >
                <span class="w-2 h-2 rounded-full {autoRefresh ? 'bg-emerald-500 shadow-xs shadow-emerald-500/50' : 'bg-muted-foreground'}"></span>
                <span class="text-[11px] font-mono">{autoRefresh ? localeStore.t('dashboard.auto10s') : localeStore.t('dashboard.paused')}</span>
            </button>

            <!-- Manual Refresh Button -->
            <button
                type="button"
                onclick={() => fetchActiveUsers(true)}
                disabled={isLoading || routerContextStore.isOffline}
                class="p-2 rounded-lg border border-border hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer disabled:opacity-50"
                title={routerContextStore.isOffline ? localeStore.t('dashboard.offlineDesc') : localeStore.t('common.refresh')}
            >
                <RefreshCw class="w-3.5 h-3.5 {isLoading ? 'animate-spin' : ''}" />
            </button>
        </div>
    </div>

    <!-- Alerts -->
    {#if errorMessage}
        <div class="mx-5 mt-4 p-3 rounded-xl bg-destructive/10 border border-destructive/20 text-destructive text-xs flex items-center space-x-2">
            <AlertCircle class="w-4 h-4 shrink-0" />
            <span>{errorMessage}</span>
        </div>
    {/if}

    <!-- Active Sessions Table -->
    <div class="p-4 sm:p-5">
        <DataTable
            {columns}
            data={filteredUsers}
            isLoading={isLoading && users.length === 0}
            pageSize={15}
            emptyMessage={localeStore.t('dashboard.noActiveUsers')}
            customCells={{
                user: userCell,
                address: addressCell,
                mac_address: macCell,
                uptime: uptimeCell,
                bytes_out: bytesOutCell,
                bytes_in: bytesInCell,
                actions: actionsCell,
            }}
        />
    </div>
</div>

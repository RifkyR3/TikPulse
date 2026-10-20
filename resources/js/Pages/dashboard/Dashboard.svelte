<script lang="ts">
    import { onMount, onDestroy } from 'svelte';
    import AdminLayout from '@/components/layout/AdminLayout.svelte';
    import InterfaceTrafficChart from '@/components/dashboard/InterfaceTrafficChart.svelte';
    import ActiveHotspotTable from '@/components/dashboard/ActiveHotspotTable.svelte';
    import { routerContextStore } from '@/stores/routerContext.svelte';
    import { safeModeStore } from '@/stores/safeMode.svelte';
    import { localeStore } from '@/stores/locale.svelte';
    import { usePageVisibility } from '@/utils/pageVisibility';
    import apiClient from '@/services/api';
    import type { ApiResponse } from '@/types/api';
    import type { RouterItem } from '@/types/router';
    import { toast } from 'svelte-sonner';
    import {
        Cpu,
        HardDrive,
        Clock,
        ShieldAlert,
        ShieldCheck,
        RefreshCw,
        Layers,
        WifiOff,
    } from '@lucide/svelte';

    interface TelemetryData {
        uptime?: string;
        version?: string;
        cpu_load?: number | string;
        free_memory?: number | string;
        total_memory?: number | string;
        free_hdd_space?: number | string;
        total_hdd_space?: number | string;
        board_name?: string;
        architecture_name?: string;
        cpu_count?: number | string;
        cpu_frequency?: number | string;
        [key: string]: unknown;
    }

    const { isPageVisible, onVisibilityChange } = usePageVisibility();

    let telemetry = $state<TelemetryData | null>(null);
    let isSyncing = $state(false);
    let syncError = $state<string | null>(null);
    let isInitialMount = $state(true);
    let telemetryPollInterval: any = null;

    function getNum(v: unknown): number {
        if (typeof v === 'number') return v;
        if (typeof v === 'string') {
            const n = parseInt(v, 10);
            return isNaN(n) ? 0 : n;
        }
        return 0;
    }

    const rawTelemetry = $derived((telemetry || {}) as Record<string, unknown>);

    const totalMemory = $derived.by(() => {
        const val =
            rawTelemetry.total_memory ??
            rawTelemetry['total-memory'] ??
            routerContextStore.activeRouter?.total_memory_bytes ??
            routerContextStore.activeRouter?.total_memory;
        return getNum(val);
    });

    const freeMemory = $derived.by(() => {
        const val =
            rawTelemetry.free_memory ??
            rawTelemetry['free-memory'] ??
            routerContextStore.activeRouter?.free_memory_bytes ??
            routerContextStore.activeRouter?.free_memory;
        return getNum(val);
    });

    const memoryUsagePercent = $derived.by(() => {
        const total = totalMemory;
        const free = freeMemory;
        if (total > 0 && free >= 0) {
            const used = Math.max(0, total - free);
            return Math.min(100, Math.max(0, Math.round((used / total) * 100)));
        }
        return routerContextStore.activeRouter?.memory_usage_percent || 0;
    });

    const cpuLoad = $derived.by(() => {
        const val = rawTelemetry.cpu_load ?? rawTelemetry['cpu-load'] ?? routerContextStore.activeRouter?.cpu_load;
        return getNum(val);
    });

    const cpuCount = $derived.by(() => {
        const val = rawTelemetry.cpu_count ?? rawTelemetry['cpu-count'];
        const n = getNum(val);
        return n > 0 ? n : 1;
    });

    const boardName = $derived.by(() => {
        const val = rawTelemetry.board_name ?? rawTelemetry['board-name'];
        return (val as string) || routerContextStore.activeRouter?.board_model || routerContextStore.activeRouter?.board_name || routerContextStore.activeRouter?.name || 'MikroTik';
    });

    const architectureName = $derived.by(() => {
        const val = rawTelemetry.architecture_name ?? rawTelemetry['architecture-name'];
        return (val as string) || 'N/A';
    });

    const systemVersion = $derived.by(() => {
        const val = rawTelemetry.version;
        return (val as string) || routerContextStore.activeRouter?.ros_version || 'v7.x';
    });

    const uptimeStr = $derived.by(() => {
        const val = rawTelemetry.uptime;
        return (val as string) || routerContextStore.activeRouter?.uptime || 'Ready';
    });

    const formattedMemory = $derived.by(() => {
        const total = totalMemory;
        const free = freeMemory;
        if (total <= 0) {
            return { used: '-', total: '-' };
        }
        const formatBytes = (bytes: number): string => {
            if (bytes >= 1024 * 1024 * 1024) return (bytes / (1024 * 1024 * 1024)).toFixed(1) + ' GB';
            if (bytes >= 1024 * 1024) return Math.round(bytes / (1024 * 1024)) + ' MB';
            if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';
            return bytes + ' B';
        };
        const used = Math.max(0, total - free);
        return {
            used: formatBytes(used),
            total: formatBytes(total),
        };
    });

    async function fetchLiveTelemetry(showLoading = false, isManual = false) {
        if (!routerContextStore.activeRouter) return;
        if (
            !showLoading &&
            (routerContextStore.isPollingPaused ||
                routerContextStore.isOffline ||
                routerContextStore.consecutiveFailures >= routerContextStore.maxRetries) &&
            !routerContextStore.isReconnecting
        ) {
            return;
        }

        if (showLoading) isSyncing = true;
        syncError = null;

        try {
            const response = await apiClient.get<
                ApiResponse<{ router: RouterItem; telemetry: TelemetryData; is_polling_paused?: boolean }>
            >(`/routers/${routerContextStore.activeRouter.id}/telemetry`);

            if (response.data.status === 'success') {
                telemetry = response.data.data.telemetry;
                routerContextStore.recordSuccess();
                if (response.data.data.router) {
                    routerContextStore.updateRouterInState(response.data.data.router);
                }
                if (response.data.data.telemetry) {
                    routerContextStore.updateLiveTelemetry(response.data.data.telemetry);
                }
                if (isManual) {
                    toast.success('MikroTik telemetry synchronized.');
                }
            } else if (response.data.data?.is_polling_paused) {
                routerContextStore.recordFailure('Router offline');
                if (isManual) {
                    toast.error('Router is offline.');
                }
            }
        } catch (err: unknown) {
            const axiosErr = err as { response?: { data?: { message?: string } } };
            syncError = axiosErr.response?.data?.message || 'Failed to sync live telemetry';
            routerContextStore.recordFailure(syncError);
            if (isManual) {
                toast.error(syncError);
            }
        } finally {
            if (showLoading) isSyncing = false;
        }
    }

    async function handleManualReconnect() {
        syncError = null;
        const ok = await routerContextStore.reconnectRouter();
        if (ok && routerContextStore.activeRouter) {
            await Promise.all([
                fetchLiveTelemetry(true, true),
                safeModeStore.fetchStatus(routerContextStore.activeRouter.id),
            ]);
            resetPollInterval();
        }
    }

    function resetPollInterval() {
        if (telemetryPollInterval) {
            clearInterval(telemetryPollInterval);
            telemetryPollInterval = null;
        }
        if (
            routerContextStore.isPollingPaused ||
            routerContextStore.isOffline ||
            routerContextStore.consecutiveFailures >= routerContextStore.maxRetries
        ) {
            return;
        }
        const sec = routerContextStore.refreshIntervalSeconds;
        if (sec > 0 && isPageVisible.value) {
            telemetryPollInterval = setInterval(() => {
                if (
                    routerContextStore.activeRouter &&
                    !routerContextStore.isPollingPaused &&
                    !routerContextStore.isOffline &&
                    routerContextStore.consecutiveFailures < routerContextStore.maxRetries &&
                    isPageVisible.value
                ) {
                    fetchLiveTelemetry(false, false);
                }
            }, sec * 1000);
        }
    }

    function handleIntervalChange(event: Event) {
        const val = Number((event.target as HTMLSelectElement).value);
        routerContextStore.setRefreshInterval(val);
        resetPollInterval();
    }

    let currentActiveRouterId: number | null = null;

    onMount(async () => {
        await routerContextStore.fetchRouters();
        if (routerContextStore.activeRouter) {
            currentActiveRouterId = routerContextStore.activeRouter.id;
            await Promise.all([
                fetchLiveTelemetry(true, false),
                safeModeStore.fetchStatus(routerContextStore.activeRouter.id),
            ]);
            resetPollInterval();
        }
        isInitialMount = false;

        onVisibilityChange((visible) => {
            if (visible) {
                if (
                    routerContextStore.activeRouter &&
                    !routerContextStore.isPollingPaused &&
                    !routerContextStore.isOffline &&
                    routerContextStore.consecutiveFailures < routerContextStore.maxRetries
                ) {
                    fetchLiveTelemetry(false, false);
                }
                resetPollInterval();
            } else {
                if (telemetryPollInterval) {
                    clearInterval(telemetryPollInterval);
                    telemetryPollInterval = null;
                }
            }
        });
    });

    onDestroy(() => {
        if (telemetryPollInterval) clearInterval(telemetryPollInterval);
    });

    $effect(() => {
        const id = routerContextStore.activeRouterId;
        if (id && id !== currentActiveRouterId) {
            currentActiveRouterId = id;
            telemetry = null;
            Promise.all([
                fetchLiveTelemetry(false, false),
                safeModeStore.fetchStatus(id),
            ]).then(() => resetPollInterval());
        }
    });
</script>

<AdminLayout>
    <!-- Welcome & Safe Mode Bar -->
    <div class="relative overflow-hidden rounded-2xl border border-border bg-gradient-to-br from-card via-card to-muted/20 p-6 shadow-xs">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">
                        Enterprise Console
                    </span>
                    <span class="text-xs text-muted-foreground font-mono">
                        {routerContextStore.activeRouter?.name || 'TikPulse Router'} ({routerContextStore.activeRouter?.host || routerContextStore.activeRouter?.ip_address || '127.0.0.1'})
                    </span>
                </div>
                <h2 class="text-2xl font-black tracking-tight text-foreground">
                    {localeStore.t('dashboard.title')}
                </h2>
                <p class="text-xs text-muted-foreground max-w-2xl">
                    {localeStore.t('dashboard.subtitle')}
                </p>
            </div>

            <!-- Right Side Actions & Safe Mode Warning Banner -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                {#if safeModeStore.status}
                    <div
                        class="flex items-center space-x-2 px-3.5 py-2 rounded-xl text-xs font-semibold border transition-all shadow-2xs {safeModeStore.isUnlocked
                            ? 'bg-amber-500/10 border-amber-500/30 text-amber-600 dark:text-amber-400'
                            : 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400'}"
                    >
                        {#if safeModeStore.isUnlocked}
                            <ShieldAlert class="w-4 h-4 text-amber-500 shrink-0" />
                        {:else}
                            <ShieldCheck class="w-4 h-4 text-emerald-500 shrink-0" />
                        {/if}
                        <div class="flex flex-col text-left">
                            <span>{safeModeStore.isUnlocked ? localeStore.t('safeMode.safeModeUnlocked') : localeStore.t('safeMode.safeModeActive')}</span>
                            {#if safeModeStore.isUnlocked}
                                <span class="text-[10px] font-mono opacity-80">
                                    {localeStore.t('safeMode.revertsIn')} {safeModeStore.formattedTimeRemaining}
                                </span>
                            {:else}
                                <span class="text-[10px] opacity-80">
                                    {localeStore.t('safeMode.destructiveBlocked')}
                                </span>
                            {/if}
                        </div>
                    </div>
                {/if}

                <!-- Auto-Refresh Interval Selector -->
                <div class="flex items-center space-x-1.5 px-3 py-2 rounded-xl border border-border bg-card text-xs font-medium text-foreground">
                    <Clock class="w-3.5 h-3.5 text-muted-foreground shrink-0" />
                    <span class="text-muted-foreground text-[11px] font-medium hidden sm:inline">{localeStore.t('dashboard.refreshRate')}:</span>
                    <select
                        value={routerContextStore.refreshIntervalSeconds}
                        onchange={handleIntervalChange}
                        class="bg-transparent text-xs font-semibold text-foreground focus:outline-none cursor-pointer"
                        title="Auto-refresh interval rate"
                    >
                        <option value={0}>{localeStore.t('dashboard.pause')}</option>
                        <option value={5}>5s</option>
                        <option value={10}>10s</option>
                        <option value={15}>15s (Default)</option>
                        <option value={30}>30s</option>
                        <option value={60}>60s</option>
                    </select>
                </div>

                <button
                    type="button"
                    onclick={() => fetchLiveTelemetry(true, true)}
                    disabled={isSyncing || !routerContextStore.activeRouter}
                    class="px-3 py-2 rounded-xl border border-border bg-card hover:bg-muted text-xs font-medium text-foreground transition-all cursor-pointer flex items-center space-x-1.5 disabled:opacity-50"
                    title={localeStore.t('dashboard.syncTelemetry')}
                >
                    <RefreshCw class="w-3.5 h-3.5 {isSyncing ? 'animate-spin' : ''}" />
                    <span>{localeStore.t('dashboard.syncTelemetry')}</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Offline & Polling Paused Banner -->
    {#if routerContextStore.isPollingPaused || routerContextStore.activeRouter?.status === 'offline'}
        <div
            class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-5 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4"
        >
            <div class="flex items-start space-x-3.5">
                <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                    <WifiOff class="w-5 h-5" />
                </div>
                <div class="space-y-0.5">
                    <div class="flex items-center space-x-2">
                        <h4 class="font-bold text-sm text-foreground">
                            {localeStore.t('dashboard.offlineTitle')}
                        </h4>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-amber-500/20 text-amber-700 dark:text-amber-300">
                            {routerContextStore.consecutiveFailures}/{routerContextStore.maxRetries} {localeStore.t('dashboard.retryLimit')}
                        </span>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {localeStore.t('dashboard.offlineDesc')}
                    </p>
                </div>
            </div>

            <button
                type="button"
                onclick={handleManualReconnect}
                disabled={routerContextStore.isReconnecting}
                class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs shadow-md shadow-amber-600/20 flex items-center space-x-2 transition-all cursor-pointer disabled:opacity-50 shrink-0"
            >
                <RefreshCw class="w-4 h-4 {routerContextStore.isReconnecting ? 'animate-spin' : ''}" />
                <span>{routerContextStore.isReconnecting ? localeStore.t('common.connecting') : localeStore.t('common.reconnect')}</span>
            </button>
        </div>
    {/if}

    <!-- Telemetry Gauges / Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- CPU Load Card -->
        <div class="rounded-2xl border border-border bg-card p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">{localeStore.t('dashboard.cpuLoad')}</span>
                <div class="w-9 h-9 rounded-xl bg-sky-500/10 text-sky-500 flex items-center justify-center">
                    <Cpu class="w-4 h-4" />
                </div>
            </div>
            <div class="flex items-baseline space-x-2">
                <span class="text-2xl font-black text-foreground tracking-tight font-mono">{cpuLoad}%</span>
                <span class="text-xs text-muted-foreground">{localeStore.t('dashboard.utilized')}</span>
            </div>
            <div class="w-full bg-muted rounded-full h-2 overflow-hidden">
                <div
                    class="h-full rounded-full transition-all duration-500 {cpuLoad > 85
                        ? 'bg-destructive'
                        : cpuLoad > 60
                          ? 'bg-amber-500'
                          : 'bg-sky-500'}"
                    style="width: {cpuLoad}%"
                ></div>
            </div>
            <div class="flex items-center justify-between text-[11px] text-muted-foreground font-mono">
                <span>Arch: {architectureName}</span>
                <span>{cpuCount} {cpuCount > 1 ? 'Cores' : 'Core'}</span>
            </div>
        </div>

        <!-- RAM / Memory Card -->
        <div class="rounded-2xl border border-border bg-card p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">{localeStore.t('dashboard.memoryRam')}</span>
                <div class="w-9 h-9 rounded-xl bg-indigo-500/10 text-indigo-500 flex items-center justify-center">
                    <HardDrive class="w-4 h-4" />
                </div>
            </div>
            <div class="flex items-baseline space-x-2">
                <span class="text-2xl font-black text-foreground tracking-tight font-mono">{memoryUsagePercent}%</span>
                <span class="text-xs text-muted-foreground">{localeStore.t('dashboard.used')}</span>
            </div>
            <div class="w-full bg-muted rounded-full h-2 overflow-hidden">
                <div
                    class="h-full rounded-full transition-all duration-500 {memoryUsagePercent > 85
                        ? 'bg-destructive'
                        : memoryUsagePercent > 60
                          ? 'bg-amber-500'
                          : 'bg-indigo-500'}"
                    style="width: {memoryUsagePercent}%"
                ></div>
            </div>
            <div class="flex items-center justify-between text-[11px] text-muted-foreground font-mono">
                <span>{formattedMemory.used} {localeStore.t('dashboard.used')}</span>
                <span>{formattedMemory.total} {localeStore.t('dashboard.total')}</span>
            </div>
        </div>

        <!-- System Uptime Card -->
        <div class="rounded-2xl border border-border bg-card p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">{localeStore.t('dashboard.systemUptime')}</span>
                <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center">
                    <Clock class="w-4 h-4" />
                </div>
            </div>
            <div>
                <span class="text-xl font-black text-foreground tracking-tight font-mono block truncate">
                    {uptimeStr}
                </span>
                <span class="text-xs text-muted-foreground mt-0.5 block">{localeStore.t('dashboard.continuousRuntime')}</span>
            </div>
            <div class="pt-1 flex items-center space-x-1.5 text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">
                <span class="w-2 h-2 rounded-full bg-emerald-500 shadow-xs shadow-emerald-500/50"></span>
                <span>{localeStore.t('dashboard.syncedRos')}</span>
            </div>
        </div>

        <!-- Model & Version Card -->
        <div class="rounded-2xl border border-border bg-card p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">{localeStore.t('dashboard.deviceModel')}</span>
                <div class="w-9 h-9 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center">
                    <Layers class="w-4 h-4" />
                </div>
            </div>
            <div>
                <span class="text-base font-black text-foreground tracking-tight block truncate font-mono">
                    {boardName}
                </span>
                <span class="text-xs text-muted-foreground mt-0.5 block font-mono">
                    ROS {systemVersion}
                </span>
            </div>
            <div class="pt-1 flex items-center justify-between text-[11px] text-muted-foreground font-mono">
                <span>{localeStore.t('dashboard.protocol')}: <strong class="text-foreground uppercase">{routerContextStore.activeRouter?.protocol || 'socket'}</strong></span>
                <span>{localeStore.t('dashboard.port')}: <strong class="text-foreground">{routerContextStore.activeRouter?.port || '8728'}</strong></span>
            </div>
        </div>
    </div>

    <!-- Live Real-Time Bandwidth Traffic Visualizer -->
    <InterfaceTrafficChart />

    <!-- Real-Time Hotspot Active Sessions Table -->
    <ActiveHotspotTable />
</AdminLayout>

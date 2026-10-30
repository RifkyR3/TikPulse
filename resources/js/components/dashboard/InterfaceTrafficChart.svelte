<script lang="ts">
    import { onMount, onDestroy } from 'svelte';
    import type ApexCharts from 'apexcharts';
    import 'apexcharts/dist/apexcharts.css';
    import { routerContextStore } from '@/stores/routerContext.svelte';
    import { localeStore } from '@/stores/locale.svelte';
    import { themeStore } from '@/stores/theme.svelte';
    import { usePageVisibility } from '@/utils/pageVisibility';
    import apiClient from '@/services/api';
    import type { ApiResponse } from '@/types/api';
    import { Activity, ArrowDown, ArrowUp, Play, Pause } from '@lucide/svelte';

    interface InterfaceItem {
        name: string;
        type?: string;
        comment?: string | null;
        running?: boolean;
        disabled?: boolean;
        label: string;
    }

    const { isPageVisible, onVisibilityChange } = usePageVisibility();

    let currentInterface = $state('ether1');
    let availableInterfaces = $state<InterfaceItem[]>([]);
    let isFetchingInterfaces = $state<boolean>(false);
    let rxBps = $state(0);
    let txBps = $state(0);
    let isStreamPaused = $state(false);

    let chartContainerRef: HTMLDivElement | null = $state(null);
    let chartInstance: ApexCharts | null = null;
    let pollTimer: any = null;

    const maxDataPoints = 16;
    const labels: string[] = [];
    const rxData: number[] = [];
    const txData: number[] = [];

    for (let i = 0; i < maxDataPoints; i++) {
        labels.push('');
        rxData.push(0);
        txData.push(0);
    }

    function getDefaultInterfaces(): InterfaceItem[] {
        const model = (
            routerContextStore.activeRouter?.board_model ||
            routerContextStore.activeRouter?.board_name ||
            routerContextStore.activeRouter?.name ||
            ''
        ).toUpperCase();

        if (model.includes('HEX') || model.includes('750') || model.includes('GR3')) {
            return [
                { name: 'ether1', label: 'ether1 (WAN)', running: true },
                { name: 'ether2', label: 'ether2 (LAN)', running: false },
                { name: 'ether3', label: 'ether3', running: false },
                { name: 'ether4', label: 'ether4', running: false },
                { name: 'ether5', label: 'ether5', running: true },
            ];
        }
        if (model.includes('4011') || model.includes('3011') || model.includes('2011')) {
            const list: InterfaceItem[] = [];
            for (let i = 1; i <= 10; i++) {
                list.push({ name: `ether${i}`, label: `ether${i}${i === 1 ? ' (WAN)' : ''}`, running: i === 1 });
            }
            list.push({ name: 'sfp-sfpplus1', label: 'sfp-sfpplus1 (Fiber)', running: false });
            return list;
        }
        return [
            { name: 'ether1', label: 'ether1', running: true },
            { name: 'ether2', label: 'ether2', running: false },
            { name: 'ether3', label: 'ether3', running: false },
        ];
    }

    async function fetchInterfaces() {
        if (!routerContextStore.activeRouter) {
            availableInterfaces = getDefaultInterfaces();
            return;
        }

        try {
            isFetchingInterfaces = true;
            const res = await apiClient.get<ApiResponse<InterfaceItem[]>>(`/routers/${routerContextStore.activeRouter.id}/interfaces`);
            if (res.data.status === 'success' && Array.isArray(res.data.data) && res.data.data.length > 0) {
                availableInterfaces = res.data.data;
            } else {
                availableInterfaces = getDefaultInterfaces();
            }
        } catch {
            if (availableInterfaces.length === 0) {
                availableInterfaces = getDefaultInterfaces();
            }
        } finally {
            isFetchingInterfaces = false;
            if (availableInterfaces.length > 0) {
                const exists = availableInterfaces.some((i) => i.name === currentInterface);
                if (!exists) {
                    const running = availableInterfaces.find((i) => i.running && !i.disabled);
                    currentInterface = running ? running.name : availableInterfaces[0].name;
                }
            }
        }
    }

    function formatBps(bps: number): string {
        if (bps >= 1_000_000_000) return (bps / 1_000_000_000).toFixed(2) + ' Gbps';
        if (bps >= 1_000_000) return (bps / 1_000_000).toFixed(2) + ' Mbps';
        if (bps >= 1_000) return (bps / 1_000).toFixed(1) + ' Kbps';
        return bps + ' bps';
    }

    let ApexChartsConstructor: any = null;

    async function initChart() {
        if (!chartContainerRef) return;

        if (chartInstance) {
            chartInstance.destroy();
            chartInstance = null;
        }

        if (!ApexChartsConstructor) {
            const mod = await import('apexcharts');
            ApexChartsConstructor = mod.default || mod;
        }

        const isDark = themeStore.isDark;

        const options: ApexCharts.ApexOptions = {
            chart: {
                type: 'area',
                height: 160,
                parentHeightOffset: 0,
                animations: {
                    enabled: false,
                    dynamicAnimation: {
                        enabled: false,
                    },
                },
                toolbar: {
                    show: false,
                },
                zoom: {
                    enabled: false,
                },
                background: 'transparent',
                fontFamily: 'inherit',
            },
            stroke: {
                curve: 'smooth',
                width: 2,
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.35,
                    opacityTo: 0.05,
                    stops: [0, 90, 100],
                },
            },
            colors: ['#0284c7', '#6366f1'],
            series: [
                {
                    name: 'Rx (Download)',
                    data: [...rxData],
                },
                {
                    name: 'Tx (Upload)',
                    data: [...txData],
                },
            ],
            xaxis: {
                categories: [...labels],
                labels: {
                    show: false,
                },
                axisBorder: {
                    show: false,
                },
                axisTicks: {
                    show: false,
                },
                tooltip: {
                    enabled: false,
                },
                crosshairs: {
                    show: true,
                    stroke: {
                        color: isDark ? 'rgba(255, 255, 255, 0.2)' : 'rgba(0, 0, 0, 0.15)',
                        dashArray: 3,
                    },
                },
            },
            yaxis: {
                min: 0,
                forceNiceScale: true,
                labels: {
                    formatter: (val: number) => formatBps(val),
                    style: {
                        colors: '#888888',
                        fontSize: '9px',
                        fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
                    },
                },
            },
            grid: {
                borderColor: isDark ? 'rgba(255, 255, 255, 0.06)' : 'rgba(0, 0, 0, 0.06)',
                strokeDashArray: 3,
                padding: {
                    top: -12,
                    right: 8,
                    bottom: -6,
                    left: 10,
                },
            },
            markers: {
                size: 0,
                hover: {
                    size: 4,
                },
            },
            dataLabels: {
                enabled: false,
            },
            legend: {
                show: false,
            },
            theme: {
                mode: isDark ? 'dark' : 'light',
            },
            tooltip: {
                theme: isDark ? 'dark' : 'light',
                shared: true,
                intersect: false,
                y: {
                    formatter: (val: number) => formatBps(val ?? 0),
                },
            },
        };

        chartInstance = new ApexChartsConstructor(chartContainerRef, options);
        chartInstance.render();
    }

    async function fetchTraffic(force = false) {
        if (!routerContextStore.activeRouter) return;
        if (
            routerContextStore.isPollingPaused ||
            routerContextStore.isOffline ||
            routerContextStore.consecutiveFailures >= routerContextStore.maxRetries ||
            isStreamPaused ||
            !isPageVisible.value
        ) {
            rxBps = 0;
            txBps = 0;
            return;
        }

        try {
            const res = await apiClient.get(`/routers/${routerContextStore.activeRouter.id}/traffic`, {
                params: { interface: currentInterface },
            });

            if (res.data.status === 'success' && res.data.data) {
                rxBps = Number(res.data.data.rx_bps) || 0;
                txBps = Number(res.data.data.tx_bps) || 0;

                const now = new Date();
                const timeStr = `${now.getMinutes()}:${String(now.getSeconds()).padStart(2, '0')}`;

                labels.push(timeStr);
                rxData.push(rxBps);
                txData.push(txBps);

                if (labels.length > maxDataPoints) {
                    labels.shift();
                    rxData.shift();
                    txData.shift();
                }

                if (chartInstance && isPageVisible.value && !isStreamPaused && !routerContextStore.isOffline) {
                    chartInstance.updateOptions(
                        {
                            series: [
                                { name: 'Rx (Download)', data: [...rxData] },
                                { name: 'Tx (Upload)', data: [...txData] },
                            ],
                            xaxis: {
                                categories: [...labels],
                            },
                        },
                        false,
                        false
                    );
                }
            } else if (res.data.is_polling_paused) {
                routerContextStore.recordFailure('Router offline');
            }
        } catch {
            rxBps = 0;
            txBps = 0;
            routerContextStore.recordFailure('Traffic query failed');
        }
    }

    function resetTrafficTimer() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        if (
            !isStreamPaused &&
            !routerContextStore.isPollingPaused &&
            !routerContextStore.isOffline &&
            routerContextStore.consecutiveFailures < routerContextStore.maxRetries &&
            isPageVisible.value
        ) {
            pollTimer = setInterval(() => {
                if (
                    !isStreamPaused &&
                    !routerContextStore.isPollingPaused &&
                    !routerContextStore.isOffline &&
                    routerContextStore.consecutiveFailures < routerContextStore.maxRetries &&
                    isPageVisible.value
                ) {
                    fetchTraffic(false);
                }
            }, 3000);
        }
    }

    function toggleStream() {
        isStreamPaused = !isStreamPaused;
        if (!isStreamPaused && !routerContextStore.isOffline && !routerContextStore.isPollingPaused && routerContextStore.consecutiveFailures < routerContextStore.maxRetries) {
            fetchTraffic(true);
        }
        resetTrafficTimer();
    }

    onMount(async () => {
        initChart();
        await fetchInterfaces();
        if (!routerContextStore.isPollingPaused && !routerContextStore.isOffline && routerContextStore.consecutiveFailures < routerContextStore.maxRetries) {
            fetchTraffic(true);
            resetTrafficTimer();
        }

        onVisibilityChange((visible) => {
            if (visible && !isStreamPaused && !routerContextStore.isPollingPaused && !routerContextStore.isOffline && routerContextStore.consecutiveFailures < routerContextStore.maxRetries) {
                fetchTraffic(false);
                resetTrafficTimer();
            } else {
                if (pollTimer) {
                    clearInterval(pollTimer);
                    pollTimer = null;
                }
            }
        });
    });

    onDestroy(() => {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        if (chartInstance) {
            chartInstance.destroy();
            chartInstance = null;
        }
    });

    // Reactively update ApexCharts theme when dark/light mode changes
    $effect(() => {
        const isDark = themeStore.isDark;
        if (chartInstance) {
            chartInstance.updateOptions(
                {
                    theme: { mode: isDark ? 'dark' : 'light' },
                    tooltip: { theme: isDark ? 'dark' : 'light' },
                    grid: {
                        borderColor: isDark ? 'rgba(255, 255, 255, 0.06)' : 'rgba(0, 0, 0, 0.06)',
                    },
                    xaxis: {
                        crosshairs: {
                            stroke: {
                                color: isDark ? 'rgba(255, 255, 255, 0.2)' : 'rgba(0, 0, 0, 0.15)',
                            },
                        },
                    },
                },
                false,
                false
            );
        }
    });

    let currentChartRouterId: number | null = null;

    $effect(() => {
        const id = routerContextStore.activeRouterId;
        if (id && id !== currentChartRouterId) {
            currentChartRouterId = id;
            labels.length = 0;
            rxData.length = 0;
            txData.length = 0;
            for (let i = 0; i < maxDataPoints; i++) {
                labels.push('');
                rxData.push(0);
                txData.push(0);
            }
            if (chartInstance) {
                chartInstance.updateOptions(
                    {
                        series: [
                            { name: 'Rx (Download)', data: [...rxData] },
                            { name: 'Tx (Upload)', data: [...txData] },
                        ],
                        xaxis: {
                            categories: [...labels],
                        },
                    },
                    false,
                    false
                );
            }
            fetchInterfaces().then(() => {
                if (!routerContextStore.isOffline && !routerContextStore.isPollingPaused && routerContextStore.consecutiveFailures < routerContextStore.maxRetries) {
                    fetchTraffic(true);
                }
            });
        }
    });
</script>

<div class="rounded-2xl border border-border bg-card p-5 shadow-xs">
    <!-- Card Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-4">
        <div class="flex items-center space-x-2.5">
            <div class="w-8 h-8 rounded-lg bg-sky-500/10 text-sky-500 flex items-center justify-center shrink-0">
                <Activity class="w-4 h-4 text-sky-500" />
            </div>
            <div>
                <div class="flex items-center space-x-2">
                    <h3 class="text-sm font-bold text-foreground leading-tight">{localeStore.t('dashboard.liveWanTraffic')}</h3>
                    {#if routerContextStore.isPollingPaused || routerContextStore.isOffline}
                        <span
                            class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20"
                        >
                            {routerContextStore.isOffline ? localeStore.t('common.offline') : localeStore.t('dashboard.paused')}
                        </span>
                    {/if}
                </div>
                <span class="text-[11px] text-muted-foreground">{localeStore.t('dashboard.throughputSubtitle')}</span>
            </div>
        </div>

        <!-- Interface selector & Stream Pause/Resume -->
        <div class="flex items-center space-x-2">
            <button
                type="button"
                onclick={toggleStream}
                class="px-2.5 py-1.5 rounded-lg border text-xs font-semibold flex items-center space-x-1.5 cursor-pointer transition-colors {isStreamPaused
                    ? 'bg-amber-500/10 border-amber-500/20 text-amber-600 dark:text-amber-400'
                    : 'bg-card border-border hover:bg-muted text-foreground'}"
                title={isStreamPaused ? 'Resume live traffic chart' : 'Pause live traffic chart to save CPU & network'}
            >
                {#if isStreamPaused}
                    <Play class="w-3.5 h-3.5 text-amber-500 fill-amber-500" />
                {:else}
                    <Pause class="w-3.5 h-3.5 text-muted-foreground" />
                {/if}
                <span class="text-[11px] font-mono">{isStreamPaused ? 'Paused' : 'Live'}</span>
            </button>

            <select
                bind:value={currentInterface}
                onchange={() => fetchTraffic(true)}
                disabled={isFetchingInterfaces || availableInterfaces.length === 0}
                class="rounded-lg border border-border bg-background py-1.5 px-2.5 text-xs font-semibold text-foreground focus:outline-none focus:ring-1 focus:ring-sky-500 cursor-pointer disabled:opacity-60 max-w-[220px] truncate"
            >
                {#each availableInterfaces as iface (iface.name)}
                    <option value={iface.name}>
                        {iface.label}{iface.running ? ' ⚡' : iface.disabled ? ' (Disabled)' : ''}
                    </option>
                {/each}
            </select>
        </div>
    </div>

    <!-- Metric Badges -->
    <div class="grid grid-cols-2 gap-3 mb-4">
        <div class="rounded-xl border border-sky-500/20 bg-sky-500/5 p-3 flex items-center justify-between">
            <div>
                <div class="flex items-center space-x-1 text-[11px] font-semibold text-sky-600 dark:text-sky-400 uppercase tracking-wider">
                    <ArrowDown class="w-3.5 h-3.5" />
                    <span>{localeStore.t('dashboard.downloadRx')}</span>
                </div>
                <div class="text-lg font-black text-foreground font-mono mt-0.5">
                    {formatBps(rxBps)}
                </div>
            </div>
            <div class="h-2 w-2 rounded-full bg-sky-500 shadow-xs shadow-sky-500/50"></div>
        </div>

        <div class="rounded-xl border border-indigo-500/20 bg-indigo-500/5 p-3 flex items-center justify-between">
            <div>
                <div class="flex items-center space-x-1 text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">
                    <ArrowUp class="w-3.5 h-3.5" />
                    <span>{localeStore.t('dashboard.uploadTx')}</span>
                </div>
                <div class="text-lg font-black text-foreground font-mono mt-0.5">
                    {formatBps(txBps)}
                </div>
            </div>
            <div class="h-2 w-2 rounded-full bg-indigo-500 shadow-xs shadow-indigo-500/50"></div>
        </div>
    </div>

    <!-- Real-Time Bandwidth Visualizer (Powered by ApexCharts) -->
    <div class="relative w-full h-44 bg-background/50 rounded-xl border border-border/60 overflow-hidden p-1">
        <div bind:this={chartContainerRef} class="w-full h-full min-h-[160px]"></div>

        <!-- Chart Legend -->
        <div class="absolute bottom-2 right-3 z-10 flex items-center space-x-3 text-[10px] font-mono text-muted-foreground bg-card/90 backdrop-blur-xs px-2.5 py-1 rounded-md border border-border/80 shadow-xs pointer-events-none">
            <span class="flex items-center space-x-1.5">
                <span class="w-2 h-0.5 bg-sky-500 rounded-full inline-block"></span>
                <span>Rx (Download)</span>
            </span>
            <span class="flex items-center space-x-1.5">
                <span class="w-2 h-0.5 bg-indigo-500 rounded-full inline-block"></span>
                <span>Tx (Upload)</span>
            </span>
        </div>
    </div>
</div>

<style>
    :global(.apexcharts-tooltip) {
        background: var(--card) !important;
        border: 1px solid var(--border) !important;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1) !important;
        border-radius: 0.5rem !important;
        color: var(--foreground) !important;
    }
    :global(.apexcharts-tooltip-title) {
        background: var(--muted) !important;
        border-bottom: 1px solid var(--border) !important;
        font-family: ui-monospace, SFMono-Regular, monospace !important;
        font-size: 11px !important;
        font-weight: 600 !important;
        color: var(--foreground) !important;
    }
    :global(.apexcharts-tooltip-text-y-value),
    :global(.apexcharts-tooltip-text-y-label) {
        color: var(--foreground) !important;
        font-family: ui-monospace, SFMono-Regular, monospace !important;
        font-size: 11px !important;
    }
    :global(.apexcharts-xaxistooltip) {
        display: none !important;
    }
</style>

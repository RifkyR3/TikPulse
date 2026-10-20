<script lang="ts">
    import { onMount, onDestroy } from 'svelte';
    import Chart from 'chart.js/auto';
    import { routerContextStore } from '@/stores/routerContext.svelte';
    import { localeStore } from '@/stores/locale.svelte';
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

    let canvasRef: HTMLCanvasElement | null = $state(null);
    let chartInstance: Chart | null = null;
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

    function initChart() {
        if (!canvasRef) return;
        const ctx = canvasRef.getContext('2d');
        if (!ctx) return;

        const rxGradient = ctx.createLinearGradient(0, 0, 0, 140);
        rxGradient.addColorStop(0, 'rgba(14, 165, 233, 0.28)');
        rxGradient.addColorStop(1, 'rgba(14, 165, 233, 0.00)');

        const txGradient = ctx.createLinearGradient(0, 0, 0, 140);
        txGradient.addColorStop(0, 'rgba(99, 102, 241, 0.22)');
        txGradient.addColorStop(1, 'rgba(99, 102, 241, 0.00)');

        chartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Rx (Download)',
                        data: rxData,
                        borderColor: '#0284c7',
                        borderWidth: 2,
                        backgroundColor: rxGradient,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                    },
                    {
                        label: 'Tx (Upload)',
                        data: txData,
                        borderColor: '#6366f1',
                        borderWidth: 2,
                        backgroundColor: txGradient,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                resizeDelay: 300,
                devicePixelRatio: 1,
                animation: false,
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const val = Number(context.raw) || 0;
                                return ` ${context.dataset.label}: ${formatBps(val)}`;
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        display: false,
                        grid: { display: false },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(150, 150, 150, 0.08)' },
                        ticks: {
                            callback: (val) => formatBps(Number(val)),
                            font: { size: 9, family: 'monospace' },
                            maxTicksLimit: 4,
                            color: '#888888',
                        },
                    },
                },
            },
        });
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
                    chartInstance.update('none');
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
                chartInstance.update('none');
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

    <!-- HTML5 Canvas Hardware-Accelerated Chart (Powered by Chart.js) -->
    <div class="relative w-full h-40 bg-background/50 rounded-xl border border-border/60 overflow-hidden">
        <canvas bind:this={canvasRef} class="block w-full h-full"></canvas>

        <!-- Chart Legend -->
        <div class="absolute bottom-2 right-3 flex items-center space-x-3 text-[10px] font-mono text-muted-foreground bg-card px-2 py-1 rounded-md border border-border/80 shadow-xs pointer-events-none">
            <span class="flex items-center space-x-1">
                <span class="w-2 h-0.5 bg-sky-500 inline-block"></span>
                <span>Rx (Download)</span>
            </span>
            <span class="flex items-center space-x-1">
                <span class="w-2 h-0.5 bg-indigo-500 inline-block"></span>
                <span>Tx (Upload)</span>
            </span>
        </div>
    </div>
</div>

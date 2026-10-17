import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '@/services/api';
import type { Router, RouterItem } from '@/types/router';
import type { ApiResponse } from '@/types/api';

export const useRouterContextStore = defineStore('routerContext', () => {
    const routers = ref<Router[]>([]);
    const activeRouterId = ref<number | null>(
        localStorage.getItem('tikpulse_active_router_id')
            ? Number(localStorage.getItem('tikpulse_active_router_id'))
            : null
    );
    const isLoading = ref<boolean>(false);
    const isReconnecting = ref<boolean>(false);

    // Retry and Offline Circuit Breaker
    const consecutiveFailures = ref<number>(0);
    const maxRetries = ref<number>(3);
    const isPollingPaused = ref<boolean>(false);
    const pauseReason = ref<string | null>(null);

    // Configurable Auto-Refresh Interval (0 = Pause, 5, 10, 15 Default, 30, 60)
    const refreshIntervalSeconds = ref<number>(
        localStorage.getItem('tikpulse_refresh_interval') !== null
            ? Number(localStorage.getItem('tikpulse_refresh_interval'))
            : 15
    );

    const setRefreshInterval = (seconds: number): void => {
        refreshIntervalSeconds.value = seconds;
        localStorage.setItem('tikpulse_refresh_interval', String(seconds));
        if (seconds === 0) {
            isPollingPaused.value = true;
            pauseReason.value = 'Polling paused by user';
        } else {
            if (consecutiveFailures.value < maxRetries.value) {
                isPollingPaused.value = false;
                pauseReason.value = null;
            }
        }
    };

    const activeRouter = computed(() => {
        if (!activeRouterId.value) return routers.value[0] || null;
        return routers.value.find((r) => r.id === activeRouterId.value) || routers.value[0] || null;
    });

    const isOffline = computed(() => {
        return (
            activeRouter.value?.status === 'offline' ||
            isPollingPaused.value ||
            consecutiveFailures.value >= maxRetries.value
        );
    });

    const fetchRouters = async (): Promise<void> => {
        isLoading.value = true;
        try {
            const res = await apiClient.get<ApiResponse<Router[]> | Router[]>('/routers');
            const data = Array.isArray(res.data) ? res.data : (res.data.data || []);
            // Preserve live telemetry if already in memory
            routers.value = data.map((newRouter) => {
                const existing = routers.value.find((r) => r.id === newRouter.id);
                if (existing && existing.cpu_load !== null && existing.cpu_load !== undefined) {
                    return {
                        ...newRouter,
                        cpu_load: existing.cpu_load,
                        memory_usage_percent: existing.memory_usage_percent,
                        free_memory_bytes: existing.free_memory_bytes,
                        total_memory_bytes: existing.total_memory_bytes,
                        uptime: existing.uptime || newRouter.uptime,
                    };
                }
                return newRouter;
            });

            // If activeRouterId is not set or invalid, default to first router
            if (data.length > 0) {
                if (!activeRouterId.value || !data.some((r) => r.id === activeRouterId.value)) {
                    setActiveRouter(data[0].id);
                } else {
                    const current = data.find((r) => r.id === activeRouterId.value);
                    if (current?.status === 'offline') {
                        consecutiveFailures.value = maxRetries.value;
                        isPollingPaused.value = true;
                        pauseReason.value = 'Router is offline. Polling paused to prevent connection timeout loops.';
                    }
                }
            }
        } catch (err) {
            console.error('Failed to fetch routers:', err);
        } finally {
            isLoading.value = false;
        }
    };

    const setActiveRouter = (id: number): void => {
        activeRouterId.value = id;
        localStorage.setItem('tikpulse_active_router_id', String(id));
        // Clear old bypass token if switching router
        localStorage.removeItem('tikpulse_safemode_bypass_token');

        const r = routers.value.find((item) => item.id === id);
        if (r && r.status === 'offline') {
            consecutiveFailures.value = maxRetries.value;
            isPollingPaused.value = true;
            pauseReason.value = 'Router is offline. Polling paused to prevent connection timeout loops.';
        } else {
            resetRetryCount();
        }
    };

    const updateRouterInState = (updated: Router | RouterItem): void => {
        const index = routers.value.findIndex((r) => r.id === updated.id);
        if (index !== -1) {
            routers.value[index] = { ...routers.value[index], ...updated } as Router;
        }
    };

    /**
     * Synchronize live telemetry values immediately into the active router state.
     */
    const updateLiveTelemetry = (telemetryData: Record<string, unknown>): void => {
        if (!activeRouter.value) return;
        const cpu = telemetryData.cpu_load ?? telemetryData['cpu-load'];
        const freeMem = telemetryData.free_memory ?? telemetryData['free-memory'];
        const totalMem = telemetryData.total_memory ?? telemetryData['total-memory'];
        const uptime = telemetryData.uptime;
        const rosVersion = telemetryData.version;
        const boardName = telemetryData.board_name ?? telemetryData['board-name'];

        const numCpu = cpu !== undefined && cpu !== null ? Number(cpu) : activeRouter.value.cpu_load;
        const numFree = freeMem !== undefined && freeMem !== null ? Number(freeMem) : activeRouter.value.free_memory_bytes;
        const numTotal = totalMem !== undefined && totalMem !== null ? Number(totalMem) : activeRouter.value.total_memory_bytes;
        const memPercent = (numTotal && numTotal > 0 && numFree !== null && numFree !== undefined)
            ? Math.min(100, Math.max(0, Math.round(((numTotal - numFree) / numTotal) * 100)))
            : activeRouter.value.memory_usage_percent;

        const updated: Partial<Router> = {
            id: activeRouter.value.id,
            cpu_load: numCpu,
            free_memory_bytes: numFree,
            total_memory_bytes: numTotal,
            free_memory: numFree,
            total_memory: numTotal,
            memory_usage_percent: memPercent,
            uptime: (uptime as string) || activeRouter.value.uptime,
            ros_version: (rosVersion as string) || activeRouter.value.ros_version,
            board_model: (boardName as string) || activeRouter.value.board_model,
        };

        updateRouterInState(updated as Router);
    };

    /**
     * Record a connection failure and pause polling if max retries threshold reached.
     * Strictly capped at maxRetries so it never displays 4/3, 8/3, etc.
     */
    const recordFailure = (reason = 'Connection failed'): void => {
        if (consecutiveFailures.value >= maxRetries.value) {
            consecutiveFailures.value = maxRetries.value;
            isPollingPaused.value = true;
            return;
        }

        consecutiveFailures.value += 1;

        if (consecutiveFailures.value >= maxRetries.value) {
            consecutiveFailures.value = maxRetries.value;
            isPollingPaused.value = true;
            pauseReason.value = `Router offline. Polling paused after reaching limit (${maxRetries.value}/${maxRetries.value}).`;
            if (activeRouter.value) {
                activeRouter.value.status = 'offline';
            }
        }
    };

    /**
     * Record a successful connection and resume polling.
     */
    const recordSuccess = (): void => {
        consecutiveFailures.value = 0;
        isPollingPaused.value = false;
        pauseReason.value = null;
        if (activeRouter.value) {
            activeRouter.value.status = 'online';
        }
    };

    /**
     * Reset failure counter and unpause polling circuit (called on manual reconnect).
     */
    const resetRetryCount = (): void => {
        consecutiveFailures.value = 0;
        isPollingPaused.value = false;
        pauseReason.value = null;
    };

    /**
     * Explicit manual retry button handler.
     * Immediately resets circuit breaker so user can trigger reconnection.
     */
    const reconnectRouter = async (routerId?: number): Promise<boolean> => {
        const targetId = routerId || activeRouter.value?.id;
        if (!targetId) return false;

        // Reset failure counter on explicit reconnect request
        resetRetryCount();
        isReconnecting.value = true;

        try {
            const res = await apiClient.post<ApiResponse<{ success: boolean; ping_ms?: number }>>(
                `/routers/${targetId}/test-connection`
            );

            if (res.data.status === 'success') {
                recordSuccess();
                await fetchRouters();
                return true;
            } else {
                consecutiveFailures.value = maxRetries.value;
                isPollingPaused.value = true;
                pauseReason.value = 'Connection test returned error.';
                if (activeRouter.value) {
                    activeRouter.value.status = 'offline';
                }
                return false;
            }
        } catch {
            consecutiveFailures.value = maxRetries.value;
            isPollingPaused.value = true;
            pauseReason.value = 'Unable to reach router socket/REST API.';
            if (activeRouter.value) {
                activeRouter.value.status = 'offline';
            }
            return false;
        } finally {
            isReconnecting.value = false;
        }
    };

    return {
        routers,
        activeRouterId,
        activeRouter,
        isLoading,
        isReconnecting,
        consecutiveFailures,
        maxRetries,
        isPollingPaused,
        pauseReason,
        refreshIntervalSeconds,
        setRefreshInterval,
        isOffline,
        fetchRouters,
        setActiveRouter,
        updateRouterInState,
        updateLiveTelemetry,
        recordFailure,
        recordSuccess,
        resetRetryCount,
        reconnectRouter,
    };
});

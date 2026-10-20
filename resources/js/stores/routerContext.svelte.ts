import apiClient from '@/services/api';
import type { Router, RouterItem } from '@/types/router';
import type { ApiResponse } from '@/types/api';

class RouterContextStore {
    routers = $state<Router[]>([]);
    activeRouterId = $state<number | null>(
        typeof localStorage !== 'undefined' && localStorage.getItem('tikpulse_active_router_id')
            ? Number(localStorage.getItem('tikpulse_active_router_id'))
            : null
    );
    isLoading = $state<boolean>(false);
    isReconnecting = $state<boolean>(false);

    consecutiveFailures = $state<number>(0);
    maxRetries = $state<number>(3);
    isPollingPaused = $state<boolean>(false);
    pauseReason = $state<string | null>(null);

    refreshIntervalSeconds = $state<number>(
        typeof localStorage !== 'undefined' && localStorage.getItem('tikpulse_refresh_interval') !== null
            ? Number(localStorage.getItem('tikpulse_refresh_interval'))
            : 15
    );

    activeRouter = $derived.by(() => {
        if (!this.activeRouterId) return this.routers[0] || null;
        return this.routers.find((r) => r.id === this.activeRouterId) || this.routers[0] || null;
    });

    isOffline = $derived.by(() => {
        return (
            this.activeRouter?.status === 'offline' ||
            this.isPollingPaused ||
            this.consecutiveFailures >= this.maxRetries
        );
    });

    setRefreshInterval(seconds: number): void {
        this.refreshIntervalSeconds = seconds;
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem('tikpulse_refresh_interval', String(seconds));
        }
        if (seconds === 0) {
            this.isPollingPaused = true;
            this.pauseReason = 'Polling paused by user';
        } else {
            if (this.consecutiveFailures < this.maxRetries) {
                this.isPollingPaused = false;
                this.pauseReason = null;
            }
        }
    }

    async fetchRouters(): Promise<void> {
        this.isLoading = true;
        try {
            const res = await apiClient.get<ApiResponse<Router[]> | Router[]>('/routers');
            const data = Array.isArray(res.data) ? res.data : (res.data.data || []);
            this.routers = data.map((newRouter) => {
                const existing = this.routers.find((r) => r.id === newRouter.id);
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

            if (data.length > 0) {
                if (!this.activeRouterId || !data.some((r) => r.id === this.activeRouterId)) {
                    this.setActiveRouter(data[0].id);
                } else {
                    const current = data.find((r) => r.id === this.activeRouterId);
                    if (current?.status === 'offline') {
                        this.consecutiveFailures = this.maxRetries;
                        this.isPollingPaused = true;
                        this.pauseReason = 'Router is offline. Polling paused to prevent connection timeout loops.';
                    }
                }
            }
        } catch (err) {
            console.error('Failed to fetch routers:', err);
        } finally {
            this.isLoading = false;
        }
    }

    setActiveRouter(id: number): void {
        this.activeRouterId = id;
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem('tikpulse_active_router_id', String(id));
            localStorage.removeItem('tikpulse_safemode_bypass_token');
        }

        const r = this.routers.find((item) => item.id === id);
        if (r && r.status === 'offline') {
            this.consecutiveFailures = this.maxRetries;
            this.isPollingPaused = true;
            this.pauseReason = 'Router is offline. Polling paused to prevent connection timeout loops.';
        } else {
            this.resetRetryCount();
        }
    }

    updateRouterInState(updated: Router | RouterItem): void {
        const index = this.routers.findIndex((r) => r.id === updated.id);
        if (index !== -1) {
            this.routers[index] = { ...this.routers[index], ...updated } as Router;
        }
    }

    updateLiveTelemetry(telemetryData: Record<string, unknown>): void {
        if (!this.activeRouter) return;
        const cpu = telemetryData.cpu_load ?? telemetryData['cpu-load'];
        const freeMem = telemetryData.free_memory ?? telemetryData['free-memory'];
        const totalMem = telemetryData.total_memory ?? telemetryData['total-memory'];
        const uptime = telemetryData.uptime;
        const rosVersion = telemetryData.version;
        const boardName = telemetryData.board_name ?? telemetryData['board-name'];

        const numCpu = cpu !== undefined && cpu !== null ? Number(cpu) : this.activeRouter.cpu_load;
        const numFree = freeMem !== undefined && freeMem !== null ? Number(freeMem) : this.activeRouter.free_memory_bytes;
        const numTotal = totalMem !== undefined && totalMem !== null ? Number(totalMem) : this.activeRouter.total_memory_bytes;
        const memPercent = (numTotal && numTotal > 0 && numFree !== null && numFree !== undefined)
            ? Math.min(100, Math.max(0, Math.round(((numTotal - numFree) / numTotal) * 100)))
            : this.activeRouter.memory_usage_percent;

        const updated: Partial<Router> = {
            id: this.activeRouter.id,
            cpu_load: numCpu,
            free_memory_bytes: numFree,
            total_memory_bytes: numTotal,
            free_memory: numFree,
            total_memory: numTotal,
            memory_usage_percent: memPercent,
            uptime: (uptime as string) || this.activeRouter.uptime,
            ros_version: (rosVersion as string) || this.activeRouter.ros_version,
            board_model: (boardName as string) || this.activeRouter.board_model,
        };

        this.updateRouterInState(updated as Router);
    }

    recordFailure(reason = 'Connection failed'): void {
        if (this.consecutiveFailures >= this.maxRetries) {
            this.consecutiveFailures = this.maxRetries;
            this.isPollingPaused = true;
            return;
        }

        this.consecutiveFailures += 1;

        if (this.consecutiveFailures >= this.maxRetries) {
            this.consecutiveFailures = this.maxRetries;
            this.isPollingPaused = true;
            this.pauseReason = `Router offline. Polling paused after reaching limit (${this.maxRetries}/${this.maxRetries}).`;
            if (this.activeRouter) {
                this.activeRouter.status = 'offline';
            }
        }
    }

    recordSuccess(): void {
        this.consecutiveFailures = 0;
        this.isPollingPaused = false;
        this.pauseReason = null;
        if (this.activeRouter) {
            this.activeRouter.status = 'online';
        }
    }

    resetRetryCount(): void {
        this.consecutiveFailures = 0;
        this.isPollingPaused = false;
        this.pauseReason = null;
    }

    async reconnectRouter(routerId?: number): Promise<boolean> {
        const targetId = routerId || this.activeRouter?.id;
        if (!targetId) return false;

        this.resetRetryCount();
        this.isReconnecting = true;

        try {
            const res = await apiClient.post<ApiResponse<{ success: boolean; ping_ms?: number }>>(
                `/routers/${targetId}/test-connection`
            );

            if (res.data.status === 'success') {
                this.recordSuccess();
                await this.fetchRouters();
                return true;
            } else {
                this.consecutiveFailures = this.maxRetries;
                this.isPollingPaused = true;
                this.pauseReason = 'Connection test returned error.';
                if (this.activeRouter) {
                    this.activeRouter.status = 'offline';
                }
                return false;
            }
        } catch {
            this.consecutiveFailures = this.maxRetries;
            this.isPollingPaused = true;
            this.pauseReason = 'Unable to reach router socket/REST API.';
            if (this.activeRouter) {
                this.activeRouter.status = 'offline';
            }
            return false;
        } finally {
            this.isReconnecting = false;
        }
    }
}

export const routerContextStore = new RouterContextStore();
export const routerStore = routerContextStore;
export const useRouterContextStore = () => routerContextStore;

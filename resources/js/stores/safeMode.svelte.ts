import apiClient from '@/services/api';
import type { SafeModeStatus, SafeModeUnlockResponse } from '@/types/safe-mode';
import type { ApiResponse } from '@/types/api';

class SafeModeStore {
    status = $state<SafeModeStatus | null>(null);
    bypassToken = $state<string | null>(
        typeof localStorage !== 'undefined' ? localStorage.getItem('tikpulse_safemode_bypass_token') : null
    );
    remainingSeconds = $state<number>(0);
    isLoading = $state<boolean>(false);
    private timerInterval: any = null;

    isSafeModeActive = $derived.by(() => {
        if (!this.status) return true;
        return this.status.is_safe_mode_enabled && !this.bypassToken;
    });

    isUnlocked = $derived(!this.isSafeModeActive);

    formattedTimeRemaining = $derived.by(() => {
        if (this.remainingSeconds <= 0) return '00:00';
        const mins = Math.floor(this.remainingSeconds / 60);
        const secs = this.remainingSeconds % 60;
        return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    });

    stopTimer() {
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
            this.timerInterval = null;
        }
    }

    startTimer(seconds: number, routerId: number) {
        this.stopTimer();
        this.remainingSeconds = seconds;

        this.timerInterval = setInterval(() => {
            if (this.remainingSeconds > 1) {
                this.remainingSeconds--;
            } else {
                this.lock(routerId);
            }
        }, 1000);
    }

    async fetchStatus(routerId: number): Promise<void> {
        if (!routerId) return;
        try {
            const res = await apiClient.get<ApiResponse<SafeModeStatus>>(`/routers/${routerId}/safe-mode/status`);
            if (res.data.data) {
                this.status = res.data.data;
                if (!res.data.data.is_bypassed) {
                    this.bypassToken = null;
                    if (typeof localStorage !== 'undefined') {
                        localStorage.removeItem('tikpulse_safemode_bypass_token');
                    }
                    this.stopTimer();
                }
            }
        } catch (err) {
            console.error('Failed to fetch safe mode status:', err);
        }
    }

    async unlock(routerId: number, password: string, reason?: string): Promise<boolean> {
        this.isLoading = true;
        try {
            const res = await apiClient.post<ApiResponse<SafeModeUnlockResponse>>(
                `/routers/${routerId}/safe-mode/unlock`,
                {
                    password,
                    verification_text: 'UNLOCK',
                    reason,
                }
            );

            if (res.data.status === 'success' && res.data.data) {
                const token = res.data.data.bypass_token;
                this.bypassToken = token;
                if (typeof localStorage !== 'undefined') {
                    localStorage.setItem('tikpulse_safemode_bypass_token', token);
                }
                this.startTimer(res.data.data.expires_in_seconds, routerId);
                await this.fetchStatus(routerId);
                return true;
            }
            return false;
        } catch (err: any) {
            throw new Error(err.response?.data?.message || 'Verification failed. Safe Mode remains active.');
        } finally {
            this.isLoading = false;
        }
    }

    async lock(routerId: number): Promise<void> {
        this.stopTimer();
        this.remainingSeconds = 0;
        const tokenToRevoke = this.bypassToken;
        this.bypassToken = null;
        if (typeof localStorage !== 'undefined') {
            localStorage.removeItem('tikpulse_safemode_bypass_token');
        }

        try {
            await apiClient.post(`/routers/${routerId}/safe-mode/lock`, {
                bypass_token: tokenToRevoke,
            });
        } catch {
            // Ignore failure on lock
        } finally {
            await this.fetchStatus(routerId);
        }
    }
}

export const safeModeStore = new SafeModeStore();

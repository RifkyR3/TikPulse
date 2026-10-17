import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '@/services/api';
import type { SafeModeStatus, SafeModeUnlockResponse } from '@/types/safe-mode';
import type { ApiResponse } from '@/types/api';

export const useSafeModeStore = defineStore('safeMode', () => {
    const status = ref<SafeModeStatus | null>(null);
    const bypassToken = ref<string | null>(localStorage.getItem('tikpulse_safemode_bypass_token'));
    const remainingSeconds = ref<number>(0);
    const timerInterval = ref<any>(null);
    const isLoading = ref<boolean>(false);

    const isSafeModeActive = computed(() => {
        if (!status.value) return true;
        return status.value.is_safe_mode_enabled && !bypassToken.value;
    });

    const isUnlocked = computed(() => !isSafeModeActive.value);

    const formattedTimeRemaining = computed(() => {
        if (remainingSeconds.value <= 0) return '00:00';
        const mins = Math.floor(remainingSeconds.value / 60);
        const secs = remainingSeconds.value % 60;
        return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    });

    const stopTimer = () => {
        if (timerInterval.value) {
            clearInterval(timerInterval.value);
            timerInterval.value = null;
        }
    };

    const startTimer = (seconds: number, routerId: number) => {
        stopTimer();
        remainingSeconds.value = seconds;

        timerInterval.value = setInterval(() => {
            if (remainingSeconds.value > 1) {
                remainingSeconds.value--;
            } else {
                // Auto-revert when timer expires
                lock(routerId);
            }
        }, 1000);
    };

    const fetchStatus = async (routerId: number): Promise<void> => {
        if (!routerId) return;
        try {
            const res = await apiClient.get<ApiResponse<SafeModeStatus>>(`/routers/${routerId}/safe-mode/status`);
            if (res.data.data) {
                status.value = res.data.data;
                if (!res.data.data.is_bypassed) {
                    bypassToken.value = null;
                    localStorage.removeItem('tikpulse_safemode_bypass_token');
                    stopTimer();
                }
            }
        } catch (err) {
            console.error('Failed to fetch safe mode status:', err);
        }
    };

    const unlock = async (routerId: number, password: string, reason?: string): Promise<boolean> => {
        isLoading.value = true;
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
                bypassToken.value = token;
                localStorage.setItem('tikpulse_safemode_bypass_token', token);
                startTimer(res.data.data.expires_in_seconds, routerId);
                await fetchStatus(routerId);
                return true;
            }
            return false;
        } catch (err: any) {
            throw new Error(err.response?.data?.message || 'Verification failed. Safe Mode remains active.');
        } finally {
            isLoading.value = false;
        }
    };

    const lock = async (routerId: number): Promise<void> => {
        stopTimer();
        remainingSeconds.value = 0;
        const tokenToRevoke = bypassToken.value;
        bypassToken.value = null;
        localStorage.removeItem('tikpulse_safemode_bypass_token');

        try {
            await apiClient.post(`/routers/${routerId}/safe-mode/lock`, {
                bypass_token: tokenToRevoke,
            });
        } catch {
            // Ignore failure on lock
        } finally {
            await fetchStatus(routerId);
        }
    };

    return {
        status,
        bypassToken,
        remainingSeconds,
        isLoading,
        isSafeModeActive,
        isUnlocked,
        formattedTimeRemaining,
        fetchStatus,
        unlock,
        lock,
    };
});

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '@/services/api';
import type { User, AuthResponseData } from '@/types/auth';
import type { ApiResponse } from '@/types/api';

import { useLocaleStore } from '@/stores/locale';

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null);
    const token = ref<string | null>(localStorage.getItem('tikpulse_token'));
    const isLoading = ref<boolean>(false);
    const error = ref<string | null>(null);

    const isAuthenticated = computed(() => !!token.value && !!user.value);
    const isSuperAdmin = computed(() => user.value?.role === 'superadmin');

    const login = async (email: string, password: string, remember = false): Promise<boolean> => {
        isLoading.value = true;
        error.value = null;

        try {
            const res = await apiClient.post<ApiResponse<AuthResponseData>>('/auth/login', {
                email,
                password,
                remember,
            });

            if (res.data.status === 'success' && res.data.data) {
                user.value = res.data.data.user;
                token.value = res.data.data.token;
                localStorage.setItem('tikpulse_token', res.data.data.token);
                if (remember) {
                    localStorage.setItem('tikpulse_remembered_email', email);
                } else {
                    localStorage.removeItem('tikpulse_remembered_email');
                }

                // Load user's saved language or restore from local selection
                const localeStore = useLocaleStore();
                localeStore.initFromUser(user.value.preferred_locale);

                return true;
            }
            return false;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Invalid credentials or login failed.';
            return false;
        } finally {
            isLoading.value = false;
        }
    };

    const logout = async (): Promise<void> => {
        try {
            await apiClient.post('/auth/logout');
        } catch {
            // Ignore failure on logout
        } finally {
            user.value = null;
            token.value = null;
            localStorage.removeItem('tikpulse_token');
            localStorage.removeItem('tikpulse_active_router_id');
            localStorage.removeItem('tikpulse_safemode_bypass_token');
            sessionStorage.removeItem('tikpulse_memory_route');
        }
    };

    const fetchMe = async (): Promise<boolean> => {
        if (!token.value) return false;

        isLoading.value = true;
        try {
            const res = await apiClient.get<ApiResponse<{ user: User }>>('/auth/me');
            if (res.data.status === 'success' && res.data.data?.user) {
                user.value = res.data.data.user;
                const localeStore = useLocaleStore();
                localeStore.initFromUser(user.value.preferred_locale);
                return true;
            }
            return false;
        } catch {
            token.value = null;
            user.value = null;
            localStorage.removeItem('tikpulse_token');
            return false;
        } finally {
            isLoading.value = false;
        }
    };

    return {
        user,
        token,
        isLoading,
        error,
        isAuthenticated,
        isSuperAdmin,
        login,
        logout,
        fetchMe,
    };
});

import apiClient from '@/services/api';
import type { User, AuthResponseData } from '@/types/auth';
import type { ApiResponse } from '@/types/api';
import { localeStore } from '@/stores/locale.svelte';
import { router } from '@inertiajs/svelte';

class AuthStore {
    user = $state<User | null>(null);
    token = $state<string | null>(
        typeof localStorage !== 'undefined' ? localStorage.getItem('tikpulse_token') : null
    );
    isLoading = $state<boolean>(false);
    error = $state<string | null>(null);

    isAuthenticated = $derived(!!this.token && !!this.user);
    isSuperAdmin = $derived(this.user?.role === 'superadmin');

    async login(email: string, password: string, remember = false): Promise<boolean> {
        this.isLoading = true;
        this.error = null;

        try {
            const res = await apiClient.post<ApiResponse<AuthResponseData>>('/auth/login', {
                email,
                password,
                remember,
            });

            if (res.data.status === 'success' && res.data.data) {
                this.user = res.data.data.user;
                this.token = res.data.data.token;
                if (typeof localStorage !== 'undefined') {
                    localStorage.setItem('tikpulse_token', res.data.data.token);
                    if (remember) {
                        localStorage.setItem('tikpulse_remembered_email', email);
                    } else {
                        localStorage.removeItem('tikpulse_remembered_email');
                    }
                }

                localeStore.initFromUser(this.user.preferred_locale);
                return true;
            }
            return false;
        } catch (err: any) {
            this.error = err.response?.data?.message || 'Invalid credentials or login failed.';
            return false;
        } finally {
            this.isLoading = false;
        }
    }

    async logout(): Promise<void> {
        try {
            await apiClient.post('/auth/logout');
        } catch {
            // Ignore failure on logout
        } finally {
            this.user = null;
            this.token = null;
            if (typeof localStorage !== 'undefined') {
                localStorage.removeItem('tikpulse_token');
                localStorage.removeItem('tikpulse_active_router_id');
                localStorage.removeItem('tikpulse_safemode_bypass_token');
            }
            if (typeof sessionStorage !== 'undefined') {
                sessionStorage.removeItem('tikpulse_memory_route');
            }
            router.visit('/login');
        }
    }

    async fetchMe(): Promise<boolean> {
        if (!this.token) return false;

        this.isLoading = true;
        try {
            const res = await apiClient.get<ApiResponse<{ user: User }>>('/auth/me');
            if (res.data.status === 'success' && res.data.data?.user) {
                this.user = res.data.data.user;
                localeStore.initFromUser(this.user.preferred_locale);
                return true;
            }
            return false;
        } catch {
            this.token = null;
            this.user = null;
            if (typeof localStorage !== 'undefined') {
                localStorage.removeItem('tikpulse_token');
            }
            return false;
        } finally {
            this.isLoading = false;
        }
    }
}

export const authStore = new AuthStore();

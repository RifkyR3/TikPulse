import apiClient from '@/services/api';
import type { User, AuthResponseData, UserSettings } from '@/types/auth';
import type { ApiResponse } from '@/types/api';
import { localeStore } from '@/stores/locale.svelte';
import { themeStore } from '@/stores/theme.svelte';
import { router } from '@inertiajs/svelte';

class AuthStore {
    user = $state<User | null>(
        (() => {
            if (typeof localStorage === 'undefined') return null;
            const saved = localStorage.getItem('tikpulse_user');
            if (!saved) return null;
            try {
                return JSON.parse(saved);
            } catch {
                return null;
            }
        })()
    );
    token = $state<string | null>(
        typeof localStorage !== 'undefined' ? localStorage.getItem('tikpulse_token') : null
    );
    isLoading = $state<boolean>(false);
    error = $state<string | null>(null);

    isAuthenticated = $derived(!!this.user);
    isSuperAdmin = $derived(this.user?.is_superadmin ?? (this.user?.role === 'superadmin'));
    isSimulating = $derived(!!this.user?.is_simulating);
    activeGroup = $derived(this.user?.is_simulating ? this.user?.simulated_group : this.user?.group);

    initFromPageProps(user: User | null): void {
        if (!user) return;
        this.user = user;
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem('tikpulse_user', JSON.stringify(user));
        }
        this.applyUserSettings();
    }

    can(moduleKey: string, action: 'read' | 'write' | 'update' | 'delete' = 'read'): boolean {
        if (!this.user) return false;

        // If inherently superadmin and not simulating another role
        if (this.isSuperAdmin && !this.isSimulating) {
            return true;
        }

        const perms = this.user.effective_permissions;
        if (!perms || !perms[moduleKey]) {
            // Fallback for superadmin / admin / operator legacy
            if (this.user.role === 'superadmin' && !this.isSimulating) return true;
            if (this.user.role === 'admin' && !this.isSimulating) return true;
            if (this.user.role === 'operator') {
                return ['dashboard', 'routers', 'hotspot_vouchers', 'ppp_subscribers'].includes(moduleKey)
                    && ['read', 'write', 'update'].includes(action);
            }
            return false;
        }

        const key = `can_${action}` as 'can_read' | 'can_write' | 'can_update' | 'can_delete';
        return !!perms[moduleKey][key];
    }

    canAccessRouter(routerId: number): boolean {
        if (!this.user) return false;
        if (this.isSuperAdmin && !this.isSimulating) return true;
        if (this.user.allowed_router_ids === null || this.user.allowed_router_ids === undefined) return true;
        return this.user.allowed_router_ids.includes(routerId);
    }

    async login(loginOrEmail: string, password: string, remember = false): Promise<{ success: boolean; accountType?: string }> {
        this.isLoading = true;
        this.error = null;

        try {
            const res = await apiClient.post<ApiResponse<AuthResponseData>>('/auth/login', {
                login: loginOrEmail,
                email: loginOrEmail,
                password,
                remember,
            });

            if (res.data.status === 'success' && res.data.data) {
                this.user = res.data.data.user;
                this.token = res.data.data.token;
                const accountType = res.data.data.account_type || (this.user.role === 'customer' ? 'customer' : 'admin');

                if (typeof localStorage !== 'undefined') {
                    localStorage.setItem('tikpulse_token', res.data.data.token);
                    localStorage.setItem('tikpulse_user', JSON.stringify(res.data.data.user));
                    if (remember) {
                        localStorage.setItem('tikpulse_remembered_email', loginOrEmail);
                    } else {
                        localStorage.removeItem('tikpulse_remembered_email');
                    }
                }

                this.applyUserSettings();
                return { success: true, accountType };
            }
            return { success: false };
        } catch (err: any) {
            const serverMsg = err.response?.data?.message;
            if (serverMsg === 'These credentials do not match our records.' || err.response?.data?.errors?.email) {
                this.error = localeStore.locale === 'id'
                    ? 'Kombinasi identitas atau kata sandi tidak sesuai.'
                    : 'These credentials do not match our records.';
            } else {
                this.error = serverMsg || (localeStore.locale === 'id' ? 'Login gagal. Silakan coba lagi.' : 'Invalid credentials or login failed.');
            }
            return { success: false };
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
                localStorage.removeItem('tikpulse_user');
                localStorage.removeItem('tikpulse_active_router_id');
                localStorage.removeItem('tikpulse_safemode_bypass_token');
            }
            if (typeof sessionStorage !== 'undefined') {
                sessionStorage.removeItem('tikpulse_memory_route');
            }
            if (typeof document !== 'undefined') {
                document.cookie = 'tikpulse_token=; path=/; expires=Thu, 01 Jan 1970 00:00:01 GMT;';
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
                if (typeof localStorage !== 'undefined') {
                    localStorage.setItem('tikpulse_user', JSON.stringify(this.user));
                }
                this.applyUserSettings();
                return true;
            }
            return false;
        } catch {
            this.token = null;
            this.user = null;
            if (typeof localStorage !== 'undefined') {
                localStorage.removeItem('tikpulse_token');
                localStorage.removeItem('tikpulse_user');
            }
            return false;
        } finally {
            this.isLoading = false;
        }
    }

    async switchRole(groupId: number | null): Promise<boolean> {
        this.isLoading = true;
        try {
            const res = await apiClient.post<ApiResponse<{ user: User }>>('/auth/switch-role', {
                group_id: groupId,
            });
            if (res.data.status === 'success' && res.data.data?.user) {
                this.user = res.data.data.user;
                if (typeof localStorage !== 'undefined') {
                    localStorage.setItem('tikpulse_user', JSON.stringify(this.user));
                }
                return true;
            }
            return false;
        } catch (err: any) {
            this.error = err.response?.data?.message || 'Failed to switch role.';
            return false;
        } finally {
            this.isLoading = false;
        }
    }

    async resetRole(): Promise<boolean> {
        this.isLoading = true;
        try {
            const res = await apiClient.post<ApiResponse<{ user: User }>>('/auth/reset-role');
            if (res.data.status === 'success' && res.data.data?.user) {
                this.user = res.data.data.user;
                if (typeof localStorage !== 'undefined') {
                    localStorage.setItem('tikpulse_user', JSON.stringify(this.user));
                }
                return true;
            }
            return false;
        } catch (err: any) {
            this.error = err.response?.data?.message || 'Failed to reset role.';
            return false;
        } finally {
            this.isLoading = false;
        }
    }

    async updateSettings(settings: Partial<UserSettings>): Promise<boolean> {
        try {
            const res = await apiClient.post<ApiResponse<{ user: User }>>('/auth/settings', settings);
            if (res.data.status === 'success' && res.data.data?.user) {
                this.user = res.data.data.user;
                if (typeof localStorage !== 'undefined') {
                    localStorage.setItem('tikpulse_user', JSON.stringify(this.user));
                }
                this.applyUserSettings();
                return true;
            }
            return false;
        } catch (err: any) {
            this.error = err.response?.data?.message || 'Failed to update settings.';
            return false;
        }
    }

    private applyUserSettings(): void {
        if (!this.user) return;
        localeStore.initFromUser(this.user.preferred_locale);
        themeStore.initFromUserSettings(this.user.settings as any);
    }
}

export const authStore = new AuthStore();

if (typeof window !== 'undefined' && authStore.token) {
    authStore.fetchMe();
}

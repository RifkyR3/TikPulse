import axios, { AxiosError, type InternalAxiosRequestConfig } from 'axios';
import type { ApiResponse } from '@/types/api';

const apiClient = axios.create({
    baseURL: '/api/v1',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    },
    withCredentials: true,
});

apiClient.interceptors.request.use((config: InternalAxiosRequestConfig) => {
    // Inject Sanctum Bearer Token
    const token = localStorage.getItem('tikpulse_token');
    if (token && config.headers) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    // Inject Active Router Context
    const activeRouterId = localStorage.getItem('tikpulse_active_router_id');
    if (activeRouterId && config.headers && !config.headers['X-Router-ID']) {
        config.headers['X-Router-ID'] = activeRouterId;
    }

    // Inject Temporary Safe Mode Bypass Token if active
    const bypassToken = localStorage.getItem('tikpulse_safemode_bypass_token');
    if (bypassToken && config.headers && !config.headers['X-SafeMode-Bypass']) {
        config.headers['X-SafeMode-Bypass'] = bypassToken;
    }

    // Inject Active Locale Context
    const locale = localStorage.getItem('tikpulse_locale');
    if (locale && config.headers && !config.headers['X-Locale']) {
        config.headers['X-Locale'] = locale;
    }

    return config;
}, (error) => Promise.reject(error));

apiClient.interceptors.response.use(
    (response) => response,
    (error: AxiosError<ApiResponse>) => {
        if (error.response?.status === 401) {
            // Unauthenticated: clear token and redirect to login if not already on login page
            localStorage.removeItem('tikpulse_token');
            if (window.location.pathname !== '/login') {
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    }
);

export default apiClient;

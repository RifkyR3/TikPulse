<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRouterContextStore } from '@/stores/routerContext';
import { useLocaleStore } from '@/stores/locale';
import { usePageVisibility } from '@/utils/pageVisibility';
import { debounce } from '@/utils/debounce';
import { toast } from 'vue-sonner';
import apiClient from '@/services/api';
import type { ApiResponse } from '@/types/api';
import {
    Users,
    Search,
    RefreshCw,
    UserX,
    Wifi,
    Clock,
    ArrowDownUp,
    AlertCircle,
    CheckCircle2,
    ChevronLeft,
    ChevronRight
} from 'lucide-vue-next';

interface ActiveHotspotUser {
    id: string;
    server: string;
    user: string;
    address: string;
    mac_address: string;
    uptime: string;
    bytes_in: number;
    bytes_out: number;
    bytes_in_formatted: string;
    bytes_out_formatted: string;
    login_by: string;
    comment: string | null;
}

import DataTable from '@/components/common/DataTable.vue';
import type { ColumnDef } from '@tanstack/vue-table';

const routerStore = useRouterContextStore();
const localeStore = useLocaleStore();
const { isPageVisible, onVisibilityChange } = usePageVisibility();

const columns = computed<ColumnDef<ActiveHotspotUser>[]>(() => [
    {
        id: 'user',
        accessorKey: 'user',
        header: localeStore.t('dashboard.userVoucher'),
    },
    {
        id: 'address',
        accessorKey: 'address',
        header: localeStore.t('dashboard.ipAddress'),
    },
    {
        id: 'mac_address',
        accessorKey: 'mac_address',
        header: localeStore.t('dashboard.macAddress'),
    },
    {
        id: 'uptime',
        accessorKey: 'uptime',
        header: localeStore.t('dashboard.uptime'),
    },
    {
        id: 'bytes_out',
        accessorKey: 'bytes_out',
        header: localeStore.t('dashboard.downloaded'),
    },
    {
        id: 'bytes_in',
        accessorKey: 'bytes_in',
        header: localeStore.t('dashboard.uploaded'),
    },
    {
        id: 'actions',
        header: localeStore.t('common.actions'),
    },
]);

const users = ref<ActiveHotspotUser[]>([]);
const searchQuery = ref('');
const debouncedSearch = ref('');
const currentPage = ref(1);
const perPage = ref(15);
const isLoading = ref(false);
const isKicking = ref<string | null>(null);
const errorMessage = ref<string | null>(null);
const successMessage = ref<string | null>(null);
const autoRefresh = ref(true);
let refreshInterval: number | null = null;

const onSearchInput = debounce((val: string) => {
    debouncedSearch.value = val;
    currentPage.value = 1;
}, 200);

watch(searchQuery, (newVal) => {
    onSearchInput(newVal);
});

const filteredUsers = computed(() => {
    if (!debouncedSearch.value.trim()) return users.value;
    const query = debouncedSearch.value.toLowerCase().trim();
    return users.value.filter(u =>
        u.user.toLowerCase().includes(query) ||
        u.address.toLowerCase().includes(query) ||
        u.mac_address.toLowerCase().includes(query)
    );
});

const totalPages = computed(() => {
    if (perPage.value === -1) return 1;
    return Math.max(1, Math.ceil(filteredUsers.value.length / perPage.value));
});

const paginatedUsers = computed(() => {
    if (perPage.value === -1) return filteredUsers.value;
    const start = (currentPage.value - 1) * perPage.value;
    return filteredUsers.value.slice(start, start + perPage.value);
});

watch(totalPages, (pages) => {
    if (currentPage.value > pages) {
        currentPage.value = pages;
    }
});

const totalBytesInFormatted = computed(() => {
    const total = users.value.reduce((acc, u) => acc + (u.bytes_in || 0), 0);
    return formatBytes(total);
});

const totalBytesOutFormatted = computed(() => {
    const total = users.value.reduce((acc, u) => acc + (u.bytes_out || 0), 0);
    return formatBytes(total);
});

function formatBytes(bytes: number): string {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`;
}

const fetchActiveUsers = async (showLoading = true) => {
    if (!routerStore.activeRouter) {
        users.value = [];
        return;
    }
    // Strict guard: if router is offline or polling is paused or limit reached, do NOT connect automatically!
    if ((routerStore.isPollingPaused || routerStore.isOffline || routerStore.consecutiveFailures >= routerStore.maxRetries) && !routerStore.isReconnecting) {
        return;
    }
    if (!isPageVisible.value) {
        return;
    }

    if (showLoading) isLoading.value = true;
    errorMessage.value = null;

    try {
        const response = await apiClient.get<ApiResponse<ActiveHotspotUser[]>>(
            `/routers/${routerStore.activeRouter.id}/hotspot/active`
        );
        if (response.data.status === 'success' && Array.isArray(response.data.data)) {
            users.value = response.data.data;
        } else if (response.data.is_polling_paused) {
            routerStore.recordFailure('Router offline');
        }
    } catch (err: unknown) {
        const axiosErr = err as { response?: { data?: { message?: string } } };
        errorMessage.value = axiosErr.response?.data?.message || 'Failed to fetch active hotspot users';
        routerStore.recordFailure(errorMessage.value);
    } finally {
        if (showLoading) isLoading.value = false;
    }
};

const handleKick = async (user: ActiveHotspotUser) => {
    if (!routerStore.activeRouter) return;
    if (!confirm(`Are you sure you want to terminate session for user "${user.user}" (${user.address})?`)) {
        return;
    }

    isKicking.value = user.id;
    errorMessage.value = null;
    successMessage.value = null;

    try {
        const response = await apiClient.post<ApiResponse<null>>(
            `/routers/${routerStore.activeRouter.id}/hotspot/active/${encodeURIComponent(user.id)}/kick`
        );
        const msg = response.data.message || `User "${user.user}" disconnected successfully.`;
        successMessage.value = msg;
        toast.success(msg);
        // Optimistically remove
        users.value = users.value.filter(u => u.id !== user.id);
        setTimeout(() => { successMessage.value = null; }, 4000);
    } catch (err: unknown) {
        const axiosErr = err as { response?: { data?: { message?: string } } };
        const msg = axiosErr.response?.data?.message || 'Failed to disconnect user';
        errorMessage.value = msg;
        toast.error(msg);
    } finally {
        isKicking.value = null;
    }
};

const resetInterval = () => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
    if (autoRefresh.value && !routerStore.isPollingPaused && !routerStore.isOffline && routerStore.consecutiveFailures < routerStore.maxRetries && isPageVisible.value) {
        refreshInterval = window.setInterval(() => {
            if (autoRefresh.value && routerStore.activeRouter && !routerStore.isPollingPaused && !routerStore.isOffline && routerStore.consecutiveFailures < routerStore.maxRetries && isPageVisible.value) {
                fetchActiveUsers(false);
            }
        }, 10000);
    }
};

// Lifecycle & Polling
watch(() => routerStore.activeRouter?.id, () => {
    currentPage.value = 1;
    if (!routerStore.isOffline && !routerStore.isPollingPaused && routerStore.consecutiveFailures < routerStore.maxRetries) {
        fetchActiveUsers(true);
    }
    resetInterval();
});

watch([autoRefresh, () => routerStore.isPollingPaused, () => routerStore.isOffline], () => {
    resetInterval();
});

onVisibilityChange((visible) => {
    if (visible && autoRefresh.value && routerStore.activeRouter && !routerStore.isPollingPaused && !routerStore.isOffline && routerStore.consecutiveFailures < routerStore.maxRetries) {
        fetchActiveUsers(false);
        resetInterval();
    } else {
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
    }
});

onMounted(() => {
    if (!routerStore.isOffline && !routerStore.isPollingPaused && routerStore.consecutiveFailures < routerStore.maxRetries) {
        fetchActiveUsers(true);
    }
    resetInterval();
});

onUnmounted(() => {
    if (refreshInterval) clearInterval(refreshInterval);
});
</script>

<template>
    <div class="rounded-2xl border border-border bg-card shadow-sm overflow-hidden flex flex-col">
        <!-- Table Header & Controls (Solid bg-card, zero GPU blur) -->
        <div class="p-4 sm:p-5 border-b border-border flex flex-col md:flex-row md:items-center justify-between gap-4 bg-card">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-sky-500/10 text-sky-500 flex items-center justify-center shrink-0">
                    <Users class="w-5 h-5" />
                </div>
                <div>
                    <div class="flex items-center space-x-2">
                        <h3 class="text-sm font-bold text-foreground tracking-tight">{{ localeStore.t('dashboard.activeHotspotUsers') }}</h3>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">
                            {{ users.length }} {{ localeStore.t('common.online') }}
                        </span>
                    </div>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        {{ localeStore.t('dashboard.activeSessionsSubtitle') }} {{ routerStore.activeRouter?.name || 'Selected Router' }}
                    </p>
                </div>
            </div>

            <!-- Actions Bar -->
            <div class="flex flex-wrap items-center gap-2.5">
                <!-- Search input -->
                <div class="relative w-full sm:w-60">
                    <Search class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="localeStore.t('common.search') + '...'"
                        class="w-full pl-8 pr-3 py-1.5 text-xs rounded-lg border border-border bg-background focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 text-foreground transition-all"
                    />
                </div>

                <!-- Auto Refresh Switcher -->
                <button
                    type="button"
                    @click="autoRefresh = !autoRefresh"
                    :class="[
                        'px-2.5 py-1.5 rounded-lg border text-xs font-medium inline-flex items-center space-x-1.5 transition-colors cursor-pointer',
                        autoRefresh
                            ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-600 dark:text-emerald-400'
                            : 'bg-muted border-border text-muted-foreground'
                    ]"
                    :title="autoRefresh ? 'Auto-refresh every 10s (Click to pause)' : 'Auto-refresh paused (Click to resume)'"
                >
                    <span class="w-2 h-2 rounded-full" :class="autoRefresh ? 'bg-emerald-500 shadow-xs shadow-emerald-500/50' : 'bg-muted-foreground'"></span>
                    <span class="text-[11px] font-mono">{{ autoRefresh ? localeStore.t('dashboard.auto10s') : localeStore.t('dashboard.paused') }}</span>
                </button>

                <!-- Manual Refresh Button -->
                <button
                    type="button"
                    @click="fetchActiveUsers(true)"
                    :disabled="isLoading || routerStore.isOffline"
                    class="p-2 rounded-lg border border-border hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer disabled:opacity-50"
                    :title="routerStore.isOffline ? localeStore.t('dashboard.offlineDesc') : localeStore.t('common.refresh')"
                >
                    <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isLoading }" />
                </button>
            </div>
        </div>

        <!-- Alerts -->
        <div v-if="errorMessage" class="mx-5 mt-4 p-3 rounded-xl bg-destructive/10 border border-destructive/20 text-destructive text-xs flex items-center space-x-2">
            <AlertCircle class="w-4 h-4 shrink-0" />
            <span>{{ errorMessage }}</span>
        </div>

        <!-- Active Sessions Table (Powered by TanStack Table v8) -->
        <div class="p-4 sm:p-5">
            <DataTable
                :columns="columns"
                :data="filteredUsers"
                :is-loading="isLoading && users.length === 0"
                :page-size="perPage === -1 ? 100 : perPage"
                :empty-message="localeStore.t('dashboard.noActiveUsers')"
            >
                <!-- Custom Cell: User Voucher -->
                <template #cell-user="{ row }">
                    <div class="font-bold text-foreground font-mono">{{ row.user }}</div>
                    <div v-if="row.comment" class="text-[10px] text-muted-foreground truncate max-w-xs">
                        {{ row.comment }}
                    </div>
                </template>

                <!-- Custom Cell: IP Address -->
                <template #cell-address="{ row }">
                    <span class="font-mono text-muted-foreground">{{ row.address }}</span>
                </template>

                <!-- Custom Cell: MAC Address -->
                <template #cell-mac_address="{ row }">
                    <span class="font-mono text-muted-foreground uppercase text-[11px]">{{ row.mac_address || '-' }}</span>
                </template>

                <!-- Custom Cell: Uptime -->
                <template #cell-uptime="{ row }">
                    <div class="flex items-center space-x-1.5 font-mono text-muted-foreground">
                        <Clock class="w-3 h-3 text-muted-foreground/60 shrink-0" />
                        <span>{{ row.uptime || '-' }}</span>
                    </div>
                </template>

                <!-- Custom Cell: Downloaded -->
                <template #cell-bytes_out="{ row }">
                    <span class="font-mono text-emerald-600 dark:text-emerald-400 font-semibold">
                        {{ row.bytes_out_formatted || formatBytes(row.bytes_out) }}
                    </span>
                </template>

                <!-- Custom Cell: Uploaded -->
                <template #cell-bytes_in="{ row }">
                    <span class="font-mono text-sky-600 dark:text-sky-400 font-semibold">
                        {{ row.bytes_in_formatted || formatBytes(row.bytes_in) }}
                    </span>
                </template>

                <!-- Custom Cell: Kick Action -->
                <template #cell-actions="{ row }">
                    <div class="text-right">
                        <button
                            type="button"
                            @click="handleKick(row)"
                            :disabled="isKicking === row.id"
                            class="inline-flex items-center space-x-1 px-2.5 py-1 rounded-md bg-destructive/10 hover:bg-destructive/20 text-destructive text-[11px] font-semibold border border-destructive/20 transition-colors cursor-pointer disabled:opacity-50"
                            title="Disconnect user session"
                        >
                            <RefreshCw v-if="isKicking === row.id" class="w-3 h-3 animate-spin" />
                            <UserX v-else class="w-3 h-3" />
                            <span>Kick</span>
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>
    </div>
</template>

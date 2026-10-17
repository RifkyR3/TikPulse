<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import AdminLayout from '@/components/layout/AdminLayout.vue';
import AddDedicatedUserModal from '@/components/ppp/AddDedicatedUserModal.vue';
import PppProfileModal from '@/components/ppp/PppProfileModal.vue';
import SafeModeUnlockModal from '@/components/safe-mode/SafeModeUnlockModal.vue';
import DataTable from '@/components/common/DataTable.vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { useRouterContextStore } from '@/stores/routerContext';
import { useSafeModeStore } from '@/stores/safeMode';
import { useLocaleStore } from '@/stores/locale';
import { debounce } from '@/utils/debounce';
import { usePageVisibility } from '@/utils/pageVisibility';
import { toast } from 'vue-sonner';
import apiClient from '@/services/api';
import type { DedicatedUserItem, PppProfileItem, PppActiveItem, IpPoolItem } from '@/types/ppp';
import {
    Radio,
    Users,
    Activity,
    Layers,
    Plus,
    RefreshCw,
    Search,
    Trash2,
    Edit3,
    Power,
    Clock,
    DollarSign,
    MapPin,
    Pause,
    Play,
    Wifi
} from 'lucide-vue-next';

const routerStore = useRouterContextStore();
const safeModeStore = useSafeModeStore();
const localeStore = useLocaleStore();

const activeTab = ref<'users' | 'active' | 'profiles'>('users');

// Data State
const dedicatedUsers = ref<DedicatedUserItem[]>([]);
const pppProfiles = ref<PppProfileItem[]>([]);
const activeSessions = ref<PppActiveItem[]>([]);
const ipPools = ref<IpPoolItem[]>([]);

// Meta stats
const stats = ref({
    total: 0,
    online: 0,
    disabled: 0,
    total_monthly_revenue: 0,
});

// Loading states
const isLoading = ref(false);
const isSyncingUsers = ref(false);
const isSyncingProfiles = ref(false);
const isDeletingUser = ref<string | null>(null);
const isDeletingProfile = ref<number | null>(null);
const isKickingSession = ref<string | null>(null);

// Filters
const searchQuery = ref('');
const profileFilter = ref('');
const statusFilter = ref<'all' | 'active' | 'disabled' | 'online'>('all');

// Modals
const isUserModalOpen = ref(false);
const editingUser = ref<DedicatedUserItem | null>(null);
const isProfileModalOpen = ref(false);
const editingProfile = ref<PppProfileItem | null>(null);

// Safe Mode modal state
const isSafeModeModalOpen = ref(false);
const pendingSafeModeAction = ref<'delete_user' | 'delete_profile' | null>(null);
const pendingTargetItem = ref<{ id: string | number; name: string } | null>(null);

// Auto-refresh for active sessions
const isAutoRefreshActive = ref(true);
let activePollingTimer: number | null = null;
const { isVisible } = usePageVisibility();

// Column definitions for TanStack DataTable
const userColumns: ColumnDef<DedicatedUserItem>[] = [
    {
        id: 'name',
        accessorKey: 'name',
        header: 'Subscriber / Identity',
    },
    {
        id: 'profile',
        accessorKey: 'profile',
        header: 'Profile & Protocol',
    },
    {
        id: 'remote_address',
        accessorKey: 'remote_address',
        header: 'Remote IP & MAC',
    },
    {
        id: 'monthly_price',
        accessorKey: 'monthly_price',
        header: 'Monthly Rate',
    },
    {
        id: 'status',
        header: 'Connection',
    },
    {
        id: 'account',
        header: 'Account Status',
    },
    {
        id: 'actions',
        header: 'Actions',
    },
];

const activeColumns: ColumnDef<PppActiveItem>[] = [
    {
        id: 'name',
        accessorKey: 'name',
        header: 'User / Identity',
    },
    {
        id: 'service',
        accessorKey: 'service',
        header: 'Service',
    },
    {
        id: 'address',
        accessorKey: 'address',
        header: 'Assigned IP Address',
    },
    {
        id: 'caller_id',
        accessorKey: 'caller_id',
        header: 'Caller ID (MAC)',
    },
    {
        id: 'uptime',
        accessorKey: 'uptime',
        header: 'Uptime',
    },
    {
        id: 'encoding',
        accessorKey: 'encoding',
        header: 'Encoding',
    },
    {
        id: 'actions',
        header: 'Actions',
    },
];

const profileColumns: ColumnDef<PppProfileItem>[] = [
    {
        id: 'name',
        accessorKey: 'name',
        header: 'Profile Name',
    },
    {
        id: 'rate_limit',
        accessorKey: 'rate_limit',
        header: 'Rate Limit (Rx/Tx)',
    },
    {
        id: 'local_address',
        accessorKey: 'local_address',
        header: 'Local Address',
    },
    {
        id: 'remote_address',
        accessorKey: 'remote_address',
        header: 'Remote Address / Pool',
    },
    {
        id: 'only_one',
        accessorKey: 'only_one',
        header: 'Only One',
    },
    {
        id: 'price',
        accessorKey: 'price',
        header: 'Monthly Price',
    },
    {
        id: 'dedicated_users_count',
        accessorKey: 'dedicated_users_count',
        header: 'Subscribers',
    },
    {
        id: 'actions',
        header: 'Actions',
    },
];

// Fetch Dedicated Users
const fetchDedicatedUsers = async (fresh = false) => {
    if (!routerStore.activeRouter) return;
    isLoading.value = true;
    try {
        const params: Record<string, string | boolean> = {};
        if (fresh) params.fresh = true;
        if (searchQuery.value) params.search = searchQuery.value;
        if (profileFilter.value) params.profile = profileFilter.value;
        if (statusFilter.value !== 'all') params.status = statusFilter.value;

        const res = await apiClient.get<{
            status: string;
            data: DedicatedUserItem[];
            meta: { total: number; online: number; disabled: number; total_monthly_revenue: number };
        }>(`/routers/${routerStore.activeRouter.id}/ppp/secrets`, { params });

        dedicatedUsers.value = res.data.data;
        if (res.data.meta) {
            stats.value = res.data.meta;
        }
    } catch (err: unknown) {
        console.error('Failed to load dedicated users:', err);
    } finally {
        isLoading.value = false;
    }
};

const debouncedSearch = debounce(() => {
    fetchDedicatedUsers();
}, 300);

// Fetch PPP Profiles
const fetchPppProfiles = async () => {
    if (!routerStore.activeRouter) return;
    try {
        const res = await apiClient.get<{ status: string; data: PppProfileItem[] }>(
            `/routers/${routerStore.activeRouter.id}/ppp/profiles`
        );
        pppProfiles.value = res.data.data;
    } catch (err: unknown) {
        console.error('Failed to load PPP profiles:', err);
    }
};

// Fetch IP Pools
const fetchIpPools = async () => {
    if (!routerStore.activeRouter) return;
    try {
        const res = await apiClient.get<{ status: string; data: IpPoolItem[] }>(
            `/routers/${routerStore.activeRouter.id}/ppp/pools`
        );
        ipPools.value = res.data.data;
    } catch {
        // Non-fatal
    }
};

// Fetch Live Active Sessions
const fetchActiveSessions = async (fresh = false) => {
    if (!routerStore.activeRouter || routerStore.isOffline) return;
    try {
        const res = await apiClient.get<{ status: string; data: PppActiveItem[]; total_active: number }>(
            `/routers/${routerStore.activeRouter.id}/ppp/active`,
            { params: { fresh } }
        );
        activeSessions.value = res.data.data;
        stats.value.online = res.data.total_active;
    } catch (err: unknown) {
        console.error('Failed to query live PPP sessions:', err);
    }
};

// Polling interval logic
const startActivePolling = () => {
    stopActivePolling();
    if (!isAutoRefreshActive.value) return;

    activePollingTimer = window.setInterval(() => {
        if (isVisible.value && activeTab.value === 'active' && !routerStore.isOffline) {
            fetchActiveSessions(true);
        }
    }, 3000);
};

const stopActivePolling = () => {
    if (activePollingTimer) {
        clearInterval(activePollingTimer);
        activePollingTimer = null;
    }
};

// Sync from MikroTik
const syncUsersFromRouter = async () => {
    if (!routerStore.activeRouter) return;
    isSyncingUsers.value = true;
    try {
        const res = await apiClient.post<{ status: string; message: string }>(
            `/routers/${routerStore.activeRouter.id}/ppp/secrets/sync`
        );
        toast.success(res.data.message || 'Dedicated users synchronized.');
        await fetchDedicatedUsers(true);
    } catch (err: unknown) {
        const axiosErr = err as { response?: { data?: { message?: string } } };
        toast.error(axiosErr.response?.data?.message || 'Failed to sync users.');
    } finally {
        isSyncingUsers.value = false;
    }
};

const syncProfilesFromRouter = async () => {
    if (!routerStore.activeRouter) return;
    isSyncingProfiles.value = true;
    try {
        const res = await apiClient.post<{ status: string; message: string }>(
            `/routers/${routerStore.activeRouter.id}/ppp/profiles/sync`
        );
        toast.success(res.data.message || 'PPP profiles synchronized.');
        await fetchPppProfiles();
    } catch (err: unknown) {
        const axiosErr = err as { response?: { data?: { message?: string } } };
        toast.error(axiosErr.response?.data?.message || 'Failed to sync profiles.');
    } finally {
        isSyncingProfiles.value = false;
    }
};

// Toggle user enable/disable
const toggleUserStatus = async (user: DedicatedUserItem) => {
    if (!routerStore.activeRouter) return;
    try {
        const res = await apiClient.post<{ status: string; message: string }>(
            `/routers/${routerStore.activeRouter.id}/ppp/secrets/${user.id}/toggle`
        );
        toast.success(res.data.message);
        user.disabled = !user.disabled;
        if (user.disabled) {
            user.is_online = false;
            user.active_session = null;
        }
    } catch (err: unknown) {
        const axiosErr = err as { response?: { data?: { message?: string } } };
        toast.error(axiosErr.response?.data?.message || 'Failed to toggle status.');
    }
};

// Kick active session
const kickSession = async (sessionId: string, userName: string) => {
    if (!routerStore.activeRouter) return;
    isKickingSession.value = sessionId;
    try {
        await apiClient.post(
            `/routers/${routerStore.activeRouter.id}/ppp/active/${encodeURIComponent(sessionId)}/kick`
        );
        toast.success(`Session for '${userName}' disconnected.`);
        activeSessions.value = activeSessions.value.filter((s) => s.id !== sessionId);
        const match = dedicatedUsers.value.find((u) => u.name === userName);
        if (match) {
            match.is_online = false;
            match.active_session = null;
        }
    } catch (err: unknown) {
        const axiosErr = err as { response?: { data?: { message?: string } } };
        toast.error(axiosErr.response?.data?.message || 'Failed to disconnect session.');
    } finally {
        isKickingSession.value = null;
    }
};

// Request User Deletion
const requestDeleteUser = (user: DedicatedUserItem) => {
    if (routerStore.activeRouter?.is_safe_mode_enabled && !safeModeStore.isUnlocked) {
        pendingSafeModeAction.value = 'delete_user';
        pendingTargetItem.value = { id: user.id, name: user.name };
        isSafeModeModalOpen.value = true;
        return;
    }
    executeDeleteUser(user.id);
};

const executeDeleteUser = async (userId: string) => {
    if (!routerStore.activeRouter) return;
    isDeletingUser.value = userId;
    try {
        const headers: Record<string, string> = {};
        if (safeModeStore.bypassToken) {
            headers['X-SafeMode-Bypass'] = safeModeStore.bypassToken;
        }

        await apiClient.delete(
            `/routers/${routerStore.activeRouter.id}/ppp/secrets/${userId}`,
            { headers }
        );
        toast.success('Dedicated user deleted.');
        dedicatedUsers.value = dedicatedUsers.value.filter((u) => u.id !== userId);
    } catch (err: unknown) {
        const axiosErr = err as { response?: { status?: number; data?: { message?: string } } };
        if (axiosErr.response?.status === 403) {
            pendingSafeModeAction.value = 'delete_user';
            pendingTargetItem.value = { id: userId, name: 'user' };
            isSafeModeModalOpen.value = true;
        } else {
            toast.error(axiosErr.response?.data?.message || 'Failed to delete dedicated user.');
        }
    } finally {
        isDeletingUser.value = null;
    }
};

// Request Profile Deletion
const requestDeleteProfile = (profile: PppProfileItem) => {
    if (routerStore.activeRouter?.is_safe_mode_enabled && !safeModeStore.isUnlocked) {
        pendingSafeModeAction.value = 'delete_profile';
        pendingTargetItem.value = { id: profile.id, name: profile.name };
        isSafeModeModalOpen.value = true;
        return;
    }
    executeDeleteProfile(profile.id);
};

const executeDeleteProfile = async (profileId: number) => {
    if (!routerStore.activeRouter) return;
    isDeletingProfile.value = profileId;
    try {
        const headers: Record<string, string> = {};
        if (safeModeStore.bypassToken) {
            headers['X-SafeMode-Bypass'] = safeModeStore.bypassToken;
        }

        await apiClient.delete(
            `/routers/${routerStore.activeRouter.id}/ppp/profiles/${profileId}`,
            { headers }
        );
        toast.success('PPP profile deleted.');
        pppProfiles.value = pppProfiles.value.filter((p) => p.id !== profileId);
    } catch (err: unknown) {
        const axiosErr = err as { response?: { status?: number; data?: { message?: string } } };
        if (axiosErr.response?.status === 403) {
            pendingSafeModeAction.value = 'delete_profile';
            pendingTargetItem.value = { id: profileId, name: 'profile' };
            isSafeModeModalOpen.value = true;
        } else {
            toast.error(axiosErr.response?.data?.message || 'Failed to delete profile.');
        }
    } finally {
        isDeletingProfile.value = null;
    }
};

// Safe Mode Unlock callback
const handleSafeModeUnlocked = () => {
    isSafeModeModalOpen.value = false;
    if (pendingSafeModeAction.value === 'delete_user' && pendingTargetItem.value) {
        executeDeleteUser(pendingTargetItem.value.id as string);
    } else if (pendingSafeModeAction.value === 'delete_profile' && pendingTargetItem.value) {
        executeDeleteProfile(pendingTargetItem.value.id as number);
    }
    pendingSafeModeAction.value = null;
    pendingTargetItem.value = null;
};

// Modal helpers
const openAddUser = () => {
    editingUser.value = null;
    isUserModalOpen.value = true;
};

const openEditUser = (user: DedicatedUserItem) => {
    editingUser.value = user;
    isUserModalOpen.value = true;
};

const openAddProfile = () => {
    editingProfile.value = null;
    isProfileModalOpen.value = true;
};

const openEditProfile = (profile: PppProfileItem) => {
    editingProfile.value = profile;
    isProfileModalOpen.value = true;
};

const formatCurrency = (amount: number): string => {
    return new Intl.NumberFormat(localeStore.locale === 'id' ? 'id-ID' : 'en-US', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount);
};

// Initial load & router watchers
watch(
    () => routerStore.activeRouter?.id,
    (id) => {
        if (id) {
            fetchDedicatedUsers(true);
            fetchPppProfiles();
            fetchIpPools();
            if (activeTab.value === 'active') {
                fetchActiveSessions(true);
            }
        }
    },
    { immediate: true }
);

watch(activeTab, (tab) => {
    if (tab === 'active') {
        fetchActiveSessions(true);
        startActivePolling();
    } else {
        stopActivePolling();
        if (tab === 'users') {
            fetchDedicatedUsers(true);
        } else if (tab === 'profiles') {
            fetchPppProfiles();
        }
    }
});

onMounted(() => {
    if (activeTab.value === 'active') {
        startActivePolling();
    }
});

onUnmounted(() => {
    stopActivePolling();
});
</script>

<template>
    <AdminLayout>
        <!-- Top Title & Global Actions (Standardized with VouchersIndexView & RouterListView) -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">
                        Dedicated Studio
                    </span>
                    <span class="text-xs text-muted-foreground font-mono">
                        {{ routerStore.activeRouter?.name || 'Selected Router' }}
                    </span>
                </div>
                <h2 class="text-2xl font-black tracking-tight text-foreground mt-1">
                    {{ localeStore.locale === 'id' ? 'Pelanggan Dedicated (PPP / PPPoE)' : 'Dedicated Subscribers (PPP / PPPoE)' }}
                </h2>
                <p class="text-xs text-muted-foreground mt-0.5">
                    {{ localeStore.locale === 'id' ? 'Manajemen pelanggan internet dedicated, IP statis, profil kecepatan, dan monitoring sesi aktif PPPoE / L2TP.' : 'Manage dedicated corporate / home PPPoE subscribers, static IP bindings, bandwidth limits, and live connection states.' }}
                </p>
            </div>

            <!-- Action Buttons: 1 primary action button per tab + Keep Sync from Router -->
            <div class="flex flex-wrap items-center gap-2.5">
                <!-- Actions for Dedicated Users tab -->
                <template v-if="activeTab === 'users'">
                    <button
                        type="button"
                        @click="syncUsersFromRouter"
                        :disabled="isSyncingUsers || !routerStore.activeRouter"
                        class="px-3 py-2 rounded-xl border border-border hover:bg-muted text-xs font-semibold text-foreground flex items-center space-x-1.5 transition-colors cursor-pointer disabled:opacity-50"
                        title="Pull secrets from MikroTik (/ppp/secret)"
                    >
                        <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isSyncingUsers }" />
                        <span class="hidden sm:inline">{{ localeStore.locale === 'id' ? 'Sinkron dari Router' : 'Sync from Router' }}</span>
                    </button>

                    <button
                        type="button"
                        @click="openAddUser"
                        :disabled="!routerStore.activeRouter"
                        class="px-4 py-2 rounded-xl bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white font-bold text-xs shadow-md shadow-sky-600/20 flex items-center space-x-1.5 transition-colors cursor-pointer disabled:opacity-50"
                    >
                        <Plus class="w-3.5 h-3.5" />
                        <span>{{ localeStore.locale === 'id' ? 'Tambah Pelanggan' : 'Add Subscriber' }}</span>
                    </button>
                </template>

                <!-- Actions for Active Sessions tab -->
                <template v-else-if="activeTab === 'active'">
                    <button
                        type="button"
                        @click="isAutoRefreshActive = !isAutoRefreshActive; isAutoRefreshActive ? startActivePolling() : stopActivePolling()"
                        class="px-3 py-2 rounded-xl border border-border hover:bg-muted text-xs font-semibold text-foreground flex items-center space-x-1.5 transition-colors cursor-pointer"
                    >
                        <Play v-if="!isAutoRefreshActive" class="w-3.5 h-3.5 text-emerald-500" />
                        <Pause v-else class="w-3.5 h-3.5 text-amber-500" />
                        <span>{{ isAutoRefreshActive ? (localeStore.locale === 'id' ? 'Jeda Auto-Refresh' : 'Pause Auto-Refresh') : (localeStore.locale === 'id' ? 'Lanjutkan' : 'Resume') }}</span>
                    </button>

                    <button
                        type="button"
                        @click="fetchActiveSessions(true)"
                        class="p-2 rounded-xl border border-border hover:bg-muted text-foreground transition-colors cursor-pointer"
                        title="Refresh Now"
                    >
                        <RefreshCw class="w-3.5 h-3.5" />
                    </button>
                </template>

                <!-- Actions for PPP Profiles tab -->
                <template v-else-if="activeTab === 'profiles'">
                    <button
                        type="button"
                        @click="syncProfilesFromRouter"
                        :disabled="isSyncingProfiles || !routerStore.activeRouter"
                        class="px-3 py-2 rounded-xl border border-border hover:bg-muted text-xs font-semibold text-foreground flex items-center space-x-1.5 transition-colors cursor-pointer disabled:opacity-50"
                        title="Pull profiles from MikroTik (/ppp/profile)"
                    >
                        <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isSyncingProfiles }" />
                        <span class="hidden sm:inline">{{ localeStore.locale === 'id' ? 'Sinkron Profil' : 'Sync Profiles' }}</span>
                    </button>

                    <button
                        type="button"
                        @click="openAddProfile"
                        :disabled="!routerStore.activeRouter"
                        class="px-4 py-2 rounded-xl bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white font-bold text-xs shadow-md shadow-sky-600/20 flex items-center space-x-1.5 transition-colors cursor-pointer disabled:opacity-50"
                    >
                        <Plus class="w-3.5 h-3.5" />
                        <span>{{ localeStore.locale === 'id' ? 'Tambah Profil' : 'New Profile' }}</span>
                    </button>
                </template>
            </div>
        </div>

        <!-- Tabs Navigation (Identical border-b-2 pattern to VouchersIndexView) -->
        <div class="border-b border-border flex items-center space-x-1">
            <button
                type="button"
                @click="activeTab = 'users'"
                :class="[
                    'px-4 py-2.5 text-xs font-bold border-b-2 transition-all cursor-pointer flex items-center space-x-2',
                    activeTab === 'users'
                        ? 'border-sky-500 text-sky-600 dark:text-sky-400'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                ]"
            >
                <Users class="w-4 h-4" />
                <span>{{ localeStore.locale === 'id' ? 'Pelanggan Dedicated' : 'Dedicated Subscribers' }}</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-muted text-muted-foreground font-mono">
                    {{ dedicatedUsers.length }}
                </span>
            </button>

            <button
                type="button"
                @click="activeTab = 'active'"
                :class="[
                    'px-4 py-2.5 text-xs font-bold border-b-2 transition-all cursor-pointer flex items-center space-x-2',
                    activeTab === 'active'
                        ? 'border-sky-500 text-sky-600 dark:text-sky-400'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                ]"
            >
                <Radio class="w-4 h-4" />
                <span>{{ localeStore.locale === 'id' ? 'Sesi Aktif' : 'Active Sessions' }}</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-muted text-muted-foreground font-mono">
                    {{ activeSessions.length || stats.online }}
                </span>
            </button>

            <button
                type="button"
                @click="activeTab = 'profiles'"
                :class="[
                    'px-4 py-2.5 text-xs font-bold border-b-2 transition-all cursor-pointer flex items-center space-x-2',
                    activeTab === 'profiles'
                        ? 'border-sky-500 text-sky-600 dark:text-sky-400'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                ]"
            >
                <Wifi class="w-4 h-4" />
                <span>{{ localeStore.locale === 'id' ? 'Profil Bandwidth' : 'PPP Profiles' }}</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-muted text-muted-foreground font-mono">
                    {{ pppProfiles.length }}
                </span>
            </button>
        </div>

        <!-- Stats Overview Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <div class="rounded-2xl border border-border bg-card p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                        {{ localeStore.locale === 'id' ? 'Total Dedicated' : 'Total Subscribers' }}
                    </span>
                    <div class="w-8 h-8 rounded-xl bg-sky-500/10 text-sky-500 flex items-center justify-center">
                        <Users class="w-4 h-4" />
                    </div>
                </div>
                <div class="mt-2 text-2xl font-black text-foreground">
                    {{ stats.total }}
                </div>
                <div class="text-[11px] text-muted-foreground mt-0.5">
                    {{ dedicatedUsers.length }} {{ localeStore.locale === 'id' ? 'terdaftar di sistem' : 'registered in router' }}
                </div>
            </div>

            <div class="rounded-2xl border border-border bg-card p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                        {{ localeStore.locale === 'id' ? 'Online / Terhubung' : 'Active Sessions' }}
                    </span>
                    <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center">
                        <Radio class="w-4 h-4" />
                    </div>
                </div>
                <div class="mt-2 text-2xl font-black text-emerald-500 flex items-center space-x-2">
                    <span>{{ stats.online }}</span>
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                    </span>
                </div>
                <div class="text-[11px] text-muted-foreground mt-0.5">
                    {{ localeStore.locale === 'id' ? 'Sesi PPPoE aktif di router' : 'Live PPPoE tunnel sessions' }}
                </div>
            </div>

            <div class="rounded-2xl border border-border bg-card p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                        {{ localeStore.locale === 'id' ? 'Nonaktif / Terisolir' : 'Disabled Secrets' }}
                    </span>
                    <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center">
                        <Power class="w-4 h-4" />
                    </div>
                </div>
                <div class="mt-2 text-2xl font-black text-amber-500">
                    {{ stats.disabled }}
                </div>
                <div class="text-[11px] text-muted-foreground mt-0.5">
                    {{ localeStore.locale === 'id' ? 'Akun dinonaktifkan / isolir' : 'Disabled accounts' }}
                </div>
            </div>

            <div class="rounded-2xl border border-border bg-card p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                        {{ localeStore.locale === 'id' ? 'Estimasi Omzet / Bln' : 'Est. Monthly Revenue' }}
                    </span>
                    <div class="w-8 h-8 rounded-xl bg-indigo-500/10 text-indigo-500 flex items-center justify-center">
                        <DollarSign class="w-4 h-4" />
                    </div>
                </div>
                <div class="mt-2 text-lg sm:text-xl font-black text-foreground truncate">
                    {{ formatCurrency(stats.total_monthly_revenue) }}
                </div>
                <div class="text-[11px] text-muted-foreground mt-0.5">
                    {{ localeStore.locale === 'id' ? 'Total nilai paket langganan' : 'Total monthly subscription' }}
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 1: DEDICATED USERS (PPP SECRETS)       -->
        <!-- ========================================== -->
        <div v-if="activeTab === 'users'" class="space-y-4">
            <!-- Filter Bar Card (Identical pattern to VouchersIndexView) -->
            <div class="rounded-2xl border border-border bg-card p-4 shadow-sm flex flex-col md:flex-row items-center justify-between gap-3">
                <div class="relative w-full md:w-80">
                    <Search class="absolute left-3 top-2.5 w-4 h-4 text-muted-foreground" />
                    <input
                        v-model="searchQuery"
                        @input="debouncedSearch"
                        type="text"
                        :placeholder="localeStore.locale === 'id' ? 'Cari nama, IP remote, telepon...' : 'Search by name, remote IP, phone...'"
                        class="w-full pl-9 pr-4 py-2 rounded-xl border border-border bg-background text-xs font-mono text-foreground placeholder:text-muted-foreground placeholder:font-sans focus:outline-none focus:ring-1 focus:ring-sky-500"
                    />
                </div>

                <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                    <!-- Profile Filter -->
                    <select
                        v-model="profileFilter"
                        @change="fetchDedicatedUsers(false)"
                        class="rounded-xl border border-border bg-background px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-sky-500 cursor-pointer"
                    >
                        <option value="">{{ localeStore.locale === 'id' ? 'Semua Profil' : 'All Profiles' }}</option>
                        <option v-for="p in pppProfiles" :key="p.id" :value="p.name">
                            {{ p.name }}
                        </option>
                    </select>

                    <!-- Status Filter -->
                    <select
                        v-model="statusFilter"
                        @change="fetchDedicatedUsers(false)"
                        class="rounded-xl border border-border bg-background px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-sky-500 cursor-pointer"
                    >
                        <option value="all">{{ localeStore.locale === 'id' ? 'Semua Status' : 'All Status' }}</option>
                        <option value="online">{{ localeStore.locale === 'id' ? 'Hanya Online' : 'Online Only' }}</option>
                        <option value="active">{{ localeStore.locale === 'id' ? 'Akun Aktif' : 'Active (Enabled)' }}</option>
                        <option value="disabled">{{ localeStore.locale === 'id' ? 'Terisolir' : 'Disabled' }}</option>
                    </select>

                    <!-- Refresh Button -->
                    <button
                        type="button"
                        @click="fetchDedicatedUsers(true)"
                        class="p-2 rounded-xl border border-border hover:bg-muted text-foreground transition-colors cursor-pointer"
                        title="Refresh"
                    >
                        <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isLoading }" />
                    </button>
                </div>
            </div>

            <!-- DataTable for Dedicated Subscribers -->
            <DataTable
                :columns="userColumns"
                :data="dedicatedUsers"
                :is-loading="isLoading && dedicatedUsers.length === 0"
                :page-size="15"
                :empty-message="localeStore.locale === 'id' ? 'Belum ada pelanggan dedicated.' : 'No dedicated subscribers found.'"
            >
                <!-- Subscriber & Identity -->
                <template #cell-name="{ row }">
                    <div class="flex items-start space-x-2.5">
                        <div
                            class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 mt-0.5"
                            :class="row.is_online ? 'bg-emerald-500/10 text-emerald-500' : 'bg-muted text-muted-foreground'"
                        >
                            <Radio v-if="row.is_online" class="w-3.5 h-3.5" />
                            <Users v-else class="w-3.5 h-3.5" />
                        </div>
                        <div class="overflow-hidden">
                            <div class="font-bold text-foreground truncate">
                                {{ row.customer_name || row.name }}
                            </div>
                            <div class="text-[11px] font-mono text-muted-foreground flex items-center space-x-1.5 mt-0.5">
                                <span>{{ row.name }}</span>
                                <span v-if="row.customer_phone" class="text-[10px] text-sky-500">
                                    &bull; {{ row.customer_phone }}
                                </span>
                            </div>
                            <div v-if="row.customer_address" class="text-[10px] text-muted-foreground truncate max-w-xs mt-0.5 flex items-center space-x-1">
                                <MapPin class="w-3 h-3 shrink-0" />
                                <span class="truncate">{{ row.customer_address }}</span>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Profile & Protocol -->
                <template #cell-profile="{ row }">
                    <div class="space-y-1">
                        <div class="flex items-center space-x-1.5">
                            <span class="font-semibold text-foreground">{{ row.profile }}</span>
                            <span
                                v-if="row.profile_detail?.rate_limit"
                                class="px-1.5 py-0.2 rounded text-[10px] font-mono bg-sky-500/10 text-sky-600 dark:text-sky-400 font-bold border border-sky-500/20"
                            >
                                {{ row.profile_detail.rate_limit }}
                            </span>
                        </div>
                        <span class="inline-block px-1.5 py-0.2 rounded text-[10px] font-mono uppercase bg-muted text-muted-foreground font-bold">
                            {{ row.service }}
                        </span>
                    </div>
                </template>

                <!-- Remote IP & MAC -->
                <template #cell-remote_address="{ row }">
                    <div class="font-mono text-xs space-y-0.5">
                        <div class="flex items-center space-x-1">
                            <span :class="row.remote_address ? 'text-foreground font-semibold' : 'text-muted-foreground'">
                                {{ row.remote_address || (localeStore.locale === 'id' ? 'Pool Dinamis' : 'Dynamic Pool') }}
                            </span>
                            <span
                                v-if="row.remote_address && row.remote_address.includes('.')"
                                class="text-[9px] px-1 rounded bg-indigo-500/10 text-indigo-500 font-bold uppercase"
                            >
                                Static
                            </span>
                        </div>
                        <div v-if="row.caller_id" class="text-[10px] text-muted-foreground truncate max-w-[140px]" :title="row.caller_id">
                            MAC: {{ row.caller_id }}
                        </div>
                    </div>
                </template>

                <!-- Monthly Price -->
                <template #cell-monthly_price="{ row }">
                    <div class="font-bold text-foreground">
                        {{ formatCurrency(row.monthly_price) }}
                    </div>
                    <div class="text-[10px] text-muted-foreground uppercase font-mono">
                        / {{ row.billing_cycle || 'mo' }}
                    </div>
                </template>

                <!-- Connection Status -->
                <template #cell-status="{ row }">
                    <div v-if="row.is_online" class="flex items-center space-x-1.5">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        <div>
                            <span class="text-[11px] font-bold text-emerald-500">Online</span>
                            <div v-if="row.active_session?.uptime" class="text-[10px] text-muted-foreground font-mono">
                                {{ row.active_session.uptime }}
                            </div>
                        </div>
                    </div>
                    <div v-else class="flex items-center space-x-1.5 text-muted-foreground">
                        <span class="w-2 h-2 rounded-full bg-slate-300 dark:bg-slate-700"></span>
                        <span class="text-[11px] font-medium">Offline</span>
                    </div>
                </template>

                <!-- Account Status (Active/Disabled toggle) -->
                <template #cell-account="{ row }">
                    <button
                        type="button"
                        @click="toggleUserStatus(row)"
                        :class="[
                            'px-2.5 py-1 rounded-full text-[10px] font-bold transition-all cursor-pointer inline-flex items-center space-x-1',
                            !row.disabled
                                ? 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 hover:bg-emerald-500/20'
                                : 'bg-amber-500/10 text-amber-500 border border-amber-500/20 hover:bg-amber-500/20'
                        ]"
                        :title="row.disabled ? 'Click to enable user' : 'Click to disable / isolate user'"
                    >
                        <Power class="w-3 h-3" />
                        <span>{{ !row.disabled ? (localeStore.locale === 'id' ? 'Aktif' : 'Active') : (localeStore.locale === 'id' ? 'Terisolir' : 'Disabled') }}</span>
                    </button>
                </template>

                <!-- Actions -->
                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-end space-x-1.5">
                        <!-- Disconnect button if online -->
                        <button
                            v-if="row.is_online && row.active_session?.id"
                            type="button"
                            @click="kickSession(row.active_session.id, row.name)"
                            class="p-1.5 rounded-lg border border-border text-amber-500 hover:bg-amber-500/10 transition-colors cursor-pointer"
                            title="Disconnect active PPPoE tunnel"
                        >
                            <Power class="w-3.5 h-3.5" />
                        </button>

                        <!-- Edit button -->
                        <button
                            type="button"
                            @click="openEditUser(row)"
                            class="p-1.5 rounded-lg border border-border text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer"
                            title="Edit Dedicated Subscriber"
                        >
                            <Edit3 class="w-3.5 h-3.5" />
                        </button>

                        <!-- Delete button -->
                        <button
                            type="button"
                            @click="requestDeleteUser(row)"
                            :disabled="isDeletingUser === row.id"
                            class="p-1.5 rounded-lg border border-border text-red-500 hover:bg-red-500/10 transition-colors cursor-pointer disabled:opacity-50"
                            title="Delete Dedicated Subscriber"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- ========================================== -->
        <!-- TAB 2: LIVE ACTIVE SESSIONS (/ppp/active)  -->
        <!-- ========================================== -->
        <div v-else-if="activeTab === 'active'" class="space-y-4">
            <DataTable
                :columns="activeColumns"
                :data="activeSessions"
                :page-size="15"
                :empty-message="localeStore.locale === 'id' ? 'Tidak ada sesi PPP aktif saat ini.' : 'No active PPP connections at the moment.'"
            >
                <!-- Name -->
                <template #cell-name="{ row }">
                    <div class="flex items-center space-x-2 font-mono font-bold text-foreground">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>{{ row.name }}</span>
                    </div>
                </template>

                <!-- Service -->
                <template #cell-service="{ row }">
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">
                        {{ row.service }}
                    </span>
                </template>

                <!-- Address -->
                <template #cell-address="{ row }">
                    <span class="font-mono text-foreground font-semibold">{{ row.address }}</span>
                </template>

                <!-- Caller ID -->
                <template #cell-caller_id="{ row }">
                    <span class="font-mono text-muted-foreground">{{ row.caller_id }}</span>
                </template>

                <!-- Uptime -->
                <template #cell-uptime="{ row }">
                    <div class="flex items-center space-x-1 font-mono text-foreground">
                        <Clock class="w-3.5 h-3.5 text-muted-foreground" />
                        <span>{{ row.uptime }}</span>
                    </div>
                </template>

                <!-- Encoding -->
                <template #cell-encoding="{ row }">
                    <span class="font-mono text-muted-foreground text-[11px]">{{ row.encoding }}</span>
                </template>

                <!-- Actions -->
                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-end">
                        <button
                            type="button"
                            @click="kickSession(row.id, row.name)"
                            :disabled="isKickingSession === row.id"
                            class="px-2.5 py-1 rounded-lg border border-red-500/20 bg-red-500/10 text-red-500 hover:bg-red-500/20 text-[11px] font-bold transition-colors cursor-pointer disabled:opacity-50 inline-flex items-center space-x-1"
                        >
                            <Power class="w-3 h-3" />
                            <span>Disconnect</span>
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- ========================================== -->
        <!-- TAB 3: PPP PROFILES                        -->
        <!-- ========================================== -->
        <div v-else-if="activeTab === 'profiles'" class="space-y-4">
            <DataTable
                :columns="profileColumns"
                :data="pppProfiles"
                :page-size="15"
                :empty-message="localeStore.locale === 'id' ? 'Belum ada profil PPP.' : 'No PPP profiles found.'"
            >
                <!-- Name -->
                <template #cell-name="{ row }">
                    <span class="font-bold text-foreground">{{ row.name }}</span>
                </template>

                <!-- Rate Limit -->
                <template #cell-rate_limit="{ row }">
                    <span class="font-mono font-bold text-sky-500">{{ row.rate_limit || '-' }}</span>
                </template>

                <!-- Local Address -->
                <template #cell-local_address="{ row }">
                    <span class="font-mono text-muted-foreground">{{ row.local_address || '-' }}</span>
                </template>

                <!-- Remote Address -->
                <template #cell-remote_address="{ row }">
                    <span class="font-mono text-muted-foreground">{{ row.remote_address || '-' }}</span>
                </template>

                <!-- Only One -->
                <template #cell-only_one="{ row }">
                    <span
                        class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                        :class="row.only_one ? 'bg-emerald-500/10 text-emerald-500' : 'bg-muted text-muted-foreground'"
                    >
                        {{ row.only_one ? 'Yes' : 'No' }}
                    </span>
                </template>

                <!-- Price -->
                <template #cell-price="{ row }">
                    <span class="font-bold text-foreground">{{ formatCurrency(row.price) }}</span>
                </template>

                <!-- Subscribers count -->
                <template #cell-dedicated_users_count="{ row }">
                    <span class="font-mono font-bold text-foreground">{{ row.dedicated_users_count ?? 0 }}</span>
                </template>

                <!-- Actions -->
                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-end space-x-1.5">
                        <button
                            type="button"
                            @click="openEditProfile(row)"
                            class="p-1.5 rounded-lg border border-border text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer"
                            title="Edit Profile"
                        >
                            <Edit3 class="w-3.5 h-3.5" />
                        </button>
                        <button
                            type="button"
                            @click="requestDeleteProfile(row)"
                            :disabled="isDeletingProfile === row.id"
                            class="p-1.5 rounded-lg border border-border text-red-500 hover:bg-red-500/10 transition-colors cursor-pointer disabled:opacity-50"
                            title="Delete Profile"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- Modals -->
        <AddDedicatedUserModal
            :is-open="isUserModalOpen"
            :user-to-edit="editingUser"
            :profiles="pppProfiles"
            :pools="ipPools"
            @close="isUserModalOpen = false"
            @saved="fetchDedicatedUsers(true)"
        />

        <PppProfileModal
            :is-open="isProfileModalOpen"
            :profile-to-edit="editingProfile"
            :pools="ipPools"
            @close="isProfileModalOpen = false"
            @saved="fetchPppProfiles"
        />

        <SafeModeUnlockModal
            :is-open="isSafeModeModalOpen"
            :action-description="`Permanently deleting PPP ${pendingSafeModeAction === 'delete_user' ? 'dedicated subscriber' : 'bandwidth profile'} on MikroTik hardware.`"
            @close="isSafeModeModalOpen = false"
            @unlocked="handleSafeModeUnlocked"
        />
    </AdminLayout>
</template>

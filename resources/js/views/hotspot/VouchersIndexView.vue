<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import AdminLayout from '@/components/layout/AdminLayout.vue';
import GenerateVoucherModal from '@/components/hotspot/GenerateVoucherModal.vue';
import HotspotProfileModal from '@/components/hotspot/HotspotProfileModal.vue';
import VoucherPrintModal from '@/components/hotspot/VoucherPrintModal.vue';
import SafeModeUnlockModal from '@/components/safe-mode/SafeModeUnlockModal.vue';
import { useRouterContextStore } from '@/stores/routerContext';
import { useSafeModeStore } from '@/stores/safeMode';
import { useLocaleStore } from '@/stores/locale';
import { debounce } from '@/utils/debounce';
import { toast } from 'vue-sonner';
import apiClient from '@/services/api';
import type { ApiResponse } from '@/types/api';
import AddHotspotUserModal from '@/components/hotspot/AddHotspotUserModal.vue';
import DataTable from '@/components/common/DataTable.vue';
import type { ColumnDef } from '@tanstack/vue-table';
import type { HotspotProfileItem, VoucherBatchItem, VoucherItem, HotspotUserItem } from '@/types/hotspot';
import {
    Ticket,
    Layers,
    Plus,
    Printer,
    RefreshCw,
    Search,
    Trash2,
    Edit3,
    CheckCircle2,
    AlertCircle,
    Eye,
    ShieldAlert,
    Clock,
    Wifi,
    HardDrive,
    Sparkles,
    ArrowRight,
    Users,
    ArrowDown,
    ArrowUp,
    UserPlus
} from 'lucide-vue-next';

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
}

const voucherColumns: ColumnDef<VoucherItem>[] = [
    {
        id: 'username',
        accessorKey: 'username',
        header: 'Voucher Code',
    },
    {
        id: 'password',
        accessorKey: 'password',
        header: 'Password',
    },
    {
        id: 'profile',
        header: 'Profile',
    },
    {
        id: 'time_limit',
        accessorKey: 'time_limit',
        header: 'Duration',
    },
    {
        id: 'batch',
        header: 'Batch',
    },
    {
        id: 'status',
        accessorKey: 'status',
        header: 'Status',
    },
    {
        id: 'is_printed',
        accessorKey: 'is_printed',
        header: 'Printed',
    },
];

const hotspotUserColumns: ColumnDef<HotspotUserItem>[] = [
    {
        id: 'name',
        accessorKey: 'name',
        header: 'User / Identity',
    },
    {
        id: 'profile',
        accessorKey: 'profile',
        header: 'Profile',
    },
    {
        id: 'server',
        accessorKey: 'server',
        header: 'Server',
    },
    {
        id: 'uptime',
        accessorKey: 'uptime',
        header: 'Uptime',
    },
    {
        id: 'data_usage',
        header: 'Data Usage',
    },
    {
        id: 'disabled',
        accessorKey: 'disabled',
        header: 'Status',
    },
    {
        id: 'actions',
        header: 'Actions',
    },
];

const profileColumns: ColumnDef<HotspotProfileItem>[] = [
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
        id: 'shared_users',
        accessorKey: 'shared_users',
        header: 'Shared Users',
    },
    {
        id: 'validity',
        header: 'Validity',
    },
    {
        id: 'price',
        accessorKey: 'price',
        header: 'Price',
    },
    {
        id: 'selling_price',
        accessorKey: 'selling_price',
        header: 'Selling Price',
    },
    {
        id: 'vouchers_count',
        accessorKey: 'vouchers_count',
        header: 'Vouchers',
    },
    {
        id: 'actions',
        header: 'Actions',
    },
];

const routerStore = useRouterContextStore();
const safeModeStore = useSafeModeStore();
const localeStore = useLocaleStore();

const activeTab = ref<'batches' | 'vouchers' | 'users' | 'profiles'>('batches');

// State
const batches = ref<VoucherBatchItem[]>([]);
const vouchers = ref<VoucherItem[]>([]);
const profiles = ref<HotspotProfileItem[]>([]);
const hotspotUsers = ref<HotspotUserItem[]>([]);

const isLoading = ref(false);
const isSyncingProfiles = ref(false);
const isSyncingUsers = ref(false);
const isLoadingUsers = ref(false);
const isDeletingBatch = ref<string | null>(null);
const isDeletingUser = ref<string | null>(null);
const userToDelete = ref<HotspotUserItem | null>(null);
const isAddUserModalOpen = ref(false);

// Hotspot Users Filters
const hotspotUserSearch = ref('');
const hotspotUserProfileFilter = ref('');
const hotspotUserStatusFilter = ref('');

// Pagination & Filters
const voucherSearch = ref('');
const voucherStatusFilter = ref('');
const selectedBatchFilter = ref<string>('');
const currentPage = ref(1);
const lastPage = ref(1);
const totalVouchers = ref(0);

// Modals
const isGenerateModalOpen = ref(false);
const isProfileModalOpen = ref(false);
const editingProfile = ref<HotspotProfileItem | null>(null);
const isPrintModalOpen = ref(false);
const printBatch = ref<VoucherBatchItem | null>(null);
const printVouchers = ref<VoucherItem[]>([]);
const isUnlockModalOpen = ref(false);

const fetchBatches = async () => {
    if (!routerStore.activeRouter) return;
    try {
        const res = await apiClient.get<ApiResponse<PaginatedData<VoucherBatchItem>>>(
            `/routers/${routerStore.activeRouter.id}/vouchers/batches`
        );
        if (res.data.status === 'success' && res.data.data) {
            batches.value = res.data.data.data;
        }
    } catch {
        // Fallback
    }
};

const fetchProfiles = async () => {
    if (!routerStore.activeRouter) return;
    try {
        const res = await apiClient.get<ApiResponse<HotspotProfileItem[]>>(
            `/routers/${routerStore.activeRouter.id}/profiles`
        );
        if (res.data.status === 'success' && Array.isArray(res.data.data)) {
            profiles.value = res.data.data;
        }
    } catch {
        // Fallback
    }
};

const fetchVouchers = async (page = 1) => {
    if (!routerStore.activeRouter) return;
    isLoading.value = true;
    try {
        const params: Record<string, unknown> = {
            page,
        };
        if (voucherSearch.value.trim()) {
            params.search = voucherSearch.value.trim();
        }
        if (voucherStatusFilter.value) {
            params.status = voucherStatusFilter.value;
        }
        if (selectedBatchFilter.value) {
            params.batch_id = selectedBatchFilter.value;
        }

        const res = await apiClient.get<ApiResponse<PaginatedData<VoucherItem>>>(
            `/routers/${routerStore.activeRouter.id}/vouchers`,
            { params }
        );

        if (res.data.status === 'success' && res.data.data) {
            vouchers.value = res.data.data.data;
            currentPage.value = res.data.data.current_page;
            lastPage.value = res.data.data.last_page;
            totalVouchers.value = res.data.data.total;
        }
    } catch {
        // Fallback
    } finally {
        isLoading.value = false;
    }
};

const handleSyncProfiles = async () => {
    if (!routerStore.activeRouter) return;
    isSyncingProfiles.value = true;

    try {
        const res = await apiClient.post<ApiResponse<HotspotProfileItem[]>>(
            `/routers/${routerStore.activeRouter.id}/profiles/sync`
        );
        const msg = res.data.message || 'Profiles synchronized from MikroTik.';
        toast.success(msg);
        await fetchProfiles();
    } catch (err: unknown) {
        const axiosErr = err as { response?: { data?: { message?: string } } };
        const msg = axiosErr.response?.data?.message || 'Failed to sync profiles from MikroTik.';
        toast.error(msg);
    } finally {
        isSyncingProfiles.value = false;
    }
};

const openPrintBatch = async (batch: VoucherBatchItem) => {
    if (!routerStore.activeRouter) return;
    try {
        const res = await apiClient.get<ApiResponse<VoucherBatchItem>>(
            `/routers/${routerStore.activeRouter.id}/vouchers/batches/${batch.id}`
        );
        if (res.data.status === 'success' && res.data.data) {
            printBatch.value = res.data.data;
            printVouchers.value = res.data.data.vouchers || [];
            isPrintModalOpen.value = true;
        }
    } catch {
        // Fallback
    }
};

const handleDeleteBatch = async (batch: VoucherBatchItem) => {
    if (!routerStore.activeRouter) return;

    if (!confirm(`Are you sure you want to delete batch "${batch.name}" and remove all vouchers from MikroTik?`)) {
        return;
    }

    isDeletingBatch.value = batch.id;

    try {
        const res = await apiClient.post<ApiResponse<null>>(
            `/routers/${routerStore.activeRouter.id}/vouchers/bulk-delete`,
            { batch_id: batch.id }
        );
        const msg = res.data.message || 'Batch deleted successfully.';
        toast.success(msg);
        await Promise.all([fetchBatches(), fetchVouchers()]);
    } catch (err: unknown) {
        const axiosErr = err as { response?: { status?: number; data?: { message?: string } } };
        if (axiosErr.response?.status === 403) {
            toast.error('Bulk deletion blocked by Safe Mode guardrail. Please unlock Safe Mode first.');
            isUnlockModalOpen.value = true;
        } else {
            const msg = axiosErr.response?.data?.message || 'Failed to delete batch.';
            toast.error(msg);
        }
    } finally {
        isDeletingBatch.value = null;
    }
};

const handleDeleteProfile = async (profile: HotspotProfileItem) => {
    if (!routerStore.activeRouter) return;
    if (!confirm(`Are you sure you want to delete profile "${profile.name}"?`)) {
        return;
    }

    try {
        await apiClient.delete(`/routers/${routerStore.activeRouter.id}/profiles/${profile.id}`);
        const msg = `Profile "${profile.name}" deleted.`;
        toast.success(msg);
        await fetchProfiles();
    } catch (err: unknown) {
        const axiosErr = err as { response?: { status?: number; data?: { message?: string } } };
        if (axiosErr.response?.status === 403) {
            toast.error('Profile deletion blocked by Safe Mode guardrail. Please unlock Safe Mode first.');
            isUnlockModalOpen.value = true;
        } else {
            const msg = axiosErr.response?.data?.message || 'Failed to delete profile.';
            toast.error(msg);
        }
    }
};

const filterByBatchAndSwitch = (batchId: string) => {
    selectedBatchFilter.value = batchId;
    activeTab.value = 'vouchers';
    fetchVouchers(1);
};

const fetchHotspotUsers = async (showToast = false) => {
    if (!routerStore.activeRouter) return;
    isLoadingUsers.value = true;
    if (showToast) isSyncingUsers.value = true;
    try {
        const res = await apiClient.get<ApiResponse<HotspotUserItem[]>>(
            `/routers/${routerStore.activeRouter.id}/hotspot/users${showToast ? '?fresh=true' : ''}`
        );
        if (res.data.status === 'success' && Array.isArray(res.data.data)) {
            hotspotUsers.value = res.data.data;
            if (showToast) {
                toast.success(`Synchronized ${res.data.data.length} users from MikroTik.`);
            }
        }
    } catch (err: unknown) {
        const axiosErr = err as { response?: { data?: { message?: string } } };
        const msg = axiosErr.response?.data?.message || 'Failed to fetch hotspot users from MikroTik.';
        if (showToast) {
            toast.error(msg);
        }
    } finally {
        isLoadingUsers.value = false;
        if (showToast) isSyncingUsers.value = false;
    }
};

const filteredHotspotUsers = computed(() => {
    const q = hotspotUserSearch.value.trim().toLowerCase();
    const prof = hotspotUserProfileFilter.value;
    const stat = hotspotUserStatusFilter.value;

    return hotspotUsers.value.filter((u) => {
        if (q) {
            const matchesName = u.name.toLowerCase().includes(q);
            const matchesComment = u.comment ? u.comment.toLowerCase().includes(q) : false;
            const matchesProfile = u.profile.toLowerCase().includes(q);
            if (!matchesName && !matchesComment && !matchesProfile) return false;
        }
        if (prof && u.profile !== prof) return false;
        if (stat === 'active' && u.disabled) return false;
        if (stat === 'disabled' && !u.disabled) return false;
        return true;
    });
});

const handleDeleteHotspotUser = async (user: HotspotUserItem) => {
    if (!routerStore.activeRouter) return;

    if (safeModeStore.isLocked) {
        userToDelete.value = user;
        isUnlockModalOpen.value = true;
        return;
    }

    if (!confirm(`Are you sure you want to remove user "${user.name}" from MikroTik?`)) {
        return;
    }

    isDeletingUser.value = user.name;
    try {
        const res = await apiClient.delete(
            `/routers/${routerStore.activeRouter.id}/hotspot/users/${encodeURIComponent(user.name)}`
        );
        toast.success(res.data.message || `User "${user.name}" removed from router.`);
        await fetchHotspotUsers();
    } catch (err: unknown) {
        const axiosErr = err as { response?: { data?: { message?: string } } };
        toast.error(axiosErr.response?.data?.message || 'Failed to delete hotspot user.');
    } finally {
        isDeletingUser.value = null;
    }
};

watch(() => routerStore.activeRouter?.id, (newId) => {
    if (newId) {
        fetchBatches();
        fetchProfiles();
        fetchVouchers(1);
        fetchHotspotUsers();
    }
});

watch(activeTab, (tab) => {
    if (tab === 'users') {
        fetchHotspotUsers();
    }
});

const debouncedFetchVouchers = debounce(() => {
    if (activeTab.value === 'vouchers') {
        fetchVouchers(1);
    }
}, 300);

watch(voucherSearch, () => {
    debouncedFetchVouchers();
});

watch([voucherStatusFilter, selectedBatchFilter], () => {
    if (activeTab.value === 'vouchers') {
        fetchVouchers(1);
    }
});

onMounted(() => {
    routerStore.fetchRouters();
    fetchBatches();
    fetchProfiles();
    fetchVouchers(1);
    fetchHotspotUsers();
});
</script>

<template>
    <AdminLayout>
        <!-- Top Title & Global Actions -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">
                        Voucher Studio
                    </span>
                    <span class="text-xs text-muted-foreground font-mono">
                        {{ routerStore.activeRouter?.name || 'Selected Router' }}
                    </span>
                </div>
                <h2 class="text-2xl font-black tracking-tight text-foreground mt-1">
                    {{ localeStore.t('vouchers.title') }}
                </h2>
                <p class="text-xs text-muted-foreground mt-0.5">
                    {{ localeStore.t('vouchers.subtitle') }}
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-wrap items-center gap-2.5">
                <!-- Actions for Hotspot Users tab -->
                <template v-if="activeTab === 'users'">
                    <button
                        type="button"
                        @click="fetchHotspotUsers(true)"
                        :disabled="isSyncingUsers || !routerStore.activeRouter"
                        class="px-3 py-2 rounded-xl border border-border hover:bg-muted text-xs font-semibold text-foreground flex items-center space-x-1.5 transition-colors cursor-pointer disabled:opacity-50"
                        title="Pull users from MikroTik (/ip/hotspot/user)"
                    >
                        <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isSyncingUsers }" />
                        <span class="hidden sm:inline">{{ localeStore.t('vouchers.syncUsers') }}</span>
                    </button>

                    <button
                        type="button"
                        @click="isAddUserModalOpen = true"
                        class="px-4 py-2 rounded-xl bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white font-bold text-xs shadow-md shadow-sky-600/20 flex items-center space-x-1.5 transition-colors cursor-pointer"
                    >
                        <UserPlus class="w-3.5 h-3.5" />
                        <span>Add User</span>
                    </button>
                </template>

                <!-- Actions for Profiles tab -->
                <template v-else-if="activeTab === 'profiles'">
                    <button
                        type="button"
                        @click="handleSyncProfiles"
                        :disabled="isSyncingProfiles || !routerStore.activeRouter"
                        class="px-3 py-2 rounded-xl border border-border hover:bg-muted text-xs font-semibold text-foreground flex items-center space-x-1.5 transition-colors cursor-pointer disabled:opacity-50"
                        title="Pull profiles from MikroTik"
                    >
                        <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isSyncingProfiles }" />
                        <span class="hidden sm:inline">{{ localeStore.t('vouchers.syncProfiles') }}</span>
                    </button>

                    <button
                        type="button"
                        @click="editingProfile = null; isProfileModalOpen = true"
                        class="px-4 py-2 rounded-xl bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white font-bold text-xs shadow-md shadow-sky-600/20 flex items-center space-x-1.5 transition-colors cursor-pointer"
                    >
                        <Plus class="w-3.5 h-3.5" />
                        <span>{{ localeStore.t('vouchers.newProfile') }}</span>
                    </button>
                </template>

                <!-- Actions for Batches and Vouchers tabs -->
                <template v-else>
                    <button
                        type="button"
                        @click="isGenerateModalOpen = true"
                        class="px-4 py-2 rounded-xl bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white font-bold text-xs shadow-md shadow-sky-600/20 flex items-center space-x-2 transition-all cursor-pointer"
                    >
                        <Sparkles class="w-4 h-4" />
                        <span>{{ localeStore.t('vouchers.generateVouchers') }}</span>
                    </button>
                </template>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="border-b border-border flex items-center space-x-1">
            <button
                type="button"
                @click="activeTab = 'batches'"
                :class="[
                    'px-4 py-2.5 text-xs font-bold border-b-2 transition-all cursor-pointer flex items-center space-x-2',
                    activeTab === 'batches'
                        ? 'border-sky-500 text-sky-600 dark:text-sky-400'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                ]"
            >
                <Layers class="w-4 h-4" />
                <span>{{ localeStore.t('vouchers.batchesTab') }}</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-muted text-muted-foreground font-mono">
                    {{ batches.length }}
                </span>
            </button>

            <button
                type="button"
                @click="activeTab = 'vouchers'"
                :class="[
                    'px-4 py-2.5 text-xs font-bold border-b-2 transition-all cursor-pointer flex items-center space-x-2',
                    activeTab === 'vouchers'
                        ? 'border-sky-500 text-sky-600 dark:text-sky-400'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                ]"
            >
                <Ticket class="w-4 h-4" />
                <span>{{ localeStore.t('vouchers.allVouchersTab') }}</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-muted text-muted-foreground font-mono">
                    {{ totalVouchers }}
                </span>
            </button>

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
                <span>{{ localeStore.t('vouchers.hotspotUsersTab') }}</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-muted text-muted-foreground font-mono">
                    {{ hotspotUsers.length }}
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
                <span>{{ localeStore.t('vouchers.profilesTab') }}</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-muted text-muted-foreground font-mono">
                    {{ profiles.length }}
                </span>
            </button>
        </div>

        <!-- ========================================== -->
        <!-- TAB 1: VOUCHER BATCHES                     -->
        <!-- ========================================== -->
        <div v-if="activeTab === 'batches'" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div
                    v-for="batch in batches"
                    :key="batch.id"
                    class="rounded-2xl border border-border bg-card p-5 shadow-sm space-y-4 hover:border-border/80 transition-all flex flex-col justify-between"
                >
                    <div class="space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h4 class="font-bold text-sm text-foreground truncate" :title="batch.name">
                                    {{ batch.name }}
                                </h4>
                                <span class="text-xs text-muted-foreground font-mono">
                                    {{ new Date(batch.created_at).toLocaleDateString() }}
                                </span>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20 font-mono">
                                {{ batch.quantity }} pcs
                            </span>
                        </div>

                        <!-- Profile Info Badge -->
                        <div class="p-2.5 rounded-xl bg-muted/40 border border-border text-xs space-y-1 font-mono">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Profile:</span>
                                <strong class="text-foreground">{{ batch.profile?.name || 'Default' }}</strong>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Rate:</span>
                                <span class="text-foreground">{{ batch.profile?.rate_limit || 'Unlimited' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Price:</span>
                                <span class="font-bold text-emerald-600 dark:text-emerald-400">
                                    Rp {{ Number(batch.profile?.selling_price || 0).toLocaleString() }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Batch Card Actions -->
                    <div class="pt-2 border-t border-border flex items-center justify-between gap-2">
                        <button
                            type="button"
                            @click="openPrintBatch(batch)"
                            class="flex-1 py-1.5 px-3 rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs shadow-xs flex items-center justify-center space-x-1.5 cursor-pointer transition-colors"
                        >
                            <Printer class="w-3.5 h-3.5" />
                            <span>Print Thermal / A4</span>
                        </button>

                        <button
                            type="button"
                            @click="filterByBatchAndSwitch(batch.id)"
                            class="p-2 rounded-lg border border-border hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                            title="View Vouchers in Batch"
                        >
                            <Eye class="w-3.5 h-3.5" />
                        </button>

                        <button
                            type="button"
                            @click="handleDeleteBatch(batch)"
                            :disabled="isDeletingBatch !== null"
                            class="p-2 rounded-lg border border-border hover:bg-destructive/10 text-muted-foreground hover:text-destructive transition-colors cursor-pointer disabled:opacity-40"
                            title="Delete Batch & MikroTik Users"
                        >
                            <RefreshCw v-if="isDeletingBatch === batch.id" class="w-3.5 h-3.5 animate-spin text-destructive" />
                            <Trash2 v-else class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="batches.length === 0" class="col-span-full py-16 text-center border-2 border-dashed border-border rounded-2xl p-8">
                    <div class="flex flex-col items-center justify-center space-y-3">
                        <div class="w-12 h-12 rounded-2xl bg-muted text-muted-foreground flex items-center justify-center">
                            <Ticket class="w-6 h-6" />
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-foreground">No Voucher Batches Generated Yet</h4>
                            <p class="text-xs text-muted-foreground mt-0.5">
                                Generate your first batch of vouchers to print thermal receipts or A4 sheets.
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="isGenerateModalOpen = true"
                            class="px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs shadow-md shadow-sky-600/20 flex items-center space-x-1.5 cursor-pointer"
                        >
                            <Sparkles class="w-4 h-4" />
                            <span>Generate First Batch</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 2: ALL VOUCHERS                        -->
        <!-- ========================================== -->
        <div v-else-if="activeTab === 'vouchers'" class="space-y-4">
            <!-- Filter Bar -->
            <div class="rounded-2xl border border-border bg-card p-4 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="relative w-full sm:w-72">
                    <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="voucherSearch"
                        type="text"
                        placeholder="Search voucher code..."
                        class="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-border bg-background focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 text-foreground"
                    />
                </div>

                <div class="flex flex-wrap items-center gap-2.5 w-full sm:w-auto">
                    <!-- Batch Filter -->
                    <select
                        v-model="selectedBatchFilter"
                        class="px-3 py-1.5 text-xs rounded-xl border border-border bg-background text-foreground focus:outline-none focus:border-sky-500 cursor-pointer"
                    >
                        <option value="">All Batches</option>
                        <option v-for="b in batches" :key="b.id" :value="b.id">
                            {{ b.name }}
                        </option>
                    </select>

                    <!-- Status Filter -->
                    <select
                        v-model="voucherStatusFilter"
                        class="px-3 py-1.5 text-xs rounded-xl border border-border bg-background text-foreground focus:outline-none focus:border-sky-500 cursor-pointer"
                    >
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="used">Used</option>
                        <option value="expired">Expired</option>
                    </select>

                    <button
                        type="button"
                        @click="fetchVouchers(currentPage)"
                        class="p-2 rounded-xl border border-border hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer"
                        title="Refresh Table"
                    >
                        <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isLoading }" />
                    </button>
                </div>
            </div>

            <!-- Vouchers Table (Powered by TanStack Table v8) -->
            <DataTable
                :columns="voucherColumns"
                :data="vouchers"
                :is-loading="isLoading && vouchers.length === 0"
                :manual-pagination="true"
                :page-count="lastPage"
                :page-index="currentPage - 1"
                :total-rows="totalVouchers"
                empty-message="No vouchers found matching criteria."
                @page-change="(p) => fetchVouchers(p)"
            >
                <!-- Username -->
                <template #cell-username="{ row }">
                    <span class="font-bold font-mono text-foreground">{{ row.username }}</span>
                </template>

                <!-- Password -->
                <template #cell-password="{ row }">
                    <span class="font-mono text-muted-foreground">{{ row.password }}</span>
                </template>

                <!-- Profile -->
                <template #cell-profile="{ row }">
                    <span class="font-sans text-foreground">{{ row.profile?.name || '-' }}</span>
                </template>

                <!-- Time limit -->
                <template #cell-time_limit="{ row }">
                    <span class="font-mono text-muted-foreground">{{ row.time_limit || '-' }}</span>
                </template>

                <!-- Batch -->
                <template #cell-batch="{ row }">
                    <span class="font-sans text-muted-foreground truncate max-w-[150px] inline-block">{{ row.batch?.name || '-' }}</span>
                </template>

                <!-- Status -->
                <template #cell-status="{ row }">
                    <span
                        :class="[
                            'px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider',
                            row.status === 'active'
                                ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                : 'bg-muted text-muted-foreground'
                        ]"
                    >
                        {{ row.status }}
                    </span>
                </template>

                <!-- Printed -->
                <template #cell-is_printed="{ row }">
                    <span
                        :class="[
                            'inline-flex items-center text-[10px] font-bold',
                            row.is_printed ? 'text-sky-600 dark:text-sky-400' : 'text-muted-foreground'
                        ]"
                    >
                        {{ row.is_printed ? 'Printed' : 'Unprinted' }}
                    </span>
                </template>
            </DataTable>
        </div>

        <!-- ========================================== -->
        <!-- TAB 3: HOTSPOT USERS (/ip/hotspot/user)    -->
        <!-- ========================================== -->
        <div v-else-if="activeTab === 'users'" class="space-y-4">
            <!-- Filter Bar -->
            <div class="rounded-2xl border border-border bg-card p-4 shadow-sm flex flex-col md:flex-row items-center justify-between gap-3">
                <div class="relative w-full md:w-80">
                    <Search class="absolute left-3 top-2.5 w-4 h-4 text-muted-foreground" />
                    <input
                        v-model="hotspotUserSearch"
                        type="text"
                        placeholder="Search by user, MAC, or comment..."
                        class="w-full pl-9 pr-4 py-2 rounded-xl border border-border bg-background text-xs font-mono text-foreground placeholder:text-muted-foreground placeholder:font-sans focus:outline-none focus:ring-1 focus:ring-sky-500"
                    />
                </div>

                <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                    <!-- Profile Filter -->
                    <select
                        v-model="hotspotUserProfileFilter"
                        class="rounded-xl border border-border bg-background px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-sky-500 cursor-pointer"
                    >
                        <option value="">{{ localeStore.t('vouchers.allProfiles') || 'All Profiles' }}</option>
                        <option v-for="p in profiles" :key="p.id" :value="p.name">
                            {{ p.name }}
                        </option>
                    </select>

                    <!-- Status Filter -->
                    <select
                        v-model="hotspotUserStatusFilter"
                        class="rounded-xl border border-border bg-background px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-sky-500 cursor-pointer"
                    >
                        <option value="">All Status</option>
                        <option value="active">Active (Enabled)</option>
                        <option value="disabled">Disabled</option>
                    </select>

                    <!-- Refresh Button -->
                    <button
                        type="button"
                        @click="fetchHotspotUsers(true)"
                        :disabled="isSyncingUsers || !routerStore.activeRouter"
                        class="p-2 rounded-xl border border-border hover:bg-muted text-foreground transition-colors cursor-pointer disabled:opacity-50"
                        title="Sync from MikroTik"
                    >
                        <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isSyncingUsers }" />
                    </button>
                </div>
            </div>

            <!-- Users Table Card (Powered by TanStack Table v8) -->
            <DataTable
                :columns="hotspotUserColumns"
                :data="filteredHotspotUsers"
                :is-loading="isLoadingUsers && hotspotUsers.length === 0"
                :page-size="15"
                :empty-message="localeStore.t('vouchers.noHotspotUsers')"
            >
                <!-- Name -->
                <template #cell-name="{ row }">
                    <div class="flex items-center space-x-2">
                        <span class="font-bold text-foreground font-mono">{{ row.name }}</span>
                        <span
                            v-if="row.comment"
                            class="px-1.5 py-0.5 rounded text-[10px] font-sans font-medium bg-muted text-muted-foreground border border-border"
                            :title="row.comment"
                        >
                            {{ row.comment }}
                        </span>
                    </div>
                </template>

                <!-- Profile -->
                <template #cell-profile="{ row }">
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20 font-mono">
                        {{ row.profile }}
                    </span>
                </template>

                <!-- Server -->
                <template #cell-server="{ row }">
                    <span class="text-muted-foreground font-sans text-[11px]">{{ row.server || 'all' }}</span>
                </template>

                <!-- Uptime -->
                <template #cell-uptime="{ row }">
                    <div class="flex items-center space-x-1 font-mono text-foreground">
                        <Clock class="w-3.5 h-3.5 text-muted-foreground" />
                        <span>{{ row.uptime || '0s' }}</span>
                    </div>
                </template>

                <!-- Data Usage -->
                <template #cell-data_usage="{ row }">
                    <div class="flex flex-col font-mono">
                        <span class="font-bold text-foreground">{{ row.bytes_total_formatted }}</span>
                        <div class="flex items-center space-x-2 text-[10px] text-muted-foreground mt-0.5">
                            <span class="flex items-center text-sky-600 dark:text-sky-400">
                                <ArrowDown class="w-3 h-3 mr-0.5" />{{ row.bytes_in_formatted }}
                            </span>
                            <span class="flex items-center text-indigo-600 dark:text-indigo-400">
                                <ArrowUp class="w-3 h-3 mr-0.5" />{{ row.bytes_out_formatted }}
                            </span>
                        </div>
                    </div>
                </template>

                <!-- Status -->
                <template #cell-disabled="{ row }">
                    <span
                        :class="[
                            'px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider',
                            row.disabled
                                ? 'bg-muted text-muted-foreground'
                                : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                        ]"
                    >
                        {{ row.disabled ? 'Disabled' : 'Active' }}
                    </span>
                </template>

                <!-- Actions -->
                <template #cell-actions="{ row }">
                    <div class="text-right">
                        <button
                            type="button"
                            @click="handleDeleteHotspotUser(row)"
                            :disabled="isDeletingUser === row.name"
                            class="p-1.5 rounded-lg border border-border text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-colors cursor-pointer disabled:opacity-50"
                            title="Delete user from MikroTik"
                        >
                            <Trash2 class="w-3.5 h-3.5" :class="{ 'animate-pulse': isDeletingUser === row.name }" />
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- ========================================== -->
        <!-- TAB 4: HOTSPOT PROFILES                    -->
        <!-- ========================================== -->
        <div v-else-if="activeTab === 'profiles'" class="space-y-4">
            <DataTable
                :columns="profileColumns"
                :data="profiles"
                :page-size="10"
                empty-message="No hotspot profiles found. Click 'Sync Profiles' or 'New Profile'."
            >
                <!-- Profile Name -->
                <template #cell-name="{ row }">
                    <span class="font-bold text-foreground font-mono">{{ row.name }}</span>
                </template>

                <!-- Rate Limit -->
                <template #cell-rate_limit="{ row }">
                    <span class="font-mono text-sky-600 dark:text-sky-400">{{ row.rate_limit || 'Unlimited' }}</span>
                </template>

                <!-- Shared Users -->
                <template #cell-shared_users="{ row }">
                    <span class="font-mono text-muted-foreground">{{ row.shared_users }} User(s)</span>
                </template>

                <!-- Validity -->
                <template #cell-validity="{ row }">
                    <span class="font-sans text-muted-foreground">{{ row.validity_duration ? `${row.validity_duration} ${row.validity_unit}` : '-' }}</span>
                </template>

                <!-- Price -->
                <template #cell-price="{ row }">
                    <span class="font-mono text-muted-foreground">Rp {{ Number(row.price).toLocaleString() }}</span>
                </template>

                <!-- Selling Price -->
                <template #cell-selling_price="{ row }">
                    <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">Rp {{ Number(row.selling_price).toLocaleString() }}</span>
                </template>

                <!-- Vouchers Count -->
                <template #cell-vouchers_count="{ row }">
                    <span class="font-mono text-muted-foreground">{{ row.vouchers_count ?? 0 }}</span>
                </template>

                <!-- Actions -->
                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-end space-x-1.5 font-sans">
                        <button
                            type="button"
                            @click="editingProfile = row; isProfileModalOpen = true"
                            class="p-1.5 rounded-lg border border-border hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                            title="Edit Profile"
                        >
                            <Edit3 class="w-3.5 h-3.5" />
                        </button>
                        <button
                            type="button"
                            @click="handleDeleteProfile(row)"
                            class="p-1.5 rounded-lg border border-border hover:bg-destructive/10 text-muted-foreground hover:text-destructive transition-colors cursor-pointer"
                            title="Delete Profile (Safe Mode)"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- Global Modals -->
        <GenerateVoucherModal
            :is-open="isGenerateModalOpen"
            @close="isGenerateModalOpen = false"
            @generated="(batch) => {
                fetchBatches();
                fetchVouchers();
                openPrintBatch(batch);
            }"
        />

        <HotspotProfileModal
            :is-open="isProfileModalOpen"
            :profile-to-edit="editingProfile"
            @close="isProfileModalOpen = false"
            @saved="fetchProfiles"
        />

        <VoucherPrintModal
            :is-open="isPrintModalOpen"
            :batch="printBatch"
            :vouchers="printVouchers"
            @close="isPrintModalOpen = false"
            @printed="fetchVouchers"
        />

        <AddHotspotUserModal
            :is-open="isAddUserModalOpen"
            :profiles="profiles"
            @close="isAddUserModalOpen = false"
            @saved="fetchHotspotUsers(true)"
        />

        <SafeModeUnlockModal
            :is-open="isUnlockModalOpen"
            @close="isUnlockModalOpen = false"
            @unlocked="() => {
                isUnlockModalOpen = false;
                if (userToDelete) {
                    handleDeleteHotspotUser(userToDelete);
                    userToDelete = null;
                }
            }"
        />
    </AdminLayout>
</template>

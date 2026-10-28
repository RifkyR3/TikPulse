<script lang="ts">
    import { onMount } from 'svelte';
    import AdminLayout from '@/components/layout/AdminLayout.svelte';
    import GenerateVoucherModal from '@/components/hotspot/GenerateVoucherModal.svelte';
    import HotspotProfileModal from '@/components/hotspot/HotspotProfileModal.svelte';
    import VoucherPrintModal from '@/components/hotspot/VoucherPrintModal.svelte';
    import SafeModeUnlockModal from '@/components/safe-mode/SafeModeUnlockModal.svelte';
    import AddHotspotUserModal from '@/components/hotspot/AddHotspotUserModal.svelte';
    import DataTable, { type Column } from '@/components/common/DataTable.svelte';
    import { routerStore } from '@/stores/routerContext.svelte';
    import { safeModeStore } from '@/stores/safeMode.svelte';
    import { localeStore } from '@/stores/locale.svelte';
    import { debounce } from '@/utils/debounce';
    import { toast } from 'svelte-sonner';
    import apiClient from '@/services/api';
    import type { ApiResponse } from '@/types/api';
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
        Eye,
        Clock,
        Wifi,
        Sparkles,
        Users,
        ArrowDown,
        ArrowUp,
        UserPlus,
    } from '@lucide/svelte';

    interface PaginatedData<T> {
        data: T[];
        current_page: number;
        last_page: number;
        total: number;
    }

    const voucherColumns: Column<VoucherItem>[] = [
        { id: 'username', header: 'Voucher Code' },
        { id: 'password', header: 'Password' },
        { id: 'profile', header: 'Profile' },
        { id: 'time_limit', header: 'Duration' },
        { id: 'batch', header: 'Batch' },
        { id: 'status', header: 'Status' },
        { id: 'is_printed', header: 'Printed' },
    ];

    const hotspotUserColumns: Column<HotspotUserItem>[] = [
        { id: 'name', header: 'User / Identity' },
        { id: 'profile', header: 'Profile' },
        { id: 'server', header: 'Server' },
        { id: 'uptime', header: 'Uptime' },
        { id: 'data_usage', header: 'Data Usage' },
        { id: 'disabled', header: 'Status' },
        { id: 'actions', header: 'Actions', align: 'right' },
    ];

    const profileColumns: Column<HotspotProfileItem>[] = [
        { id: 'name', header: 'Profile Name' },
        { id: 'rate_limit', header: 'Rate Limit (Rx/Tx)' },
        { id: 'shared_users', header: 'Shared Users' },
        { id: 'validity', header: 'Validity' },
        { id: 'price', header: 'Price' },
        { id: 'selling_price', header: 'Selling Price' },
        { id: 'vouchers_count', header: 'Vouchers' },
        { id: 'actions', header: 'Actions', align: 'right' },
    ];

    let activeTab = $state<'batches' | 'vouchers' | 'users' | 'profiles'>('batches');

    // State
    let batches = $state<VoucherBatchItem[]>([]);
    let vouchers = $state<VoucherItem[]>([]);
    let profiles = $state<HotspotProfileItem[]>([]);
    let hotspotUsers = $state<HotspotUserItem[]>([]);

    let isLoading = $state(false);
    let isSyncingProfiles = $state(false);
    let isSyncingUsers = $state(false);
    let isLoadingUsers = $state(false);
    let isDeletingBatch = $state<string | null>(null);
    let isDeletingUser = $state<string | null>(null);
    let userToDelete = $state<HotspotUserItem | null>(null);
    let isAddUserModalOpen = $state(false);

    // Hotspot Users Filters
    let hotspotUserSearch = $state('');
    let hotspotUserProfileFilter = $state('');
    let hotspotUserStatusFilter = $state('');

    // Pagination & Filters
    let voucherSearch = $state('');
    let voucherStatusFilter = $state('');
    let selectedBatchFilter = $state('');
    let currentPage = $state(1);
    let lastPage = $state(1);
    let totalVouchers = $state(0);

    // Modals
    let isGenerateModalOpen = $state(false);
    let isProfileModalOpen = $state(false);
    let editingProfile = $state<HotspotProfileItem | null>(null);
    let isPrintModalOpen = $state(false);
    let printBatch = $state<VoucherBatchItem | null>(null);
    let printVouchers = $state<VoucherItem[]>([]);
    let isUnlockModalOpen = $state(false);

    async function fetchBatches() {
        if (!routerStore.activeRouter) return;
        try {
            const res = await apiClient.get<ApiResponse<PaginatedData<VoucherBatchItem>>>(
                `/routers/${routerStore.activeRouter.id}/vouchers/batches`
            );
            if (res.data.status === 'success' && res.data.data) {
                batches = res.data.data.data;
            }
        } catch {
            // Fallback
        }
    }

    async function fetchProfiles() {
        if (!routerStore.activeRouter) return;
        try {
            const res = await apiClient.get<ApiResponse<HotspotProfileItem[]>>(
                `/routers/${routerStore.activeRouter.id}/profiles`
            );
            if (res.data.status === 'success' && Array.isArray(res.data.data)) {
                profiles = res.data.data;
            }
        } catch {
            // Fallback
        }
    }

    async function fetchVouchers(page = 1) {
        if (!routerStore.activeRouter) return;
        isLoading = true;
        try {
            const params: Record<string, unknown> = { page };
            if (voucherSearch.trim()) {
                params.search = voucherSearch.trim();
            }
            if (voucherStatusFilter) {
                params.status = voucherStatusFilter;
            }
            if (selectedBatchFilter) {
                params.batch_id = selectedBatchFilter;
            }

            const res = await apiClient.get<ApiResponse<PaginatedData<VoucherItem>>>(
                `/routers/${routerStore.activeRouter.id}/vouchers`,
                { params }
            );

            if (res.data.status === 'success' && res.data.data) {
                vouchers = res.data.data.data;
                currentPage = res.data.data.current_page;
                lastPage = res.data.data.last_page;
                totalVouchers = res.data.data.total;
            }
        } catch {
            // Fallback
        } finally {
            isLoading = false;
        }
    }

    async function handleSyncProfiles() {
        if (!routerStore.activeRouter) return;
        isSyncingProfiles = true;
        try {
            const res = await apiClient.post<ApiResponse<HotspotProfileItem[]>>(
                `/routers/${routerStore.activeRouter.id}/profiles/sync`
            );
            toast.success(res.data.message || 'Profiles synchronized from MikroTik.');
            await fetchProfiles();
        } catch (err: unknown) {
            const axiosErr = err as { response?: { data?: { message?: string } } };
            toast.error(axiosErr.response?.data?.message || 'Failed to sync profiles from MikroTik.');
        } finally {
            isSyncingProfiles = false;
        }
    }

    async function openPrintBatch(batch: VoucherBatchItem) {
        if (!routerStore.activeRouter) return;
        try {
            const res = await apiClient.get<ApiResponse<VoucherBatchItem>>(
                `/routers/${routerStore.activeRouter.id}/vouchers/batches/${batch.id}`
            );
            if (res.data.status === 'success' && res.data.data) {
                printBatch = res.data.data;
                printVouchers = res.data.data.vouchers || [];
                isPrintModalOpen = true;
            }
        } catch {
            // Fallback
        }
    }

    async function handleDeleteBatch(batch: VoucherBatchItem) {
        if (!routerStore.activeRouter) return;

        if (!confirm(`Are you sure you want to delete batch "${batch.name}" and remove all vouchers from MikroTik?`)) {
            return;
        }

        isDeletingBatch = batch.id;
        try {
            const res = await apiClient.post<ApiResponse<null>>(
                `/routers/${routerStore.activeRouter.id}/vouchers/bulk-delete`,
                { batch_id: batch.id }
            );
            toast.success(res.data.message || 'Batch deleted successfully.');
            await Promise.all([fetchBatches(), fetchVouchers()]);
        } catch (err: unknown) {
            const axiosErr = err as { response?: { status?: number; data?: { message?: string } } };
            if (axiosErr.response?.status === 403) {
                toast.error('Bulk deletion blocked by Safe Mode guardrail. Please unlock Safe Mode first.');
                isUnlockModalOpen = true;
            } else {
                toast.error(axiosErr.response?.data?.message || 'Failed to delete batch.');
            }
        } finally {
            isDeletingBatch = null;
        }
    }

    async function handleDeleteProfile(profile: HotspotProfileItem) {
        if (!routerStore.activeRouter) return;
        if (!confirm(`Are you sure you want to delete profile "${profile.name}"?`)) {
            return;
        }

        try {
            await apiClient.delete(`/routers/${routerStore.activeRouter.id}/profiles/${profile.id}`);
            toast.success(`Profile "${profile.name}" deleted.`);
            await fetchProfiles();
        } catch (err: unknown) {
            const axiosErr = err as { response?: { status?: number; data?: { message?: string } } };
            if (axiosErr.response?.status === 403) {
                toast.error('Profile deletion blocked by Safe Mode guardrail. Please unlock Safe Mode first.');
                isUnlockModalOpen = true;
            } else {
                toast.error(axiosErr.response?.data?.message || 'Failed to delete profile.');
            }
        }
    }

    function filterByBatchAndSwitch(batchId: string) {
        selectedBatchFilter = batchId;
        activeTab = 'vouchers';
        fetchVouchers(1);
    }

    async function syncHotspotUsersFromRouter() {
        if (!routerStore.activeRouter) return;
        isSyncingUsers = true;
        try {
            const res = await apiClient.post<ApiResponse<any>>(
                `/routers/${routerStore.activeRouter.id}/hotspot/users/sync`
            );
            toast.success(res.data.message || 'Hotspot users synchronized with credentials.');
            await Promise.all([fetchHotspotUsers(), fetchVouchers(1)]);
        } catch (err: unknown) {
            const axiosErr = err as { response?: { data?: { message?: string } } };
            toast.error(axiosErr.response?.data?.message || 'Failed to sync hotspot users.');
        } finally {
            isSyncingUsers = false;
        }
    }

    async function fetchHotspotUsers(showToast = false) {
        if (!routerStore.activeRouter) return;
        isLoadingUsers = true;
        if (showToast) isSyncingUsers = true;
        try {
            const res = await apiClient.get<ApiResponse<HotspotUserItem[]>>(
                `/routers/${routerStore.activeRouter.id}/hotspot/users${showToast ? '?fresh=true' : ''}`
            );
            if (res.data.status === 'success' && Array.isArray(res.data.data)) {
                hotspotUsers = res.data.data;
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
            isLoadingUsers = false;
            if (showToast) isSyncingUsers = false;
        }
    }

    const filteredHotspotUsers = $derived.by(() => {
        const q = hotspotUserSearch.trim().toLowerCase();
        const prof = hotspotUserProfileFilter;
        const stat = hotspotUserStatusFilter;

        return hotspotUsers.filter((u) => {
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

    async function handleDeleteHotspotUser(user: HotspotUserItem) {
        if (!routerStore.activeRouter) return;

        if (safeModeStore.isLocked) {
            userToDelete = user;
            isUnlockModalOpen = true;
            return;
        }

        if (!confirm(`Are you sure you want to remove user "${user.name}" from MikroTik?`)) {
            return;
        }

        isDeletingUser = user.name;
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
            isDeletingUser = null;
        }
    }

    const debouncedFetchVouchers = debounce(() => {
        if (activeTab === 'vouchers') {
            fetchVouchers(1);
        }
    }, 300);

    function onVoucherSearchInput() {
        debouncedFetchVouchers();
    }

    function onVoucherFilterChange() {
        if (activeTab === 'vouchers') {
            fetchVouchers(1);
        }
    }

    let currentVoucherRouterId: number | null = null;

    $effect(() => {
        const id = routerStore.activeRouterId;
        if (id && id !== currentVoucherRouterId) {
            currentVoucherRouterId = id;
            fetchBatches();
            fetchProfiles();
            fetchVouchers(1);
            fetchHotspotUsers();
        }
    });

    onMount(() => {
        routerStore.fetchRouters();
        fetchBatches();
        fetchProfiles();
        fetchVouchers(1);
        fetchHotspotUsers();
    });
</script>

<!-- Voucher Cell Snippets -->
{#snippet vUsernameCell(row: VoucherItem)}
    <span class="font-bold font-mono text-foreground">{row.username}</span>
{/snippet}

{#snippet vPasswordCell(row: VoucherItem)}
    <span class="font-mono text-muted-foreground">{row.password}</span>
{/snippet}

{#snippet vProfileCell(row: VoucherItem)}
    <span class="font-sans text-foreground">{row.profile?.name || '-'}</span>
{/snippet}

{#snippet vTimeLimitCell(row: VoucherItem)}
    <span class="font-mono text-muted-foreground">{row.time_limit || '-'}</span>
{/snippet}

{#snippet vBatchCell(row: VoucherItem)}
    <span class="font-sans text-muted-foreground truncate max-w-[150px] inline-block">{row.batch?.name || '-'}</span>
{/snippet}

{#snippet vStatusCell(row: VoucherItem)}
    <span
        class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {row.status === 'active'
            ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
            : 'bg-muted text-muted-foreground'}"
    >
        {row.status}
    </span>
{/snippet}

{#snippet vPrintedCell(row: VoucherItem)}
    <span
        class="inline-flex items-center text-[10px] font-bold {row.is_printed ? 'text-sky-600 dark:text-sky-400' : 'text-muted-foreground'}"
    >
        {row.is_printed ? 'Printed' : 'Unprinted'}
    </span>
{/snippet}

<!-- Hotspot User Cell Snippets -->
{#snippet uNameCell(row: HotspotUserItem)}
    <div class="flex items-center space-x-2">
        <span class="font-bold text-foreground font-mono">{row.name}</span>
        {#if row.comment}
            <span
                class="px-1.5 py-0.5 rounded text-[10px] font-sans font-medium bg-muted text-muted-foreground border border-border"
                title={row.comment}
            >
                {row.comment}
            </span>
        {/if}
    </div>
{/snippet}

{#snippet uProfileCell(row: HotspotUserItem)}
    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20 font-mono">
        {row.profile}
    </span>
{/snippet}

{#snippet uServerCell(row: HotspotUserItem)}
    <span class="text-muted-foreground font-sans text-[11px]">{row.server || 'all'}</span>
{/snippet}

{#snippet uUptimeCell(row: HotspotUserItem)}
    <div class="flex items-center space-x-1 font-mono text-foreground">
        <Clock class="w-3.5 h-3.5 text-muted-foreground mr-1" />
        <span>{row.uptime || '0s'}</span>
    </div>
{/snippet}

{#snippet uDataUsageCell(row: HotspotUserItem)}
    <div class="flex flex-col font-mono">
        <span class="font-bold text-foreground">{row.bytes_total_formatted}</span>
        <div class="flex items-center space-x-2 text-[10px] text-muted-foreground mt-0.5">
            <span class="flex items-center text-sky-600 dark:text-sky-400">
                <ArrowDown class="w-3 h-3 mr-0.5" />{row.bytes_in_formatted}
            </span>
            <span class="flex items-center text-indigo-600 dark:text-indigo-400">
                <ArrowUp class="w-3 h-3 mr-0.5" />{row.bytes_out_formatted}
            </span>
        </div>
    </div>
{/snippet}

{#snippet uDisabledCell(row: HotspotUserItem)}
    <span
        class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {row.disabled
            ? 'bg-muted text-muted-foreground'
            : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'}"
    >
        {row.disabled ? 'Disabled' : 'Active'}
    </span>
{/snippet}

{#snippet uActionsCell(row: HotspotUserItem)}
    <div class="text-right">
        <button
            type="button"
            onclick={() => handleDeleteHotspotUser(row)}
            disabled={isDeletingUser === row.name}
            class="p-1.5 rounded-lg border border-border text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-colors cursor-pointer disabled:opacity-50"
            title="Delete user from MikroTik"
        >
            <Trash2 class="w-3.5 h-3.5 {isDeletingUser === row.name ? 'animate-pulse' : ''}" />
        </button>
    </div>
{/snippet}

<!-- Profile Cell Snippets -->
{#snippet pNameCell(row: HotspotProfileItem)}
    <span class="font-bold text-foreground font-mono">{row.name}</span>
{/snippet}

{#snippet pRateLimitCell(row: HotspotProfileItem)}
    <span class="font-mono text-sky-600 dark:text-sky-400">{row.rate_limit || 'Unlimited'}</span>
{/snippet}

{#snippet pSharedUsersCell(row: HotspotProfileItem)}
    <span class="font-mono text-muted-foreground">{row.shared_users} User(s)</span>
{/snippet}

{#snippet pValidityCell(row: HotspotProfileItem)}
    <span class="font-sans text-muted-foreground">{row.validity_duration ? `${row.validity_duration} ${row.validity_unit}` : '-'}</span>
{/snippet}

{#snippet pPriceCell(row: HotspotProfileItem)}
    <span class="font-mono text-muted-foreground">Rp {Number(row.price).toLocaleString()}</span>
{/snippet}

{#snippet pSellingPriceCell(row: HotspotProfileItem)}
    <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">Rp {Number(row.selling_price).toLocaleString()}</span>
{/snippet}

{#snippet pVouchersCountCell(row: HotspotProfileItem)}
    <span class="font-mono text-muted-foreground">{row.vouchers_count ?? 0}</span>
{/snippet}

{#snippet pActionsCell(row: HotspotProfileItem)}
    <div class="flex items-center justify-end space-x-1.5 font-sans">
        <button
            type="button"
            onclick={() => { editingProfile = row; isProfileModalOpen = true; }}
            class="p-1.5 rounded-lg border border-border hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
            title="Edit Profile"
        >
            <Edit3 class="w-3.5 h-3.5" />
        </button>
        <button
            type="button"
            onclick={() => handleDeleteProfile(row)}
            class="p-1.5 rounded-lg border border-border hover:bg-destructive/10 text-muted-foreground hover:text-destructive transition-colors cursor-pointer"
            title="Delete Profile (Safe Mode)"
        >
            <Trash2 class="w-3.5 h-3.5" />
        </button>
    </div>
{/snippet}

<AdminLayout title="Hotspot & Vouchers">
    <div class="space-y-6">
        <!-- Top Title & Global Actions -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">
                        Voucher Studio
                    </span>
                    <span class="text-xs text-muted-foreground font-mono">
                        {routerStore.activeRouter?.name || 'Selected Router'}
                    </span>
                </div>
                <h2 class="text-2xl font-black tracking-tight text-foreground mt-1">
                    {localeStore.t('vouchers.title')}
                </h2>
                <p class="text-xs text-muted-foreground mt-0.5">
                    {localeStore.t('vouchers.subtitle')}
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-wrap items-center gap-2.5">
                {#if activeTab === 'users'}
                    <button
                        type="button"
                        onclick={syncHotspotUsersFromRouter}
                        disabled={isSyncingUsers || !routerStore.activeRouter}
                        class="px-3 py-2 rounded-xl border border-border hover:bg-muted text-xs font-semibold text-foreground flex items-center space-x-1.5 transition-colors cursor-pointer disabled:opacity-50"
                        title="Pull and sync hotspot users into database"
                    >
                        <RefreshCw class="w-3.5 h-3.5 {isSyncingUsers ? 'animate-spin' : ''} mr-1" />
                        <span class="hidden sm:inline">{localeStore.t('vouchers.syncUsers')}</span>
                    </button>

                    <button
                        type="button"
                        onclick={() => (isAddUserModalOpen = true)}
                        class="px-4 py-2 rounded-xl bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white font-bold text-xs shadow-md shadow-sky-600/20 flex items-center space-x-1.5 transition-colors cursor-pointer"
                    >
                        <UserPlus class="w-3.5 h-3.5 mr-1" />
                        <span>Add User</span>
                    </button>
                {:else if activeTab === 'profiles'}
                    <button
                        type="button"
                        onclick={handleSyncProfiles}
                        disabled={isSyncingProfiles || !routerStore.activeRouter}
                        class="px-3 py-2 rounded-xl border border-border hover:bg-muted text-xs font-semibold text-foreground flex items-center space-x-1.5 transition-colors cursor-pointer disabled:opacity-50"
                        title="Pull profiles from MikroTik"
                    >
                        <RefreshCw class="w-3.5 h-3.5 {isSyncingProfiles ? 'animate-spin' : ''} mr-1" />
                        <span class="hidden sm:inline">{localeStore.t('vouchers.syncProfiles')}</span>
                    </button>

                    <button
                        type="button"
                        onclick={() => { editingProfile = null; isProfileModalOpen = true; }}
                        class="px-4 py-2 rounded-xl bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white font-bold text-xs shadow-md shadow-sky-600/20 flex items-center space-x-1.5 transition-colors cursor-pointer"
                    >
                        <Plus class="w-3.5 h-3.5 mr-1" />
                        <span>{localeStore.t('vouchers.newProfile')}</span>
                    </button>
                {:else}
                    <button
                        type="button"
                        onclick={syncHotspotUsersFromRouter}
                        disabled={isSyncingUsers || !routerStore.activeRouter}
                        class="px-3 py-2 rounded-xl border border-border hover:bg-muted text-xs font-semibold text-foreground flex items-center space-x-1.5 transition-colors cursor-pointer disabled:opacity-50"
                        title="Pull and sync hotspot users into database"
                    >
                        <RefreshCw class="w-3.5 h-3.5 {isSyncingUsers ? 'animate-spin' : ''} mr-1" />
                        <span class="hidden sm:inline">{localeStore.t('vouchers.syncUsers')}</span>
                    </button>

                    <button
                        type="button"
                        onclick={() => (isGenerateModalOpen = true)}
                        class="px-4 py-2 rounded-xl bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white font-bold text-xs shadow-md shadow-sky-600/20 flex items-center space-x-2 transition-all cursor-pointer"
                    >
                        <Sparkles class="w-4 h-4 mr-1.5" />
                        <span>{localeStore.t('vouchers.generateVouchers')}</span>
                    </button>
                {/if}
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="border-b border-border flex items-center space-x-1">
            <button
                type="button"
                onclick={() => (activeTab = 'batches')}
                class="px-4 py-2.5 text-xs font-bold border-b-2 transition-all cursor-pointer flex items-center space-x-2 {activeTab === 'batches'
                    ? 'border-sky-500 text-sky-600 dark:text-sky-400'
                    : 'border-transparent text-muted-foreground hover:text-foreground'}"
            >
                <Layers class="w-4 h-4 mr-1.5" />
                <span>{localeStore.t('vouchers.batchesTab')}</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-muted text-muted-foreground font-mono ml-1">
                    {batches.length}
                </span>
            </button>

            <button
                type="button"
                onclick={() => (activeTab = 'vouchers')}
                class="px-4 py-2.5 text-xs font-bold border-b-2 transition-all cursor-pointer flex items-center space-x-2 {activeTab === 'vouchers'
                    ? 'border-sky-500 text-sky-600 dark:text-sky-400'
                    : 'border-transparent text-muted-foreground hover:text-foreground'}"
            >
                <Ticket class="w-4 h-4 mr-1.5" />
                <span>{localeStore.t('vouchers.allVouchersTab')}</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-muted text-muted-foreground font-mono ml-1">
                    {totalVouchers}
                </span>
            </button>

            <button
                type="button"
                onclick={() => { activeTab = 'users'; fetchHotspotUsers(); }}
                class="px-4 py-2.5 text-xs font-bold border-b-2 transition-all cursor-pointer flex items-center space-x-2 {activeTab === 'users'
                    ? 'border-sky-500 text-sky-600 dark:text-sky-400'
                    : 'border-transparent text-muted-foreground hover:text-foreground'}"
            >
                <Users class="w-4 h-4 mr-1.5" />
                <span>{localeStore.t('vouchers.hotspotUsersTab')}</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-muted text-muted-foreground font-mono ml-1">
                    {hotspotUsers.length}
                </span>
            </button>

            <button
                type="button"
                onclick={() => (activeTab = 'profiles')}
                class="px-4 py-2.5 text-xs font-bold border-b-2 transition-all cursor-pointer flex items-center space-x-2 {activeTab === 'profiles'
                    ? 'border-sky-500 text-sky-600 dark:text-sky-400'
                    : 'border-transparent text-muted-foreground hover:text-foreground'}"
            >
                <Wifi class="w-4 h-4 mr-1.5" />
                <span>{localeStore.t('vouchers.profilesTab')}</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-muted text-muted-foreground font-mono ml-1">
                    {profiles.length}
                </span>
            </button>
        </div>

        <!-- TAB 1: VOUCHER BATCHES -->
        {#if activeTab === 'batches'}
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    {#each batches as batch (batch.id)}
                        <div
                            class="rounded-2xl border border-border bg-card p-5 shadow-sm space-y-4 hover:border-border/80 transition-all flex flex-col justify-between"
                        >
                            <div class="space-y-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <h4 class="font-bold text-sm text-foreground truncate" title={batch.name}>
                                            {batch.name}
                                        </h4>
                                        <span class="text-xs text-muted-foreground font-mono">
                                            {new Date(batch.created_at).toLocaleDateString()}
                                        </span>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20 font-mono">
                                        {batch.quantity} pcs
                                    </span>
                                </div>

                                <!-- Profile Info Badge -->
                                <div class="p-2.5 rounded-xl bg-muted/40 border border-border text-xs space-y-1 font-mono">
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Profile:</span>
                                        <strong class="text-foreground">{batch.profile?.name || 'Default'}</strong>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Rate:</span>
                                        <span class="text-foreground">{batch.profile?.rate_limit || 'Unlimited'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Price:</span>
                                        <span class="font-bold text-emerald-600 dark:text-emerald-400">
                                            Rp {Number(batch.profile?.selling_price || 0).toLocaleString()}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Batch Card Actions -->
                            <div class="pt-2 border-t border-border flex items-center justify-between gap-2">
                                <button
                                    type="button"
                                    onclick={() => openPrintBatch(batch)}
                                    class="flex-1 py-1.5 px-3 rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs shadow-xs flex items-center justify-center space-x-1.5 cursor-pointer transition-colors"
                                >
                                    <Printer class="w-3.5 h-3.5 mr-1" />
                                    <span>Print Thermal / A4</span>
                                </button>

                                <button
                                    type="button"
                                    onclick={() => filterByBatchAndSwitch(batch.id)}
                                    class="p-2 rounded-lg border border-border hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                                    title="View Vouchers in Batch"
                                >
                                    <Eye class="w-3.5 h-3.5" />
                                </button>

                                <button
                                    type="button"
                                    onclick={() => handleDeleteBatch(batch)}
                                    disabled={isDeletingBatch !== null}
                                    class="p-2 rounded-lg border border-border hover:bg-destructive/10 text-muted-foreground hover:text-destructive transition-colors cursor-pointer disabled:opacity-40"
                                    title="Delete Batch & MikroTik Users"
                                >
                                    {#if isDeletingBatch === batch.id}
                                        <RefreshCw class="w-3.5 h-3.5 animate-spin text-destructive" />
                                    {:else}
                                        <Trash2 class="w-3.5 h-3.5" />
                                    {/if}
                                </button>
                            </div>
                        </div>
                    {/each}

                    {#if batches.length === 0}
                        <div class="col-span-full py-16 text-center border-2 border-dashed border-border rounded-2xl p-8">
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
                                    onclick={() => (isGenerateModalOpen = true)}
                                    class="px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs shadow-md shadow-sky-600/20 flex items-center space-x-1.5 cursor-pointer"
                                >
                                    <Sparkles class="w-4 h-4 mr-1" />
                                    <span>Generate First Batch</span>
                                </button>
                            </div>
                        </div>
                    {/if}
                </div>
            </div>
        {:else if activeTab === 'vouchers'}
            <!-- TAB 2: ALL VOUCHERS -->
            <div class="space-y-4">
                <div class="rounded-2xl border border-border bg-card p-4 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="relative w-full sm:w-72">
                        <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                        <input
                            bind:value={voucherSearch}
                            oninput={onVoucherSearchInput}
                            type="text"
                            placeholder="Search voucher code..."
                            class="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-border bg-background focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 text-foreground"
                        />
                    </div>

                    <div class="flex flex-wrap items-center gap-2.5 w-full sm:w-auto">
                        <select
                            bind:value={selectedBatchFilter}
                            onchange={onVoucherFilterChange}
                            class="px-3 py-1.5 text-xs rounded-xl border border-border bg-background text-foreground focus:outline-none focus:border-sky-500 cursor-pointer"
                        >
                            <option value="">All Batches</option>
                            {#each batches as b (b.id)}
                                <option value={b.id}>{b.name}</option>
                            {/each}
                        </select>

                        <select
                            bind:value={voucherStatusFilter}
                            onchange={onVoucherFilterChange}
                            class="px-3 py-1.5 text-xs rounded-xl border border-border bg-background text-foreground focus:outline-none focus:border-sky-500 cursor-pointer"
                        >
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="used">Used</option>
                            <option value="expired">Expired</option>
                        </select>

                        <button
                            type="button"
                            onclick={() => fetchVouchers(currentPage)}
                            class="p-2 rounded-xl border border-border hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer"
                            title="Refresh Table"
                        >
                            <RefreshCw class="w-3.5 h-3.5 {isLoading ? 'animate-spin' : ''}" />
                        </button>
                    </div>
                </div>

                <DataTable
                    columns={voucherColumns}
                    data={vouchers}
                    isLoading={isLoading && vouchers.length === 0}
                    manualPagination={true}
                    pageCount={lastPage}
                    pageIndex={currentPage - 1}
                    totalRows={totalVouchers}
                    emptyMessage="No vouchers found matching criteria."
                    onPageChange={(p) => fetchVouchers(p)}
                    customCells={{
                        username: vUsernameCell,
                        password: vPasswordCell,
                        profile: vProfileCell,
                        time_limit: vTimeLimitCell,
                        batch: vBatchCell,
                        status: vStatusCell,
                        is_printed: vPrintedCell,
                    }}
                />
            </div>
        {:else if activeTab === 'users'}
            <!-- TAB 3: HOTSPOT USERS -->
            <div class="space-y-4">
                <div class="rounded-2xl border border-border bg-card p-4 shadow-sm flex flex-col md:flex-row items-center justify-between gap-3">
                    <div class="relative w-full md:w-80">
                        <Search class="absolute left-3 top-2.5 w-4 h-4 text-muted-foreground" />
                        <input
                            bind:value={hotspotUserSearch}
                            type="text"
                            placeholder="Search by user, MAC, or comment..."
                            class="w-full pl-9 pr-4 py-2 rounded-xl border border-border bg-background text-xs font-mono text-foreground placeholder:text-muted-foreground placeholder:font-sans focus:outline-none focus:ring-1 focus:ring-sky-500"
                        />
                    </div>

                    <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                        <select
                            bind:value={hotspotUserProfileFilter}
                            class="rounded-xl border border-border bg-background px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-sky-500 cursor-pointer"
                        >
                            <option value="">{localeStore.t('vouchers.allProfiles') || 'All Profiles'}</option>
                            {#each profiles as p (p.id)}
                                <option value={p.name}>{p.name}</option>
                            {/each}
                        </select>

                        <select
                            bind:value={hotspotUserStatusFilter}
                            class="rounded-xl border border-border bg-background px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-sky-500 cursor-pointer"
                        >
                            <option value="">All Status</option>
                            <option value="active">Active (Enabled)</option>
                            <option value="disabled">Disabled</option>
                        </select>

                        <button
                            type="button"
                            onclick={() => fetchHotspotUsers(true)}
                            disabled={isSyncingUsers || !routerStore.activeRouter}
                            class="p-2 rounded-xl border border-border hover:bg-muted text-foreground transition-colors cursor-pointer disabled:opacity-50"
                            title="Sync from MikroTik"
                        >
                            <RefreshCw class="w-4 h-4 {isSyncingUsers ? 'animate-spin' : ''}" />
                        </button>
                    </div>
                </div>

                <DataTable
                    columns={hotspotUserColumns}
                    data={filteredHotspotUsers}
                    isLoading={isLoadingUsers && hotspotUsers.length === 0}
                    pageSize={15}
                    emptyMessage={localeStore.t('vouchers.noHotspotUsers')}
                    customCells={{
                        name: uNameCell,
                        profile: uProfileCell,
                        server: uServerCell,
                        uptime: uUptimeCell,
                        data_usage: uDataUsageCell,
                        disabled: uDisabledCell,
                        actions: uActionsCell,
                    }}
                />
            </div>
        {:else if activeTab === 'profiles'}
            <!-- TAB 4: HOTSPOT PROFILES -->
            <div class="space-y-4">
                <DataTable
                    columns={profileColumns}
                    data={profiles}
                    pageSize={10}
                    emptyMessage="No hotspot profiles found. Click 'Sync Profiles' or 'New Profile'."
                    customCells={{
                        name: pNameCell,
                        rate_limit: pRateLimitCell,
                        shared_users: pSharedUsersCell,
                        validity: pValidityCell,
                        price: pPriceCell,
                        selling_price: pSellingPriceCell,
                        vouchers_count: pVouchersCountCell,
                        actions: pActionsCell,
                    }}
                />
            </div>
        {/if}

        <!-- Global Modals -->
        <GenerateVoucherModal
            isOpen={isGenerateModalOpen}
            onClose={() => (isGenerateModalOpen = false)}
            onGenerated={(batch) => {
                fetchBatches();
                fetchVouchers();
                openPrintBatch(batch);
            }}
        />

        <HotspotProfileModal
            isOpen={isProfileModalOpen}
            profileToEdit={editingProfile}
            onClose={() => (isProfileModalOpen = false)}
            onSaved={fetchProfiles}
        />

        <VoucherPrintModal
            isOpen={isPrintModalOpen}
            batch={printBatch}
            vouchers={printVouchers}
            onClose={() => (isPrintModalOpen = false)}
            onPrinted={fetchVouchers}
        />

        <AddHotspotUserModal
            isOpen={isAddUserModalOpen}
            {profiles}
            onClose={() => (isAddUserModalOpen = false)}
            onSaved={() => fetchHotspotUsers(true)}
        />

        <SafeModeUnlockModal
            isOpen={isUnlockModalOpen}
            onClose={() => (isUnlockModalOpen = false)}
            onUnlocked={() => {
                isUnlockModalOpen = false;
                if (userToDelete) {
                    handleDeleteHotspotUser(userToDelete);
                    userToDelete = null;
                }
            }}
        />
    </div>
</AdminLayout>

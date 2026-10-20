<script lang="ts">
    import { onMount, onDestroy } from 'svelte';
    import AdminLayout from '@/components/layout/AdminLayout.svelte';
    import AddDedicatedUserModal from '@/components/ppp/AddDedicatedUserModal.svelte';
    import PppProfileModal from '@/components/ppp/PppProfileModal.svelte';
    import SafeModeUnlockModal from '@/components/safe-mode/SafeModeUnlockModal.svelte';
    import DataTable, { type Column } from '@/components/common/DataTable.svelte';
    import { routerStore } from '@/stores/routerContext.svelte';
    import { safeModeStore } from '@/stores/safeMode.svelte';
    import { localeStore } from '@/stores/locale.svelte';
    import { debounce } from '@/utils/debounce';
    import { isDocumentVisible } from '@/utils/pageVisibility';
    import { toast } from 'svelte-sonner';
    import apiClient from '@/services/api';
    import type { DedicatedUserItem, PppProfileItem, PppActiveItem, IpPoolItem } from '@/types/ppp';
    import {
        Radio,
        Users,
        Power,
        Clock,
        DollarSign,
        MapPin,
        Pause,
        Play,
        Wifi,
        RefreshCw,
        Search,
        Plus,
        Edit3,
        Trash2,
    } from '@lucide/svelte';

    let activeTab = $state<'users' | 'active' | 'profiles'>('users');

    // Data State
    let dedicatedUsers = $state<DedicatedUserItem[]>([]);
    let pppProfiles = $state<PppProfileItem[]>([]);
    let activeSessions = $state<PppActiveItem[]>([]);
    let ipPools = $state<IpPoolItem[]>([]);

    // Meta stats
    let stats = $state({
        total: 0,
        online: 0,
        disabled: 0,
        total_monthly_revenue: 0,
    });

    // Loading states
    let isLoading = $state(false);
    let isSyncingUsers = $state(false);
    let isSyncingProfiles = $state(false);
    let isDeletingUser = $state<string | null>(null);
    let isDeletingProfile = $state<number | null>(null);
    let isKickingSession = $state<string | null>(null);

    // Filters
    let searchQuery = $state('');
    let profileFilter = $state('');
    let statusFilter = $state<'all' | 'active' | 'disabled' | 'online'>('all');

    // Modals
    let isUserModalOpen = $state(false);
    let editingUser = $state<DedicatedUserItem | null>(null);
    let isProfileModalOpen = $state(false);
    let editingProfile = $state<PppProfileItem | null>(null);

    // Safe Mode modal state
    let isSafeModeModalOpen = $state(false);
    let pendingSafeModeAction = $state<'delete_user' | 'delete_profile' | null>(null);
    let pendingTargetItem = $state<{ id: string | number; name: string } | null>(null);

    // Auto-refresh for active sessions
    let isAutoRefreshActive = $state(true);
    let activePollingTimer: number | null = null;

    const userColumns: Column<DedicatedUserItem>[] = [
        { id: 'name', header: 'Subscriber / Identity' },
        { id: 'profile', header: 'Profile & Protocol' },
        { id: 'remote_address', header: 'Remote IP & MAC' },
        { id: 'monthly_price', header: 'Monthly Rate' },
        { id: 'status', header: 'Connection' },
        { id: 'account', header: 'Account Status' },
        { id: 'actions', header: 'Actions', align: 'right' },
    ];

    const activeColumns: Column<PppActiveItem>[] = [
        { id: 'name', header: 'User / Identity' },
        { id: 'service', header: 'Service' },
        { id: 'address', header: 'Assigned IP Address' },
        { id: 'caller_id', header: 'Caller ID (MAC)' },
        { id: 'uptime', header: 'Uptime' },
        { id: 'encoding', header: 'Encoding' },
        { id: 'actions', header: 'Actions', align: 'right' },
    ];

    const profileColumns: Column<PppProfileItem>[] = [
        { id: 'name', header: 'Profile Name' },
        { id: 'rate_limit', header: 'Rate Limit (Rx/Tx)' },
        { id: 'local_address', header: 'Local Address' },
        { id: 'remote_address', header: 'Remote Address / Pool' },
        { id: 'only_one', header: 'Only One' },
        { id: 'price', header: 'Monthly Price' },
        { id: 'dedicated_users_count', header: 'Subscribers' },
        { id: 'actions', header: 'Actions', align: 'right' },
    ];

    async function fetchDedicatedUsers(fresh = false) {
        if (!routerStore.activeRouter) return;
        isLoading = true;
        try {
            const params: Record<string, string | boolean> = {};
            if (fresh) params.fresh = true;
            if (searchQuery) params.search = searchQuery;
            if (profileFilter) params.profile = profileFilter;
            if (statusFilter !== 'all') params.status = statusFilter;

            const res = await apiClient.get<{
                status: string;
                data: DedicatedUserItem[];
                meta: { total: number; online: number; disabled: number; total_monthly_revenue: number };
            }>(`/routers/${routerStore.activeRouter.id}/ppp/secrets`, { params });

            dedicatedUsers = res.data.data;
            if (res.data.meta) {
                stats = res.data.meta;
            }
        } catch {
            // Error handled
        } finally {
            isLoading = false;
        }
    }

    const debouncedSearch = debounce(() => {
        fetchDedicatedUsers();
    }, 300);

    async function fetchPppProfiles() {
        if (!routerStore.activeRouter) return;
        try {
            const res = await apiClient.get<{ status: string; data: PppProfileItem[] }>(
                `/routers/${routerStore.activeRouter.id}/ppp/profiles`
            );
            pppProfiles = res.data.data;
        } catch {
            // Error handled
        }
    }

    async function fetchIpPools() {
        if (!routerStore.activeRouter) return;
        try {
            const res = await apiClient.get<{ status: string; data: IpPoolItem[] }>(
                `/routers/${routerStore.activeRouter.id}/ppp/pools`
            );
            ipPools = res.data.data;
        } catch {
            // Non-fatal
        }
    }

    async function fetchActiveSessions(fresh = false) {
        if (!routerStore.activeRouter || routerStore.isOffline) return;
        try {
            const res = await apiClient.get<{ status: string; data: PppActiveItem[]; total_active: number }>(
                `/routers/${routerStore.activeRouter.id}/ppp/active`,
                { params: { fresh } }
            );
            activeSessions = res.data.data;
            stats.online = res.data.total_active;
        } catch {
            // Error handled
        }
    }

    function startActivePolling() {
        stopActivePolling();
        if (!isAutoRefreshActive) return;

        activePollingTimer = window.setInterval(() => {
            if (isDocumentVisible() && activeTab === 'active' && !routerStore.isOffline) {
                fetchActiveSessions(true);
            }
        }, 3000);
    }

    function stopActivePolling() {
        if (activePollingTimer) {
            clearInterval(activePollingTimer);
            activePollingTimer = null;
        }
    }

    async function syncUsersFromRouter() {
        if (!routerStore.activeRouter) return;
        isSyncingUsers = true;
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
            isSyncingUsers = false;
        }
    }

    async function syncProfilesFromRouter() {
        if (!routerStore.activeRouter) return;
        isSyncingProfiles = true;
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
            isSyncingProfiles = false;
        }
    }

    async function toggleUserStatus(user: DedicatedUserItem) {
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
    }

    async function kickSession(sessionId: string, userName: string) {
        if (!routerStore.activeRouter) return;
        isKickingSession = sessionId;
        try {
            await apiClient.post(
                `/routers/${routerStore.activeRouter.id}/ppp/active/${encodeURIComponent(sessionId)}/kick`
            );
            toast.success(`Session for '${userName}' disconnected.`);
            activeSessions = activeSessions.filter((s) => s.id !== sessionId);
            const match = dedicatedUsers.find((u) => u.name === userName);
            if (match) {
                match.is_online = false;
                match.active_session = null;
            }
        } catch (err: unknown) {
            const axiosErr = err as { response?: { data?: { message?: string } } };
            toast.error(axiosErr.response?.data?.message || 'Failed to disconnect session.');
        } finally {
            isKickingSession = null;
        }
    }

    function requestDeleteUser(user: DedicatedUserItem) {
        if (routerStore.activeRouter?.is_safe_mode_enabled && !safeModeStore.isUnlocked) {
            pendingSafeModeAction = 'delete_user';
            pendingTargetItem = { id: user.id, name: user.name };
            isSafeModeModalOpen = true;
            return;
        }
        executeDeleteUser(user.id);
    }

    async function executeDeleteUser(userId: string) {
        if (!routerStore.activeRouter) return;
        isDeletingUser = userId;
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
            dedicatedUsers = dedicatedUsers.filter((u) => u.id !== userId);
        } catch (err: unknown) {
            const axiosErr = err as { response?: { status?: number; data?: { message?: string } } };
            if (axiosErr.response?.status === 403) {
                pendingSafeModeAction = 'delete_user';
                pendingTargetItem = { id: userId, name: 'user' };
                isSafeModeModalOpen = true;
            } else {
                toast.error(axiosErr.response?.data?.message || 'Failed to delete dedicated user.');
            }
        } finally {
            isDeletingUser = null;
        }
    }

    function requestDeleteProfile(profile: PppProfileItem) {
        if (routerStore.activeRouter?.is_safe_mode_enabled && !safeModeStore.isUnlocked) {
            pendingSafeModeAction = 'delete_profile';
            pendingTargetItem = { id: profile.id, name: profile.name };
            isSafeModeModalOpen = true;
            return;
        }
        executeDeleteProfile(profile.id);
    }

    async function executeDeleteProfile(profileId: number) {
        if (!routerStore.activeRouter) return;
        isDeletingProfile = profileId;
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
            pppProfiles = pppProfiles.filter((p) => p.id !== profileId);
        } catch (err: unknown) {
            const axiosErr = err as { response?: { status?: number; data?: { message?: string } } };
            if (axiosErr.response?.status === 403) {
                pendingSafeModeAction = 'delete_profile';
                pendingTargetItem = { id: profileId, name: 'profile' };
                isSafeModeModalOpen = true;
            } else {
                toast.error(axiosErr.response?.data?.message || 'Failed to delete profile.');
            }
        } finally {
            isDeletingProfile = null;
        }
    }

    function handleSafeModeUnlocked() {
        isSafeModeModalOpen = false;
        if (pendingSafeModeAction === 'delete_user' && pendingTargetItem) {
            executeDeleteUser(pendingTargetItem.id as string);
        } else if (pendingSafeModeAction === 'delete_profile' && pendingTargetItem) {
            executeDeleteProfile(pendingTargetItem.id as number);
        }
        pendingSafeModeAction = null;
        pendingTargetItem = null;
    }

    function formatCurrency(amount: number): string {
        return new Intl.NumberFormat(localeStore.locale === 'id' ? 'id-ID' : 'en-US', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(amount);
    }

    let currentDedicatedRouterId: number | null = null;

    $effect(() => {
        const id = routerStore.activeRouterId;
        if (id && id !== currentDedicatedRouterId) {
            currentDedicatedRouterId = id;
            fetchDedicatedUsers(true);
            fetchPppProfiles();
            fetchIpPools();
            if (activeTab === 'active') {
                fetchActiveSessions(true);
            }
        }
    });

    let previousActiveTab: 'users' | 'active' | 'profiles' = 'users';

    $effect(() => {
        const tab = activeTab;
        if (tab !== previousActiveTab) {
            previousActiveTab = tab;
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
        }
    });

    onMount(() => {
        if (activeTab === 'active') {
            startActivePolling();
        }
    });

    onDestroy(() => {
        stopActivePolling();
    });
</script>

<!-- User Cell Snippets -->
{#snippet uNameCell(row: DedicatedUserItem)}
    <div class="flex items-start space-x-2.5">
        <div
            class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 mt-0.5 {row.is_online ? 'bg-emerald-500/10 text-emerald-500' : 'bg-muted text-muted-foreground'}"
        >
            {#if row.is_online}
                <Radio class="w-3.5 h-3.5" />
            {:else}
                <Users class="w-3.5 h-3.5" />
            {/if}
        </div>
        <div class="overflow-hidden">
            <div class="font-bold text-foreground truncate">
                {row.customer_name || row.name}
            </div>
            <div class="text-[11px] font-mono text-muted-foreground flex items-center space-x-1.5 mt-0.5">
                <span>{row.name}</span>
                {#if row.customer_phone}
                    <span class="text-[10px] text-sky-500">
                        &bull; {row.customer_phone}
                    </span>
                {/if}
            </div>
            {#if row.customer_address}
                <div class="text-[10px] text-muted-foreground truncate max-w-xs mt-0.5 flex items-center space-x-1">
                    <MapPin class="w-3 h-3 shrink-0 mr-1" />
                    <span class="truncate">{row.customer_address}</span>
                </div>
            {/if}
        </div>
    </div>
{/snippet}

{#snippet uProfileCell(row: DedicatedUserItem)}
    <div class="space-y-1">
        <div class="flex items-center space-x-1.5">
            <span class="font-semibold text-foreground">{row.profile}</span>
            {#if row.profile_detail?.rate_limit}
                <span
                    class="px-1.5 py-0.2 rounded text-[10px] font-mono bg-sky-500/10 text-sky-600 dark:text-sky-400 font-bold border border-sky-500/20"
                >
                    {row.profile_detail.rate_limit}
                </span>
            {/if}
        </div>
        <span class="inline-block px-1.5 py-0.2 rounded text-[10px] font-mono uppercase bg-muted text-muted-foreground font-bold">
            {row.service}
        </span>
    </div>
{/snippet}

{#snippet uRemoteAddressCell(row: DedicatedUserItem)}
    <div class="font-mono text-xs space-y-0.5">
        <div class="flex items-center space-x-1">
            <span class={row.remote_address ? 'text-foreground font-semibold' : 'text-muted-foreground'}>
                {row.remote_address || (localeStore.locale === 'id' ? 'Pool Dinamis' : 'Dynamic Pool')}
            </span>
            {#if row.remote_address && row.remote_address.includes('.')}
                <span
                    class="text-[9px] px-1 rounded bg-indigo-500/10 text-indigo-500 font-bold uppercase ml-1"
                >
                    Static
                </span>
            {/if}
        </div>
        {#if row.caller_id}
            <div class="text-[10px] text-muted-foreground truncate max-w-[140px]" title={row.caller_id}>
                MAC: {row.caller_id}
            </div>
        {/if}
    </div>
{/snippet}

{#snippet uMonthlyPriceCell(row: DedicatedUserItem)}
    <div class="font-bold text-foreground">
        {formatCurrency(row.monthly_price)}
    </div>
    <div class="text-[10px] text-muted-foreground uppercase font-mono">
        / {row.billing_cycle || 'mo'}
    </div>
{/snippet}

{#snippet uStatusCell(row: DedicatedUserItem)}
    {#if row.is_online}
        <div class="flex items-center space-x-1.5">
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
            </span>
            <div>
                <span class="text-[11px] font-bold text-emerald-500 ml-1">Online</span>
                {#if row.active_session?.uptime}
                    <div class="text-[10px] text-muted-foreground font-mono">
                        {row.active_session.uptime}
                    </div>
                {/if}
            </div>
        </div>
    {:else}
        <div class="flex items-center space-x-1.5 text-muted-foreground">
            <span class="w-2 h-2 rounded-full bg-slate-300 dark:bg-slate-700"></span>
            <span class="text-[11px] font-medium ml-1">Offline</span>
        </div>
    {/if}
{/snippet}

{#snippet uAccountCell(row: DedicatedUserItem)}
    <button
        type="button"
        onclick={() => toggleUserStatus(row)}
        class="px-2.5 py-1 rounded-full text-[10px] font-bold transition-all cursor-pointer inline-flex items-center space-x-1 {!row.disabled
            ? 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 hover:bg-emerald-500/20'
            : 'bg-amber-500/10 text-amber-500 border border-amber-500/20 hover:bg-amber-500/20'}"
        title={row.disabled ? 'Click to enable user' : 'Click to disable / isolate user'}
    >
        <Power class="w-3 h-3 mr-1" />
        <span>{!row.disabled ? (localeStore.locale === 'id' ? 'Aktif' : 'Active') : (localeStore.locale === 'id' ? 'Terisolir' : 'Disabled')}</span>
    </button>
{/snippet}

{#snippet uActionsCell(row: DedicatedUserItem)}
    <div class="flex items-center justify-end space-x-1.5">
        {#if row.is_online && row.active_session?.id}
            <button
                type="button"
                onclick={() => kickSession(row.active_session!.id, row.name)}
                class="p-1.5 rounded-lg border border-border text-amber-500 hover:bg-amber-500/10 transition-colors cursor-pointer"
                title="Disconnect active PPPoE tunnel"
            >
                <Power class="w-3.5 h-3.5" />
            </button>
        {/if}

        <button
            type="button"
            onclick={() => { editingUser = row; isUserModalOpen = true; }}
            class="p-1.5 rounded-lg border border-border text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer"
            title="Edit Dedicated Subscriber"
        >
            <Edit3 class="w-3.5 h-3.5" />
        </button>

        <button
            type="button"
            onclick={() => requestDeleteUser(row)}
            disabled={isDeletingUser === row.id}
            class="p-1.5 rounded-lg border border-border text-red-500 hover:bg-red-500/10 transition-colors cursor-pointer disabled:opacity-50"
            title="Delete Dedicated Subscriber"
        >
            <Trash2 class="w-3.5 h-3.5" />
        </button>
    </div>
{/snippet}

<!-- Active Session Cell Snippets -->
{#snippet aNameCell(row: PppActiveItem)}
    <div class="flex items-center space-x-2 font-mono font-bold text-foreground">
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse mr-1.5"></span>
        <span>{row.name}</span>
    </div>
{/snippet}

{#snippet aServiceCell(row: PppActiveItem)}
    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">
        {row.service}
    </span>
{/snippet}

{#snippet aAddressCell(row: PppActiveItem)}
    <span class="font-mono text-foreground font-semibold">{row.address}</span>
{/snippet}

{#snippet aCallerIdCell(row: PppActiveItem)}
    <span class="font-mono text-muted-foreground">{row.caller_id}</span>
{/snippet}

{#snippet aUptimeCell(row: PppActiveItem)}
    <div class="flex items-center space-x-1 font-mono text-foreground">
        <Clock class="w-3.5 h-3.5 text-muted-foreground mr-1" />
        <span>{row.uptime}</span>
    </div>
{/snippet}

{#snippet aEncodingCell(row: PppActiveItem)}
    <span class="font-mono text-muted-foreground text-[11px]">{row.encoding}</span>
{/snippet}

{#snippet aActionsCell(row: PppActiveItem)}
    <div class="flex items-center justify-end">
        <button
            type="button"
            onclick={() => kickSession(row.id, row.name)}
            disabled={isKickingSession === row.id}
            class="px-2.5 py-1 rounded-lg border border-red-500/20 bg-red-500/10 text-red-500 hover:bg-red-500/20 text-[11px] font-bold transition-colors cursor-pointer disabled:opacity-50 inline-flex items-center space-x-1"
        >
            <Power class="w-3 h-3 mr-1" />
            <span>Disconnect</span>
        </button>
    </div>
{/snippet}

<!-- Profile Cell Snippets -->
{#snippet prNameCell(row: PppProfileItem)}
    <span class="font-bold text-foreground">{row.name}</span>
{/snippet}

{#snippet prRateLimitCell(row: PppProfileItem)}
    <span class="font-mono font-bold text-sky-500">{row.rate_limit || '-'}</span>
{/snippet}

{#snippet prLocalAddressCell(row: PppProfileItem)}
    <span class="font-mono text-muted-foreground">{row.local_address || '-'}</span>
{/snippet}

{#snippet prRemoteAddressCell(row: PppProfileItem)}
    <span class="font-mono text-muted-foreground">{row.remote_address || '-'}</span>
{/snippet}

{#snippet prOnlyOneCell(row: PppProfileItem)}
    <span
        class="px-2 py-0.5 rounded-full text-[10px] font-bold {row.only_one ? 'bg-emerald-500/10 text-emerald-500' : 'bg-muted text-muted-foreground'}"
    >
        {row.only_one ? 'Yes' : 'No'}
    </span>
{/snippet}

{#snippet prPriceCell(row: PppProfileItem)}
    <span class="font-bold text-foreground">{formatCurrency(row.price)}</span>
{/snippet}

{#snippet prSubscribersCountCell(row: PppProfileItem)}
    <span class="font-mono font-bold text-foreground">{row.dedicated_users_count ?? 0}</span>
{/snippet}

{#snippet prActionsCell(row: PppProfileItem)}
    <div class="flex items-center justify-end space-x-1.5">
        <button
            type="button"
            onclick={() => { editingProfile = row; isProfileModalOpen = true; }}
            class="p-1.5 rounded-lg border border-border text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer"
            title="Edit Profile"
        >
            <Edit3 class="w-3.5 h-3.5" />
        </button>
        <button
            type="button"
            onclick={() => requestDeleteProfile(row)}
            disabled={isDeletingProfile === row.id}
            class="p-1.5 rounded-lg border border-border text-red-500 hover:bg-red-500/10 transition-colors cursor-pointer disabled:opacity-50"
            title="Delete Profile"
        >
            <Trash2 class="w-3.5 h-3.5" />
        </button>
    </div>
{/snippet}

<AdminLayout title="Dedicated Users">
    <div class="space-y-6">
        <!-- Top Title & Global Actions -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">
                        Dedicated Studio
                    </span>
                    <span class="text-xs text-muted-foreground font-mono">
                        {routerStore.activeRouter?.name || 'Selected Router'}
                    </span>
                </div>
                <h2 class="text-2xl font-black tracking-tight text-foreground mt-1">
                    {localeStore.locale === 'id' ? 'Pelanggan Dedicated (PPP / PPPoE)' : 'Dedicated Subscribers (PPP / PPPoE)'}
                </h2>
                <p class="text-xs text-muted-foreground mt-0.5">
                    {localeStore.locale === 'id' ? 'Manajemen pelanggan internet dedicated, IP statis, profil kecepatan, dan monitoring sesi aktif PPPoE / L2TP.' : 'Manage dedicated corporate / home PPPoE subscribers, static IP bindings, bandwidth limits, and live connection states.'}
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-wrap items-center gap-2.5">
                {#if activeTab === 'users'}
                    <button
                        type="button"
                        onclick={syncUsersFromRouter}
                        disabled={isSyncingUsers || !routerStore.activeRouter}
                        class="px-3 py-2 rounded-xl border border-border hover:bg-muted text-xs font-semibold text-foreground flex items-center space-x-1.5 transition-colors cursor-pointer disabled:opacity-50"
                        title="Pull secrets from MikroTik (/ppp/secret)"
                    >
                        <RefreshCw class="w-3.5 h-3.5 {isSyncingUsers ? 'animate-spin' : ''} mr-1" />
                        <span class="hidden sm:inline">{localeStore.locale === 'id' ? 'Sinkron dari Router' : 'Sync from Router'}</span>
                    </button>

                    <button
                        type="button"
                        onclick={() => { editingUser = null; isUserModalOpen = true; }}
                        disabled={!routerStore.activeRouter}
                        class="px-4 py-2 rounded-xl bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white font-bold text-xs shadow-md shadow-sky-600/20 flex items-center space-x-1.5 transition-colors cursor-pointer disabled:opacity-50"
                    >
                        <Plus class="w-3.5 h-3.5 mr-1" />
                        <span>{localeStore.locale === 'id' ? 'Tambah Pelanggan' : 'Add Subscriber'}</span>
                    </button>
                {:else if activeTab === 'active'}
                    <button
                        type="button"
                        onclick={() => { isAutoRefreshActive = !isAutoRefreshActive; isAutoRefreshActive ? startActivePolling() : stopActivePolling(); }}
                        class="px-3 py-2 rounded-xl border border-border hover:bg-muted text-xs font-semibold text-foreground flex items-center space-x-1.5 transition-colors cursor-pointer"
                    >
                        {#if !isAutoRefreshActive}
                            <Play class="w-3.5 h-3.5 text-emerald-500 mr-1" />
                        {:else}
                            <Pause class="w-3.5 h-3.5 text-amber-500 mr-1" />
                        {/if}
                        <span>{isAutoRefreshActive ? (localeStore.locale === 'id' ? 'Jeda Auto-Refresh' : 'Pause Auto-Refresh') : (localeStore.locale === 'id' ? 'Lanjutkan' : 'Resume')}</span>
                    </button>

                    <button
                        type="button"
                        onclick={() => fetchActiveSessions(true)}
                        class="p-2 rounded-xl border border-border hover:bg-muted text-foreground transition-colors cursor-pointer"
                        title="Refresh Now"
                    >
                        <RefreshCw class="w-3.5 h-3.5" />
                    </button>
                {:else if activeTab === 'profiles'}
                    <button
                        type="button"
                        onclick={syncProfilesFromRouter}
                        disabled={isSyncingProfiles || !routerStore.activeRouter}
                        class="px-3 py-2 rounded-xl border border-border hover:bg-muted text-xs font-semibold text-foreground flex items-center space-x-1.5 transition-colors cursor-pointer disabled:opacity-50"
                        title="Pull profiles from MikroTik (/ppp/profile)"
                    >
                        <RefreshCw class="w-3.5 h-3.5 {isSyncingProfiles ? 'animate-spin' : ''} mr-1" />
                        <span class="hidden sm:inline">{localeStore.locale === 'id' ? 'Sinkron Profil' : 'Sync Profiles'}</span>
                    </button>

                    <button
                        type="button"
                        onclick={() => { editingProfile = null; isProfileModalOpen = true; }}
                        disabled={!routerStore.activeRouter}
                        class="px-4 py-2 rounded-xl bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white font-bold text-xs shadow-md shadow-sky-600/20 flex items-center space-x-1.5 transition-colors cursor-pointer disabled:opacity-50"
                    >
                        <Plus class="w-3.5 h-3.5 mr-1" />
                        <span>{localeStore.locale === 'id' ? 'Tambah Profil' : 'New Profile'}</span>
                    </button>
                {/if}
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="border-b border-border flex items-center space-x-1">
            <button
                type="button"
                onclick={() => (activeTab = 'users')}
                class="px-4 py-2.5 text-xs font-bold border-b-2 transition-all cursor-pointer flex items-center space-x-2 {activeTab === 'users'
                    ? 'border-sky-500 text-sky-600 dark:text-sky-400'
                    : 'border-transparent text-muted-foreground hover:text-foreground'}"
            >
                <Users class="w-4 h-4 mr-1.5" />
                <span>{localeStore.locale === 'id' ? 'Pelanggan Dedicated' : 'Dedicated Subscribers'}</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-muted text-muted-foreground font-mono ml-1">
                    {dedicatedUsers.length}
                </span>
            </button>

            <button
                type="button"
                onclick={() => (activeTab = 'active')}
                class="px-4 py-2.5 text-xs font-bold border-b-2 transition-all cursor-pointer flex items-center space-x-2 {activeTab === 'active'
                    ? 'border-sky-500 text-sky-600 dark:text-sky-400'
                    : 'border-transparent text-muted-foreground hover:text-foreground'}"
            >
                <Radio class="w-4 h-4 mr-1.5" />
                <span>{localeStore.locale === 'id' ? 'Sesi Aktif' : 'Active Sessions'}</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-muted text-muted-foreground font-mono ml-1">
                    {activeSessions.length || stats.online}
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
                <span>{localeStore.locale === 'id' ? 'Profil Bandwidth' : 'PPP Profiles'}</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-muted text-muted-foreground font-mono ml-1">
                    {pppProfiles.length}
                </span>
            </button>
        </div>

        <!-- Stats Overview Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <div class="rounded-2xl border border-border bg-card p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                        {localeStore.locale === 'id' ? 'Total Dedicated' : 'Total Subscribers'}
                    </span>
                    <div class="w-8 h-8 rounded-xl bg-sky-500/10 text-sky-500 flex items-center justify-center">
                        <Users class="w-4 h-4" />
                    </div>
                </div>
                <div class="mt-2 text-2xl font-black text-foreground">
                    {stats.total}
                </div>
                <div class="text-[11px] text-muted-foreground mt-0.5">
                    {dedicatedUsers.length} {localeStore.locale === 'id' ? 'terdaftar di sistem' : 'registered in router'}
                </div>
            </div>

            <div class="rounded-2xl border border-border bg-card p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                        {localeStore.locale === 'id' ? 'Online / Terhubung' : 'Active Sessions'}
                    </span>
                    <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center">
                        <Radio class="w-4 h-4" />
                    </div>
                </div>
                <div class="mt-2 text-2xl font-black text-emerald-500 flex items-center space-x-2">
                    <span>{stats.online}</span>
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                    </span>
                </div>
                <div class="text-[11px] text-muted-foreground mt-0.5">
                    {localeStore.locale === 'id' ? 'Sesi PPPoE aktif di router' : 'Live PPPoE tunnel sessions'}
                </div>
            </div>

            <div class="rounded-2xl border border-border bg-card p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                        {localeStore.locale === 'id' ? 'Nonaktif / Terisolir' : 'Disabled Secrets'}
                    </span>
                    <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center">
                        <Power class="w-4 h-4" />
                    </div>
                </div>
                <div class="mt-2 text-2xl font-black text-amber-500">
                    {stats.disabled}
                </div>
                <div class="text-[11px] text-muted-foreground mt-0.5">
                    {localeStore.locale === 'id' ? 'Akun dinonaktifkan / isolir' : 'Disabled accounts'}
                </div>
            </div>

            <div class="rounded-2xl border border-border bg-card p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                        {localeStore.locale === 'id' ? 'Estimasi Omzet / Bln' : 'Est. Monthly Revenue'}
                    </span>
                    <div class="w-8 h-8 rounded-xl bg-indigo-500/10 text-indigo-500 flex items-center justify-center">
                        <DollarSign class="w-4 h-4" />
                    </div>
                </div>
                <div class="mt-2 text-lg sm:text-xl font-black text-foreground truncate">
                    {formatCurrency(stats.total_monthly_revenue)}
                </div>
                <div class="text-[11px] text-muted-foreground mt-0.5">
                    {localeStore.locale === 'id' ? 'Total nilai paket langganan' : 'Total monthly subscription'}
                </div>
            </div>
        </div>

        <!-- TAB 1: DEDICATED USERS -->
        {#if activeTab === 'users'}
            <div class="space-y-4">
                <div class="rounded-2xl border border-border bg-card p-4 shadow-sm flex flex-col md:flex-row items-center justify-between gap-3">
                    <div class="relative w-full md:w-80">
                        <Search class="absolute left-3 top-2.5 w-4 h-4 text-muted-foreground" />
                        <input
                            bind:value={searchQuery}
                            oninput={debouncedSearch}
                            type="text"
                            placeholder={localeStore.locale === 'id' ? 'Cari nama, IP remote, telepon...' : 'Search by name, remote IP, phone...'}
                            class="w-full pl-9 pr-4 py-2 rounded-xl border border-border bg-background text-xs font-mono text-foreground placeholder:text-muted-foreground placeholder:font-sans focus:outline-none focus:ring-1 focus:ring-sky-500"
                        />
                    </div>

                    <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                        <select
                            bind:value={profileFilter}
                            onchange={() => fetchDedicatedUsers(false)}
                            class="rounded-xl border border-border bg-background px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-sky-500 cursor-pointer"
                        >
                            <option value="">{localeStore.locale === 'id' ? 'Semua Profil' : 'All Profiles'}</option>
                            {#each pppProfiles as p (p.id)}
                                <option value={p.name}>{p.name}</option>
                            {/each}
                        </select>

                        <select
                            bind:value={statusFilter}
                            onchange={() => fetchDedicatedUsers(false)}
                            class="rounded-xl border border-border bg-background px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-sky-500 cursor-pointer"
                        >
                            <option value="all">{localeStore.locale === 'id' ? 'Semua Status' : 'All Status'}</option>
                            <option value="online">{localeStore.locale === 'id' ? 'Hanya Online' : 'Online Only'}</option>
                            <option value="active">{localeStore.locale === 'id' ? 'Akun Aktif' : 'Active (Enabled)'}</option>
                            <option value="disabled">{localeStore.locale === 'id' ? 'Terisolir' : 'Disabled'}</option>
                        </select>

                        <button
                            type="button"
                            onclick={() => fetchDedicatedUsers(true)}
                            class="p-2 rounded-xl border border-border hover:bg-muted text-foreground transition-colors cursor-pointer"
                            title="Refresh"
                        >
                            <RefreshCw class="w-4 h-4 {isLoading ? 'animate-spin' : ''}" />
                        </button>
                    </div>
                </div>

                <DataTable
                    columns={userColumns}
                    data={dedicatedUsers}
                    isLoading={isLoading && dedicatedUsers.length === 0}
                    pageSize={15}
                    emptyMessage={localeStore.locale === 'id' ? 'Belum ada pelanggan dedicated.' : 'No dedicated subscribers found.'}
                    customCells={{
                        name: uNameCell,
                        profile: uProfileCell,
                        remote_address: uRemoteAddressCell,
                        monthly_price: uMonthlyPriceCell,
                        status: uStatusCell,
                        account: uAccountCell,
                        actions: uActionsCell,
                    }}
                />
            </div>
        {:else if activeTab === 'active'}
            <!-- TAB 2: LIVE ACTIVE SESSIONS -->
            <div class="space-y-4">
                <DataTable
                    columns={activeColumns}
                    data={activeSessions}
                    pageSize={15}
                    emptyMessage={localeStore.locale === 'id' ? 'Tidak ada sesi PPP aktif saat ini.' : 'No active PPP connections at the moment.'}
                    customCells={{
                        name: aNameCell,
                        service: aServiceCell,
                        address: aAddressCell,
                        caller_id: aCallerIdCell,
                        uptime: aUptimeCell,
                        encoding: aEncodingCell,
                        actions: aActionsCell,
                    }}
                />
            </div>
        {:else if activeTab === 'profiles'}
            <!-- TAB 3: PPP PROFILES -->
            <div class="space-y-4">
                <DataTable
                    columns={profileColumns}
                    data={pppProfiles}
                    pageSize={15}
                    emptyMessage={localeStore.locale === 'id' ? 'Belum ada profil PPP.' : 'No PPP profiles found.'}
                    customCells={{
                        name: prNameCell,
                        rate_limit: prRateLimitCell,
                        local_address: prLocalAddressCell,
                        remote_address: prRemoteAddressCell,
                        only_one: prOnlyOneCell,
                        price: prPriceCell,
                        dedicated_users_count: prSubscribersCountCell,
                        actions: prActionsCell,
                    }}
                />
            </div>
        {/if}

        <!-- Modals -->
        <AddDedicatedUserModal
            isOpen={isUserModalOpen}
            userToEdit={editingUser}
            profiles={pppProfiles}
            pools={ipPools}
            onClose={() => (isUserModalOpen = false)}
            onSaved={() => fetchDedicatedUsers(true)}
        />

        <PppProfileModal
            isOpen={isProfileModalOpen}
            profileToEdit={editingProfile}
            pools={ipPools}
            onClose={() => (isProfileModalOpen = false)}
            onSaved={fetchPppProfiles}
        />

        <SafeModeUnlockModal
            isOpen={isSafeModeModalOpen}
            actionDescription={`Permanently deleting PPP ${pendingSafeModeAction === 'delete_user' ? 'dedicated subscriber' : 'bandwidth profile'} on MikroTik hardware.`}
            onClose={() => (isSafeModeModalOpen = false)}
            onUnlocked={handleSafeModeUnlocked}
        />
    </div>
</AdminLayout>

<script lang="ts">
    import { onMount, onDestroy } from 'svelte';
    import { router } from '@inertiajs/svelte';
    import { authStore } from '@/stores/auth.svelte';
    import { localeStore } from '@/stores/locale.svelte';
    import { usePageVisibility } from '@/utils/pageVisibility';
    import apiClient from '@/services/api';
    import type { ApiResponse } from '@/types/api';
    import { toast } from 'svelte-sonner';
    import ThemeToggle from '@/components/layout/ThemeToggle.svelte';
    import LanguageSwitcher from '@/components/layout/LanguageSwitcher.svelte';
    import CustomerProfileModal from '@/components/customer/CustomerProfileModal.svelte';
    import * as Card from '@/components/ui/card';
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import { Separator } from '@/components/ui/separator';
    import {
        Activity,
        Clock,
        Zap,
        ArrowDown,
        ArrowUp,
        HardDrive,
        Smartphone,
        Wifi,
        WifiOff,
        RefreshCw,
        Power,
        LogOut,
        Loader2,
        ShieldCheck,
        Calendar,
        DollarSign,
        CheckCircle2,
        UserCog,
        Mail,
    } from '@lucide/svelte';
    import EmailVerificationBanner from '@/components/layout/EmailVerificationBanner.svelte';

    interface CustomerSession {
        id?: string | null;
        ip_address?: string | null;
        mac_address?: string | null;
        caller_id?: string | null;
        uptime?: string;
        time_left?: string | null;
        idle_time?: string | null;
        bytes_in?: number;
        bytes_out?: number;
        bytes_total?: number;
        bytes_in_formatted?: string;
        bytes_out_formatted?: string;
        bytes_total_formatted?: string;
        service?: string;
        encoding?: string | null;
    }

    interface CustomerStatusData {
        account_type: 'hotspot_customer' | 'ppp_customer' | 'admin';
        username: string;
        name?: string;
        customer_name?: string;
        customer_phone?: string | null;
        customer_address?: string | null;
        is_online: boolean;
        router?: {
            id: number;
            name: string;
            status: string;
        } | null;
        profile?: {
            name: string;
            rate_limit?: string | null;
            price?: number;
            validity_duration?: string | null;
            validity_unit?: string | null;
        } | null;
        limits?: {
            time_limit?: string | null;
            data_limit_bytes?: number | null;
            data_limit_formatted?: string | null;
        } | null;
        service?: string;
        billing_cycle?: string;
        remote_address?: string | null;
        disabled?: boolean;
        session?: CustomerSession | null;
        voucher_status?: string;
        created_at?: string;
        email?: string | null;
        is_email_verified?: boolean;
        email_verified_at?: string | null;
    }

    const { isPageVisible, onVisibilityChange } = usePageVisibility();

    let statusData = $state<CustomerStatusData | null>(null);
    let isLoading = $state(true);
    let isRefreshing = $state(false);
    let isDisconnecting = $state(false);
    let isProfileModalOpen = $state(false);
    let isAutoRefresh = $state(true);
    let pollTimer: ReturnType<typeof setInterval> | null = null;

    async function fetchStatus(showToast = false) {
        if (showToast) isRefreshing = true;
        try {
            const res = await apiClient.get<ApiResponse<CustomerStatusData>>('/customer/status');
            if (res.data.status === 'success' && res.data.data) {
                statusData = res.data.data;
                if (authStore.user && statusData) {
                    if (statusData.email !== undefined) {
                        authStore.user.email = statusData.email ?? '';
                    }
                    if (statusData.is_email_verified !== undefined) {
                        authStore.user.is_email_verified = statusData.is_email_verified;
                    }
                    if (statusData.email_verified_at !== undefined) {
                        authStore.user.email_verified_at = statusData.email_verified_at;
                    }
                    if (typeof localStorage !== 'undefined') {
                        localStorage.setItem('tikpulse_user', JSON.stringify(authStore.user));
                    }
                }
                if (showToast) {
                    toast.success(localeStore.locale === 'id' ? 'Status koneksi diperbarui.' : 'Connection status refreshed.');
                }
            }
        } catch (err: unknown) {
            const axiosErr = err as { response?: { status?: number; data?: { message?: string } } };
            if (axiosErr.response?.status === 401) {
                stopPolling();
                authStore.logout();
            } else if (showToast) {
                toast.error(axiosErr.response?.data?.message || 'Failed to refresh connection status.');
            }
        } finally {
            isLoading = false;
            if (showToast) isRefreshing = false;
        }
    }

    async function handleDisconnect() {
        if (!confirm(localeStore.t('customer.disconnectConfirm'))) {
            return;
        }

        isDisconnecting = true;
        try {
            const res = await apiClient.post<ApiResponse<null>>('/customer/disconnect');
            toast.success(res.data.message || localeStore.t('customer.disconnectSuccess'));
            await fetchStatus();
        } catch (err: unknown) {
            const axiosErr = err as { response?: { data?: { message?: string } } };
            toast.error(axiosErr.response?.data?.message || 'Failed to disconnect session.');
        } finally {
            isDisconnecting = false;
        }
    }

    async function handleLogout() {
        stopPolling();
        try {
            await apiClient.post('/customer/logout');
        } catch {
            // Ignore
        } finally {
            await authStore.logout();
        }
    }

    function formatCurrency(val?: number): string {
        if (!val || val <= 0) return 'Rp 0';
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(val);
    }

    function startPolling() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(() => {
            if (isAutoRefresh && isPageVisible() && !isProfileModalOpen) {
                fetchStatus(false);
            }
        }, 5000);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    onMount(() => {
        fetchStatus(false);
        startPolling();

        const handleEmailVerified = () => {
            fetchStatus(false);
        };
        if (typeof window !== 'undefined') {
            window.addEventListener('email-verified', handleEmailVerified);
        }

        const unsub = onVisibilityChange((visible) => {
            if (visible && isAutoRefresh && !isProfileModalOpen) {
                fetchStatus(false);
            }
        });

        return () => {
            if (typeof window !== 'undefined') {
                window.removeEventListener('email-verified', handleEmailVerified);
            }
            unsub();
            stopPolling();
        };
    });

    onDestroy(() => {
        stopPolling();
    });
</script>

<svelte:head>
    <title>{localeStore.t('customer.statusTitle')} - TikPulse</title>
</svelte:head>

<div class="min-h-screen bg-background text-foreground flex flex-col">
    <!-- Top Navigation Header -->
    <header class="sticky top-0 z-40 border-b border-border bg-card/80 backdrop-blur-md px-4 lg:px-8 py-3.5 flex items-center justify-between shadow-xs">
        <div class="flex items-center space-x-3">
            <div class="size-10 rounded-xl bg-gradient-to-tr from-sky-500 via-indigo-500 to-primary flex items-center justify-center text-white shadow-md shadow-sky-500/20">
                <Activity class="size-5.5" />
            </div>
            <div>
                <div class="flex items-center space-x-2">
                    <span class="font-black text-lg tracking-tight">Tik<span class="text-sky-500">Pulse</span></span>
                    <Badge variant="outline" class="text-[10px] uppercase font-mono tracking-wider font-bold border-sky-500/30 text-sky-500 bg-sky-500/10">
                        Portal
                    </Badge>
                </div>
                <p class="text-[11px] text-muted-foreground hidden sm:block">
                    {localeStore.t('customer.statusSubtitle')}
                </p>
            </div>
        </div>

        <div class="flex items-center space-x-2">
            <LanguageSwitcher />
            <ThemeToggle />
            <Button
                variant="outline"
                size="sm"
                onclick={() => (isProfileModalOpen = true)}
                class="text-xs font-semibold gap-1.5 border-border hover:bg-sky-500/10 hover:text-sky-500 hover:border-sky-500/30 cursor-pointer"
                title={localeStore.t('customer.manageProfile')}
            >
                <UserCog class="size-3.5" />
                <span class="hidden md:inline">{localeStore.t('customer.manageProfile')}</span>
            </Button>
            <Button
                variant="outline"
                size="sm"
                onclick={handleLogout}
                class="text-xs font-semibold gap-1.5 border-border hover:bg-destructive/10 hover:text-destructive hover:border-destructive/30"
                title={localeStore.t('customer.logout')}
            >
                <LogOut class="size-3.5" />
                <span class="hidden md:inline">{localeStore.t('customer.logout')}</span>
            </Button>
        </div>
    </header>

    <EmailVerificationBanner />

    <!-- Main Container -->
    <main class="flex-1 max-w-6xl w-full mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
        {#if isLoading && !statusData}
            <div class="flex flex-col items-center justify-center py-24 space-y-3">
                <Loader2 class="size-8 animate-spin text-sky-500" />
                <p class="text-xs text-muted-foreground font-medium animate-pulse">
                    {localeStore.locale === 'id' ? 'Memuat status koneksi internet...' : 'Querying live connection metrics...'}
                </p>
            </div>
        {:else if statusData}
            <!-- Hero Account Status Banner -->
            <div class="relative overflow-hidden rounded-2xl border border-border bg-gradient-to-br from-card via-card to-muted/40 p-5 sm:p-6 shadow-sm">
                <div class="absolute -right-12 -bottom-12 size-48 rounded-full bg-sky-500/5 blur-2xl pointer-events-none"></div>

                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 relative z-10">
                    <div class="flex items-start sm:items-center space-x-4">
                        <div class="size-14 rounded-2xl border border-border bg-card shadow-sm flex items-center justify-center shrink-0">
                            {#if statusData.is_online}
                                <Wifi class="size-7 text-emerald-500 animate-pulse" />
                            {:else}
                                <WifiOff class="size-7 text-muted-foreground" />
                            {/if}
                        </div>
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="text-xl sm:text-2xl font-black tracking-tight text-foreground">
                                    {statusData.customer_name || statusData.username}
                                </h1>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold font-mono uppercase tracking-wide border {statusData.account_type === 'hotspot_customer' ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20' : 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/20'}">
                                    {statusData.account_type === 'hotspot_customer' ? localeStore.t('customer.hotspotVoucher') : localeStore.t('customer.pppDedicated')}
                                </span>
                            </div>
                            <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs text-muted-foreground">
                                <span class="flex items-center gap-1.5">
                                    <span>{localeStore.t('customer.router')}:</span>
                                    <span class="font-semibold text-foreground">{statusData.router?.name || 'MikroTik Gateway'}</span>
                                </span>
                                {#if statusData.username && statusData.customer_name && statusData.username !== statusData.customer_name}
                                    <span class="text-muted-foreground/60">•</span>
                                    <span class="font-mono text-muted-foreground">ID: {statusData.username}</span>
                                {/if}
                                {#if statusData.email && !statusData.email.endsWith('@customer.tikpulse.local')}
                                    <span class="text-muted-foreground/60">•</span>
                                    <span class="inline-flex items-center gap-1.5">
                                        <Mail class="size-3 text-muted-foreground" />
                                        <span>{statusData.email}</span>
                                        {#if statusData.is_email_verified}
                                            <span class="inline-flex items-center text-[10px] text-emerald-500 font-semibold gap-0.5" title={localeStore.t('customer.emailVerified')}>
                                                <CheckCircle2 class="size-3 text-emerald-500" />
                                                <span>{localeStore.t('customer.emailVerified')}</span>
                                            </span>
                                        {:else}
                                            <span class="inline-flex items-center text-[10px] text-amber-600 dark:text-amber-400 font-medium bg-amber-500/10 px-1.5 py-0.5 rounded-full border border-amber-500/20">
                                                {localeStore.t('customer.emailUnverified')}
                                            </span>
                                        {/if}
                                    </span>
                                {/if}
                            </div>
                        </div>
                    </div>

                    <!-- Status Pill & Quick Actions -->
                    <div class="flex items-center gap-2.5 self-start md:self-center">
                        <div class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-bold border {statusData.is_online ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20 shadow-xs' : 'bg-muted text-muted-foreground border-border'}">
                            <span class="size-2 rounded-full {statusData.is_online ? 'bg-emerald-500 animate-ping' : 'bg-muted-foreground'} mr-2"></span>
                            {statusData.is_online ? localeStore.t('customer.online') : localeStore.t('customer.offline')}
                        </div>

                        <Button
                            variant="outline"
                            size="sm"
                            onclick={() => (isProfileModalOpen = true)}
                            class="gap-1.5 text-xs font-semibold cursor-pointer border-border hover:bg-sky-500/10 hover:text-sky-500 hover:border-sky-500/30"
                            title={localeStore.t('customer.manageProfile')}
                        >
                            <UserCog class="size-3.5 text-sky-500" />
                            <span class="hidden sm:inline">{localeStore.t('customer.manageProfile')}</span>
                        </Button>

                        <Button
                            variant="outline"
                            size="sm"
                            onclick={() => fetchStatus(true)}
                            disabled={isRefreshing}
                            class="gap-1.5 text-xs font-semibold cursor-pointer"
                        >
                            <RefreshCw class="size-3.5 {isRefreshing ? 'animate-spin' : ''}" />
                            <span class="hidden sm:inline">{localeStore.t('customer.refreshStatus')}</span>
                        </Button>

                        {#if statusData.is_online}
                            <Button
                                variant="destructive"
                                size="sm"
                                onclick={handleDisconnect}
                                disabled={isDisconnecting}
                                class="gap-1.5 text-xs font-semibold cursor-pointer shadow-sm shadow-destructive/20"
                            >
                                {#if isDisconnecting}
                                    <Loader2 class="size-3.5 animate-spin" />
                                {:else}
                                    <Power class="size-3.5" />
                                {/if}
                                <span>{localeStore.t('customer.disconnectSession')}</span>
                            </Button>
                        {/if}
                    </div>
                </div>
            </div>

            <!-- Metric Cards Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- 1. Session Duration / Uptime -->
                <Card.Root class="border-border shadow-xs hover:border-sky-500/30 transition-all">
                    <Card.Content class="p-5 flex items-start justify-between">
                        <div class="space-y-1">
                            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                                {localeStore.t('customer.uptime')}
                            </p>
                            <div class="text-2xl font-black font-mono tracking-tight text-foreground">
                                {statusData.session?.uptime || (statusData.is_online ? 'Active' : '0s')}
                            </div>
                            <p class="text-[11px] text-muted-foreground flex items-center gap-1">
                                {#if statusData.is_online}
                                    <CheckCircle2 class="size-3 text-emerald-500" />
                                    <span>{localeStore.locale === 'id' ? 'Sesi sedang berjalan' : 'Active connection session'}</span>
                                {:else}
                                    <span>{localeStore.locale === 'id' ? 'Tidak ada sesi aktif' : 'No active session'}</span>
                                {/if}
                            </p>
                        </div>
                        <div class="size-10 rounded-xl bg-sky-500/10 text-sky-500 border border-sky-500/20 flex items-center justify-center shrink-0">
                            <Clock class="size-5" />
                        </div>
                    </Card.Content>
                </Card.Root>

                <!-- 2. Bandwidth Profile / Speed Limit -->
                <Card.Root class="border-border shadow-xs hover:border-indigo-500/30 transition-all">
                    <Card.Content class="p-5 flex items-start justify-between">
                        <div class="space-y-1">
                            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                                {localeStore.t('customer.speedProfile')}
                            </p>
                            <div class="text-2xl font-black font-mono tracking-tight text-foreground truncate max-w-[170px]" title={statusData.profile?.rate_limit || 'Unlimited'}>
                                {statusData.profile?.rate_limit || 'Full Speed'}
                            </div>
                            <p class="text-[11px] text-muted-foreground truncate max-w-[170px]">
                                {statusData.profile?.name || 'Default Profile'}
                            </p>
                        </div>
                        <div class="size-10 rounded-xl bg-indigo-500/10 text-indigo-500 border border-indigo-500/20 flex items-center justify-center shrink-0">
                            <Zap class="size-5" />
                        </div>
                    </Card.Content>
                </Card.Root>

                <!-- 3. Download Data -->
                <Card.Root class="border-border shadow-xs hover:border-emerald-500/30 transition-all">
                    <Card.Content class="p-5 flex items-start justify-between">
                        <div class="space-y-1">
                            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                                {localeStore.t('customer.download')}
                            </p>
                            <div class="text-2xl font-black font-mono tracking-tight text-foreground">
                                {statusData.session?.bytes_in_formatted || '0 B'}
                            </div>
                            <p class="text-[11px] text-muted-foreground flex items-center gap-1">
                                <ArrowDown class="size-3 text-emerald-500" />
                                <span>Incoming traffic</span>
                            </p>
                        </div>
                        <div class="size-10 rounded-xl bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 flex items-center justify-center shrink-0">
                            <ArrowDown class="size-5" />
                        </div>
                    </Card.Content>
                </Card.Root>

                <!-- 4. Upload Data -->
                <Card.Root class="border-border shadow-xs hover:border-violet-500/30 transition-all">
                    <Card.Content class="p-5 flex items-start justify-between">
                        <div class="space-y-1">
                            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                                {localeStore.t('customer.upload')}
                            </p>
                            <div class="text-2xl font-black font-mono tracking-tight text-foreground">
                                {statusData.session?.bytes_out_formatted || '0 B'}
                            </div>
                            <p class="text-[11px] text-muted-foreground flex items-center gap-1">
                                <ArrowUp class="size-3 text-violet-500" />
                                <span>Outgoing traffic</span>
                            </p>
                        </div>
                        <div class="size-10 rounded-xl bg-violet-500/10 text-violet-500 border border-violet-500/20 flex items-center justify-center shrink-0">
                            <ArrowUp class="size-5" />
                        </div>
                    </Card.Content>
                </Card.Root>
            </div>

            <!-- Secondary Detail Panels -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Device & IP Session Info -->
                <Card.Root class="border-border shadow-xs lg:col-span-2">
                    <Card.Header class="pb-3 border-b border-border/60">
                        <Card.Title class="text-base font-bold flex items-center gap-2">
                            <Smartphone class="size-4.5 text-sky-500" />
                            <span>{localeStore.locale === 'id' ? 'Informasi Perangkat & Jaringan' : 'Device & Network Parameters'}</span>
                        </Card.Title>
                    </Card.Header>
                    <Card.Content class="p-5 divide-y divide-border/60">
                        <div class="py-2.5 flex items-center justify-between text-xs">
                            <span class="text-muted-foreground font-medium">{localeStore.t('customer.ipAddress')}</span>
                            <span class="font-mono font-bold text-foreground">
                                {statusData.session?.ip_address || statusData.remote_address || '-'}
                            </span>
                        </div>
                        <div class="py-2.5 flex items-center justify-between text-xs">
                            <span class="text-muted-foreground font-medium">{localeStore.t('customer.macAddress')}</span>
                            <span class="font-mono font-bold text-foreground">
                                {statusData.session?.mac_address || statusData.session?.caller_id || '-'}
                            </span>
                        </div>
                        <div class="py-2.5 flex items-center justify-between text-xs">
                            <span class="text-muted-foreground font-medium">{localeStore.t('customer.totalUsage')}</span>
                            <span class="font-mono font-bold text-sky-500">
                                {statusData.session?.bytes_total_formatted || '0 B'}
                            </span>
                        </div>
                        {#if statusData.session?.time_left}
                            <div class="py-2.5 flex items-center justify-between text-xs">
                                <span class="text-muted-foreground font-medium">{localeStore.t('customer.validityRemaining')}</span>
                                <span class="font-mono font-bold text-amber-500">
                                    {statusData.session.time_left}
                                </span>
                            </div>
                        {/if}
                        {#if statusData.session?.idle_time}
                            <div class="py-2.5 flex items-center justify-between text-xs">
                                <span class="text-muted-foreground font-medium">Idle Time</span>
                                <span class="font-mono text-muted-foreground">
                                    {statusData.session.idle_time}
                                </span>
                            </div>
                        {/if}
                    </Card.Content>
                </Card.Root>

                <!-- Subscription & Billing Summary -->
                <Card.Root class="border-border shadow-xs">
                    <Card.Header class="pb-3 border-b border-border/60">
                        <Card.Title class="text-base font-bold flex items-center gap-2">
                            <ShieldCheck class="size-4.5 text-indigo-500" />
                            <span>{localeStore.locale === 'id' ? 'Paket & Langganan' : 'Subscription Plan'}</span>
                        </Card.Title>
                    </Card.Header>
                    <Card.Content class="p-5 space-y-3.5 text-xs">
                        {#if statusData.account_type === 'hotspot_customer'}
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground">{localeStore.t('customer.validity')}</span>
                                <span class="font-semibold text-foreground">
                                    {statusData.profile?.validity_duration ? `${statusData.profile.validity_duration} ${statusData.profile.validity_unit || ''}` : (statusData.limits?.time_limit || 'Unlimited')}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground">{localeStore.t('customer.quotaRemaining')}</span>
                                <span class="font-semibold text-foreground">
                                    {statusData.limits?.data_limit_formatted || localeStore.t('customer.quotaUnlimited')}
                                </span>
                            </div>
                            {#if (statusData.profile?.price ?? 0) > 0}
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">{localeStore.locale === 'id' ? 'Tarif Voucher' : 'Voucher Price'}</span>
                                    <span class="font-bold text-foreground">
                                        {formatCurrency(statusData.profile?.price)}
                                    </span>
                                </div>
                            {/if}
                        {:else}
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground">{localeStore.t('customer.monthlyPrice')}</span>
                                <span class="font-bold text-emerald-500 text-sm">
                                    {formatCurrency(statusData.profile?.price)}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground">{localeStore.t('customer.billingCycle')}</span>
                                <span class="font-semibold text-foreground uppercase font-mono">
                                    {statusData.billing_cycle || 'Monthly'}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground">{localeStore.t('customer.serviceType')}</span>
                                <span class="font-semibold text-foreground uppercase font-mono">
                                    {statusData.service || 'PPPoE'}
                                </span>
                            </div>
                            {#if statusData.customer_phone}
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">Phone</span>
                                    <span class="font-mono text-foreground">{statusData.customer_phone}</span>
                                </div>
                            {/if}
                        {/if}

                        <Separator class="my-2" />

                        <!-- Auto Refresh Switch -->
                        <div class="flex items-center justify-between pt-1">
                            <span class="text-muted-foreground text-[11px]">Auto Refresh (5s)</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    bind:checked={isAutoRefresh}
                                    class="sr-only peer"
                                />
                                <div class="w-9 h-5 bg-muted peer-focus:outline-hidden rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-sky-500"></div>
                            </label>
                        </div>
                    </Card.Content>
                </Card.Root>
            </div>
        {/if}
    </main>

    <!-- Footer -->
    <footer class="border-t border-border/60 py-4 px-6 text-center text-xs text-muted-foreground mt-auto bg-card/40">
        <p>
            Tik<span class="text-sky-500 font-bold">Pulse</span> Enterprise • Clean Architecture & Safe Mode Guardrails
        </p>
    </footer>

    <!-- Customer Profile Management Modal -->
    <CustomerProfileModal
        isOpen={isProfileModalOpen}
        customerData={statusData}
        onClose={() => (isProfileModalOpen = false)}
        onSuccess={() => fetchStatus(false)}
    />
</div>

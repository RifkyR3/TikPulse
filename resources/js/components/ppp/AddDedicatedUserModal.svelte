<script lang="ts">
    import { routerStore } from '@/stores/routerContext.svelte';
    import { localeStore } from '@/stores/locale.svelte';
    import apiClient from '@/services/api';
    import type { DedicatedUserItem, PppProfileItem, IpPoolItem } from '@/types/ppp';
    import { X, UserPlus, Check, Loader2, KeyRound, Network, Building, RotateCcw } from '@lucide/svelte';

    let {
        isOpen = false,
        userToEdit = null,
        profiles = [],
        pools = [],
        onClose,
        onSaved,
    }: {
        isOpen: boolean;
        userToEdit?: DedicatedUserItem | null;
        profiles: PppProfileItem[];
        pools: IpPoolItem[];
        onClose: () => void;
        onSaved: () => void;
    } = $props();

    let name = $state('');
    let password = $state('');
    let service = $state<'pppoe' | 'any' | 'l2tp' | 'sstp' | 'pptp' | 'ovpn'>('pppoe');
    let profile = $state('default');
    let local_address = $state('');
    let remote_address = $state('');
    let caller_id = $state('');
    let routes = $state('');
    let customer_name = $state('');
    let customer_phone = $state('');
    let customer_address = $state('');
    let monthly_price = $state<number | string | null>('');
    let billing_cycle = $state('monthly');
    let comment = $state('');
    let disabled = $state(false);

    let isSubmitting = $state(false);
    let error = $state<string | null>(null);

    const selectedProfile = $derived(profiles.find((p) => p.name === profile));
    const selectedProfilePrice = $derived(Number(selectedProfile?.price || 0));

    function formatCurrency(val: number): string {
        return new Intl.NumberFormat(localeStore.locale === 'id' ? 'id-ID' : 'en-US', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(val);
    }

    function resetToProfilePrice() {
        monthly_price = '';
    }

    $effect(() => {
        if (userToEdit) {
            name = userToEdit.name;
            password = userToEdit.password || '';
            service = (userToEdit.service as any) || 'pppoe';
            profile = userToEdit.profile || 'default';
            local_address = userToEdit.local_address || '';
            remote_address = userToEdit.remote_address || '';
            caller_id = userToEdit.caller_id || '';
            routes = userToEdit.routes || '';
            customer_name = userToEdit.customer_name || '';
            customer_phone = userToEdit.customer_phone || '';
            customer_address = userToEdit.customer_address || '';
            monthly_price = userToEdit.monthly_price !== null && userToEdit.monthly_price !== undefined ? userToEdit.monthly_price : '';
            billing_cycle = userToEdit.billing_cycle || 'monthly';
            comment = userToEdit.comment || '';
            disabled = userToEdit.disabled ?? false;
        } else {
            name = '';
            password = '';
            service = 'pppoe';
            profile = profiles[0]?.name || 'default';
            local_address = '';
            remote_address = '';
            caller_id = '';
            routes = '';
            customer_name = '';
            customer_phone = '';
            customer_address = '';
            monthly_price = '';
            billing_cycle = 'monthly';
            comment = '';
            disabled = false;
        }
        error = null;
    });

    async function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        if (!routerStore.activeRouter) return;
        error = null;
        isSubmitting = true;

        const parsedPrice = monthly_price === '' || monthly_price === null || monthly_price === undefined
            ? (selectedProfilePrice > 0 ? selectedProfilePrice : 0)
            : Number(monthly_price);

        const payload = {
            name,
            password,
            service,
            profile,
            local_address,
            remote_address,
            caller_id,
            routes,
            customer_name,
            customer_phone,
            customer_address,
            monthly_price: isNaN(parsedPrice) ? 0 : parsedPrice,
            billing_cycle,
            comment,
            disabled,
        };

        try {
            if (userToEdit) {
                await apiClient.put(
                    `/routers/${routerStore.activeRouter.id}/ppp/secrets/${userToEdit.id}`,
                    payload
                );
            } else {
                await apiClient.post(
                    `/routers/${routerStore.activeRouter.id}/ppp/secrets`,
                    payload
                );
            }
            onSaved();
            onClose();
        } catch (err: unknown) {
            const axiosErr = err as { response?: { data?: { message?: string } } };
            error = axiosErr.response?.data?.message || 'Failed to save dedicated user.';
        } finally {
            isSubmitting = false;
        }
    }
</script>

{#if isOpen}
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 animate-in fade-in duration-150 overflow-y-auto"
    >
        <div
            class="relative w-full max-w-2xl rounded-2xl border border-border bg-card p-6 shadow-2xl transition-all my-8 max-h-[90vh] flex flex-col"
            role="dialog"
            aria-modal="true"
        >
            <!-- Header -->
            <div class="flex items-center justify-between pb-4 border-b border-border">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-sky-500/10 text-sky-500 flex items-center justify-center shrink-0">
                        <UserPlus class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-foreground">
                            {userToEdit ? (localeStore.locale === 'id' ? 'Edit Pelanggan Dedicated (PPP Secret)' : 'Edit Dedicated User (PPP Secret)') : (localeStore.locale === 'id' ? 'Tambah Pelanggan Dedicated Baru' : 'Add New Dedicated User')}
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            {localeStore.locale === 'id' ? 'Akun PPPoE / L2TP dedicated dengan IP statis dan manajemen langganan.' : 'Dedicated PPPoE / L2TP client with static IP assignment and customer billing.'}
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={onClose}
                    class="p-1 rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground transition-colors cursor-pointer"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            {#if error}
                <div class="my-3 rounded-xl border border-red-500/20 bg-red-500/10 p-3 text-xs text-red-500">
                    {error}
                </div>
            {/if}

            <!-- Body Form with Scroll -->
            <form onsubmit={handleSubmit} class="flex-1 overflow-y-auto py-4 space-y-6 pr-1">
                <!-- Section 1: PPPoE / RouterOS Credentials -->
                <div class="space-y-3">
                    <div class="flex items-center space-x-2 text-xs font-bold text-sky-500 uppercase tracking-wider">
                        <KeyRound class="w-4 h-4 mr-1" />
                        <span>{localeStore.locale === 'id' ? 'Kredensial MikroTik (PPP Secret)' : 'MikroTik Credentials (PPP Secret)'}</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label for="dedicated_username" class="text-xs font-semibold text-foreground">
                                {localeStore.locale === 'id' ? 'Username / ID Secret *' : 'Username / Identity *'}
                            </label>
                            <input
                                id="dedicated_username"
                                bind:value={name}
                                disabled={!!userToEdit}
                                type="text"
                                required
                                placeholder="e.g. cust_kantor01"
                                class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none disabled:opacity-60"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label for="dedicated_password" class="text-xs font-semibold text-foreground">
                                {localeStore.locale === 'id' ? 'Password *' : 'Password *'}
                            </label>
                            <input
                                id="dedicated_password"
                                bind:value={password}
                                type="text"
                                required={!userToEdit}
                                placeholder="••••••••"
                                class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label for="dedicated_service" class="text-xs font-semibold text-foreground">Service Protocol</label>
                            <select
                                id="dedicated_service"
                                bind:value={service}
                                class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-medium focus:ring-2 focus:ring-sky-500 focus:outline-none cursor-pointer"
                            >
                                <option value="pppoe">PPPoE (Recommended)</option>
                                <option value="any">Any Service</option>
                                <option value="l2tp">L2TP VPN</option>
                                <option value="sstp">SSTP</option>
                                <option value="pptp">PPTP</option>
                                <option value="ovpn">OpenVPN</option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <label for="dedicated_profile" class="text-xs font-semibold text-foreground">
                                {localeStore.locale === 'id' ? 'Profil Kecepatan (Profile) *' : 'Bandwidth Profile *'}
                            </label>
                            <select
                                id="dedicated_profile"
                                bind:value={profile}
                                required
                                class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-medium focus:ring-2 focus:ring-sky-500 focus:outline-none cursor-pointer"
                            >
                                <option value="default">default</option>
                                {#each profiles as p (p.id)}
                                    <option value={p.name}>
                                        {p.name} {p.rate_limit ? `(${p.rate_limit})` : ''}
                                    </option>
                                {/each}
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Section 2: IP Assignment & Network Security -->
                <div class="space-y-3 pt-2 border-t border-border/60">
                    <div class="flex items-center space-x-2 text-xs font-bold text-indigo-500 uppercase tracking-wider">
                        <Network class="w-4 h-4 mr-1" />
                        <span>{localeStore.locale === 'id' ? 'Penetapan IP & Keamanan Jaringan' : 'IP Assignment & Network Security'}</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label for="dedicated_remote_address" class="text-xs font-semibold text-foreground">
                                {localeStore.locale === 'id' ? 'IP Remote / Statis Dedicated' : 'Remote Address (Dedicated IP / Pool)'}
                            </label>
                            <input
                                id="dedicated_remote_address"
                                bind:value={remote_address}
                                list="user-pool-options"
                                type="text"
                                placeholder="e.g. 10.10.10.50 or pool-dedicated"
                                class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none"
                            />
                            <datalist id="user-pool-options">
                                {#each pools as pool (pool.name)}
                                    <option value={pool.name}>
                                        {pool.name} ({pool.ranges})
                                    </option>
                                {/each}
                            </datalist>
                            <p class="text-[10px] text-muted-foreground">
                                {localeStore.locale === 'id' ? 'Isi IP statis agar pelanggan selalu dapat IP tetap.' : 'Assign static IP to ensure dedicated subscriber IP retention.'}
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="dedicated_local_address" class="text-xs font-semibold text-foreground">Local Address (Gateway)</label>
                            <input
                                id="dedicated_local_address"
                                bind:value={local_address}
                                type="text"
                                placeholder="e.g. 10.10.10.1"
                                class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label for="dedicated_caller_id" class="text-xs font-semibold text-foreground">Caller ID (MAC Lock)</label>
                            <input
                                id="dedicated_caller_id"
                                bind:value={caller_id}
                                type="text"
                                placeholder="e.g. D4:CA:6D:11:22:33"
                                class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none"
                            />
                            <p class="text-[10px] text-muted-foreground">
                                {localeStore.locale === 'id' ? 'Kunci koneksi ke MAC address modem/router CPE pelanggan.' : 'Binds login strictly to client CPE modem MAC address.'}
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="dedicated_routes" class="text-xs font-semibold text-foreground">Routes (Optional)</label>
                            <input
                                id="dedicated_routes"
                                bind:value={routes}
                                type="text"
                                placeholder="e.g. 192.168.1.0/24"
                                class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none"
                            />
                        </div>
                    </div>
                </div>

                <!-- Section 3: Customer & Billing Details -->
                <div class="space-y-3 pt-2 border-t border-border/60">
                    <div class="flex items-center space-x-2 text-xs font-bold text-emerald-500 uppercase tracking-wider">
                        <Building class="w-4 h-4 mr-1" />
                        <span>{localeStore.locale === 'id' ? 'Data Pelanggan & Tagihan' : 'Subscriber Info & Billing'}</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label for="dedicated_customer_name" class="text-xs font-semibold text-foreground">
                                {localeStore.locale === 'id' ? 'Nama Pelanggan / Perusahaan' : 'Subscriber / Company Name'}
                            </label>
                            <input
                                id="dedicated_customer_name"
                                bind:value={customer_name}
                                type="text"
                                placeholder="e.g. PT Mandiri Nusantara"
                                class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-medium focus:ring-2 focus:ring-sky-500 focus:outline-none"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label for="dedicated_customer_phone" class="text-xs font-semibold text-foreground">
                                {localeStore.locale === 'id' ? 'No. WhatsApp / Telepon' : 'WhatsApp / Contact Phone'}
                            </label>
                            <input
                                id="dedicated_customer_phone"
                                bind:value={customer_phone}
                                type="text"
                                placeholder="e.g. 081234567890"
                                class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <label for="dedicated_monthly_price" class="text-xs font-semibold text-foreground">
                                    {localeStore.locale === 'id' ? 'Biaya Langganan (Rp / Bulan)' : 'Monthly Rate (Rp / Month)'}
                                </label>
                                {#if monthly_price !== '' && monthly_price !== null}
                                    <button
                                        type="button"
                                        onclick={resetToProfilePrice}
                                        class="text-[10px] text-sky-500 hover:text-sky-400 flex items-center space-x-1 cursor-pointer transition-colors"
                                        title={localeStore.locale === 'id' ? 'Gunakan tarif otomatis dari profil' : 'Reset to auto rate from profile'}
                                    >
                                        <RotateCcw class="w-2.5 h-2.5 mr-1" />
                                        <span>{localeStore.locale === 'id' ? 'Ambil Tarif Profil' : 'Use Profile Rate'}</span>
                                    </button>
                                {/if}
                            </div>
                            <input
                                id="dedicated_monthly_price"
                                bind:value={monthly_price}
                                type="number"
                                min="0"
                                step="1000"
                                placeholder={selectedProfilePrice > 0 ? ('Auto (' + formatCurrency(selectedProfilePrice) + ')') : (localeStore.locale === 'id' ? 'Otomatis dari profil (Rp 0)' : 'Auto from profile (Rp 0)')}
                                class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none"
                            />
                            <p class="text-[10px] text-muted-foreground flex items-center justify-between">
                                <span>
                                    {localeStore.locale === 'id' 
                                        ? 'Kosongkan jika ingin otomatis mengambil tarif paket profil.' 
                                        : 'Leave empty to automatically inherit package profile price.'}
                                </span>
                                {#if selectedProfilePrice > 0}
                                    <span class="font-medium text-sky-500">
                                        {localeStore.locale === 'id' ? 'Tarif profil:' : 'Profile rate:'} {formatCurrency(selectedProfilePrice)}
                                    </span>
                                {/if}
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="dedicated_billing_cycle" class="text-xs font-semibold text-foreground">
                                {localeStore.locale === 'id' ? 'Siklus Penagihan' : 'Billing Cycle'}
                            </label>
                            <select
                                id="dedicated_billing_cycle"
                                bind:value={billing_cycle}
                                class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-medium focus:ring-2 focus:ring-sky-500 focus:outline-none cursor-pointer"
                            >
                                <option value="monthly">{localeStore.locale === 'id' ? 'Bulanan (Monthly)' : 'Monthly'}</option>
                                <option value="quarterly">{localeStore.locale === 'id' ? 'Triwulan (3 Bulan)' : 'Quarterly (3 Months)'}</option>
                                <option value="annual">{localeStore.locale === 'id' ? 'Tahunan (Annual)' : 'Annual'}</option>
                            </select>
                        </div>

                        <div class="space-y-1.5 col-span-1 sm:col-span-2">
                            <label for="dedicated_customer_address" class="text-xs font-semibold text-foreground">
                                {localeStore.locale === 'id' ? 'Alamat Pemasangan' : 'Installation Site Address'}
                            </label>
                            <textarea
                                id="dedicated_customer_address"
                                bind:value={customer_address}
                                rows="2"
                                placeholder="e.g. Gedung Wisma Lt. 3, Jl. Thamrin No. 12"
                                class="w-full p-2.5 rounded-lg border border-border bg-background text-xs font-medium focus:ring-2 focus:ring-sky-500 focus:outline-none resize-none"
                            ></textarea>
                        </div>

                        <div class="space-y-1.5 col-span-1 sm:col-span-2">
                            <label for="dedicated_comment" class="text-xs font-semibold text-foreground">
                                {localeStore.locale === 'id' ? 'Catatan / Komentar' : 'Notes / RouterOS Comment'}
                            </label>
                            <input
                                id="dedicated_comment"
                                bind:value={comment}
                                type="text"
                                placeholder="e.g. TikPulse Dedicated PPPoE - Paket Bisnis"
                                class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs focus:ring-2 focus:ring-sky-500 focus:outline-none"
                            />
                        </div>
                    </div>
                </div>

                <!-- Section 4: Account Status -->
                <div class="pt-2 border-t border-border/60 flex items-center justify-between">
                    <div>
                        <div class="text-xs font-semibold text-foreground">
                            {localeStore.locale === 'id' ? 'Status Akun Dedicated' : 'Dedicated Account Status'}
                        </div>
                        <p class="text-[10px] text-muted-foreground">
                            {localeStore.locale === 'id' ? 'Jika dinonaktifkan, akun akan segera diputus dari jaringan.' : 'If disabled, account is isolated and disconnected immediately.'}
                        </p>
                    </div>

                    <label class="relative inline-flex items-center cursor-pointer">
                        <input
                            type="checkbox"
                            checked={!disabled}
                            onchange={(e) => (disabled = !(e.target as HTMLInputElement).checked)}
                            class="sr-only peer"
                        />
                        <div class="w-9 h-5 bg-muted peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                        <span class="ml-2 text-xs font-bold {!disabled ? 'text-emerald-500' : 'text-amber-500'}">
                            {!disabled ? localeStore.t('common.active') : localeStore.t('common.disabled')}
                        </span>
                    </label>
                </div>

                <!-- Footer Buttons -->
                <div class="flex items-center justify-end space-x-2 pt-4 border-t border-border mt-2">
                    <button
                        type="button"
                        onclick={onClose}
                        class="px-4 py-2 rounded-xl border border-border text-xs font-medium hover:bg-muted transition-colors cursor-pointer"
                    >
                        {localeStore.t('common.cancel')}
                    </button>
                    <button
                        type="submit"
                        disabled={isSubmitting}
                        class="px-5 py-2 rounded-xl bg-sky-500 text-white text-xs font-bold hover:bg-sky-600 transition-colors flex items-center space-x-1.5 shadow-md shadow-sky-500/20 disabled:opacity-50 cursor-pointer"
                    >
                        {#if isSubmitting}
                            <Loader2 class="w-4 h-4 animate-spin mr-1" />
                        {:else}
                            <Check class="w-4 h-4 mr-1" />
                        {/if}
                        <span>{userToEdit ? localeStore.t('common.save') : (localeStore.locale === 'id' ? 'Simpan Pelanggan' : 'Save Subscriber')}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
{/if}

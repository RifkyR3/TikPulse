<script lang="ts">
    import { routerStore } from '@/stores/routerContext.svelte';
    import { localeStore } from '@/stores/locale.svelte';
    import apiClient from '@/services/api';
    import type { PppProfileItem, IpPoolItem } from '@/types/ppp';
    import { X, Layers, Check, Loader2 } from '@lucide/svelte';

    let {
        isOpen = false,
        profileToEdit = null,
        pools = [],
        onClose,
        onSaved,
    }: {
        isOpen: boolean;
        profileToEdit?: PppProfileItem | null;
        pools?: IpPoolItem[];
        onClose: () => void;
        onSaved: () => void;
    } = $props();

    let name = $state('');
    let local_address = $state('');
    let remote_address = $state('');
    let rate_limit = $state('10M/20M');
    let only_one = $state(true);
    let dns_server = $state('8.8.8.8,1.1.1.1');
    let price = $state(250000);
    let comment = $state('');

    let isSubmitting = $state(false);
    let error = $state<string | null>(null);

    $effect(() => {
        if (profileToEdit) {
            name = profileToEdit.name;
            local_address = profileToEdit.local_address || '';
            remote_address = profileToEdit.remote_address || '';
            rate_limit = profileToEdit.rate_limit || '';
            only_one = profileToEdit.only_one ?? true;
            dns_server = profileToEdit.dns_server || '';
            price = profileToEdit.price || 0;
            comment = profileToEdit.comment || '';
        } else {
            name = '';
            local_address = '';
            remote_address = '';
            rate_limit = '10M/20M';
            only_one = true;
            dns_server = '8.8.8.8,1.1.1.1';
            price = 250000;
            comment = '';
        }
        error = null;
    });

    async function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        if (!routerStore.activeRouter) return;
        error = null;
        isSubmitting = true;

        const payload = {
            name,
            local_address,
            remote_address,
            rate_limit,
            only_one,
            dns_server,
            price,
            comment,
        };

        try {
            if (profileToEdit) {
                await apiClient.put(
                    `/routers/${routerStore.activeRouter.id}/ppp/profiles/${profileToEdit.id}`,
                    payload
                );
            } else {
                await apiClient.post(
                    `/routers/${routerStore.activeRouter.id}/ppp/profiles`,
                    payload
                );
            }
            onSaved();
            onClose();
        } catch (err: unknown) {
            const axiosErr = err as { response?: { data?: { message?: string } } };
            error = axiosErr.response?.data?.message || 'Failed to save PPP profile.';
        } finally {
            isSubmitting = false;
        }
    }
</script>

{#if isOpen}
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 animate-in fade-in duration-150"
    >
        <div
            class="relative w-full max-w-lg rounded-2xl border border-border bg-card p-6 shadow-2xl transition-all"
            role="dialog"
            aria-modal="true"
        >
            <button
                type="button"
                onclick={onClose}
                class="absolute right-4 top-4 p-1 rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground transition-colors cursor-pointer"
            >
                <X class="w-5 h-5" />
            </button>

            <div class="flex items-center space-x-3 mb-5">
                <div class="w-10 h-10 rounded-xl bg-sky-500/10 text-sky-500 flex items-center justify-center shrink-0">
                    <Layers class="w-5 h-5" />
                </div>
                <div>
                    <h3 class="text-base font-bold text-foreground">
                        {profileToEdit ? (localeStore.locale === 'id' ? 'Edit Profil Bandwidth PPP' : 'Edit PPP Bandwidth Profile') : (localeStore.locale === 'id' ? 'Buat Profil Bandwidth PPP' : 'Create PPP Bandwidth Profile')}
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        {localeStore.locale === 'id' ? 'Konfigurasi batasan kecepatan (rate-limit) & gateway PPPoE dedicated.' : 'Configure rate-limit & IP gateway for dedicated PPPoE subscribers.'}
                    </p>
                </div>
            </div>

            {#if error}
                <div class="mb-4 rounded-xl border border-red-500/20 bg-red-500/10 p-3 text-xs text-red-500">
                    {error}
                </div>
            {/if}

            <form onsubmit={handleSubmit} class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5 col-span-2">
                        <label for="ppp_profile_name" class="text-xs font-semibold text-foreground">
                            {localeStore.locale === 'id' ? 'Nama Profil *' : 'Profile Name *'}
                        </label>
                        <input
                            id="ppp_profile_name"
                            bind:value={name}
                            disabled={!!profileToEdit}
                            type="text"
                            required
                            placeholder="e.g. Dedicated-20M"
                            class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-medium focus:ring-2 focus:ring-sky-500 focus:outline-none disabled:opacity-60"
                        />
                    </div>

                    <div class="space-y-1.5 col-span-2 sm:col-span-1">
                        <label for="ppp_profile_rate_limit" class="text-xs font-semibold text-foreground">
                            {localeStore.locale === 'id' ? 'Rate Limit (Upload/Download)' : 'Rate Limit (Rx/Tx)'}
                        </label>
                        <input
                            id="ppp_profile_rate_limit"
                            bind:value={rate_limit}
                            type="text"
                            placeholder="e.g. 10M/20M"
                            class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none"
                        />
                        <p class="text-[10px] text-muted-foreground">e.g. 5M/10M, 20M/50M</p>
                    </div>

                    <div class="space-y-1.5 col-span-2 sm:col-span-1">
                        <label for="ppp_profile_price" class="text-xs font-semibold text-foreground">
                            {localeStore.locale === 'id' ? 'Biaya Bulanan (Rp)' : 'Monthly Price (Rp)'}
                        </label>
                        <input
                            id="ppp_profile_price"
                            bind:value={price}
                            type="number"
                            min="0"
                            step="1000"
                            class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none"
                        />
                    </div>

                    <div class="space-y-1.5 col-span-2 sm:col-span-1">
                        <label for="ppp_profile_local_address" class="text-xs font-semibold text-foreground">Local Address (Gateway)</label>
                        <input
                            id="ppp_profile_local_address"
                            bind:value={local_address}
                            type="text"
                            placeholder="e.g. 10.10.10.1"
                            class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none"
                        />
                    </div>

                    <div class="space-y-1.5 col-span-2 sm:col-span-1">
                        <label for="ppp_profile_remote_address" class="text-xs font-semibold text-foreground">Remote Address / Pool</label>
                        <input
                            id="ppp_profile_remote_address"
                            bind:value={remote_address}
                            list="pool-options"
                            type="text"
                            placeholder="e.g. pool-dedicated"
                            class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none"
                        />
                        <datalist id="pool-options">
                            {#each pools as pool (pool.name)}
                                <option value={pool.name}>
                                    {pool.name} ({pool.ranges})
                                </option>
                            {/each}
                        </datalist>
                    </div>

                    <div class="space-y-1.5 col-span-2">
                        <label for="ppp_profile_dns_server" class="text-xs font-semibold text-foreground">DNS Servers</label>
                        <input
                            id="ppp_profile_dns_server"
                            bind:value={dns_server}
                            type="text"
                            placeholder="e.g. 8.8.8.8,1.1.1.1"
                            class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none"
                        />
                    </div>

                    <div class="space-y-1.5 col-span-2">
                        <label for="ppp_profile_comment" class="text-xs font-semibold text-foreground">
                            {localeStore.locale === 'id' ? 'Keterangan' : 'Comment'}
                        </label>
                        <input
                            id="ppp_profile_comment"
                            bind:value={comment}
                            type="text"
                            placeholder="e.g. Paket Kantor Dedicated 20Mbps"
                            class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs focus:ring-2 focus:ring-sky-500 focus:outline-none"
                        />
                    </div>

                    <div class="col-span-2 pt-1 flex items-center space-x-2">
                        <input
                            id="only_one"
                            bind:checked={only_one}
                            type="checkbox"
                            class="w-4 h-4 rounded text-sky-500 border-border focus:ring-sky-500 cursor-pointer"
                        />
                        <label for="only_one" class="text-xs font-medium text-foreground cursor-pointer select-none">
                            {localeStore.locale === 'id' ? 'Hanya 1 Sesi Aktif (Only-One Session)' : 'Enforce Single Session per Secret (Only-One)'}
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-2 pt-4 border-t border-border mt-5">
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
                        <span>{profileToEdit ? localeStore.t('common.save') : (localeStore.locale === 'id' ? 'Simpan Profil' : 'Save Profile')}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
{/if}

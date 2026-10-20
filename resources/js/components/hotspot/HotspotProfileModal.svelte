<script lang="ts">
    import { routerStore } from '@/stores/routerContext.svelte';
    import apiClient from '@/services/api';
    import type { HotspotProfileItem } from '@/types/hotspot';
    import { X, Layers, Check, Loader2 } from '@lucide/svelte';

    let {
        isOpen = false,
        profileToEdit = null,
        onClose,
        onSaved,
    }: {
        isOpen: boolean;
        profileToEdit?: HotspotProfileItem | null;
        onClose: () => void;
        onSaved: () => void;
    } = $props();

    let name = $state('');
    let shared_users = $state(1);
    let rate_limit = $state('2M/5M');
    let validity_duration = $state('1');
    let validity_unit = $state<'minutes' | 'hours' | 'days'>('hours');
    let price = $state(3000);
    let selling_price = $state(3000);
    let address_pool = $state('');

    let isSubmitting = $state(false);
    let error = $state<string | null>(null);

    $effect(() => {
        if (profileToEdit) {
            name = profileToEdit.name;
            shared_users = profileToEdit.shared_users || 1;
            rate_limit = profileToEdit.rate_limit || '';
            validity_duration = profileToEdit.validity_duration || '1';
            validity_unit = profileToEdit.validity_unit || 'hours';
            price = profileToEdit.price || 0;
            selling_price = profileToEdit.selling_price || 0;
            address_pool = profileToEdit.address_pool || '';
        } else {
            name = '';
            shared_users = 1;
            rate_limit = '2M/5M';
            validity_duration = '1';
            validity_unit = 'hours';
            price = 3000;
            selling_price = 3000;
            address_pool = '';
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
            shared_users,
            rate_limit,
            validity_duration,
            validity_unit,
            price,
            selling_price,
            address_pool,
        };

        try {
            if (profileToEdit) {
                await apiClient.put(
                    `/routers/${routerStore.activeRouter.id}/profiles/${profileToEdit.id}`,
                    payload
                );
            } else {
                await apiClient.post(
                    `/routers/${routerStore.activeRouter.id}/profiles`,
                    payload
                );
            }
            onSaved();
            onClose();
        } catch (err: unknown) {
            const axiosErr = err as { response?: { data?: { message?: string } } };
            error = axiosErr.response?.data?.message || 'Failed to save hotspot profile.';
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
                class="absolute top-4 right-4 text-muted-foreground hover:text-foreground p-1 rounded-lg transition-colors cursor-pointer"
            >
                <X class="w-5 h-5" />
            </button>

            <!-- Header -->
            <div class="flex items-center space-x-3 mb-5">
                <div class="w-11 h-11 rounded-xl bg-purple-500/10 border border-purple-500/20 text-purple-500 flex items-center justify-center shrink-0">
                    <Layers class="w-6 h-6" />
                </div>
                <div>
                    <h3 class="text-lg font-bold text-foreground leading-tight">
                        {profileToEdit ? 'Edit Hotspot Profile' : 'Add Hotspot Profile'}
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        Configure bandwidth limits, validity periods, and voucher pricing
                    </p>
                </div>
            </div>

            <!-- Error Banner -->
            {#if error}
                <div class="mb-4 rounded-xl bg-destructive/10 border border-destructive/20 p-3 text-xs text-destructive font-medium">
                    {error}
                </div>
            {/if}

            <form onsubmit={handleSubmit} class="space-y-4">
                <div>
                    <label for="hotspot_profile_name" class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                        Profile Name
                    </label>
                    <input
                        id="hotspot_profile_name"
                        bind:value={name}
                        type="text"
                        required
                        disabled={!!profileToEdit}
                        placeholder="e.g. 1Jam-5Mbps or Harian-3M"
                        class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20 disabled:opacity-60"
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="hotspot_profile_rate_limit" class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Rate Limit (Rx/Tx)
                        </label>
                        <input
                            id="hotspot_profile_rate_limit"
                            bind:value={rate_limit}
                            type="text"
                            placeholder="e.g. 2M/5M, 512k/1M"
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground font-mono focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20"
                        />
                    </div>

                    <div>
                        <label for="hotspot_profile_shared_users" class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Shared Users
                        </label>
                        <input
                            id="hotspot_profile_shared_users"
                            bind:value={shared_users}
                            type="number"
                            min="1"
                            max="50"
                            required
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground font-mono focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20"
                        />
                    </div>
                </div>

                <!-- Validity Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="hotspot_profile_validity_duration" class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Validity Duration
                        </label>
                        <input
                            id="hotspot_profile_validity_duration"
                            bind:value={validity_duration}
                            type="text"
                            placeholder="e.g. 1, 2, 24"
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground font-mono focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20"
                        />
                    </div>

                    <div>
                        <label for="hotspot_profile_validity_unit" class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Validity Unit
                        </label>
                        <select
                            id="hotspot_profile_validity_unit"
                            bind:value={validity_unit}
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20 cursor-pointer"
                        >
                            <option value="minutes">Minutes (m)</option>
                            <option value="hours">Hours (h)</option>
                            <option value="days">Days (d)</option>
                        </select>
                    </div>
                </div>

                <!-- Pricing Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="hotspot_profile_price" class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Modal Price (Rp)
                        </label>
                        <input
                            id="hotspot_profile_price"
                            bind:value={price}
                            type="number"
                            min="0"
                            placeholder="3000"
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground font-mono focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20"
                        />
                    </div>

                    <div>
                        <label for="hotspot_profile_selling_price" class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Selling Price (Rp)
                        </label>
                        <input
                            id="hotspot_profile_selling_price"
                            bind:value={selling_price}
                            type="number"
                            min="0"
                            placeholder="3000"
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground font-mono focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20"
                        />
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end space-x-3 pt-3 border-t border-border">
                    <button
                        type="button"
                        onclick={onClose}
                        class="px-4 py-2 text-xs font-semibold rounded-lg border border-border hover:bg-muted text-foreground transition-colors cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={isSubmitting}
                        class="px-5 py-2 text-xs font-semibold rounded-lg bg-purple-600 hover:bg-purple-500 text-white shadow-md shadow-purple-600/20 flex items-center space-x-2 disabled:opacity-50 transition-colors cursor-pointer"
                    >
                        {#if isSubmitting}
                            <Loader2 class="w-3.5 h-3.5 animate-spin mr-1.5" />
                        {:else}
                            <Check class="w-3.5 h-3.5 mr-1.5" />
                        {/if}
                        <span>Save Profile</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
{/if}

<script lang="ts">
    import { routerStore } from '@/stores/routerContext.svelte';
    import apiClient from '@/services/api';
    import type { ApiResponse } from '@/types/api';
    import type { HotspotProfileItem, VoucherBatchItem } from '@/types/hotspot';
    import {
        Sparkles,
        X,
        Check,
        Loader2,
        Eye,
        ShieldCheck,
    } from '@lucide/svelte';

    let {
        isOpen = false,
        onClose,
        onGenerated,
    }: {
        isOpen: boolean;
        onClose: () => void;
        onGenerated: (batch: VoucherBatchItem) => void;
    } = $props();

    let profiles = $state<HotspotProfileItem[]>([]);
    let isLoadingProfiles = $state(false);
    let isGenerating = $state(false);
    let error = $state<string | null>(null);

    let name = $state('');
    let profile_id = $state<number | null>(null);
    let quantity = $state(25);
    let code_format = $state<'numeric' | 'alphabet' | 'alphanumeric' | 'random_prefix'>('numeric');
    let character_length = $state(6);
    let prefix = $state('');
    let password_mode = $state<'same_as_username' | 'random_password'>('same_as_username');
    let time_limit = $state('');
    let data_limit_mb = $state<number | null>(null);
    let sync_to_mikrotik = $state(true);

    const quickQuantities = [10, 25, 50, 100, 250, 500];

    const selectedProfile = $derived(
        profiles.find((p) => p.id === profile_id) || null
    );

    const sampleVoucherCode = $derived.by(() => {
        const pfx = prefix ? prefix.trim() : (code_format === 'random_prefix' ? 'VIP-' : '');
        const len = Math.max(4, character_length);
        let sample = '';
        if (code_format === 'numeric') {
            sample = '849201537'.slice(0, len);
        } else if (code_format === 'alphabet') {
            sample = 'kmpqxyzab'.slice(0, len);
        } else {
            sample = '7x2k9m4b'.slice(0, len);
        }
        return pfx + sample;
    });

    async function fetchProfiles() {
        if (!routerStore.activeRouter) return;
        isLoadingProfiles = true;
        try {
            const res = await apiClient.get<ApiResponse<HotspotProfileItem[]>>(
                `/routers/${routerStore.activeRouter.id}/profiles`
            );
            if (res.data.status === 'success' && Array.isArray(res.data.data)) {
                profiles = res.data.data;
                if (profiles.length > 0 && !profile_id) {
                    profile_id = profiles[0].id;
                }
            }
        } catch {
            // Fallback
        } finally {
            isLoadingProfiles = false;
        }
    }

    $effect(() => {
        if (isOpen) {
            error = null;
            fetchProfiles();
            const now = new Date();
            name = `Batch-${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, '0')}${String(now.getDate()).padStart(2, '0')}-${String(now.getHours()).padStart(2, '0')}${String(now.getMinutes()).padStart(2, '0')}`;
        }
    });

    async function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        if (!routerStore.activeRouter) {
            error = 'No router selected.';
            return;
        }

        if (!profile_id) {
            error = 'Please select a hotspot profile.';
            return;
        }

        error = null;
        isGenerating = true;

        const payload = {
            name,
            profile_id,
            quantity,
            code_format,
            character_length,
            prefix,
            password_mode,
            time_limit,
            data_limit_mb,
            sync_to_mikrotik,
        };

        try {
            const res = await apiClient.post<ApiResponse<{ batch: VoucherBatchItem; vouchers_count: number; synced_count: number }>>(
                `/routers/${routerStore.activeRouter.id}/vouchers/generate`,
                payload
            );

            if (res.data.status === 'success' && res.data.data) {
                onGenerated(res.data.data.batch);
                onClose();
            }
        } catch (err: unknown) {
            const axiosErr = err as { response?: { data?: { message?: string } } };
            error = axiosErr.response?.data?.message || 'Failed to generate voucher batch.';
        } finally {
            isGenerating = false;
        }
    }
</script>

{#if isOpen}
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 animate-in fade-in duration-150"
    >
        <div
            class="relative w-full max-w-2xl max-h-[92vh] overflow-y-auto rounded-2xl border border-border bg-card p-6 shadow-2xl transition-all"
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
                <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-sky-500 to-indigo-600 text-white flex items-center justify-center shrink-0 shadow-md shadow-sky-500/20">
                    <Sparkles class="w-5 h-5" />
                </div>
                <div>
                    <h3 class="text-lg font-bold text-foreground leading-tight">Generate Hotspot Vouchers</h3>
                    <p class="text-xs text-muted-foreground">
                        Batch voucher generator with instant MikroTik RouterOS synchronization
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
                <!-- Batch Name & Profile Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="voucher_batch_name" class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Batch Name
                        </label>
                        <input
                            id="voucher_batch_name"
                            bind:value={name}
                            type="text"
                            required
                            placeholder="e.g. Batch-20261015-01"
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                        />
                    </div>

                    <div>
                        <label for="voucher_profile_id" class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Hotspot Profile
                        </label>
                        <select
                            id="voucher_profile_id"
                            bind:value={profile_id}
                            required
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 cursor-pointer"
                        >
                            {#if profiles.length === 0}
                                <option disabled value={null}>
                                    {isLoadingProfiles ? 'Loading profiles...' : 'No profiles found (Create or sync one first)'}
                                </option>
                            {/if}
                            {#each profiles as p (p.id)}
                                <option value={p.id}>
                                    {p.name} ({p.rate_limit || 'Unlimited'} - Rp {Number(p.selling_price).toLocaleString()})
                                </option>
                            {/each}
                        </select>
                    </div>
                </div>

                <!-- Quantity Quick Selector -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="voucher_quantity" class="block text-xs font-semibold uppercase tracking-wider text-foreground">
                            Voucher Quantity
                        </label>
                        <span class="text-xs text-muted-foreground font-mono font-bold">{quantity} vouchers</span>
                    </div>

                    <div class="flex flex-wrap gap-2 mb-2">
                        {#each quickQuantities as q}
                            <button
                                type="button"
                                onclick={() => (quantity = q)}
                                class="px-3 py-1 text-xs font-bold rounded-lg border transition-all cursor-pointer {quantity === q
                                     ? 'bg-sky-500/10 border-sky-500/30 text-sky-600 dark:text-sky-400'
                                    : 'border-border text-muted-foreground hover:bg-muted'}"
                            >
                                {q}
                            </button>
                        {/each}
                    </div>

                    <input
                        id="voucher_quantity"
                        bind:value={quantity}
                        type="number"
                        min="1"
                        max="1000"
                        required
                        class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground font-mono focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                    />
                </div>

                <!-- Code Format & Character Length Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label for="voucher_code_format" class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Code Format
                        </label>
                        <select
                            id="voucher_code_format"
                            bind:value={code_format}
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-xs text-foreground focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 cursor-pointer"
                        >
                            <option value="numeric">Numeric (Digits only: 849201)</option>
                            <option value="alphabet">Alphabet (Letters only: kmpqxy)</option>
                            <option value="alphanumeric">Alphanumeric (Letters & Digits)</option>
                            <option value="random_prefix">Custom Prefix + Random</option>
                        </select>
                    </div>

                    <div>
                        <label for="voucher_character_length" class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Length (Digits)
                        </label>
                        <input
                            id="voucher_character_length"
                            bind:value={character_length}
                            type="number"
                            min="4"
                            max="12"
                            required
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-xs text-foreground font-mono focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                        />
                    </div>

                    <div>
                        <label for="voucher_prefix" class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Prefix (Optional)
                        </label>
                        <input
                            id="voucher_prefix"
                            bind:value={prefix}
                            type="text"
                            placeholder="e.g. VIP-, CAFE-"
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-xs text-foreground font-mono focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                        />
                    </div>
                </div>

                <!-- Password Mode -->
                <div>
                    <span class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1.5">
                        Login Mode
                    </span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label
                            class="flex items-center space-x-3 p-3 rounded-xl border cursor-pointer transition-all {password_mode === 'same_as_username'
                                ? 'border-sky-500 bg-sky-500/10'
                                : 'border-border bg-background hover:bg-muted/40'}"
                        >
                            <input
                                type="radio"
                                value="same_as_username"
                                bind:group={password_mode}
                                class="w-4 h-4 text-sky-500"
                            />
                            <div>
                                <span class="font-bold text-xs text-foreground block">Username = Password</span>
                                <span class="text-[11px] text-muted-foreground block">Single-field voucher code (Mikhmon standard)</span>
                            </div>
                        </label>

                        <label
                            class="flex items-center space-x-3 p-3 rounded-xl border cursor-pointer transition-all {password_mode === 'random_password'
                                ? 'border-sky-500 bg-sky-500/10'
                                : 'border-border bg-background hover:bg-muted/40'}"
                        >
                            <input
                                type="radio"
                                value="random_password"
                                bind:group={password_mode}
                                class="w-4 h-4 text-sky-500"
                            />
                            <div>
                                <span class="font-bold text-xs text-foreground block">Username ≠ Password</span>
                                <span class="text-[11px] text-muted-foreground block">Separate username and password credentials</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Overrides (Time Limit / Data Limit) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    <div>
                        <label for="voucher_time_limit" class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Time Limit (Optional Override)
                        </label>
                        <input
                            id="voucher_time_limit"
                            bind:value={time_limit}
                            type="text"
                            placeholder="e.g. 2h, 1d, 30d (blank = profile default)"
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-xs text-foreground font-mono focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                        />
                    </div>

                    <div>
                        <label for="voucher_data_limit_mb" class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Data Quota MB (Optional)
                        </label>
                        <input
                            id="voucher_data_limit_mb"
                            bind:value={data_limit_mb}
                            type="number"
                            min="1"
                            placeholder="e.g. 1024 for 1GB"
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-xs text-foreground font-mono focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                        />
                    </div>
                </div>

                <!-- Live Preview Card -->
                <div class="p-3.5 rounded-xl border border-sky-500/30 bg-sky-500/5 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-sky-600 dark:text-sky-400 flex items-center space-x-1.5">
                            <Eye class="w-3.5 h-3.5 mr-1" />
                            <span>Voucher Live Sample Preview</span>
                        </span>
                        <span class="text-[11px] font-mono text-muted-foreground">
                            Price: <strong>Rp {selectedProfile ? Number(selectedProfile.selling_price).toLocaleString() : '0'}</strong>
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2 p-2.5 rounded-lg bg-background border border-border font-mono text-xs">
                        <div>
                            <span class="text-muted-foreground block text-[10px]">USERNAME:</span>
                            <strong class="text-foreground text-sm tracking-wider">{sampleVoucherCode}</strong>
                        </div>
                        {#if password_mode === 'random_password'}
                            <div>
                                <span class="text-muted-foreground block text-[10px]">PASSWORD:</span>
                                <strong class="text-foreground text-sm tracking-wider">9x8m2k</strong>
                            </div>
                        {/if}
                        <div>
                            <span class="text-muted-foreground block text-[10px]">SPEED:</span>
                            <span class="text-foreground">{selectedProfile?.rate_limit || 'Profile Default'}</span>
                        </div>
                    </div>
                </div>

                <!-- Sync to MikroTik Checkbox -->
                <div class="flex items-center space-x-3 pt-1">
                    <input
                        id="sync_checkbox"
                        bind:checked={sync_to_mikrotik}
                        type="checkbox"
                        class="w-4 h-4 rounded border-border text-sky-500 focus:ring-sky-500 cursor-pointer"
                    />
                    <label for="sync_checkbox" class="text-xs text-foreground font-medium cursor-pointer flex items-center space-x-1.5">
                        <ShieldCheck class="w-3.5 h-3.5 text-emerald-500 mr-1" />
                        <span>Synchronize instantly to MikroTik (/ip/hotspot/user)</span>
                    </label>
                </div>

                <!-- Actions -->
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
                        disabled={isGenerating || profiles.length === 0}
                        class="px-5 py-2 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-500 text-white shadow-md shadow-sky-600/20 flex items-center space-x-2 disabled:opacity-50 transition-colors cursor-pointer"
                    >
                        {#if isGenerating}
                            <Loader2 class="w-3.5 h-3.5 animate-spin mr-1.5" />
                        {:else}
                            <Sparkles class="w-3.5 h-3.5 mr-1.5" />
                        {/if}
                        <span>Generate {quantity} Vouchers</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
{/if}

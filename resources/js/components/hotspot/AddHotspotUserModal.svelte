<script lang="ts">
    import { routerStore } from '@/stores/routerContext.svelte';
    import apiClient from '@/services/api';
    import type { HotspotProfileItem } from '@/types/hotspot';
    import { toast } from 'svelte-sonner';
    import { X, UserPlus, Check, Loader2 } from '@lucide/svelte';

    let {
        isOpen = false,
        profiles = [],
        onClose,
        onSaved,
    }: {
        isOpen: boolean;
        profiles: HotspotProfileItem[];
        onClose: () => void;
        onSaved: () => void;
    } = $props();

    let name = $state('');
    let password = $state('');
    let profile = $state('');
    let comment = $state('');
    let time_limit = $state('');
    let data_limit_mb = $state<number | null>(null);

    let isSubmitting = $state(false);
    let error = $state<string | null>(null);

    $effect(() => {
        if (isOpen) {
            name = '';
            password = '';
            profile = profiles[0]?.name || '';
            comment = '';
            time_limit = '';
            data_limit_mb = null;
            error = null;
        }
    });

    async function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        if (!routerStore.activeRouter) return;
        if (!name.trim() || !password.trim()) {
            error = 'Username and password are required.';
            return;
        }

        isSubmitting = true;
        error = null;

        try {
            const payload: Record<string, unknown> = {
                name: name.trim(),
                password: password.trim(),
                profile: profile || profiles[0]?.name || 'default',
                comment: comment.trim() || null,
                time_limit: time_limit.trim() || null,
                data_limit: data_limit_mb ? data_limit_mb * 1024 * 1024 : null,
            };

            const res = await apiClient.post(`/routers/${routerStore.activeRouter.id}/hotspot/users`, payload);

            if (res.data.status === 'success') {
                toast.success(res.data.message || 'User added to MikroTik.');
                onSaved();
                onClose();
            } else {
                error = res.data.message || 'Failed to add user.';
            }
        } catch (err: unknown) {
            const axiosErr = err as { response?: { data?: { message?: string } } };
            error = axiosErr.response?.data?.message || 'Failed to add hotspot user to router.';
            toast.error(error);
        } finally {
            isSubmitting = false;
        }
    }
</script>

{#if isOpen}
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs transition-opacity"
    >
        <div class="relative w-full max-w-md rounded-2xl border border-border bg-card shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-border px-6 py-4 bg-muted/20">
                <div class="flex items-center space-x-2.5">
                    <div class="w-8 h-8 rounded-lg bg-sky-500/10 text-sky-500 flex items-center justify-center">
                        <UserPlus class="w-4 h-4" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-foreground">Add Hotspot User</h3>
                        <p class="text-[11px] text-muted-foreground font-mono">
                            {routerStore.activeRouter?.name} (/ip/hotspot/user)
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={onClose}
                    class="p-1 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Error Banner -->
            {#if error}
                <div class="p-3 mx-6 mt-4 rounded-xl bg-destructive/10 border border-destructive/20 text-destructive text-xs">
                    {error}
                </div>
            {/if}

            <!-- Form -->
            <form onsubmit={handleSubmit} class="p-6 space-y-4">
                <div class="space-y-1.5">
                    <label for="hotspot_user_name" class="text-xs font-semibold text-foreground">
                        Username / MAC Address <span class="text-destructive">*</span>
                    </label>
                    <input
                        id="hotspot_user_name"
                        bind:value={name}
                        type="text"
                        required
                        placeholder="e.g. user01 or AA:BB:CC:DD:EE:FF"
                        class="w-full rounded-xl border border-border bg-background px-3 py-2 text-xs font-mono text-foreground focus:outline-none focus:ring-1 focus:ring-sky-500"
                    />
                </div>

                <div class="space-y-1.5">
                    <label for="hotspot_user_password" class="text-xs font-semibold text-foreground">
                        Password <span class="text-destructive">*</span>
                    </label>
                    <input
                        id="hotspot_user_password"
                        bind:value={password}
                        type="text"
                        required
                        placeholder="Enter password"
                        class="w-full rounded-xl border border-border bg-background px-3 py-2 text-xs font-mono text-foreground focus:outline-none focus:ring-1 focus:ring-sky-500"
                    />
                </div>

                <div class="space-y-1.5">
                    <label for="hotspot_user_profile" class="text-xs font-semibold text-foreground">Bandwidth Profile</label>
                    <select
                        id="hotspot_user_profile"
                        bind:value={profile}
                        class="w-full rounded-xl border border-border bg-background px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-sky-500 cursor-pointer"
                    >
                        <option value="">Default (or select profile)</option>
                        {#each profiles as p (p.id)}
                            <option value={p.name}>
                                {p.name} ({p.rate_limit || 'Unlimited'})
                            </option>
                        {/each}
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label for="hotspot_user_time_limit" class="text-xs font-semibold text-foreground">Uptime Limit (Optional)</label>
                        <input
                            id="hotspot_user_time_limit"
                            bind:value={time_limit}
                            type="text"
                            placeholder="e.g. 1d, 12h, 30m"
                            class="w-full rounded-xl border border-border bg-background px-3 py-2 text-xs font-mono text-foreground focus:outline-none focus:ring-1 focus:ring-sky-500"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <label for="hotspot_user_data_limit_mb" class="text-xs font-semibold text-foreground">Data Limit (MB)</label>
                        <input
                            id="hotspot_user_data_limit_mb"
                            bind:value={data_limit_mb}
                            type="number"
                            min="0"
                            placeholder="e.g. 1024"
                            class="w-full rounded-xl border border-border bg-background px-3 py-2 text-xs font-mono text-foreground focus:outline-none focus:ring-1 focus:ring-sky-500"
                        />
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label for="hotspot_user_comment" class="text-xs font-semibold text-foreground">Comment / Note (Optional)</label>
                    <input
                        id="hotspot_user_comment"
                        bind:value={comment}
                        type="text"
                        placeholder="e.g. Laptop Staff, TV Smart, Guest"
                        class="w-full rounded-xl border border-border bg-background px-3 py-2 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-sky-500"
                    />
                </div>

                <!-- Footer Actions -->
                <div class="pt-2 flex items-center justify-end space-x-2">
                    <button
                        type="button"
                        onclick={onClose}
                        class="px-4 py-2 rounded-xl border border-border hover:bg-muted text-xs font-medium text-foreground transition-colors cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={isSubmitting}
                        class="px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs shadow-md shadow-sky-600/20 flex items-center space-x-1.5 transition-all cursor-pointer disabled:opacity-50"
                    >
                        {#if isSubmitting}
                            <Loader2 class="w-3.5 h-3.5 animate-spin mr-1" />
                        {:else}
                            <Check class="w-3.5 h-3.5 mr-1" />
                        {/if}
                        <span>Save to MikroTik</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
{/if}

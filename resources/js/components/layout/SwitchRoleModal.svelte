<script lang="ts">
    import { authStore } from '@/stores/auth.svelte';
    import { localeStore } from '@/stores/locale.svelte';
    import apiClient from '@/services/api';
    import type { Group } from '@/types/group';
    import type { ApiResponse } from '@/types/api';
    import { X, Shield, ShieldAlert, ArrowRight, Check, RefreshCw, Server, Sparkles } from '@lucide/svelte';
    import { toast } from 'svelte-sonner';
    import { router } from '@inertiajs/svelte';

    let {
        isOpen = false,
        onClose,
    }: {
        isOpen: boolean;
        onClose: () => void;
    } = $props();

    let groups = $state<Group[]>([]);
    let isLoading = $state(false);
    let switchingId = $state<number | null>(null);

    $effect(() => {
        if (isOpen) {
            fetchGroups();
        }
    });

    async function fetchGroups() {
        isLoading = true;
        try {
            const res = await apiClient.get<ApiResponse<Group[]>>('/groups');
            if (res.data.status === 'success') {
                groups = res.data.data;
            }
        } catch {
            toast.error('Failed to load access groups.');
        } finally {
            isLoading = false;
        }
    }

    async function handleSwitch(groupId: number | null) {
        switchingId = groupId ?? -1;
        try {
            const success = groupId === null
                ? await authStore.resetRole()
                : await authStore.switchRole(groupId);

            if (success) {
                toast.success(groupId ? 'Switched role context.' : 'Reverted to Super Administrator.');
                onClose();
                router.reload();
            } else {
                toast.error('Role switch failed.');
            }
        } finally {
            switchingId = null;
        }
    }
</script>

{#if isOpen}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Backdrop -->
        <button
            type="button"
            class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity cursor-default"
            onclick={onClose}
            aria-label="Close modal"
        ></button>

        <!-- Modal Dialog -->
        <div class="relative w-full max-w-lg rounded-2xl border border-border bg-card p-6 shadow-2xl z-10 space-y-5 animate-in fade-in zoom-in-95 duration-150 max-h-[85vh] flex flex-col">
            <!-- Header -->
            <div class="flex items-center justify-between pb-3 border-b border-border shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="size-8 rounded-lg bg-gradient-to-tr from-sky-600 to-indigo-600 text-white flex items-center justify-center shadow-md shadow-sky-500/20">
                        <Sparkles class="size-4" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-foreground">{localeStore.t('users.switchRole')}</h3>
                        <p class="text-[11px] text-muted-foreground">Test permissions and router scoping as different groups</p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={onClose}
                    class="size-7 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground flex items-center justify-center transition-colors cursor-pointer"
                >
                    <X class="size-4" />
                </button>
            </div>

            <!-- Content -->
            <div class="space-y-3 overflow-y-auto pr-1 flex-1">
                {#if authStore.isSimulating}
                    <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 text-xs">
                            <ShieldAlert class="size-4 text-amber-500 shrink-0" />
                            <div>
                                <div class="font-bold text-amber-500">Currently Simulating: {authStore.user?.simulated_group?.name}</div>
                                <div class="text-[11px] text-muted-foreground">Revert back to experience full superadmin capabilities.</div>
                            </div>
                        </div>
                        <button
                            type="button"
                            onclick={() => handleSwitch(null)}
                            disabled={switchingId !== null}
                            class="px-2.5 py-1.5 rounded-lg bg-amber-500 text-amber-950 font-bold text-xs hover:bg-amber-600 transition-colors cursor-pointer shrink-0 disabled:opacity-50"
                        >
                            Revert Now
                        </button>
                    </div>
                {/if}

                {#if isLoading}
                    <div class="py-8 text-center text-xs text-muted-foreground flex items-center justify-center gap-2">
                        <RefreshCw class="size-4 animate-spin" />
                        <span>Loading available roles...</span>
                    </div>
                {:else}
                    <div class="space-y-2">
                        {#each groups as group (group.id)}
                            {@const isCurrent = authStore.user?.simulated_group_id === group.id || (!authStore.isSimulating && group.is_superadmin)}
                            <div class="p-3.5 rounded-xl border transition-all flex items-start justify-between gap-3 {isCurrent ? 'border-sky-500/50 bg-sky-500/5' : 'border-border bg-card/60 hover:bg-muted/40'}">
                                <div class="space-y-1 min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-xs text-foreground truncate">{group.name}</span>
                                        {#if group.is_superadmin}
                                            <span class="text-[9px] uppercase font-mono px-1.5 py-0.5 rounded-md bg-sky-500/15 text-sky-500 font-bold">Superadmin</span>
                                        {:else}
                                            <span class="text-[9px] uppercase font-mono px-1.5 py-0.5 rounded-md bg-muted text-muted-foreground font-semibold">{group.slug}</span>
                                        {/if}
                                    </div>
                                    {#if group.description}
                                        <p class="text-[11px] text-muted-foreground line-clamp-1">{group.description}</p>
                                    {/if}
                                    <div class="flex items-center gap-3 pt-1 text-[10px] text-muted-foreground font-mono">
                                        <span class="flex items-center gap-1">
                                            <Server class="size-3 text-sky-500" />
                                            <span>{group.all_routers ? 'All Routers' : `${group.router_ids?.length || 0} Router(s)`}</span>
                                        </span>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    onclick={() => handleSwitch(group.is_superadmin ? null : group.id)}
                                    disabled={switchingId !== null || isCurrent}
                                    class="px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1 shrink-0 transition-all cursor-pointer {isCurrent ? 'bg-sky-500/15 text-sky-500 cursor-default' : 'bg-muted hover:bg-foreground hover:text-background text-foreground'}"
                                >
                                    {#if isCurrent}
                                        <Check class="size-3.5" />
                                        <span>Active</span>
                                    {:else if switchingId === group.id}
                                        <RefreshCw class="size-3.5 animate-spin" />
                                        <span>Switching...</span>
                                    {:else}
                                        <span>Select</span>
                                        <ArrowRight class="size-3.5" />
                                    {/if}
                                </button>
                            </div>
                        {/each}
                    </div>
                {/if}
            </div>

            <!-- Footer -->
            <div class="pt-2 border-t border-border flex justify-end shrink-0">
                <button
                    type="button"
                    onclick={onClose}
                    class="px-4 py-2 rounded-xl border border-border bg-muted/40 hover:bg-muted text-foreground font-semibold text-xs transition-colors cursor-pointer"
                >
                    {localeStore.t('common.close')}
                </button>
            </div>
        </div>
    </div>
{/if}

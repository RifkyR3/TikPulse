<script lang="ts">
    import { authStore } from '@/stores/auth.svelte';
    import { localeStore } from '@/stores/locale.svelte';
    import { ShieldAlert, ArrowLeft, RefreshCw } from '@lucide/svelte';
    import { router } from '@inertiajs/svelte';

    let isReverting = $state(false);

    async function handleExit() {
        isReverting = true;
        try {
            const success = await authStore.resetRole();
            if (success) {
                router.reload();
            }
        } finally {
            isReverting = false;
        }
    }
</script>

{#if authStore.isSimulating}
    <aside aria-label="Role Simulation Warning" class="w-full bg-gradient-to-r from-amber-500/15 via-orange-500/15 to-amber-500/15 border-b border-amber-500/30 px-4 py-2 flex items-center justify-between gap-3 text-xs z-50">
        <div class="flex items-center gap-2.5 min-w-0">
            <span class="size-6 rounded-md bg-amber-500/20 text-amber-500 flex items-center justify-center shrink-0">
                <ShieldAlert class="size-4 animate-pulse" />
            </span>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="font-bold text-amber-500 uppercase tracking-wider text-[11px]">
                    {localeStore.t('users.simulatingRole')}:
                </span>
                <span class="font-semibold text-foreground px-2 py-0.5 rounded-md bg-amber-500/15 border border-amber-500/30">
                    {authStore.user?.simulated_group?.name || 'Restricted Role'}
                </span>
                <span class="text-muted-foreground hidden md:inline text-[11px]">
                    {localeStore.t('users.simulatingNotice')} <strong>{authStore.user?.simulated_group?.name}</strong>.
                </span>
            </div>
        </div>

        <button
            type="button"
            onclick={handleExit}
            disabled={isReverting}
            class="px-2.5 py-1 rounded-md bg-amber-500 hover:bg-amber-600 text-amber-950 font-bold text-xs flex items-center gap-1.5 shrink-0 transition-all cursor-pointer shadow-xs disabled:opacity-50"
        >
            {#if isReverting}
                <RefreshCw class="size-3.5 animate-spin" />
            {:else}
                <ArrowLeft class="size-3.5" />
            {/if}
            <span>{localeStore.t('users.exitSimulation')}</span>
        </button>
    </aside>
{/if}

<script lang="ts">
    import { safeModeStore } from '@/stores/safeMode.svelte';
    import { routerContextStore } from '@/stores/routerContext.svelte';
    import { localeStore } from '@/stores/locale.svelte';
    import { ShieldCheck, ShieldAlert } from '@lucide/svelte';

    let { onOpenUnlockModal }: { onOpenUnlockModal?: () => void } = $props();

    function handleClick() {
        if (safeModeStore.isUnlocked) {
            if (routerContextStore.activeRouter) {
                safeModeStore.lock(routerContextStore.activeRouter.id);
            }
        } else {
            onOpenUnlockModal?.();
        }
    }
</script>

<button
    type="button"
    onclick={handleClick}
    class="group relative inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl border text-xs font-bold transition-all shadow-xs cursor-pointer select-none {safeModeStore.isUnlocked
        ? 'bg-amber-500/10 border-amber-500/40 text-amber-600 dark:text-amber-400 hover:bg-amber-500/20'
        : 'bg-emerald-500/10 border-emerald-500/40 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20'}"
    title={safeModeStore.isUnlocked ? localeStore.t('safeMode.clickToLock') : localeStore.t('safeMode.clickToUnlock')}
>
    <span class="relative flex h-2 w-2">
        <span
            class="relative inline-flex rounded-full h-2 w-2 {safeModeStore.isUnlocked
                ? 'bg-amber-500 shadow-xs shadow-amber-500/50'
                : 'bg-emerald-500 shadow-xs shadow-emerald-500/50'}"
        ></span>
    </span>

    {#if safeModeStore.isUnlocked}
        <ShieldAlert class="w-3.5 h-3.5" />
    {:else}
        <ShieldCheck class="w-3.5 h-3.5" />
    {/if}

    <span>
        {safeModeStore.isUnlocked ? `SAFE MODE (${safeModeStore.formattedTimeRemaining})` : localeStore.t('safeMode.safeModeOn')}
    </span>

    <span class="text-[10px] opacity-75 group-hover:opacity-100 uppercase ml-0.5 font-mono">
        {safeModeStore.isUnlocked ? '• LOCK' : '• UNLOCK'}
    </span>
</button>

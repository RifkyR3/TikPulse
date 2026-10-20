<script lang="ts">
    import { onMount } from 'svelte';
    import { routerContextStore } from '@/stores/routerContext.svelte';
    import { safeModeStore } from '@/stores/safeMode.svelte';
    import { localeStore } from '@/stores/locale.svelte';
    import { Server, ChevronDown, Check, Plus, RefreshCw } from '@lucide/svelte';
    import { Separator } from '@/components/ui/separator';

    let { onAddRouter }: { onAddRouter?: () => void } = $props();

    let isOpen = $state(false);

    async function handleSelect(routerId: number) {
        routerContextStore.setActiveRouter(routerId);
        isOpen = false;
        await safeModeStore.fetchStatus(routerId);
    }

    onMount(async () => {
        if (routerContextStore.routers.length === 0) {
            await routerContextStore.fetchRouters();
        }
    });
</script>

<div class="relative">
    {#if isOpen}
        <button
            type="button"
            class="fixed inset-0 z-40 bg-transparent border-0 cursor-default w-full h-full"
            onclick={() => (isOpen = false)}
            aria-label="Close router switcher"
        ></button>
    {/if}

    <button
        type="button"
        onclick={() => (isOpen = !isOpen)}
        class="inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-border bg-background hover:bg-muted text-foreground text-xs font-medium transition-all shadow-xs cursor-pointer relative z-40 select-none"
    >
        <div class="size-5 rounded bg-primary/10 text-primary flex items-center justify-center shrink-0">
            <Server class="size-3.5" />
        </div>
        <div class="flex flex-col text-left leading-tight max-w-[120px] sm:max-w-[180px] truncate">
            <span class="font-bold truncate text-foreground">
                {routerContextStore.activeRouter?.name || localeStore.t('nav.selectRouter')}
            </span>
            <span class="text-[10px] text-muted-foreground truncate font-mono">
                {routerContextStore.activeRouter?.host || localeStore.t('nav.noRouter')}
            </span>
        </div>
        <ChevronDown class="size-3.5 text-muted-foreground ml-0.5 transition-transform {isOpen ? 'rotate-180' : ''}" />
    </button>

    {#if isOpen}
        <div class="absolute top-full left-0 mt-2 w-72 rounded-xl border border-border bg-card p-1.5 shadow-xl z-50 animate-in fade-in slide-in-from-top-2 duration-150">
            <div class="px-2.5 py-1.5 flex items-center justify-between">
                <span class="text-[11px] font-bold text-muted-foreground uppercase tracking-wider">MikroTik Routers</span>
                <button
                    type="button"
                    onclick={() => routerContextStore.fetchRouters()}
                    class="p-1 hover:bg-muted rounded text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                    title="Refresh router list"
                >
                    <RefreshCw class="size-3 {routerContextStore.isLoading ? 'animate-spin' : ''}" />
                </button>
            </div>

            <Separator class="my-1" />

            <!-- List of routers -->
            <div class="max-h-60 overflow-y-auto space-y-0.5">
                {#each routerContextStore.routers as r (r.id)}
                    <button
                        type="button"
                        onclick={() => handleSelect(r.id)}
                        class="w-full flex items-center justify-between px-2.5 py-1.5 rounded-lg text-left text-xs transition-colors cursor-pointer {r.id === routerContextStore.activeRouterId ? 'bg-sky-500/10 text-sky-600 dark:text-sky-400 font-semibold' : 'hover:bg-muted text-foreground'}"
                    >
                        <div class="flex items-center gap-2 truncate">
                            <span
                                class="size-2 rounded-full shrink-0 {r.status === 'online' ? 'bg-emerald-500 shadow-xs shadow-emerald-500/50' : r.status === 'error' ? 'bg-rose-500' : 'bg-slate-400'}"
                            ></span>
                            <div class="truncate">
                                <div class="truncate font-medium">{r.name}</div>
                                <div class="text-[10px] text-muted-foreground font-mono">{r.host}:{r.api_port} ({r.protocol})</div>
                            </div>
                        </div>
                        {#if r.id === routerContextStore.activeRouterId}
                            <Check class="size-3.5 shrink-0 text-sky-500" />
                        {/if}
                    </button>
                {:else}
                    <div class="p-4 text-center text-xs text-muted-foreground">
                        No routers registered yet.
                    </div>
                {/each}
            </div>

            <Separator class="my-1" />

            <!-- Add new router button -->
            <button
                type="button"
                onclick={() => {
                    onAddRouter?.();
                    isOpen = false;
                }}
                class="w-full flex items-center justify-center gap-1.5 py-1.5 px-3 rounded-lg border border-dashed border-border hover:border-sky-500/50 hover:bg-sky-500/5 text-xs font-semibold text-muted-foreground hover:text-sky-500 transition-all cursor-pointer"
            >
                <Plus class="size-3.5" />
                <span>Register New Router</span>
            </button>
        </div>
    {/if}
</div>

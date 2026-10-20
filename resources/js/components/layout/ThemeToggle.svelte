<script lang="ts">
    import { themeStore } from '@/stores/theme.svelte';
    import { Sun, Moon } from '@lucide/svelte';
</script>

<button
    type="button"
    onclick={() => themeStore.toggleTheme()}
    class="relative inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-card text-foreground transition-colors hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer"
    title="Toggle Theme (Dark / Light)"
    aria-label="Toggle Theme"
>
    {#if themeStore.isDark}
        <Sun class="h-4 w-4 transition-transform duration-200 rotate-0 scale-100 text-amber-400" />
    {:else}
        <Moon class="h-4 w-4 transition-transform duration-200 rotate-0 scale-100 text-slate-700" />
    {/if}
</button>

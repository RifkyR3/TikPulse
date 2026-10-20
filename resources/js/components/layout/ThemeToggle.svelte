<script lang="ts">
    import { themeStore } from '@/stores/theme.svelte';
    import { Button } from '@/components/ui/button';
    import { Sun, Moon } from '@lucide/svelte';
</script>

<Button
    onclick={() => themeStore.toggleTheme()}
    variant="ghost"
    size="icon-sm"
    class="size-8 text-foreground"
    title={themeStore.isDark ? "Switch to light theme" : "Switch to dark theme"}
    aria-label="Toggle Theme"
>
    {#if themeStore.isDark}
        <Sun class="size-4 scale-100 rotate-0 transition-all text-amber-400" />
    {:else}
        <Moon class="size-4 scale-100 rotate-0 transition-all text-slate-700 dark:text-slate-300" />
    {/if}
    <span class="sr-only">Toggle theme</span>
</Button>

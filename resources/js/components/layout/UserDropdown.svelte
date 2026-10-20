<script lang="ts">
    import { authStore } from '@/stores/auth.svelte';
    import { localeStore } from '@/stores/locale.svelte';
    import { LogOut, Shield, ChevronDown, User, Activity } from '@lucide/svelte';
    import * as Avatar from '@/components/ui/avatar';
    import { Badge } from '@/components/ui/badge';
    import { Separator } from '@/components/ui/separator';

    let isOpen = $state(false);

    function getInitials(name?: string) {
        if (!name) return 'A';
        return name
            .split(' ')
            .map((n) => n[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    }

    async function handleLogout() {
        isOpen = false;
        await authStore.logout();
    }
</script>

<div class="relative">
    {#if isOpen}
        <button
            type="button"
            class="fixed inset-0 z-40 bg-transparent border-0 cursor-default w-full h-full"
            onclick={() => (isOpen = false)}
            aria-label="Close user menu"
        ></button>
    {/if}

    <button
        type="button"
        onclick={() => (isOpen = !isOpen)}
        class="flex items-center gap-2 p-1 rounded-lg border border-border bg-background hover:bg-muted text-foreground transition-all cursor-pointer shadow-xs relative z-40"
    >
        <Avatar.Root class="size-7 rounded-md">
            <Avatar.Fallback class="rounded-md bg-gradient-to-tr from-sky-600 to-indigo-600 text-white font-bold text-xs">
                {getInitials(authStore.user?.name)}
            </Avatar.Fallback>
        </Avatar.Root>
        <div class="hidden md:flex flex-col text-left leading-tight max-w-[120px] truncate">
            <span class="text-xs font-semibold truncate text-foreground">{authStore.user?.name || 'Administrator'}</span>
            <span class="text-[10px] text-muted-foreground uppercase font-mono">{authStore.user?.role || 'admin'}</span>
        </div>
        <ChevronDown class="size-3.5 text-muted-foreground transition-transform hidden sm:inline {isOpen ? 'rotate-180' : ''}" />
    </button>

    {#if isOpen}
        <div class="absolute top-full right-0 mt-2 w-56 rounded-xl border border-border bg-card p-1.5 shadow-xl z-50 animate-in fade-in slide-in-from-top-2 duration-150">
            <div class="px-2.5 py-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-foreground truncate">{authStore.user?.name}</span>
                    <Badge variant="outline" class="text-[10px] uppercase font-mono px-1.5 py-0 h-4">{authStore.user?.role || 'admin'}</Badge>
                </div>
                <div class="text-[11px] text-muted-foreground truncate font-mono mt-0.5">{authStore.user?.email}</div>
            </div>

            <Separator class="my-1" />

            <div class="space-y-0.5">
                <button
                    type="button"
                    onclick={handleLogout}
                    class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-md text-xs font-medium text-destructive hover:bg-destructive/10 transition-colors cursor-pointer"
                >
                    <LogOut class="size-3.5" />
                    <span>{localeStore.t('nav.signOut')}</span>
                </button>
            </div>
        </div>
    {/if}
</div>

<script lang="ts">
    import { localeStore, type SupportedLocale } from '@/stores/locale.svelte';
    import { Globe, Check, ChevronDown } from '@lucide/svelte';

    let isOpen = $state(false);

    const languages: { code: SupportedLocale; label: string; flag: string }[] = [
        { code: 'en', label: 'English', flag: '🇬🇧' },
        { code: 'id', label: 'Bahasa Indonesia', flag: '🇮🇩' },
    ];

    async function handleSelect(code: SupportedLocale) {
        isOpen = false;
        await localeStore.setLocale(code);
    }
</script>

<div class="relative">
    {#if isOpen}
        <button
            type="button"
            class="fixed inset-0 z-40 bg-transparent border-0 cursor-default w-full h-full"
            onclick={() => (isOpen = false)}
            aria-label="Close menu"
        ></button>
    {/if}

    <button
        type="button"
        onclick={() => (isOpen = !isOpen)}
        class="inline-flex items-center gap-1.5 h-8 px-2.5 rounded-md border border-border bg-background hover:bg-muted text-xs font-semibold text-foreground transition-all shadow-xs cursor-pointer select-none"
        title={localeStore.currentLocale === 'en' ? 'Language: English (Click to change)' : 'Bahasa: Indonesia (Klik untuk ubah)'}
    >
        <Globe class="size-3.5 text-muted-foreground" />
        <span class="uppercase font-mono text-[11px] font-bold">
            {localeStore.currentLocale}
        </span>
        <ChevronDown class="size-3 text-muted-foreground transition-transform {isOpen ? 'rotate-180' : ''}" />
    </button>

    {#if isOpen}
        <div class="absolute top-full right-0 mt-2 w-44 rounded-xl border border-border bg-card p-1 shadow-lg z-50 animate-in fade-in slide-in-from-top-2 duration-150">
            <div class="px-2.5 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                {localeStore.t('common.language')}
            </div>

            <div class="space-y-0.5">
                {#each languages as lang (lang.code)}
                    <button
                        type="button"
                        onclick={() => handleSelect(lang.code)}
                        class="w-full flex items-center justify-between px-2.5 py-1.5 rounded-md text-xs font-medium transition-colors cursor-pointer text-left {localeStore.currentLocale === lang.code ? 'bg-sky-500/10 text-sky-600 dark:text-sky-400 font-bold' : 'hover:bg-muted text-foreground'}"
                    >
                        <span class="flex items-center space-x-2">
                            <span class="text-sm leading-none">{lang.flag}</span>
                            <span>{lang.label}</span>
                        </span>
                        {#if localeStore.currentLocale === lang.code}
                            <Check class="size-3.5 text-sky-500" />
                        {/if}
                    </button>
                {/each}
            </div>
        </div>
    {/if}
</div>

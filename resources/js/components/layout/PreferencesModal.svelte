<script lang="ts">
    import { authStore } from '@/stores/auth.svelte';
    import { themeStore, ACCENT_COLORS, type AccentColor, type ThemeMode } from '@/stores/theme.svelte';
    import { localeStore, type SupportedLocale } from '@/stores/locale.svelte';
    import { routerContextStore } from '@/stores/routerContext.svelte';
    import { X, Palette, Sun, Moon, Monitor, Clock, Globe, Check } from '@lucide/svelte';
    import { toast } from 'svelte-sonner';

    let {
        isOpen = false,
        onClose,
    }: {
        isOpen: boolean;
        onClose: () => void;
    } = $props();

    let isSaving = $state(false);

    async function handleSetAccent(color: AccentColor) {
        themeStore.setAccentColor(color, true);
        toast.success(localeStore.t('users.settingsSaved'));
    }

    async function handleSetTheme(mode: ThemeMode) {
        themeStore.setTheme(mode, true);
        toast.success(localeStore.t('users.settingsSaved'));
    }

    async function handleSetLocale(loc: SupportedLocale) {
        await localeStore.setLocale(loc, true);
        toast.success(localeStore.t('users.settingsSaved'));
    }

    async function handleSetRefresh(seconds: number) {
        routerContextStore.setRefreshInterval(seconds);
        if (authStore.user) {
            await authStore.updateSettings({ refresh_interval: seconds });
        }
        toast.success(localeStore.t('users.settingsSaved'));
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
        <div class="relative w-full max-w-md rounded-2xl border border-border bg-card p-6 shadow-2xl z-10 space-y-5 animate-in fade-in zoom-in-95 duration-150">
            <div class="flex items-center justify-between pb-3 border-b border-border">
                <div class="flex items-center gap-2.5">
                    <div class="size-8 rounded-lg bg-sky-500/15 text-sky-500 flex items-center justify-center">
                        <Palette class="size-4" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-foreground">{localeStore.t('users.preferences')}</h3>
                        <p class="text-[11px] text-muted-foreground">Personalize your TikPulse experience</p>
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

            <!-- Appearance Theme -->
            <div class="space-y-2">
                <label class="text-xs font-bold text-foreground flex items-center gap-1.5">
                    <Sun class="size-3.5 text-amber-500" />
                    <span>{localeStore.t('users.theme')}</span>
                </label>
                <div class="grid grid-cols-3 gap-2">
                    <button
                        type="button"
                        onclick={() => handleSetTheme('light')}
                        class="flex items-center justify-center gap-2 p-2.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer {themeStore.theme === 'light' ? 'border-sky-500 bg-sky-500/10 text-sky-500 shadow-xs' : 'border-border bg-card/50 text-muted-foreground hover:text-foreground hover:bg-muted'}"
                    >
                        <Sun class="size-3.5" />
                        <span>Light</span>
                    </button>
                    <button
                        type="button"
                        onclick={() => handleSetTheme('dark')}
                        class="flex items-center justify-center gap-2 p-2.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer {themeStore.theme === 'dark' ? 'border-sky-500 bg-sky-500/10 text-sky-500 shadow-xs' : 'border-border bg-card/50 text-muted-foreground hover:text-foreground hover:bg-muted'}"
                    >
                        <Moon class="size-3.5" />
                        <span>Dark</span>
                    </button>
                    <button
                        type="button"
                        onclick={() => handleSetTheme('system')}
                        class="flex items-center justify-center gap-2 p-2.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer {themeStore.theme === 'system' ? 'border-sky-500 bg-sky-500/10 text-sky-500 shadow-xs' : 'border-border bg-card/50 text-muted-foreground hover:text-foreground hover:bg-muted'}"
                    >
                        <Monitor class="size-3.5" />
                        <span>System</span>
                    </button>
                </div>
            </div>

            <!-- Accent Theme Colors -->
            <div class="space-y-2">
                <label class="text-xs font-bold text-foreground flex items-center gap-1.5">
                    <Palette class="size-3.5 text-indigo-500" />
                    <span>{localeStore.t('users.accentColor')}</span>
                </label>
                <div class="grid grid-cols-3 gap-2">
                    {#each ACCENT_COLORS as color (color.id)}
                        <button
                            type="button"
                            onclick={() => handleSetAccent(color.id)}
                            class="flex items-center gap-2 p-2 rounded-xl border text-xs font-semibold transition-all cursor-pointer {themeStore.accentColor === color.id ? 'border-foreground/40 bg-muted/80 shadow-xs' : 'border-border bg-card/50 hover:bg-muted text-muted-foreground'}"
                        >
                            <span class="size-4 rounded-full {color.bgClass} flex items-center justify-center shadow-xs">
                                {#if themeStore.accentColor === color.id}
                                    <Check class="size-2.5 text-white" />
                                {/if}
                            </span>
                            <span class="text-[11px] truncate">{color.name}</span>
                        </button>
                    {/each}
                </div>
            </div>

            <!-- Language Selection -->
            <div class="space-y-2">
                <label class="text-xs font-bold text-foreground flex items-center gap-1.5">
                    <Globe class="size-3.5 text-emerald-500" />
                    <span>{localeStore.t('common.language')}</span>
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        onclick={() => handleSetLocale('en')}
                        class="flex items-center justify-center gap-2 p-2.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer {localeStore.locale === 'en' ? 'border-sky-500 bg-sky-500/10 text-sky-500 shadow-xs' : 'border-border bg-card/50 text-muted-foreground hover:text-foreground hover:bg-muted'}"
                    >
                        <span>🇺🇸</span>
                        <span>English</span>
                    </button>
                    <button
                        type="button"
                        onclick={() => handleSetLocale('id')}
                        class="flex items-center justify-center gap-2 p-2.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer {localeStore.locale === 'id' ? 'border-sky-500 bg-sky-500/10 text-sky-500 shadow-xs' : 'border-border bg-card/50 text-muted-foreground hover:text-foreground hover:bg-muted'}"
                    >
                        <span>🇮🇩</span>
                        <span>Bahasa Indonesia</span>
                    </button>
                </div>
            </div>

            <!-- Telemetry Auto-refresh Rate -->
            <div class="space-y-2">
                <label class="text-xs font-bold text-foreground flex items-center gap-1.5">
                    <Clock class="size-3.5 text-sky-500" />
                    <span>{localeStore.t('users.refreshRate')}</span>
                </label>
                <div class="grid grid-cols-6 gap-1.5">
                    {#each [0, 5, 10, 15, 30, 60] as rate (rate)}
                        <button
                            type="button"
                            onclick={() => handleSetRefresh(rate)}
                            class="py-1.5 rounded-lg border text-xs font-mono font-semibold transition-all cursor-pointer {routerContextStore.refreshIntervalSeconds === rate ? 'border-sky-500 bg-sky-500/15 text-sky-500' : 'border-border text-muted-foreground hover:bg-muted'}"
                        >
                            {rate === 0 ? 'Off' : rate + 's'}
                        </button>
                    {/each}
                </div>
            </div>

            <div class="pt-2 flex justify-end">
                <button
                    type="button"
                    onclick={onClose}
                    class="px-4 py-2 rounded-xl bg-foreground text-background font-semibold text-xs hover:opacity-90 transition-opacity cursor-pointer shadow-xs"
                >
                    {localeStore.t('common.close')}
                </button>
            </div>
        </div>
    </div>
{/if}

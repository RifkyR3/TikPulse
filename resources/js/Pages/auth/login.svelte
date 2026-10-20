<script lang="ts">
    import { onMount } from 'svelte';
    import { authStore } from '@/stores/auth.svelte';
    import { localeStore } from '@/stores/locale.svelte';
    import ThemeToggle from '@/components/layout/ThemeToggle.svelte';
    import LanguageSwitcher from '@/components/layout/LanguageSwitcher.svelte';
    import { router } from '@inertiajs/svelte';
    import { Activity, Mail, Lock, Eye, EyeOff, AlertCircle, Loader2, ShieldCheck, ArrowRight } from '@lucide/svelte';

    let email = $state('');
    let password = $state('');
    let remember = $state(true);
    let showPassword = $state(false);
    let clientError = $state<string | null>(null);

    const isProduction = import.meta.env.PROD || import.meta.env.VITE_APP_ENV === 'production';

    onMount(() => {
        const savedEmail = localStorage.getItem('tikpulse_remembered_email');
        if (savedEmail) {
            email = savedEmail;
            remember = true;
        }
    });

    function validate(): boolean {
        clientError = null;
        if (!email || !email.includes('@')) {
            clientError = 'Please provide a valid administrative email address.';
            return false;
        }
        if (!password || password.length < 4) {
            clientError = 'Password must be at least 4 characters long.';
            return false;
        }
        return true;
    }

    async function handleLogin() {
        if (!validate()) return;

        const success = await authStore.login(email, password, remember);
        if (success) {
            router.visit('/dashboard');
        }
    }

    function fillDemo() {
        email = 'admin@tikpulse.test';
        password = 'secret123';
    }
</script>

<div class="relative min-h-screen flex flex-col justify-center items-center px-4 sm:px-6 lg:px-8 bg-gradient-to-br from-background via-card/40 to-background">
    <!-- Top Navigation Bar -->
    <header class="absolute top-4 right-4 sm:top-6 sm:right-6 flex items-center space-x-2 sm:space-x-3">
        <div class="hidden sm:flex items-center space-x-2 px-3 py-1.5 rounded-full border border-border/60 bg-card text-xs font-medium text-muted-foreground">
            <ShieldCheck class="w-3.5 h-3.5 text-emerald-500" />
            <span>Enterprise v1.0</span>
        </div>
        <LanguageSwitcher />
        <ThemeToggle />
    </header>

    <!-- Main Card Container -->
    <div class="w-full max-w-md">
        <!-- Brand & Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-tr from-sky-500 via-indigo-500 to-primary text-primary-foreground shadow-lg shadow-sky-500/20 mb-4 ring-4 ring-primary/10">
                <Activity class="w-7 h-7" />
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-foreground">
                Tik<span class="text-sky-500">Pulse</span>
            </h1>
            <p class="mt-2 text-sm text-muted-foreground">
                {localeStore.t('auth.tagline')}
            </p>
        </div>

        <!-- Card -->
        <div class="rounded-2xl border border-border/80 bg-card p-6 sm:p-8 shadow-xl shadow-black/5 dark:shadow-black/20">
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-foreground">{localeStore.t('auth.welcomeBack')}</h2>
                <p class="text-xs text-muted-foreground">{localeStore.t('auth.welcomeSubtitle')}</p>
            </div>

            <!-- Error Alert Banner -->
            {#if clientError || authStore.error}
                <div class="mb-6 flex items-start space-x-3 rounded-lg border border-destructive/30 bg-destructive/10 p-3.5 text-xs text-destructive">
                    <AlertCircle class="h-4 w-4 shrink-0 mt-0.5" />
                    <div class="flex-1 font-medium leading-relaxed">
                        {clientError || authStore.error}
                    </div>
                </div>
            {/if}

            <!-- Login Form -->
            <form onsubmit={(e) => { e.preventDefault(); handleLogin(); }} class="space-y-4">
                <!-- Email Field -->
                <div>
                    <label for="login_email" class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">
                        {localeStore.t('auth.emailAddress')}
                    </label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted-foreground">
                            <Mail class="h-4 w-4" />
                        </div>
                        <input
                            id="login_email"
                            bind:value={email}
                            type="email"
                            autocomplete="email"
                            required
                            placeholder={isProduction ? 'name@company.com' : 'admin@tikpulse.test'}
                            class="block w-full rounded-lg border border-border bg-background py-2.5 pl-10 pr-3 text-sm text-foreground placeholder:text-muted-foreground/60 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 transition-all"
                        />
                    </div>
                </div>

                <!-- Password Field -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="login_password" class="block text-xs font-semibold text-foreground uppercase tracking-wider">
                            {localeStore.t('auth.password')}
                        </label>
                        <span class="text-[11px] text-muted-foreground cursor-default">{localeStore.t('auth.protectedSanctum')}</span>
                    </div>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted-foreground">
                            <Lock class="h-4 w-4" />
                        </div>
                        <input
                            id="login_password"
                            bind:value={password}
                            type={showPassword ? 'text' : 'password'}
                            autocomplete="current-password"
                            required
                            placeholder="••••••••"
                            class="block w-full rounded-lg border border-border bg-background py-2.5 pl-10 pr-10 text-sm text-foreground placeholder:text-muted-foreground/60 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 transition-all"
                        />
                        <button
                            type="button"
                            onclick={() => (showPassword = !showPassword)}
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                            tabindex="-1"
                        >
                            {#if showPassword}
                                <EyeOff class="h-4 w-4" />
                            {:else}
                                <Eye class="h-4 w-4" />
                            {/if}
                        </button>
                    </div>
                </div>

                <!-- Remember Me Option -->
                <div class="flex items-center justify-between text-xs py-0.5">
                    <label class="flex items-center space-x-2 text-foreground cursor-pointer select-none">
                        <input
                            id="login_remember_me"
                            bind:checked={remember}
                            type="checkbox"
                            class="w-4 h-4 rounded border-border text-sky-500 focus:ring-sky-500 cursor-pointer"
                        />
                        <span class="font-medium text-muted-foreground hover:text-foreground transition-colors">
                            {localeStore.t('auth.rememberMe')}
                        </span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button
                    type="submit"
                    disabled={authStore.isLoading}
                    class="w-full flex items-center justify-center space-x-2 rounded-lg bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 py-2.5 px-4 text-sm font-semibold text-white shadow-md shadow-sky-600/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-all mt-2 cursor-pointer"
                >
                    {#if authStore.isLoading}
                        <Loader2 class="w-4 h-4 animate-spin" />
                    {:else}
                        <span>{localeStore.t('auth.signIn')}</span>
                        <ArrowRight class="w-4 h-4" />
                    {/if}
                </button>
            </form>

            <!-- Demo helper (hidden in production) -->
            {#if !isProduction}
                <div class="mt-6 pt-4 border-t border-border/60 text-center">
                    <button
                        type="button"
                        onclick={fillDemo}
                        class="inline-flex items-center text-xs text-muted-foreground hover:text-sky-500 transition-colors font-medium cursor-pointer"
                    >
                        {localeStore.t('auth.fillSample')}
                    </button>
                </div>
            {/if}
        </div>

        <!-- Footer info -->
        <p class="mt-6 text-center text-xs text-muted-foreground">
            {localeStore.t('auth.footerInfo')}
        </p>
    </div>
</div>

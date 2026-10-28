<script lang="ts">
    import { onMount } from 'svelte';
    import { authStore } from '@/stores/auth.svelte';
    import { localeStore } from '@/stores/locale.svelte';
    import ThemeToggle from '@/components/layout/ThemeToggle.svelte';
    import LanguageSwitcher from '@/components/layout/LanguageSwitcher.svelte';
    import * as Card from '@/components/ui/card';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { router } from '@inertiajs/svelte';
    import { Activity, Mail, User as UserIcon, Lock, Eye, EyeOff, AlertCircle, Loader2, ArrowRight, ShieldCheck } from '@lucide/svelte';

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
        if (!email || email.trim().length < 2) {
            clientError = localeStore.locale === 'id'
                ? 'Silakan masukkan email administrator atau username Hotspot / PPP.'
                : 'Please provide an email address or Hotspot / PPP username.';
            return false;
        }
        if (!password) {
            clientError = localeStore.locale === 'id'
                ? 'Kata sandi tidak boleh kosong.'
                : 'Password is required.';
            return false;
        }
        return true;
    }

    async function handleLogin() {
        if (!validate()) return;

        const res = await authStore.login(email.trim(), password, remember);
        if (res.success) {
            if (res.accountType === 'hotspot_customer' || res.accountType === 'ppp_customer' || authStore.user?.role === 'customer') {
                router.visit('/status');
            } else {
                router.visit('/dashboard');
            }
        }
    }

    function fillDemo() {
        email = 'admin@tikpulse.test';
        password = 'secret123';
    }
</script>

<svelte:head>
    <title>Login - TikPulse Admin</title>
</svelte:head>

<div class="relative bg-background flex min-h-screen items-center justify-center p-4">
    <!-- Top Bar Controls -->
    <header class="absolute top-4 right-4 sm:top-6 sm:right-6 flex items-center gap-2">
        <div class="hidden sm:flex items-center gap-1.5 px-3 py-1 rounded-full border border-border bg-card text-xs font-medium text-muted-foreground">
            <ShieldCheck class="size-3.5 text-emerald-500" />
            <span>Enterprise v1.0</span>
        </div>
        <LanguageSwitcher />
        <ThemeToggle />
    </header>

    <!-- Centered Card Container -->
    <Card.Root class="w-full max-w-md shadow-lg border-border">
        <Card.Header class="space-y-2 text-center pb-2">
            <div class="flex justify-center">
                <div class="inline-flex items-center justify-center size-14 rounded-2xl bg-gradient-to-tr from-sky-500 via-indigo-500 to-primary text-white shadow-lg shadow-sky-500/20 mb-2 ring-4 ring-primary/10">
                    <Activity class="size-7" />
                </div>
            </div>
            <Card.Title class="text-2xl font-bold tracking-tight">
                Tik<span class="text-sky-500">Pulse</span>
            </Card.Title>
            <Card.Description>
                {localeStore.t('auth.tagline')}
            </Card.Description>
        </Card.Header>

        <Card.Content class="space-y-4">
            <!-- Demo Access Callout Box -->
            {#if !isProduction}
                <div class="bg-muted rounded-lg p-3 text-xs leading-relaxed border border-border/60">
                    <div class="flex items-center justify-between font-semibold text-foreground mb-1">
                        <span>Demo Administrative Access</span>
                        <button
                            type="button"
                            onclick={fillDemo}
                            class="text-sky-500 hover:text-sky-400 underline font-semibold cursor-pointer"
                        >
                            {localeStore.t('auth.fillSample')}
                        </button>
                    </div>
                    <p class="text-muted-foreground">
                        Email: <span class="font-mono font-semibold text-foreground">admin@tikpulse.test</span> · Password: <span class="font-mono font-semibold text-foreground">secret123</span>
                    </p>
                </div>
            {/if}

            <!-- Error Banner -->
            {#if clientError || authStore.error}
                <div class="flex items-start gap-2.5 rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-xs text-destructive">
                    <AlertCircle class="size-4 shrink-0 mt-0.5" />
                    <div class="flex-1 font-medium leading-relaxed">
                        {clientError || authStore.error}
                    </div>
                </div>
            {/if}

            <!-- Form -->
            <form onsubmit={(e) => { e.preventDefault(); handleLogin(); }} class="space-y-4">
                <!-- Email or Username Field -->
                <div class="space-y-1.5">
                    <Label for="login_email">{localeStore.t('auth.emailOrUsername')}</Label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted-foreground">
                            <UserIcon class="size-4" />
                        </div>
                        <Input
                            id="login_email"
                            bind:value={email}
                            type="text"
                            autocomplete="username"
                            required
                            placeholder={isProduction ? 'name@company.com / username' : 'admin@tikpulse.test / username'}
                            class="pl-9"
                        />
                    </div>
                </div>

                <!-- Password Field -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <Label for="login_password">{localeStore.t('auth.password')}</Label>
                        <span class="text-[10px] text-muted-foreground font-mono">{localeStore.t('auth.protectedSanctum')}</span>
                    </div>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted-foreground">
                            <Lock class="size-4" />
                        </div>
                        <Input
                            id="login_password"
                            bind:value={password}
                            type={showPassword ? 'text' : 'password'}
                            autocomplete="current-password"
                            required
                            placeholder="••••••••"
                            class="pl-9 pr-9"
                        />
                        <button
                            type="button"
                            onclick={() => (showPassword = !showPassword)}
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                            tabindex={-1}
                            aria-label="Toggle password visibility"
                        >
                            {#if showPassword}
                                <EyeOff class="size-4" />
                            {:else}
                                <Eye class="size-4" />
                            {/if}
                        </button>
                    </div>
                </div>

                <!-- Remember Me Checkbox -->
                <div class="flex items-center justify-between text-xs py-0.5">
                    <label class="flex items-center gap-2 text-foreground cursor-pointer select-none">
                        <input
                            id="login_remember_me"
                            bind:checked={remember}
                            type="checkbox"
                            class="size-4 rounded border-border text-sky-500 focus:ring-sky-500 cursor-pointer"
                        />
                        <span class="font-medium text-muted-foreground hover:text-foreground transition-colors">
                            {localeStore.t('auth.rememberMe')}
                        </span>
                    </label>
                </div>

                <!-- Submit Button -->
                <Button
                    type="submit"
                    disabled={authStore.isLoading}
                    class="w-full bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white shadow-md shadow-sky-600/20 py-2.5"
                >
                    {#if authStore.isLoading}
                        <Loader2 class="size-4 animate-spin" />
                    {:else}
                        <span>{localeStore.t('auth.signIn')}</span>
                        <ArrowRight class="size-4" />
                    {/if}
                </Button>
            </form>
        </Card.Content>

        <Card.Footer class="justify-center border-t border-border pt-4">
            <p class="text-center text-xs text-muted-foreground">
                {localeStore.t('auth.footerInfo')}
            </p>
        </Card.Footer>
    </Card.Root>
</div>

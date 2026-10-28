<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { localeStore } from '@/stores/locale.svelte';
    import ThemeToggle from '@/components/layout/ThemeToggle.svelte';
    import LanguageSwitcher from '@/components/layout/LanguageSwitcher.svelte';
    import * as Card from '@/components/ui/card';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Lock, Eye, EyeOff, CheckCircle2, Loader2, ArrowLeft } from '@lucide/svelte';
    import { toast } from 'svelte-sonner';

    let {
        token,
        email: initialEmail,
        passwordRules,
    }: {
        token: string;
        email?: string;
        passwordRules?: string;
    } = $props();

    let email = $state('');
    let password = $state('');
    let passwordConfirmation = $state('');
    let showPassword = $state(false);
    let isSubmitting = $state(false);
    let errors = $state<Record<string, string>>({});

    $effect(() => {
        if (initialEmail) {
            email = initialEmail;
        }
    });

    function handleSubmit(e: Event) {
        e.preventDefault();
        isSubmitting = true;
        errors = {};

        router.post('/reset-password', {
            token,
            email,
            password,
            password_confirmation: passwordConfirmation,
        }, {
            onSuccess: () => {
                toast.success(
                    localeStore.locale === 'id'
                        ? 'Kata sandi berhasil direset! Silakan masuk.'
                        : 'Password has been reset! Please sign in.'
                );
            },
            onError: (errs) => {
                errors = errs;
            },
            onFinish: () => {
                isSubmitting = false;
            },
        });
    }
</script>

<svelte:head>
    <title>{localeStore.locale === 'id' ? 'Setel Ulang Kata Sandi' : 'Reset Password'} - TikPulse</title>
</svelte:head>

<div class="min-h-screen bg-background flex flex-col justify-center items-center p-4 relative overflow-hidden">
    <div class="absolute -top-40 -right-40 size-96 bg-sky-500/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-40 -left-40 size-96 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="absolute top-4 right-4 flex items-center space-x-2">
        <LanguageSwitcher />
        <ThemeToggle />
    </div>

    <div class="w-full max-w-md relative z-10">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center size-14 rounded-2xl bg-gradient-to-tr from-sky-500 via-indigo-500 to-primary text-white shadow-xl shadow-sky-500/25 mb-4">
                <Lock class="size-7" />
            </div>
            <h1 class="text-2xl font-black tracking-tight text-foreground">
                Tik<span class="text-sky-500">Pulse</span>
            </h1>
            <p class="text-xs text-muted-foreground mt-1">
                {localeStore.locale === 'id' ? 'Tentukan Kata Sandi Baru' : 'Set Your New Password'}
            </p>
        </div>

        <Card.Root class="border-border shadow-xl backdrop-blur-sm bg-card/95">
            <Card.Header class="pb-2">
                <Card.Title class="text-base font-bold text-center">
                    {localeStore.locale === 'id' ? 'Perbarui Kata Sandi' : 'Reset Password'}
                </Card.Title>
                <Card.Description class="text-xs text-center mt-1">
                    {localeStore.locale === 'id'
                        ? 'Masukkan email dan buat kata sandi baru untuk akun Anda.'
                        : 'Enter your email and create a new password for your account.'}
                </Card.Description>
            </Card.Header>

            <Card.Content class="space-y-4 pt-3">
                <form onsubmit={handleSubmit} class="space-y-3.5">
                    <div class="space-y-1.5">
                        <Label for="reset-email" class="text-xs font-bold text-foreground">
                            {localeStore.locale === 'id' ? 'Alamat Email' : 'Email Address'}
                        </Label>
                        <Input
                            id="reset-email"
                            type="email"
                            bind:value={email}
                            required
                            class="text-xs"
                        />
                        {#if errors.email}
                            <p class="text-[11px] text-destructive">{errors.email}</p>
                        {/if}
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <Label for="new-pass" class="text-xs font-bold text-foreground">
                                {localeStore.locale === 'id' ? 'Kata Sandi Baru' : 'New Password'}
                            </Label>
                            <button
                                type="button"
                                onclick={() => (showPassword = !showPassword)}
                                class="text-[11px] text-sky-500 hover:underline flex items-center gap-1 cursor-pointer"
                            >
                                {#if showPassword}
                                    <EyeOff class="size-3" />
                                    <span>Hide</span>
                                {:else}
                                    <Eye class="size-3" />
                                    <span>Show</span>
                                {/if}
                            </button>
                        </div>
                        <Input
                            id="new-pass"
                            type={showPassword ? 'text' : 'password'}
                            bind:value={password}
                            required
                            placeholder="••••••••"
                            class="text-xs font-mono"
                        />
                        {#if errors.password}
                            <p class="text-[11px] text-destructive">{errors.password}</p>
                        {/if}
                    </div>

                    <div class="space-y-1.5">
                        <Label for="new-pass-confirm" class="text-xs font-bold text-foreground">
                            {localeStore.locale === 'id' ? 'Konfirmasi Kata Sandi Baru' : 'Confirm New Password'}
                        </Label>
                        <Input
                            id="new-pass-confirm"
                            type={showPassword ? 'text' : 'password'}
                            bind:value={passwordConfirmation}
                            required
                            placeholder="••••••••"
                            class="text-xs font-mono"
                        />
                    </div>

                    <Button
                        type="submit"
                        disabled={isSubmitting}
                        class="w-full bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white font-bold text-xs py-2.5 shadow-md shadow-sky-600/20 cursor-pointer mt-2"
                    >
                        {#if isSubmitting}
                            <Loader2 class="size-4 animate-spin mr-1.5" />
                            <span>{localeStore.locale === 'id' ? 'Menyimpan...' : 'Saving...'}</span>
                        {:else}
                            <CheckCircle2 class="size-4 mr-1.5" />
                            <span>{localeStore.locale === 'id' ? 'Simpan Kata Sandi Baru' : 'Reset Password'}</span>
                        {/if}
                    </Button>
                </form>

                <div class="pt-2 text-center">
                    <a
                        href="/login"
                        class="inline-flex items-center gap-1 text-xs font-semibold text-muted-foreground hover:text-foreground transition-colors"
                    >
                        <ArrowLeft class="size-3.5" />
                        <span>{localeStore.locale === 'id' ? 'Kembali ke Masuk' : 'Back to Login'}</span>
                    </a>
                </div>
            </Card.Content>
        </Card.Root>
    </div>
</div>

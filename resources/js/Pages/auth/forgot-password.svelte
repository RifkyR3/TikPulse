<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { localeStore } from '@/stores/locale.svelte';
    import ThemeToggle from '@/components/layout/ThemeToggle.svelte';
    import LanguageSwitcher from '@/components/layout/LanguageSwitcher.svelte';
    import * as Card from '@/components/ui/card';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { KeyRound, Mail, CheckCircle2, ArrowLeft, Loader2, ExternalLink } from '@lucide/svelte';
    import { toast } from 'svelte-sonner';

    let { status }: { status?: string } = $props();

    let email = $state('');
    let isSubmitting = $state(false);
    let errorMessage = $state<string | null>(null);

    function handleSubmit(e: Event) {
        e.preventDefault();
        if (!email.trim()) {
            errorMessage = localeStore.locale === 'id' ? 'Email wajib diisi.' : 'Email is required.';
            return;
        }

        isSubmitting = true;
        errorMessage = null;

        router.post('/forgot-password', { email: email.trim() }, {
            onSuccess: () => {
                toast.success(
                    localeStore.locale === 'id'
                        ? 'Tautan reset kata sandi telah dikirim ke email Anda.'
                        : 'Password reset link sent to your email.'
                );
            },
            onError: (errors) => {
                errorMessage = errors.email || 'Failed to send reset link.';
            },
            onFinish: () => {
                isSubmitting = false;
            },
        });
    }
</script>

<svelte:head>
    <title>{localeStore.locale === 'id' ? 'Lupa Kata Sandi' : 'Forgot Password'} - TikPulse</title>
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
                <KeyRound class="size-7" />
            </div>
            <h1 class="text-2xl font-black tracking-tight text-foreground">
                Tik<span class="text-sky-500">Pulse</span>
            </h1>
            <p class="text-xs text-muted-foreground mt-1">
                {localeStore.locale === 'id' ? 'Pemulihan Kata Sandi Akun' : 'Account Password Recovery'}
            </p>
        </div>

        <Card.Root class="border-border shadow-xl backdrop-blur-sm bg-card/95">
            <Card.Header class="pb-2">
                <Card.Title class="text-base font-bold text-center">
                    {localeStore.locale === 'id' ? 'Reset Kata Sandi Anda' : 'Reset Your Password'}
                </Card.Title>
                <Card.Description class="text-xs text-center mt-2 leading-relaxed">
                    {localeStore.locale === 'id'
                        ? 'Masukkan alamat email Anda yang terdaftar. Kami akan mengirimkan tautan untuk menyetel ulang kata sandi Anda.'
                        : 'Enter your registered email address and we will email you a password reset link.'}
                </Card.Description>
            </Card.Header>

            <Card.Content class="space-y-4 pt-3">
                {#if status}
                    <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs flex items-center gap-2.5">
                        <CheckCircle2 class="size-4 shrink-0" />
                        <span>{status}</span>
                    </div>
                {/if}

                <!-- Mailpit helper -->
                <div class="p-3 rounded-xl bg-sky-500/10 border border-sky-500/20 text-xs text-sky-700 dark:text-sky-300 flex items-center justify-between">
                    <div>
                        <p class="font-bold">Mailpit Dev Mailbox</p>
                        <p class="text-[11px] opacity-80">Local SMTP server on port 8025</p>
                    </div>
                    <a
                        href="http://localhost:8025"
                        target="_blank"
                        rel="noreferrer"
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-sky-500 text-white font-bold text-[11px] hover:bg-sky-600 transition-colors"
                    >
                        <span>Inbox</span>
                        <ExternalLink class="size-3" />
                    </a>
                </div>

                <form onsubmit={handleSubmit} class="space-y-4">
                    <div class="space-y-1.5">
                        <Label for="email" class="text-xs font-bold text-foreground">
                            {localeStore.locale === 'id' ? 'Alamat Email' : 'Email Address'}
                        </Label>
                        <Input
                            id="email"
                            type="email"
                            bind:value={email}
                            placeholder="admin@tikpulse.local"
                            required
                            class="text-xs"
                        />
                        {#if errorMessage}
                            <p class="text-[11px] text-destructive">{errorMessage}</p>
                        {/if}
                    </div>

                    <Button
                        type="submit"
                        disabled={isSubmitting}
                        class="w-full bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white font-bold text-xs py-2.5 shadow-md shadow-sky-600/20 cursor-pointer"
                    >
                        {#if isSubmitting}
                            <Loader2 class="size-4 animate-spin mr-1.5" />
                            <span>{localeStore.locale === 'id' ? 'Mengirim...' : 'Sending Link...'}</span>
                        {:else}
                            <Mail class="size-4 mr-1.5" />
                            <span>{localeStore.locale === 'id' ? 'Kirim Tautan Reset' : 'Email Password Reset Link'}</span>
                        {/if}
                    </Button>
                </form>

                <div class="pt-2 text-center">
                    <a
                        href="/login"
                        class="inline-flex items-center gap-1 text-xs font-semibold text-muted-foreground hover:text-foreground transition-colors"
                    >
                        <ArrowLeft class="size-3.5" />
                        <span>{localeStore.locale === 'id' ? 'Kembali ke Halaman Masuk' : 'Back to Login'}</span>
                    </a>
                </div>
            </Card.Content>
        </Card.Root>
    </div>
</div>
